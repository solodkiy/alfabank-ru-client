<?php
declare(strict_types=1);

namespace Solodkiy\AlfaBankRuClient;

use RuntimeException;

class Utils
{
    /**
     * Copyright (C) 2011-2017 by Lars Strojny <lstrojny@php.net>
     * @param $collection
     * @param callable|null $callback
     * @return mixed|null
     */
    public static function first($collection, callable $callback = null)
    {
        foreach ($collection as $index => $element) {

            if ($callback === null) {
                return $element;
            }

            if ($callback($element, $index, $collection)) {
                return $element;
            }
        }

        return null;
    }

    /**
     * @param $regex
     * @param $string
     * @return mixed
     * @throws RuntimeException
     */
    public static function extractFirstMatch(string $regex, string $string) : string
    {
        if (preg_match($regex, $string, $m)) {
            return $m[1];
        } else {
            throw new RuntimeException('Regex '.$regex.' not matched');
        }
    }

    public static function fixCurrencyCode(string $currency): string
    {
        if ($currency === 'RUR') {
            return 'RUB';
        }
        return $currency;
    }
}