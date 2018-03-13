<?php
/**
 * Created by IntelliJ IDEA.
 * User: xrain
 * Date: 2018/3/13
 * Time: 06:33
 */

namespace Synapse;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use ReflectionFunction;

class Synapse
{
    public $mq_host;
    public $mq_port = 5672;
    public $mq_vhost = '/';
    public $mq_user;
    public $mq_pass;
    public $sys_name;
    public $app_name;
    public $app_id;
    public $rpc_timeout = 3;
    public $event_process_num = 20;
    public $rpc_process_num = 20;
    public $disable_event_client = false;
    public $disable_rpc_client = false;
    public $debug = false;
    public $rpc_callback;
    public $event_callback;
    public $timezone = 'PRC';

    private $_conn;
    public $channel;
    private $_event_client;
    private $_rpc_client;

    const LogInfo = 'Info';
    const LogDebug = 'Debug';
    const LogWarn = 'Warn';
    const LogError = 'Error';

    public function serve()
    {
        date_default_timezone_set($this->timezone);
        if (empty($this->app_name) or empty($this->sys_name)) {
            self::log("Must Set SysName and AppName system exit .", self::LogError);
            exit;
        } else {
            self::log(sprintf("System Name: %s", $this->sys_name));
            self::log(sprintf("App Name: %s", $this->app_name));
        }
        if (empty($this->app_id)) {
            $this->app_id = self::randomString();
        }
        self::log(sprintf("App ID: %s", $this->app_id));

        if ($this->debug) {
            self::log("App Run Mode: Debug", self::LogDebug);
        } else {
            self::log("App Run Mode: Production");
        }
        $this->_createConnection();
        $this->_checkAndCreateExchange();

        //如果有服务器内容,创建服务器通道
        if ($this->event_callback or $this->rpc_callback) {
            $this->_createChannel();
            $this->disable_rpc_client = true;
            $this->disable_event_client = true;
        }
        //事件服务器
        if ($this->event_callback) {
            $event_server = new EventServer($this);
            $event_server->run();
            foreach ($this->event_callback as $k => $v) {
                self::log(sprintf("*EVT: %s -> %s", $k, (new ReflectionFunction($v))->name));
            }
        } else {
            self::log("Event Server Disabled: EventCallback not set", self::LogWarn);
        }
        //RPC服务器
        if ($this->rpc_callback) {
            $rpc_server = new RpcServer($this);
            $rpc_server->run();
            foreach ($this->rpc_callback as $k => $v) {
                self::log(sprintf("*RPC: %s -> %s", $k, (new ReflectionFunction($v))->name));
            }
        } else {
            self::log("Rpc Server Disabled: RpcCallback not set", self::LogWarn);
        }
        //事件客户端
        if ($this->disable_event_client) {
            self::log("Event Client Disabled: DisableEventClient set true", self::LogWarn);
        } else {
            $this->_event_client = new EventClient($this);
            self::log("Event Client Ready");
        }
        //RPC客户端
        if ($this->disable_rpc_client) {
            self::log("Rpc Client Disabled: DisableEventClient set true", self::LogWarn);
        } else {
            $this->_rpc_client = new RpcClient($this);
            $this->_rpc_client->run();
            self::log(sprintf("Rpc Client Timeout: %ds", $this->rpc_timeout));
            self::log("Rpc Client Ready");
        }

        //开始服务,同样会阻塞,也就是说客户端无法使用
        if ($this->event_callback or $this->rpc_callback) {
            while (count($this->channel->callbacks)) {
                $this->channel->wait();
            }
        }
    }

    public static function log($desc, $type = self::LogInfo)
    {
        printf("[%s][Synapse %s] %s \n", date('Y-m-d H:i:s'), $type, $desc);
    }

    public static function randomString($length = 20)
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $str = '';
        for ($i = 0; $i < $length; $i++) {
            $str .= $chars[mt_rand(0, strlen($chars) - 1)];
        }
        return $str;
    }

    private function _createConnection()
    {
        $this->_conn = new AMQPStreamConnection($this->mq_host, $this->mq_port, $this->mq_user, $this->mq_pass, $this->mq_vhost);
        self::log("Rabbit MQ Connection Created.");
    }

    private function _createChannel()
    {
        $this->channel = $this->createChannel($this->rpc_process_num + $this->event_process_num, 'Event/Rpc');
    }

    public function createChannel($processNum, $desc)
    {
        $channel = $this->_conn->channel();
        self::log(sprintf('%s Channel Created', $desc));
        if ($processNum > 0) {
            $channel->basic_qos(0, $processNum, false);
            self::log(sprintf('%s MaxProcessNum: %d', $desc, $processNum));
        }
        return $channel;
    }

    private function _checkAndCreateExchange()
    {
        $channel = $this->createChannel(0, "Exchange");
        $channel->exchange_declare($this->sys_name, 'topic', false, true, true);
        self::log("Register Exchange Successed.");
        $channel->close();
        self::log("Exchange Channel Closed");
    }

    public function sendEvent($event, $param)
    {
        if ($this->disable_event_client) {
            self::log("Event Client Disabled: DisableEventClient set true", self::LogError);
        } else {
            $this->_event_client->send($event, $param);
        }
    }

    public function sendRpc($app, $action, $param)
    {
        $res = [];
        if ($this->disable_rpc_client) {
            self::log("Rpc Client Disabled: DisableEventClient set true", self::LogError);
            $res = ['rpc_error' => "rpc client disabled"];
        } else {
            $res = $this->_rpc_client->send($app, $action, $param);
        }
        return $res;
    }
}