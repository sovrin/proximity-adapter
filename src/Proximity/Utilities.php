<?php

namespace Proximity;

/**
 * @author  Oleg Kamlowski <oleg.kamlowski@thomann.de>
 * @created 30.06.2020
 * @package Proximity
 */
final class Utilities {

    const CHAR_POOL = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!"$&/()=[]{}0123456789';

    /**
     * @return string
     */
    static function unique () {
        $chars = self::CHAR_POOL;

        $key = '';
        $length = strlen($chars);

        for ($i = 0; $i < 16; $i++) {
            $key .= $chars[mt_rand(0, $length - 1)];
        }

        $code = base64_encode($key);

        return strtok($code, '=');
    }

    /**
     * @param $timeout
     *
     * @return \Closure
     */
    static function timer ($timeout) {
        $timeStamp = microtime(true);

        return function () use ($timeStamp, $timeout) {
            return microtime(true) - $timeStamp <= $timeout;
        };
    }
}