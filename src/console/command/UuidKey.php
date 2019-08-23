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

        $output->writeln('<info>new uuid string:' . Str::keyGen() . '</info>');
    }

}