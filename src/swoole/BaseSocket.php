<?php
/**
 * Created by PhpStorm.
 * User: gjianbo
 * Date: 2019/1/12
 * Time: 17:15
 */

namespace yiqiniu\swoole;

use Swoole\Server as SwooleServer;

abstract class BaseSocket
{
    /**
     * Swoole对象
     * @var object
     */
    protected $swoole;

    protected $option = [
        //600秒内未向服务器发送任何数据，此连接将被强制关闭
        'heartbeat_idle_time' => 600,
        //每60秒遍历一次
        'heartbeat_check_interval' => 60,
    ];


    /**
     * 支持的响应事件
     * @var array
     */
    protected $event = ['Start', 'Shutdown', 'WorkerStart', 'WorkerStop', 'WorkerExit', 'Connect', 'Receive', 'Packet', 'Close', 'BufferFull', 'BufferEmpty', 'Task', 'Finish', 'PipeMessage', 'WorkerError', 'ManagerStart', 'ManagerStop', 'Open', 'Message', 'HandShake', 'Request'];


    /**
     * 架构函数
     * @access public
     */
    public function __construct($host, $port, $mode, $sockType)
    {
        $this->swoole = new SwooleServer($host, $port, $mode, $sockType);
        // 设置参数
        if (!empty($this->option)) {
            $this->swoole->set($this->option);
        }
        // 初始化
        $this->init();

        // 设置回调
        foreach ($this->event as $event) {
            if (method_exists($this, 'on' . $event)) {
                $this->swoole->on($event, [$this, 'on' . $event]);
            }
        }
    }

    protected function init()
    {
    }

    /**
     * 魔术方法 有不存在的操作的时候执行
     * @access public
     * @param string $method 方法名
     * @param array $args 参数
     * @return mixed
     */
    public function __call($method, $args)
    {
        call_user_func_array([$this->swoole, $method], $args);
    }

    /**
     * 获取参数
     * @param $name
     * @return bool
     */
    public function __get($name)
    {
        if (isset($this->$name)) {
            return $this->$name;
        }
        return false;
    }

    /**
     * 处理Socket 收到的信息
     * @param $server
     * @param $fd
     * @param $from_id
     * @param $data
     */
    abstract public function onReceive($server, $fd, $from_id, $recvdata);

}