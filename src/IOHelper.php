<?php

namespace src;

/**
 *
 */
class IOHelper
{
    const GREEN = 32;
    const RED = 31;
    const YELLOW = 33;

    /**
     * Цветной вывод в консоль
     *
     * @param string $string Строка, которую нужно разукрасить
     * @param int $color Цвет, согласно стандарту
     * @param bool $endl Добавлять ли перенос строки
     * @return string
     */
    public static function colorize($string, $color = self::GREEN, $endl = false)
    {
        $string = "\x1b[" . $color . "m" . $string . "\x1b[0m";
        if ($endl) {
            $string .= "\n";
        }

        return $string;
    }

}
