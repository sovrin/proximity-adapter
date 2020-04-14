<?php

namespace Proximity;

use Exception;

/**
 * @author  Oleg Kamlowski <oleg.kamlowski@thomann.de>
 * @created 14.04.2020
 * @package Proximity
 */
class Proximity {

    const CONFIG_HOST = 'localhost';
    const CONFIG_PORT = 'port';
    const CONFIG_DEBUG = 'debug';
    const CONFIG_TIMEOUT = 'timeout';

    const CONTEXT_PROJECT = 'project';

    /**
     * @var Proximity|null
     */
    private static $instance = null;

    /**
     * @var array
     */
    private $stack = [];

    /**
     * @var bool
     */
    private $ready = false;

    /**
     * @var Socket|null
     */
    private $socket = null;

    /**
     * @var array
     */
    private $context = [];

    /**
     * @var array
     */
    private $config = [
        self::CONFIG_HOST => 'localhost',
        self::CONFIG_PORT => 3315,
        self::CONFIG_TIMEOUT => 1,
        self::CONFIG_DEBUG => true,
    ];

    /**
     * Proximity constructor.
     */
    public function __construct () {
        // hello darkness my old friend
    }

    /**
     * @return Proximity
     */
    public static function getInstance (): Proximity {
        if (!static::$instance) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    /**
     * @param null $config
     *
     * @return array
     */
    static function config ($config = null): array {
        $instance = self::getInstance();

        if (!$config) {
            return $instance->config;
        }

        $instance->config = array_merge($instance->config, $config);

        return $instance->config;
    }

    /**
     * @param null $context
     *
     * @return array
     */
    static function context ($context = null): array {
        $instance = self::getInstance();

        if (!$context) {
            return $instance->context;
        }

        $instance->context = array_merge($instance->context, $context);

        return $instance->context;
    }

    /**
     *
     */
    static function open () {
        $instance = self::getInstance();
        $instance->socket = $instance->create();

        if (!$instance->socket) {
            return;
        }

        $payload = $instance->augment('push/open');

        $callback = function () use ($instance, $payload) {
            $instance->send($payload);
        };

        $instance->push($callback);
    }

    /**
     * @param $message
     */
    static function message ($message) {
        $instance = self::getInstance();
        $payload = $instance->augment('push/data', [
            'message' => $message,
        ]);

        $callback = function () use ($instance, $payload) {
            $instance->send($payload);
        };

        $instance->push($callback);
    }

    /**
     *
     */
    static function close () {
        $instance = self::getInstance();
        $payload = $instance->augment('push/close');

        $callback = function () use ($instance, $payload) {
            $instance->send($payload);
            $instance->socket->close();
            $instance->ready = false;
        };

        $instance->push($callback);
    }

    /**
     * @param $name
     */
    static function flag ($name = 'flag') {
        $instance = self::getInstance();
        $payload = $instance->augment('push/flag', [
            'name' => $name,
        ]);

        $callback = function () use ($instance, $payload) {
            $instance->send($payload);
        };

        $instance->push($callback);
    }

    /**
     * @param $element
     */
    private function push ($element) {
        $this->stack[] = $element;

        if ($this->ready) {
            $this->execute();
        }
    }

    /**
     *
     */
    private function execute () {
        while (count($this->stack) > 0) {
            $callback = array_shift($this->stack);
            $callback();
        }
    }

    /**
     * @param $path
     * @param array $data
     *
     * @return false|string
     */
    private function augment ($path, $data = []) {
        $context = $this->context;

        return json_encode([
            'path' => $path,
            'data' => [
                'data' => $data,
                'context' => $context,
            ],
        ]);
    }

    /**
     * @param $payload
     */
    private function send ($payload) {
        $socket = $this->socket;

        try {
            $socket->write($payload);
        } catch (Exception $exception) {
            [self::CONFIG_DEBUG => $debug] = $this->config;

            if (!$debug) {
                return;
            }

            var_dump($exception);
        }
    }

    /**
     * @return Socket|null
     */
    private function create () {
        [
            self::CONFIG_HOST => $host,
            self::CONFIG_PORT => $port,
            self::CONFIG_DEBUG => $debug,
            self::CONFIG_TIMEOUT => $timeout,
        ] = $this->config;

        $socket = new Socket($timeout);

        try {
            $socket->open($host, $port);
            $this->ready = true;
        } catch (Exception $exception) {
            if (!$debug) {
                // shallow
                return null;
            }

            var_dump($exception);
        }

        return $socket;
    }
}


