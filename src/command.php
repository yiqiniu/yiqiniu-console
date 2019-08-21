<?php
// 注册命令行指令
\think\Console::addDefaultCommands([
    'yqn:facade'=>'\\yiqiniu\\console\\command\\MakeFacade',
    'yqn:model' => '\\yiqiniu\\console\\command\\ModelAll',
    'yqn:validate' => '\\yiqiniu\\console\\command\\ValidateAll',
    'yqn:compress' =>'\\yiqiniu\\console\\command\\Compress',
    'yqn:uuid'=>'\\yiqiniu\\console\\command\\UuidKey',
]);
//添加swoole的支持
if (extension_loaded('swoole') && class_exists("think\\swoole\\command\\Swoole'")) {
    \think\Console::addDefaultCommands([
        'yqn:swoole_tcp'=>'\\yiqiniu\\console\\command\\Tcpserver'
    ]);
}
