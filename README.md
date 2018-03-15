## 西纳普斯 - synapse (PHP Version)

### 此为系统核心交互组件,包含了事件和RPC系统

### 特别说明
1. 不太推荐本语言使用此系统,因为只能运行在单线程.
2. 目前客户端和服务端不能共存,启用服务端后会自动禁用客户端
3. 并未进行大并发测试

#### 包地址
> https://packagist.org/packages/synapse-rpc/siroen

#### Demo测试地址
> https://github.com/synapse-rpc/siroen-test

#### 可以使用Nuget安装
> composer require synapse-rpc/siroen

#### 使用前奏:
1. 需要一个RabbitMQ服务器

#### 使用方式:
```PHP
    use Synapse\Synapse;
    
    $ec = function ($msg, $raw) {
        printf("收到信息: %s\n", $raw->body);
        return true;
    };
    $events = [
        'dotNet.test' => $ec,
        'golang.test' => $ec,
        'python.test' => $ec
    ];
    
    $rpcs = [
        'test' => function ($msg, $raw) {
            printf("收到RPC: %s\n", $raw->body);
            return [
                'from' => 'php',
                'm' => $msg['msg'],
                'number' => 5233
            ];
        }
    ];
    
    $app = new Synapse();
    $app->sys_name = 'simcu';
    $app->app_name = 'php';
    $app->mq_host = 'xxx';
    $app->mq_user = 'xxx';
    $app->mq_pass = 'xxx';
    $app->debug = true;
    $app->rpc_callback = $rpcs;
    $app->event_callback = $events;
    //$app->disable_rpc_client = true;
    //$app->disable_event_client = true;
    $app->serve();
```

#### CallBack说明:
```PHP
/**
 * 事件回调
 * @param array $msg 收到的信息数组
 * @param object $raw libamqp原始信息
 * @return bool 回复true表示确认信息,false将会将消息送回队列
 */
function eventCallback($msg, $raw)
{
    return true;
}

/**
 * RPC回调
 * @param array $msg 收到的信息数组
 * @param object $raw libamqp原始信息
 * @return array 必须是键值对数组,将会序列为json
 */
function rpcCallback($msg, $raw)
{
    return [];
}
```

#### 客户端方法说明:
1. 发送事件(无返回)
> Synapse.sendEvent($eventName, $param)

2. RPC请求(返回数组)
> Synapse.SendRpc($app, $method, $param)

3. 控制台日志
> Synapse::log(string desc,type = Synapse::LogInfo)

日志级别: LogWarn,LogError,LogInfo,LogDebug

#### 参数说明:

```
public $mq_host;                            //MQ主机
public $mq_port = 5672;                     //MQ端口
public $mq_vhost = '/';                     //MQ虚拟机名称,默认为/
public $mq_user;                            //MQ用户
public $mq_pass;                            //MQ密码
public $sys_name;                           //系统名称(都处于同一个系统下才能通讯)
public $app_name;                           //应用名(当前应用的名字,不能于其他应用重复)
public $app_id;                             //应用ID(支持分布式,不输入会每次启动自动随机生成)
public $rpc_timeout = 3;                    //RPC请求超时时间(只针对客户端有效)
public $event_process_num = 20;             //事件服务并发量
public $rpc_process_num = 20;               //RPC服务并发量
public $disable_event_client = false;       //禁用事件客户端
public $disable_rpc_client = false;         //禁用RPC客户端
public $debug = false;                      //调试
public $rpc_callback;                       //RPC处理函数数组
public $event_callback;                     //事件处理函数数组
public $timezone = 'PRC';                   //系统时区

```
