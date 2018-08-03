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
     * @var int Лимит записей для больших таблиц, что бы не загружать память,
     * будем брать по 200 записей из таблицы.
     */
    const LIMIT = 500;

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

    public function __construct(Configurator $configurator)
    {
        $this->configurator = $configurator;
        $this->comparator = new Comparator();
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
            $this->compareData($connection, $tables);
            $this->output("Database {$key} was synchronized!\n");
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
            $firstColumn = array_shift($table->getColumns());
            $countRecords = $this->masterConnection->fetchAssoc("SELECT count(*) as count FROM {$table->getName()}");

            while ($this->offset <= $countRecords['count']) {
                $data = $this->getChunk($table->getName());
                $this->addData($data, $firstColumn, $connection, $table);
            }

            $this->offset = 0;
            $this->output("Table {$table->getName()} was synchronized! [{$key}/{$countTable}]\n");
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
        $limit = self::LIMIT;
        $data = $this->masterConnection->fetchAll("SELECT * FROM {$tableName} LIMIT {$limit} OFFSET {$this->offset} ");

        $this->offset += self::LIMIT;
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
