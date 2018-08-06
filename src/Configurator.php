<?php

namespace src;

use exceptions\ConfigException;

/**
 *
 */
class Configurator
{
    /**
     * @var array Массив конфигурации
     */
    public $config = [];

    function __construct($config)
    {
        if (!is_array($config)) {
            throw new ConfigException("Config must be array", 1);
        }

        $this->config = $config;
        $this->checkConfiguration();
    }

    /**
     * Возращает массив с параметрами соединения для основной бд
     *
     * @return array
     */
    public function getMasterConnectionConfig()
    {
        return $this->config['master'];
    }

    /**
     * Возращает массив массивов с параметрами соединения для копий
     *
     * @return array
     */
    public function getCopiesConnectionConfig()
    {
        return $this->config['copies'];
    }

    /**
     * Возвратить конфиг исходя из типа
     *
     * @param string $type
     */
    public function getConfig($type)
    {
        return $this->config[$type];
    }

    /**
     * Проверка правильности написания конфигурации
     *
     * @return bool
     * @throws ConfigException
     */
    public function checkConfiguration()
    {
        try {
            if (empty($this->config)) {
                throw new ConfigException("Config not set!", 1);
            }

            $this->checkMasterConfiguration();
            $this->checkCopiesConfiguration();
            $this->checkExceptTablesConfiguration();
            $this->checkOtherConfiguration();
            return true;
        } catch (ConfigException $e) {
            throw $e;
        }
    }

    /**
     * Проверяем конфигурацию для подключения к продакшну
     *
     * @return void
     */
    protected function checkMasterConfiguration()
    {
        if (!isset($this->config['master'])) {
            throw new ConfigException("Configuration for 'master' db not found!", 1);
        }

        $master = $this->config['master'];

        if (!isset($master['host']) || empty($master['host'])) {
            throw new ConfigException("Parametr 'host' not set for master array configuration", 1);
        }

        if (!isset($master['dbname']) || empty($master['dbname'])) {
            throw new ConfigException("Parametr 'dbname' not set for master array configuration", 1);
        }

        if (!isset($master['user']) || empty($master['user'])) {
            throw new ConfigException("Parametr 'user' not set for master array configuration", 1);
        }

        if (!isset($master['password']) || empty($master['password'])) {
            throw new ConfigException("Parametr 'user' not set for master array configuration", 1);
        }

        unset($master);
    }

    /**
     * Проверяем конфигурацию для подключения к копиям
     *
     * @return void
     */
    protected function checkCopiesConfiguration()
    {
        if (!isset($this->config['copies'])) {
            throw new ConfigException("Configuration for 'copies' not found!", 1);
        }

        /** Соединения не проверяем, т.к. если не указаны параметры - берем из мастер копии */

        // $copies = $this->config['copies'];
        //
        // foreach ($copies as $key => $copy) {
        //     if (!isset($copy['host']) || empty($copy['host'])) {
        //         throw new ConfigException("Parametr 'host' not set for copy '{$key}' array configuration", 1);
        //     }
        //
        //     if (!isset($copy['dbname']) || empty($copy['dbname'])) {
        //         throw new ConfigException("Parametr 'dbname' not set for copy '{$key}' array configuration", 1);
        //     }
        //
        //     if (!isset($copy['user']) || empty($copy['user'])) {
        //         throw new ConfigException("Parametr 'user' not set for copy '{$key}' array configuration", 1);
        //     }
        //
        //     if (!isset($copy['password']) || empty($copy['password'])) {
        //         throw new ConfigException("Parametr 'user' not set for copy '{$key}' array configuration", 1);
        //     }
        // }
        //
        // unset($copies);
    }

    /**
     * Проверяем конфигурацию для таблиц, которые пропускаем
     *
     * @return void
     */
    protected function checkExceptTablesConfiguration()
    {
        if (!isset($this->config['exceptValues'])) {
            throw new ConfigException("Configuration for 'exceptTables' not found!", 1);
        }

        $exceptTables = $this->config['exceptValues'];

        foreach ($exceptTables as $key => $table) {
            if (is_array($table) || is_string($table)) {
                continue;
            }

            throw new ConfigException("Configuration items for 'exceptValues' must be array or string", 1);
        }

        unset($exceptTables);
    }

    /**
     * Проверяем остальные параметры конфигурации
     *
     * @return void
     */
    protected function checkOtherConfiguration()
    {
        if (!isset($this->config['logPath'])) {
            throw new ConfigException("Configuration for 'logPath' not found!", 1);
        }

        if (!file_exists($this->config['logPath'])) {
            throw new ConfigException("Dir " . $this->config['logPath'] . ' for logs not found!', 1);
        }
        if (!isset($this->config['testDumpPath'])) {
            throw new ConfigException("Configuration for 'testDumpPath' not found!", 1);
        }

        if (!file_exists($this->config['testDumpPath'])) {
            throw new ConfigException("Dir " . $this->config['testDumpPath'] . ' for dumps not found!', 1);
        }

        if (!isset($this->config['advancedLog']) || !is_bool($this->config['advancedLog'])) {
            throw new ConfigException("Configuration for 'advancedLog' is required and must be bool!", 1);
        }

        if (!isset($this->config['consoleOutput']) || !is_bool($this->config['consoleOutput'])) {
            throw new ConfigException("Configuration for 'consoleOutput' is required and must be bool!", 1);
        }

        if (!isset($this->config['makeTestDump']) || !is_bool($this->config['makeTestDump'])) {
            throw new ConfigException("Configuration for 'consoleOutput' is required and must be bool!", 1);
        }

        if (!in_array($this->config['highload'], ['low', 'middle', 'hard', 'full'])) {
            throw new ConfigException("Configuration for 'highload' may only 'low', 'middle', 'hard', 'full'!", 1);
        }
    }
}
