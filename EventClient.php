<?php
/**
 * Created by IntelliJ IDEA.
 * User: xrain
 * Date: 2018/3/13
 * Time: 06:34
 */

namespace Rpc\Synapse\Siroen;

use PhpAmqpLib\Message\AMQPMessage;

class EventClient
{
    private $_synapse;
    private $_channel;

    public function __construct(Synapse $synapse)
    {
        $this->_synapse = $synapse;
        $this->_channel = $synapse->createChannel(0, 'EventClient');
    }

    public function send($event, $param = [])
    {
        $router = sprintf('event.%s.%s', $this->_synapse->app_name, $event);
        $props = [
            'app_id' => $this->_synapse->app_id,
            'message_id' => Synapse::randomString(),
            'reply_to' => $this->_synapse->app_name,
            'type' => $event
        ];
        $body = new AMQPMessage(json_encode($param), $props);
        $this->_channel->basic_publish($body, $this->_synapse->sys_name, $router);
        if ($this->_synapse->debug) {
            Synapse::log(sprintf("Event Publish: %s@%s %s", $event, $this->_synapse->app_name, json_encode($param)));
        }
    }
}