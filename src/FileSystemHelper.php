<?php

namespace src;

/**
 *
 */
class FileSystemHelper
{
    /**
     * Разрешение пути к файлу путем удаления дублированных слешей
     *
     * @param string $path Путь к файлу или директории
     * @return string Путь к файлу
     */
    public static function resolvePath($path)
    {
        return preg_replace('/\/{2,}/', '/', $path);
    }
}
