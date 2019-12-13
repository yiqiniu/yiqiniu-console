<?php


namespace yiqiniu\console\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use yiqiniu\library\Str;

class UuidKey extends Command
{
    protected function configure()
    {
        $this->setName('make:uuid')->setDescription('Create a new uuid value');
    }

    protected function execute(Input $input, Output $output)
    {

        $output->writeln('<info>new uuid string:' . $this->keyGen() . '</info>');
    }


    /**
     * 生成Guid主键
     * @return Boolean
     */
    private function keyGen()
    {
        return str_replace('-', '', substr($this->uuid(), 1, -1));
    }

    /**
     * 生成UUID 单机使用
     * @access public
     * @return string
     */
    private function uuid()
    {
        $charid = md5(uniqid(mt_rand(), true));
        $hyphen = chr(45);// "-"
        $uuid = chr(123)// "{"
            . substr($charid, 0, 8) . $hyphen
            . substr($charid, 8, 4) . $hyphen
            . substr($charid, 12, 4) . $hyphen
            . substr($charid, 16, 4) . $hyphen
            . substr($charid, 20, 12)
            . chr(125);// "}"
        return $uuid;
    }


}