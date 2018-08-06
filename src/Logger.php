<?php

namespace src;

use src\FileSystemHelper;
use Illuminate\Log\Writer;
use Monolog\Logger as Monolog;

/**
 * Description of BaseLogger
 *
 * @author alex
 */
class Logger {

    const TYPE_ERROR = 1;
    const TYPE_INFO = 2;
    const TYPE_NOTICE = 3;

    const ERROR_LOG_PATH = '/error.log';
    const INFO_LOG_PATH = '/info.log';
    const NOTICE_LOG_PATH = '/notice.log';

    const APP_NAME = 'copier';

    /**
     * Уведомление типа критическая ошибка
     *
     * @param string $message Сообщение об ошибке
     * @return void
     */
    public static function error($message)
    {
        $logger = self::initializeLogger(self::ERROR_LOG_PATH);
        $logger->error($message);
    }

    /**
     * Уведомление типа замечание
     *
     * @param string $message Сообщение об ошибке
     * @return void
     */
    public static function notice($message)
    {
        $logger = self::initializeLogger(self::NOTICE_LOG_PATH);
        $logger->notice($message);
    }

    /**
     * Уведомление типа информация
     *
     * @param string $message Сообщение об ошибке
     * @return void
     */
    public static function info($message)
    {
        $logger = self::initializeLogger(self::INFO_LOG_PATH);
        $logger->info($message);
    }

    /**
     * Запись лога, в зависимости от типа
     *
     * @param string $message Сообщение об ошибке
     * @return void
     */
    public static function message($message, $type = self::TYPE_INFO)
    {
        switch ($type) {
            case self::TYPE_ERROR:
                return self::error($message);

            case self::TYPE_NOTICE:
                return self::notice($message);

            default:
                return self::notice($message);
        }
    }


    /**
     * Инициализация логгера от laravel
     *
     * @return Illuminate\Log\Writer
     */
    protected static function initializeLogger($path)
    {
        $log = new Writer(new Monolog(self::APP_NAME));
        if (!defined('LOG_PATH')) {
            $defaultPath = dirname(dirname(__FILE__)) . '/logs/';
            $log->useFiles(FileSystemHelper::resolvePath($defaultPath . '/' . $path));
            return $log;
        }

        $log->useFiles(FileSystemHelper::resolvePath(LOG_PATH . '/' . $path));
        return $log;
    }
}
