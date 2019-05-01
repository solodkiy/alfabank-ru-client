<?php
declare(strict_types=1);

namespace Solodkiy\AlfaBankRuClient;

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

}