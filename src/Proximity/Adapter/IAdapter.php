<?php

namespace Proximity\Adapter;

interface IAdapter {

    /**
     * @param $host
     * @param $port
     * @param $ssl
     *
     * @return mixed
     */
    public function open($host, $port, $ssl);

    /**
     * @param $data
     *
     * @return mixed
     */
    public function flush($data);

    /**
     * @return mixed
     */
    public function close();
}