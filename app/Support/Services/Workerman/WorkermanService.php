<?php

namespace App\Support\Services\Workerman;

use Channel\Client;
use Workerman\Worker;
use Workerman\Lib\Timer;
use Workerman\Connection\TcpConnection;

class WorkermanService
{
    /**
     * @var Worker
     */
    protected $worker;

    /**
     * @var TcpConnection[]
     */
    protected $connections = [];

    /**
     * @var TcpConnection[]
     */
    protected $user = [];

    /**
     * @var WorkermanHandle
     */
    protected $handle;

    /**
     * @var Response
     */
    protected $response;

    /**
     * @var int
     */
    protected $timer;

    public function __construct(Worker $worker)
    {
        $this->worker = $worker;
        $this->handle = new WorkermanHandle($this);
        $this->response = new Response;
    }

    public function setUser(TcpConnection $connection)
    {
        $this->user[$connection->adminInfo['id']] = $connection;
    }

    public function onConnect(TcpConnection $connection)
    {
        $this->connections[$connection->id] = $connection;
        $connection->lastMessageTime = time();
    }

    public function onMessage(TcpConnection $connection, $res)
    {
        $connection->lastMessageTime = time();
        $res = json_decode($res, true);
        if (!$res || !isset($res['type']) || !$res['type'] || $res['type'] == 'ping') {
            return $this->response->connection($connection)->success('ping', ['now' => time()]);
        }
        var_dump('onMessage', $res);
        if (!method_exists($this->handle, $res['type'])) {
            return;
        }

        $this->handle->{$res['type']}($connection, $res + ['data' => []], $this->response->connection($connection));
    }

    public function onWorkerStart(Worker $worker)
    {
        var_dump('onWorkerStart');

        ChannelServices::connet();

        Client::on('crmeb', function ($eventData) use ($worker) {
            if (!isset($eventData['type']) || !$eventData['type']) {
                return;
            }
            $ids = isset($eventData['ids']) && count($eventData['ids']) ? $eventData['ids'] : array_keys($this->user);
            foreach ($ids as $id) {
                if (isset($this->user[$id])) {
                    $this->response->connection($this->user[$id])->success($eventData['type'], $eventData['data'] ?? null);
                }
            }
        });

        $this->timer = Timer::add(15, function () use (&$worker) {
            $time_now = time();
            foreach ($worker->connections as $connection) {
                if ($time_now - $connection->lastMessageTime > 12) {
                    $this->response->connection($connection)->close('timeout');
                }
            }
        });
    }

    public function onClose(TcpConnection $connection)
    {
        var_dump('onClose');
        unset($this->connections[$connection->id]);
    }
}
