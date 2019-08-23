<?php


namespace yiqiniu;


use think\Route;
use yiqiniu\console\command\MakeFacade;
use yiqiniu\console\command\ModelAll;
use yiqiniu\console\command\Socket;
use yiqiniu\console\command\UuidKey;
use yiqiniu\console\command\ValidateAll;
use yiqiniu\console\command\ValidateTable;
use yiqiniu\filesystem\Oss;
use yiqiniu\filesystem\Qiniu;

class Service extends \think\Service
{

    public function boot(Route $route)
    {
        $this->commands([
            'yqn:swoole_tcp'=>Socket::class,
            'yqn:model'=>ModelAll::class,
            'yqn:validate'=>ValidateAll::class,
            'yqn:facade'=>MakeFacade::class,
            'yqn:uuid'=>UuidKey::class
        ]);
    }
}