<?php
/**
 * Created by IntelliJ IDEA.
 * User: xrain
 * Date: 2018/3/13
 * Time: 06:34
 */

namespace Rpc\Synapse\Siroen;


use PhpAmqpLib\Message\AMQPMessage;

class RpcServer
{
    private $_synapse;
    private $_queue_name;
    private $_router;

    public function __construct(Synapse $synapse)
    {
        $this->_synapse = $synapse;
        $this->_queue_name = sprintf('%s_%s_server', $synapse->sys_name, $synapse->app_name);
        $this->_router = sprintf('server.%s', $synapse->app_name);
    }

    private function _checkAndCreateQueue()
    {
        $this->_synapse->channel->queue_declare($this->_queue_name, false, true, false, true);
        $this->_synapse->channel->queue_bind($this->_queue_name, $this->_synapse->sys_name, $this->_router);
    }

    public function run()
    {
        $this->_checkAndCreateQueue();
        $callback = function ($msg) {
            if ($this->_synapse->debug) {
                Synapse::log(sprintf('Rpc Receive: (%s)%s->%s@%s %s', $msg->get_properties()['message_id'], $msg->get_properties()['reply_to'], $msg->get_properties()['type'], $this->_synapse->app_name, $msg->body));
            }
            if (array_key_exists($msg->get_properties()['type'], $this->_synapse->rpc_callback)) {
                $req = json_decode($msg->body, true);
                $res = $this->_synapse->rpc_callback[$msg->get_properties()['type']]($req, $msg);
            } else {
                $res = ['rpc_error' => 'method not found'];
            }
            $router = sprintf('client.%s.%s', $msg->get_properties()['reply_to'], $msg->get_properties()['app_id']);
            $props = [
                'app_id' => $this->_synapse->app_id,
                'message_id' => Synapse::randomString(),
                'correlation_id' => $msg->get_properties()['message_id'],
                'reply_to' => $this->_synapse->app_name,
                'type' => $msg->get_properties()['type']
            ];
            $body = new AMQPMessage(json_encode($res), $props);
            $this->_synapse->channel->basic_publish($body, $this->_synapse->sys_name, $router);
            if ($this->_synapse->debug) {
                Synapse::log(sprintf('Rpc Return: (%s)%s@%s->%s %s', $msg->get_properties()['message_id'], $msg->get_properties()['type'], $this->_synapse->app_name, $msg->get_properties()['reply_to'], json_encode($res)));
            }
        };
        $this->_synapse->channel->basic_consume($this->_queue_name, '', false, true, false, false, $callback);
    }
}