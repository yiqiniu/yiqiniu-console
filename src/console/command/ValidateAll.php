<?php


namespace yiqiniu\console\command;


use think\App;
use think\console\command\Make;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use yiqiniu\facade\Db;

/**
 * Class ModelAll
 * @package yiqiniu\console\command
 */
class ValidateAll extends Make
{

    protected $type = 'Command';


    protected $app = null;
    // 不能当做类名的表名

    protected $stubs = [
        'validate' => 'validate',
    ];

    // 是否全部字段 , false 为不为空的字段,true 全部字段
    protected $allfield = false;

    //是否pgsql数据库
    protected $is_postgressql = false;

    // 数据库架构名,PGsql 有效
    protected $schema_name = 'public';

    protected function configure()
    {
        $this->setName('make:validateall')
            ->addOption('all', '-a', Option::VALUE_NONE, "Make All Fields")
            ->addOption('force', '-f', Option::VALUE_NONE, "force update")
            ->addOption('schema', '-s', Option::VALUE_REQUIRED, "specified schema name")
            ->addOption('module', '-m', Option::VALUE_REQUIRED, "specified Module name")
            ->addOption('table', '-t', Option::VALUE_REQUIRED, "specified table name")
            ->setDescription('Generate all validations based on database table fields');
    }


    protected function execute(Input $input, Output $output)
    {


        //强制更新
        $force_update = $input->getOption('force');
        //全部字段
        $this->allfield = $input->getOption('all');
        // 指定schema
        $schema = $input->getOption('schema');
        // 指定模块
        $module = $input->getOption('module');

        $this->app = App::getInstance();
        $default = $this->app->config->get('database.default', '');
        if (!empty($default)) {
            $connect = $this->app->config->get('database.connections.' . $default);
        } else {
            $connect = $this->app->config->get('database.');
        }

        if (empty($connect['database'])) {
            $this->output->error('database not  setting.');
            return;
        }


        $table_name = trim($input->getOption('table'));
        if(!empty($table_name)){
            // 生成所有的类
            $prefix_len = strlen($connect['prefix']);
            if (substr($table_name, 0, $prefix_len) != $connect['prefix']) {
                $table_name = $connect['prefix'] . $table_name;
            }
        }

        $map_tablename=[];
        $this->is_postgressql = stripos($connect['type'], 'pgsql');
        if ($this->is_postgressql != false) {
            if(!empty($table_name)){
                $map_tablename = ['tablename' => $table_name];
            }
            if (!empty($schema)) {
                $this->schema_name = $schema;
            }
            $tablelist = Db::connect($default ?: $connect)->table('pg_class')
                ->field(['relname as name', "cast(obj_description(relfilenode,'pg_class') as varchar) as comment"])
                ->where('relname', 'in', function ($query) use ($map_tablename) {
                    $query->table('pg_tables')
                        ->where('schemaname', $this->schema_name)
                        ->where($map_tablename)
                        ->whereRaw("position('_2' in tablename)=0")->field('tablename');
                })->select();
        } else {
            if(!empty($table_name)){
                $map_tablename = ['table_name' => $table_name];
            }
            $tablelist = Db::connect($default ?: $connect)->table('information_schema.tables')
                ->where('table_schema', $connect['database'])
                ->where($map_tablename)
                ->field('table_name as name,table_comment as comment')
                ->select();
        }

        //select table_name,table_comment from information_schema.tables where table_schema='yiqiniu_new';

        $apppath = $this->app->getAppPath();
        if (!empty($module)) {
            $dirname = $apppath . $module . DIRECTORY_SEPARATOR . 'validate';
        } else {
            $dirname = $apppath . 'validate';
        }
        $dirname .= DIRECTORY_SEPARATOR;
        if (!file_exists($dirname) && !mkdir($dirname, 0644, true) && !is_dir($dirname)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $dirname));
        }
        // 获取生成空间的名称
        $namespace = $this->getNamespace2($module);

        // 判断 是否有基本BaseModel

        $stubs = $this->getStub();


        // 生成所有的类
        $prefix_len = strlen($connect['prefix']);

        $model_stub = file_get_contents($stubs['validate']);

        // table 类用于获取字段
        $dbs = Db::connect($default ?: $connect);



        foreach ($tablelist as $k => $table) {
            $class_name = $this->parseName(substr($table['name'], $prefix_len), 1, true);
            // 如果是表名是class的改为ClassModel
            $filedinfo = $this->getTablesField($dbs, $table['name']);
            $model_file = $dirname . $class_name . 'Valid.php';
            if (!file_exists($model_file) || $force_update) {
                file_put_contents($model_file, str_replace(['{%namespace%}', '{%className%}', '{%comment%}', '{%rule%}', '{%message%}'], [
                    $namespace,
                    $class_name,
                    $table['comment'],
                    $filedinfo['rule'],
                    $filedinfo['message'],
                ], $model_stub));
            }

        }


        $output->writeln('<info>' . $this->type . ':' . 'All Table Validate created successfully.</info>');


    }


    /**
     * 获取表的字段
     */
    public function getTablesField($db, $tablename)
    {

        if ($this->is_postgressql) {


            $sql = "SELECT 
            a.attname as field,
            format_type(a.atttypid,a.atttypmod) as type,
            col_description(a.attrelid,a.attnum) as comment,
            a.attnotnull as notnull   
            FROM pg_class as c,pg_attribute as a 
            where c.relname = '$tablename' and a.attrelid = c.oid and a.attnum>0;";

        } else {

            $sql = "select COLUMN_NAME as field, DATA_TYPE as type, COLUMN_COMMENT as  comment,is_NULLABLE as notnull from information_schema.columns
                    where table_name='$tablename'";

        }

        $fields = $db->query($sql);
        // 生成模板
        $templates = [
            'rule' => "'%s'=>'require',\r\n\t\t",
            'message' => "'%s.require'=>'%s不能为空',\r\n\t\t",
        ];
        //返回值
        $retdata = [
            'rule' => '',
            'message' => ''
        ];
        //忽略ID
        $ignorefield = ['id', 'bz', 'memo', 'createdate', 'createtime', 'remark', 'status', 'zt'];
        //生成字段
        foreach ($fields as $data) {
            if ($data['type'] == '-')
                continue;

            $field = $data['field'];
            if (in_array($field, $ignorefield))
                continue;

            if (!$this->allfield) {

                if (($this->is_postgressql && !((bool)('' !== $data['notnull']))) ||
                    (!$this->is_postgressql && $data['notnull'] === 'NO')) {

                    continue;
                }
            }
            $retdata['rule'] .= sprintf($templates['rule'], $field);
            $retdata['message'] .= sprintf($templates['message'], $field, !empty($data['comment']) ? $data['comment'] : $field);
        }

        return $retdata;
    }

    protected function getNamespace2($model)
    {


        return empty($model) ? 'app\\validate' : 'app\\' . $model . '\\validate';
    }


    protected function isPgsql()
    {

    }

    protected function getStub()
    {

        foreach ($this->stubs as $key => $filename) {

            $this->stubs[$key] = __DIR__ . DIRECTORY_SEPARATOR . 'stubs' . DIRECTORY_SEPARATOR . $filename . '.stub';
        }
        return $this->stubs;
    }


    /**
     * 字符串命名风格转换
     * type 0 将Java风格转换为C的风格 1 将C风格转换为Java的风格
     * @access public
     * @param string $name 字符串
     * @param integer $type 转换类型
     * @param bool $ucfirst 首字母是否大写（驼峰规则）
     * @return string
     */
    public static function parseName($name = null, $type = 0, $ucfirst = true)
    {
        if ($type) {
            $name = preg_replace_callback('/_([a-zA-Z])/', function ($match) {
                return strtoupper($match[1]);
            }, $name);
            return $ucfirst ? ucfirst($name) : lcfirst($name);
        }

        return strtolower(trim(preg_replace("/[A-Z]/", "_\\0", $name), "_"));
    }
}