<?php
/**
 * Created by IntelliJ IDEA.
 * User: xrain
 * Date: 2018/3/13
 * Time: 06:34
 */

namespace Synapse;

use Thread;

class RpcClient
{
    private $_synapse;
    private $_channel;
    private $_queue_name;
    private $_router;
    private $_response_cache = [];

    public function __construct(Synapse $synapse)
    {
        $this->_synapse = $synapse;
        $this->_channel = $synapse->createChannel(0, 'RpcClient');
        $this->_queue_name = sprintf('%s_%s_client_%s', $synapse->sys_name, $synapse->app_name, $synapse->app_id);
        $this->_router = sprintf('client.%s.%s', $synapse->app_name, $synapse->app_id);
    }

    private function _checkAndCreateQueue()
    {
        $this->_channel->queue_declare($this->_queue_name, false, true, false, true);
        $this->_channel->queue_bind($this->_queue_name, $this->_synapse->sys_name, $this->_router);
    }

    public function run()
    {
        $this->_checkAndCreateQueue();
        $callback = function ($msg) {
            echo " [x] Received ", $msg->body, "\n";
        };
//        $this->_channel->basic_consume($this->_queue_name, '', false, true, false, false, $callback);
        while (1) {
//            $this->_channel->wait();
            print_r(1111);
            sleep(1);
        }
    }

    public function send($app, $action, $param)
    {
        $router = sprintf('server.%s', $app);
        $props = [
            'app_id' => $this->_synapse->app_id,
            'message_id' => Synapse::randomString(),
            'reply_to' => $this->_synapse->app_name,
            'type' => $action
        ];
        $body = new AMQPMessage(json_encode($param), $props);
        $this->_channel->basic_publish($body, $this->_synapse->sys_name, $router);
        if ($this->_synapse->debug) {
            Synapse::log(sprintf("RPC Request: (%s)%s->%s@%s %s", $props['message_id'], $this->_synapse->app_name, $action, $app, json_encode($param)));
        }
    }
}