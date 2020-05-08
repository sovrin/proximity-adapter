<?php

namespace Proximity\Adapter;

use Exception;

/**
 * @author  Oleg Kamlowski <oleg.kamlowski@thomann.de>
 * @created 13.04.2020
 * @package Proximity
 */
class Socket implements IAdapter {

    /**
     * @var int
     */
    private $timeout;

    /**
     * @var null
     */
    private $connection = null;

    /**
     * Socket constructor.
     *
     * @param $timeout
     */
    public function __construct (int $timeout = 1) {
        $this->timeout = $timeout;
    }

    /**
     * @param string $host
     * @param int $port
     * @param bool $ssl
     *
     * @throws Exception
     */
    public function open ($host = '127.0.0.1', $port = 80, $ssl = false) {
        $start = microtime(true);
        $key = self::unique();
        $timeout = $this->timeout;

        $header = "GET / HTTP/1.1\r\n" .
            "Host: $host\r\n" .
            "pragma: no-cache\r\n" .
            "Upgrade: WebSocket\r\n" .
            "Connection: Upgrade\r\n" .
            "Sec-WebSocket-Key: $key\r\n" .
            "Sec-WebSocket-Version: 13\r\n" .
            "\r\n"
        ;

        $address = ($ssl ? 'ssl://' : '') . $host . ':' . $port;

        $socket = stream_socket_client(
            $address,
            $errorNumber,
            $errorString,
            $timeout,
            STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT
        );

        stream_set_timeout($socket, $timeout);

        $ready = false;
        while ($this->inTimeFrame($start) && !$ready) {
            $w = [$socket];
            $r = $e = [];

            if (stream_select($r, $w, $e, 0, $this->timeout * 100000 * 2)) {
                $ready = true;
                break;
            }
        }

        if (!$socket) {
            throw new Exception("Unable to connect to websocket server: $errorString ($errorNumber)");
        } elseif (!$ready) {
            throw new Exception("Unable to connect to websocket server: timed out after {$timeout} second/s");
        }

        if (ftell($socket) === 0) {
            $rc = fwrite($socket, $header);

            if (!$rc) {
                throw new Exception("Unable to send upgrade header to websocket server: $errorString ($errorNumber)");
            }

            $responseHeader = fread($socket, 1024);

            if (!strpos($responseHeader, " 101 ") || !strpos($responseHeader, 'Sec-WebSocket-Accept: ')) {
                throw new Exception("Server did not accept to upgrade connection to websocket." . $responseHeader . E_USER_ERROR);
            }
        }

        $this->connection = $socket;
    }

    /**
     * @param $data
     *
     * @return false|int
     * @throws Exception
     */
    public function flush ($data) {
        $header = chr(0x80 | 0x02);

        if (strlen($data) < 126) {
            $header .= chr(0x80 | strlen($data));
        } elseif (strlen($data) < 0xFFFF) {
            $header .= chr(0x80 | 126) . pack("n", strlen($data));
        } else {
            $header .= chr(0x80 | 127) . pack("N", 0) . pack("N", strlen($data));
        }

        $mask = pack("N", rand(1, 0x7FFFFFFF));
        $header .= $mask;

        for ($i = 0; $i < strlen($data); $i++) {
            $data[$i] = chr(ord($data[$i]) ^ ord($mask[$i % 4]));
        }

        $message = $header . $data;

        try {
            return stream_socket_sendto($this->connection, $message);
        } catch (Exception $exception) {
            throw new Exception("Unable to send data to client. Connection closed/lost?");
        }
    }

    /**
     *
     */
    public function close () {
        stream_socket_shutdown($this->connection, STREAM_SHUT_WR);
    }

    /**
     * @param $start
     *
     * @return bool
     */
    private function inTimeFrame ($start) {
        return microtime(true) - $start <= $this->timeout;
    }

    /**
     * @return string
     */
    static function unique () {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!"$&/()=[]{}0123456789';
        $key = '';
        $length = strlen($chars);

        for ($i = 0; $i < 16; $i++) {
            $key .= $chars[mt_rand(0, $length - 1)];
        }

        return base64_encode($key);
    }
}