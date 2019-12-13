<?php
/**
 * Created by PhpStorm.
 * User: gjianbo
 * Date: 2019/1/12
 * Time: 17:15
 */

namespace yiqiniu\swoole;


use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Process;
use Swoole\Runtime;
use Swoole\Server as SocketServer;
use think\App;
use think\Container;
use think\Exception;
use think\helper\Str;

use think\swoole\facade\Server;
use yiqiniu\swoole\traits\InteractsWithSwooleTable;

abstract class BaseSocket
{

    use InteractsWithSwooleTable;
    /**
     * @var Container
     */
    protected $container;

    /**
     * @var App
     */
    protected $app;


    /**
     * @var string 配置文件名
     */
    protected $config_file = '';

    /**
     * @var array  配置参数
     */
    protected $config = [];

    /**
     * @var SocketServer
     */
    protected static $server;

    /**
     * Server events.
     *
     * @var array
     */
    protected $events = [
        'start',
        'shutDown',
        'receive',
        'workerStart',
        'workerStop',
        'packet',
        'bufferFull',
        'bufferEmpty',
        'task',
        'finish',
        'pipeMessage',
        'workerError',
        'managerStart',
        'managerStop',
        'request',

    ];


    /**
     * Manager constructor.
     * @param Container $container
     */
    public function __construct(Container $container)
    {

        $this->container = $container;

        $this->app = $this->container->make(App::class);
        // 获取配置文件名
        if (empty($this->config_file)) {
            $filename = strtolower(substr(strrchr(get_class($this), "\\"), 1));

            if (substr($filename, -7) == 'service') {
                $this->config_file = substr($filename, 0, -7);
            } else {
                $this->config_file = $filename;
            }
        }

        $this->initialize();


    }

    /**
     * Run swoole server.
     */
    public function run()
    {
        $this->container->make(Server::class)->start();
    }

    /**
     * Stop swoole server.
     */
    public function stop()
    {
        $this->container->make(Server::class)->shutdown();
    }


    /**
     * 注册一个服务
     */
    public function register()
    {

        $this->app->bind(Server::class, function () {
            if (is_null(static::$server)) {
                $this->createSwooleServer();
            }
            return static::$server;
        });
    }

    /**
     * Create swoole server.
     */
    protected function createSwooleServer()
    {
        $this->config = $this->app->config->load($this->config_file);
        if (empty($this->config)) {
            throw  new Exception('load config file failure');
        }

        $host = $this->config['server']['host'];
        $port = $this->config['server']['port'];
        $socketType = $this->config['server']['socket_type'] ?? SWOOLE_SOCK_TCP;
        $mode = $this->config['server']['mode'] ?? SWOOLE_PROCESS;

        static::$server = new SocketServer($host, $port, $mode, $socketType);

        $options = $this->config['server']['options'];

        static::$server->set($options);
    }

    /**
     * Gets pid file path.
     *
     * @return string
     */
    protected function getPidFile()
    {
        return $this->config['server']['options']['pid_file'];
    }

    /**
     * Initialize.
     */
    protected function initialize()
    {
        $this->register();

        $this->setSwooleServerListeners();
    }

    /**
     * Set swoole server listeners.
     */
    protected function setSwooleServerListeners()
    {
        foreach ($this->events as $event) {
            $listener = Str::camel("on_$event");
            $callback = method_exists($this, $listener) ? [$this, $listener] : function () use ($event) {
                $this->container->event->trigger("swoole.$event", func_get_args());
            };


            $this->container->make(Server::class)->on($event, $callback);

        }
    }

    /**
     * "onStart" listener.
     */
    public function onStart()
    {

        $this->setProcessName('master process');
        $this->createPidFile();

        $this->container->event->trigger('swoole.start', func_get_args());
    }

    /**
     * The listener of "managerStart" event.
     *
     * @return void
     */
    public function onManagerStart()
    {
        $this->setProcessName('manager process');
        $this->container->event->trigger('swoole.managerStart', func_get_args());
    }

    /**
     * "onWorkerStart" listener.
     *
     * @param \Swoole\Http\Server|mixed $server
     *
     * @throws Exception
     */
    public function onWorkerStart($server)
    {
        if (isset($this->config['enable_coroutine']) && $this->config['enable_coroutine']) {
            Runtime::enableCoroutine(true);
        }

        $this->clearCache();

        $this->container->event->trigger('swoole.workerStart', func_get_args());

        // don't init app in task workers
        if ($server->taskworker) {
            $this->setProcessName('task process');

            return;
        }

        $this->setProcessName('worker process');

        //$this->prepareApplication();

    }

    protected function prepareApplication()
    {
        if (!$this->app instanceof App) {
            $this->app = new App();
            $this->app->initialize();
        }

        $this->bindSandbox();
        $this->bindSwooleTable();

    }

    /**
     * Set onTask listener.
     *
     * @param mixed $server
     * @param string|Task $taskId or $task
     * @param string $srcWorkerId
     * @param mixed $data
     */
    public function onTask($server, $taskId, $srcWorkerId, $data)
    {
        $this->container->event->trigger('swoole.task', func_get_args());
    }

    /**
     * Set onFinish listener.
     *
     * @param mixed $server
     * @param string $taskId
     * @param mixed $data
     */
    public function onFinish($server, $taskId, $data)
    {
        // task worker callback
        $this->container->event->trigger('swoole.finish', func_get_args());

        return;
    }

    /**
     * Set onShutdown listener.
     */
    public function onShutdown()
    {
        $this->removePidFile();
    }

    /**
     * Bind sandbox to Laravel app container.
     */
    protected function bindSandbox()
    {
        $this->app->bind(Sandbox::class, function (App $app) {
            return new Sandbox($app);
        });

        $this->app->bind('swoole.sandbox', Sandbox::class);
    }


    /**
     * Create pid file.
     */
    protected function createPidFile()
    {
        $pidFile = $this->getPidFile();
        $pid = $this->container->make(Server::class)->master_pid;

        file_put_contents($pidFile, $pid);
    }

    /**
     * Remove pid file.
     */
    protected function removePidFile()
    {
        $pidFile = $this->getPidFile();

        if (file_exists($pidFile)) {
            unlink($pidFile);
        }
    }

    /**
     * Clear APC or OPCache.
     */
    protected function clearCache()
    {
        if (extension_loaded('apc')) {
            apc_clear_cache();
        }

        if (extension_loaded('Zend OPcache')) {
            opcache_reset();
        }
    }

    /**
     * Set process name.
     *
     * @codeCoverageIgnore
     *
     * @param $process
     */
    protected function setProcessName($process)
    {

        $serverName = 'swoole_http_server';
        $appName = $this->container->config->get('app.name', 'ThinkPHP');

        $name = sprintf('%s: %s for %s', $serverName, $process, $appName);

        swoole_set_process_name($name);
    }

    /**
     * Add process to http server
     *
     * @param Process $process
     */
    public function addProcess(Process $process): void
    {
        $this->container->make(Server::class)->addProcess($process);
    }

    /**
     * Log server error.
     *
     * @param Throwable|Exception $e
     */
    public function logServerError(Throwable $e)
    {
        /** @var Handle $handle */
        $handle = $this->app->make(Handle::class);

        $handle->renderForConsole(new Output(), $e);

        $handle->report($e);
    }


}