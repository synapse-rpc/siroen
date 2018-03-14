<?php
/**
 * Created by IntelliJ IDEA.
 * User: xrain
 * Date: 2018/3/13
 * Time: 06:33
 */

namespace Rpc\Synapse\Siroen;
class EventServer
{
    private $_synapse;
    private $_queue_name;

    public function __construct(Synapse $synapse)
    {
        $this->_synapse = $synapse;
        $this->_queue_name = sprintf('%s_%s_event', $synapse->sys_name, $synapse->app_name);
    }

    private function _checkAndCreateQueue()
    {
        $this->_synapse->channel->queue_declare($this->_queue_name, false, true, false, true);
        foreach (array_keys($this->_synapse->event_callback) as $item) {
            $this->_synapse->channel->queue_bind($this->_queue_name, $this->_synapse->sys_name, sprintf('event.%s', $item));
        }
    }

    public function run()
    {
        $this->_checkAndCreateQueue();
        $callback = function ($msg) {
            if ($this->_synapse->debug) {
                Synapse::log(sprintf('Event Receive: %s@%s %s', $msg->get_properties()['type'], $msg->get_properties()['reply_to'], $msg->body));
            }
            $event_name = sprintf('%s.%s', $msg->get_properties()['reply_to'], $msg->get_properties()['type']);
            if (array_key_exists($event_name, $this->_synapse->event_callback)) {
                $req = json_decode($msg->body, true);
                $res = $this->_synapse->event_callback[$event_name]($req, $msg);
                if ($res) {
                    $this->_synapse->channel->basic_ack($msg->delivery_info['delivery_tag']);
                } else {
                    $this->_synapse->channel->basic_nack($msg->delivery_info['delivery_tag'], false, true);
                }
            } else {
                Synapse::log("Event Callback not abailable.", Synapse::LogError);
                $this->_synapse->channel->basic_nack($msg->delivery_info['delivery_tag']);
            }
        };
        $this->_synapse->channel->basic_consume($this->_queue_name, '', false, false, false, false, $callback);
    }
}