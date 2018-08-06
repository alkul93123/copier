<?php

namespace src;

use src\Configurator;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Schema\Comparator;
use src\Logger;

/**
 *
 */
class Copier
{
    /**
     * @var int
     */
    protected $offset = 0;

    /**
     * @var Configurator
     */
    protected $configurator;

    /**
     * @var Comparator
     */
    protected $comparator;

    /**
     * @var \Doctrine\DBAL\Connection Экземпляр подключения к основной бд
     */
    protected $masterConnection;

    /**
     * @var array Массив подключений к бд
     */
    protected $connections = [];

    /**
     * @var int Лимит записей для больших таблиц, что бы не загружать память,
     * будем брать по 200 записей из таблицы.
     */
    private $limit = 200;

    public function __construct(Configurator $configurator)
    {
        $this->configurator = $configurator;
        $this->comparator = new Comparator();
        $this->configure();
    }

    /**
     * Метод выполняет основную работу по копированию данных
     *
     * @return bool
     */
    public function fire()
    {
        $this->createConnections();
        $this->compareSchemas();

        $tables = $this->masterConnection->getSchemaManager()->listTables();

        foreach ($this->connections as $key => $connection) {
            if ($this->configurator->config['makeTestDump']) {
                $this->makeDump($connection);
            }

            $this->compareData($connection, $tables);
            $this->output("Database '{$key}' was synchronized!\n\n");
        }

        $this->output("Complite!\n");
    }

    /**
     * Синхронизируем данные в таблицах
     *
     * @param \Doctrine\DBAL\Connection $connection Экземпляр соединения
     * @param array $tables Массив таблиц
     * @return void
     */
    protected function compareData($connection, $tables)
    {
        $countTable = count($tables);
        foreach ($tables as $key => $table) {
            $this->clearTable($connection, $table->getName());
            $firstColumn = array_shift($table->getColumns());
            $countRecords = $this->masterConnection->fetchAssoc("SELECT count(*) as count FROM {$table->getName()}");

            while ($this->offset <= $countRecords['count']) {
                $data = $this->getChunk($table->getName());
                $this->addData($data, $firstColumn, $connection, $table);
            }

            $this->offset = 0;
            $this->output("Table '{$table->getName()}' was synchronized! [{$key}/{$countTable}]\n");
        }
    }

    /**
     * Добавить или обновить запись в таблице
     *
     * @param array $data Массив записей
     * @param string $column Поле, по которому сравниваем (не у всех таблиц может быть id)
     * @param \Doctrine\DBAL\Connection $connection
     * @param \Doctrine\DBAL\Schema\Table $table
     */
    protected function addData($data, $firstColumn, $connection, $table)
    {
        foreach ($data as $items) {
            $temp = $connection->fetchAssoc("SELECT * FROM {$table->getName()} where {$firstColumn->getName()} = ?", [
                    $items[$firstColumn->getName()]
                ]);

            if ($this->checkExceptValues($table->getName(), $temp)) {
                continue;
            }

            $insertArray = $this->prepareArrayBeforeInsert($items);

            if (!$temp) {
                $connection->insert($table->getName(), $insertArray);
            } else {
                $connection->update($table->getName(), $insertArray, [$firstColumn->getName() => $items[$firstColumn->getName()]]);
            }
        }
    }

    /**
     * Очищаем таблицу, перед тем как заносить данные
     *
     * @param \Doctrine\DBAL\Connection $connection
     * @param string $tableName Название таблицы
     * @return bool
     * @throws \Exception
     */
    public function clearTable($connection, $tableName)
    {
        $exceptTables = array_keys($this->configurator->getConfig('exceptValues'));

        if (in_array($tableName, $exceptTables)) {
            $except = $this->configurator->getConfig('exceptValues');
            $rows = $except[$tableName];
            $fieldName = key($rows);
            $ids = implode(',', $rows[$fieldName]);

            $sql = "DELETE FROM {$tableName} WHERE {$fieldName} NOT IN ({$ids});";
            $stmt = $connection->prepare($sql);

            if ($stmt->execute() == 1) {
                return true;
            }
        }

        $sql = "TRUNCATE table {$tableName};";
        $stmt = $connection->prepare($sql);

        if ($stmt->execute() == 1) {
            return true;
        }

        throw new \Exception("Error when clear table {$tableName} db. $res", 1);
    }

    /**
     * Проверка, если есть в конфигурационном массиве значение expectedValue
     * то нужно пропустить этот элемент
     *
     * @param string $tableName Название таблицы
     * @param string $item
     * @return bool
     */
    public function checkExceptValues($tableName, $item)
    {
        $exceptTables = array_keys($this->configurator->getConfig('exceptValues'));

        if (in_array($tableName, $exceptTables)) {
            $except = $this->configurator->getConfig('exceptValues');
            $rows = $except[$tableName];

            foreach ($rows as $key => $value) {
                if (isset($item[$key]) && in_array($item[$key], $value)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Синхронизация больших таблиц.
     * Если таблица большая, что бы не загружать память синхронизируем лимитированное
     * кол-во элементов
     *
     * @param string $tableName
     * @return array
     */
    protected function getChunk($tableName)
    {
        $data = $this->masterConnection->fetchAll("SELECT * FROM {$tableName} LIMIT {$this->limit} OFFSET {$this->offset}");

        $this->offset += $this->limit;
        return $data;
    }

    /**
     * Сравниваем и приводим все базы данных к виду мастера
     *
     * @return bool
     * @throws \Exception
     */
    protected function compareSchemas()
    {
        $masterSchema = $this->masterConnection->getSchemaManager()->createSchema();

        foreach ($this->connections as $key => $connection) {
            $slaveSchema = $connection->getSchemaManager()->createSchema();
            $sqlDiff = $slaveSchema->getMigrateToSql($masterSchema, $connection->getDatabasePlatform());

            $connection->beginTransaction();

            try {
                foreach ($sqlDiff as $diff) {
                    $connection->query($diff);
                }

                $connection->commit();
                return true;
            } catch (\Exception $e) {
                $connection->rollBack();
                throw $e;
            }
        }
    }


    /**
     * Делаем дамп бд
     *
     * @param \Doctrine\DBAL\Connection $connection
     * @return bool
     */
    protected function makeDump($connection)
    {
        $params = $connection->getParams();
        $path = $this->configurator->getConfig('testDumpPath');

        $res = system("mysqldump -u{$params['user']} -p{$params['password']} {$params['dbname']} > {$path}dump_{$params['dbname']}.sql", $retval);

        if ($retval == 0) {
            return true;
        }

        throw new \Exception("Error when creating the dump for {$params['dbname']} db. $res", 1);
    }

    /**
     * Получаем массив с таблицами из мастер бд
     *
     * @return array
     */
    protected function getMasterTables()
    {
        $result = [];
        $masterConfig = $this->configurator->getMasterConnectionConfig();
        $tables = $this->db->getConnection('master')
            ->select("SELECT table_name FROM information_schema.tables where table_schema=:database;", [
                'database' => $masterConfig['database']
            ]);

        foreach ($tables as $table) {
            $result[] = $table->table_name;
        }

        return $result;

    }

    /**
     * Создаем соединения
     *
     * @return void
     */
    protected function createConnections()
    {
        $config = new Configuration();
        $masterConfig = $this->configurator->getMasterConnectionConfig();
        $this->masterConnection = DriverManager::getConnection($masterConfig, $config);

        foreach ($this->configurator->getCopiesConnectionConfig() as $key => $copy) {
            $copyConfig = array_merge($masterConfig, $copy);

            if ($masterConfig['dbname'] == $copyConfig['dbname']) {
                throw new \Exception("Error don't possible copy from 'master' to 'master' db, copy name {$key}", 1);
            }

            $tempConn = DriverManager::getConnection($copyConfig, $config);

            /** Для поддержки mysql enum */
            $tempConn->getDatabasePlatform()->registerDoctrineTypeMapping('enum', 'string');

            $this->connections[$key] = $tempConn;
        }

        /** Для поддержки mysql enum */
        $this->masterConnection->getDatabasePlatform()->registerDoctrineTypeMapping('enum', 'string');
    }

    /**
     * Приводим ключи массива к нужному виду.
     *
     * @param array $arr Массив с ключами/значениями
     * @return array Приведенный массив
     */
    private function prepareArrayBeforeInsert($arr)
    {
        $insertArray = [];

        foreach ($arr as $key => $item) {
            $insertArray["`{$key}`"] = $item;
        }

        return $insertArray;
    }

    /**
     * Конфигурируем скрипт
     *
     * @return void
     */
    private function configure()
    {
        switch ($this->configurator->getConfig('highload')) {
            case 'low':
                $this->limit = 20;
                break;

            case 'middle':
                $this->limit = 200;
                break;

            case 'hard':
                $this->limit = 500;
                break;

            case 'full':
                $this->limit = 2500;
                break;

            default:
                $this->limit = 200;
                break;
        }
    }

    /**
     * Вывод текста в консоль и лог
     *
     * @param string $text
     * @return void
     */
    private function output($text)
    {
        if ($this->configurator->config['advancedLog'] == true) {
            Logger::info($text);
        }

        if ($this->configurator->config['consoleOutput'] == true) {
            echo IOHelper::colorize($text);
        }
    }

}
