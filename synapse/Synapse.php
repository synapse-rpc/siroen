<?php
/**
 * Created by IntelliJ IDEA.
 * User: xrain
 * Date: 2018/3/13
 * Time: 06:33
 */

namespace Synapse;
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

    const LogInfo = 'Info';
    const LogDebug = 'Debug';
    const LogWarn = 'Warn';
    const LogError = 'Error';

    public function serve()
    {

    }

    public static function log(desc,type = null)
    {

    }
}