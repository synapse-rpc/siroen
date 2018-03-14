<?php
/**
 * Created by IntelliJ IDEA.
 * User: xrain
 * Date: 2018/3/13
 * Time: 06:34
 */

namespace Rpc\Synapse\Siroen;

use PhpAmqpLib\Message\AMQPMessage;
use Exception;

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
            $this->_response_cache[$msg->get_properties()['correlation_id']] = json_decode($msg->body, true);
            $this->_channel->basic_ack($msg->delivery_info['delivery_tag']);
            if ($this->_synapse->debug) {
                Synapse::log(sprintf('RPC Response: (%s)%s@%s->%s %s', $msg->get_properties()['correlation_id'], $msg->get_properties()['type'], $msg->get_properties()['reply_to'], $this->_synapse->app_name, $msg->body), Synapse::LogDebug);
            }
        };
        $this->_channel->basic_consume($this->_queue_name, '', false, false, false, false, $callback);
        //其实这段是应该在另一个线程跑的
//        while (count($this->_channel->callbacks)) {
//            $this->_channel->wait();
//        }
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
            Synapse::log(sprintf("RPC Request: (%s)%s->%s@%s %s", $props['message_id'], $this->_synapse->app_name, $action, $app, json_encode($param)), Synapse::LogDebug);
        }
        $res = [];
        try {
            pcntl_alarm($this->_synapse->rpc_timeout);
            pcntl_signal(SIGALRM, function () {
                throw new Exception;
            });
            $this->_channel->wait();
            while (true) {
                if (array_key_exists($props['message_id'], $this->_response_cache)) {
                    $res = $this->_response_cache[$props['message_id']];
                    unset($this->_response_cache[$props['message_id']]);
                    break;
                }
            }
            pcntl_alarm(0);
        } catch (Exception $e) {
            $res = ['rpc_error' => 'timeout'];
        }
        return $res;
    }
}