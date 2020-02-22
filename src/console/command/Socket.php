<?php

namespace yiqiniu\console\command;


use think\console\command\Make;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;

class Socket extends Make
{

    protected $type = 'Command';

    //配置模板
    protected $stubs = [
        'config'=>'socket_config',
        'command'=>'socket_command',
        'service'=>'socket_server'
    ];

    // console 默认内容
    protected  $console_file=
        '<?php
// +----------------------------------------------------------------------
// | 控制台配置
// +----------------------------------------------------------------------
return [
    // 指令定义
    \'commands\' => [
    ],
];';

    protected function configure()
    {
        //parent::configure();
        $this->setName('yqn:socket')
            ->addOption('name', '--name',Option::VALUE_REQUIRED, "The name of the command")
            ->addOption('port', '--port', Option::VALUE_REQUIRED, "socket Port Number ")
            ->setDescription('Create a new Socket Server command class');
    }

    protected function buildClass(string $name,$stubpath='',$prot='')
    {
        $stub = file_get_contents(realpath($stubpath));

        return str_replace(['{%className%}','{%commandName%}', '{%prot%}'], [
            ucfirst($name),
            strtolower($name),
            $prot
        ], $stub);
    }


    protected function execute(Input $input, Output $output)
    {
        $name = ucfirst(trim($input->getOption('name')));
        $port = trim($input->getOption('port'));

        $classname = $this->getClassName($name);

        $pathname = $this->getPathName($classname);

        if (is_file($pathname)) {
            $output->writeln('<error>' . $this->type . ':' . $classname . ' already exists!</error>');
            return false;
        }
        $apppath = $this->app->getAppPath();
        $modulepath = $apppath.'swoole'.DIRECTORY_SEPARATOR;

        $command_file = $modulepath.'command'.DIRECTORY_SEPARATOR.ucfirst($name).'Command.php';
        $service_file= $modulepath.'service'.DIRECTORY_SEPARATOR.ucfirst($name).'Service.php';
        $config_file = $this->app->getConfigPath().strtolower($name).'.php';


        /* if(!file_exists($modulepath.'config')){
             mkdir($modulepath.'config',0644,true);
         }*/
        if(!file_exists($modulepath.'command')){
            mkdir($modulepath.'command',0644,true);
        }
        if(!file_exists($modulepath.'service')){
            mkdir($modulepath.'service',0644,true);
        }

        $this->getStub();

        foreach ($this->stubs as $k=>$v){

            if($k=='config' && !file_exists($config_file)){
                file_put_contents($config_file, $this->buildClass($name,$v,$port));
            }
            if($k=='command' && !file_exists($command_file)){
                file_put_contents($command_file, $this->buildClass($name,$v,$port));
            }
            if($k=='service' && !file_exists($service_file)){
                file_put_contents($service_file, $this->buildClass($name,$v,$port));
            }

        }

        $this->appendToConsole('app\swoole\command\\'.ucfirst($name).'Command');
        $output->writeln('<info>' . $this->type . ':' . $classname . ' created successfully.</info>');

        //$output->writeln('<info>'  . 'memo : please append \'' . $classname . ':class\' to config\console.php </info>');
    }

    protected function getPathName(string $name): string
    {
        $name = str_replace('app'.DIRECTORY_SEPARATOR, '', $name);

        return $this->app->getBasePath() . ltrim(str_replace('\\', '/', $name), '/') . '.php';
    }


    protected function getNamespace(string $app): string
    {
        return parent::getNamespace($app) . '\\service';
    }

    protected function getStub()
    {
        foreach ($this->stubs as $key=>$filename){
            $this->stubs[$key] = __DIR__ . DIRECTORY_SEPARATOR . 'stubs' . DIRECTORY_SEPARATOR . $filename.'.stub';
        }
    }

    /**
     * 追加命令到配置文件中
     * @param $classname
     * @return bool
     */
    protected  function  appendToConsole($classname){

        $console_file = $this->app->getConfigPath().'console.php';
        $file_context ='';
        if(file_exists($console_file)){
            $file_context = file_get_contents($console_file);
        }else{
            $file_context = $this->console_file;
        }

        if($pos = strpos($file_context,'commands')){
            if($pos2 = strpos($file_context,'[',$pos)){
                $str = substr($file_context,0,$pos2+1).sprintf("\r\n\t\t//%s\r\n\t\t%s::class,\r\n",$classname,$classname) .substr($file_context,$pos2+1);
                file_put_contents($console_file,$str);
            }
        }
        return true;

    }
}
