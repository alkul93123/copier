<?php

/**
 *
 */
class Autoloader
{
    /**
     * @var array Массив с путями к файлам, которые должны быть подключены не по
     * стандартным путям. Напр.:
     * ['SomeClass' => '/var/www/copier/SomeClass.php']
     */
    protected static $classMap = [];

    /**
     * Правила загрузки файлов, для функции __autoload
     *
     * @return
     */
    public static function autoload($className)
    {
        if (isset(static::$classMap[$className])) {
            $classFile = static::$classMap[$className];
        } elseif (strpos($className, '\\') !== false) {
            $classFile = dirname(__DIR__) . "/" . str_replace('\\', '/', str_replace('_', '-', $className)) . '.php';
            if ($classFile === false || !is_file($classFile)) {
                return;
            }
        } else {
            return;
        }

        include $classFile;

        if (!class_exists($className, false) && !interface_exists($className, false) && !trait_exists($className, false)) {
            throw new \Exception("Unable to find '$className' in file: $classFile. "
                    . "Namespace missing? Don`t use simbol '_' in the name of direcory. "
                    . "You can use '-' for creating directory");
        }
    }

}
