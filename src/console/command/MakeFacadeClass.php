<?php


namespace app\common\command;


use ReflectionClass;
use ReflectionException;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;

class MakeFacadeClass extends Command
{
    const MODEL_NAME = 'facade';


    // 方法模板
    const METHOD_TEMPLATE = ' * @method %s %s() static %s';
    // use 模板
    const USE_TEMPLATE = "use %s\{%s};";

    protected $stubs = [
        'facade' => 'facade_class',
    ];

    protected function configure()
    {
        $this->setName('yqn:facade_class')
            ->addArgument("dir", Option::VALUE_REQUIRED, "Specified Target Directory")
            ->addOption('prefix', '-p', Option::VALUE_REQUIRED, "Class Prefix")
            ->addOption('suffix', '-s', Option::VALUE_REQUIRED, "Class Suffix")
            //->addOption('namespace', '-ns', Option::VALUE_REQUIRED, "Class NameSpace")
            ->addOption('module', '-m', Option::VALUE_REQUIRED, "specified Module name")
            ->setDescription('Generate  Specifies the folder proxy class');
    }

    protected function execute(Input $input, Output $output)
    {

        // 获取参数和选项

        // 目录文件夹
        $dest_dir = trim($input->getArgument('dir'));

        // 类前缀
        $class_prefix = trim($input->getOption('prefix'));
        // 类后缀
        $class_suffix = trim($input->getOption('suffix'));
        // 命名空间
        // $namespace = trim($input->getOption('namespace'));
        // 指定模块名
        $module = trim($input->getOption('module'));


        $app_path = $this->getApppath();

        if (empty($dest_dir)) {
            $output->error('目录文件不能为空');
            return;
        }

        $dest_dir = strpos($dest_dir, $app_path) === false ? ($app_path . $dest_dir) : $dest_dir;
        if (!file_exists($dest_dir)) {
            $output->error('目录文件夹不存在');
            return;
        }
        $this->getStub();
        $template_file = current($this->stubs);

        if (!file_exists($template_file)) {
            $output->error('stub 文件未找到');
            return;
        }
        $root_namespace = str_replace([$app_path, '/'], ['app\\', '\\'], $dest_dir);
        // 生成保存文件夹目录
        $save_dest_path = $app_path;
        if (!empty($module)) {
            $save_dest_path .= $module . DIRECTORY_SEPARATOR;
        }
        $save_dest_path .= self::MODEL_NAME . DIRECTORY_SEPARATOR;

        if (!file_exists($save_dest_path)) {
            if (!mkdir($save_dest_path, 0644, true) && !is_dir($save_dest_path)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $save_dest_path));
            }
        }

        // 代理类
        $facade_namespace = $this->getFacadeNamespace($module);

        //解析类并生成结果
        $result = [];
        $this->readDir($root_namespace, $dest_dir, $result);


        $template_content = file_get_contents($template_file);
        $parent_root_namespace = dirname($root_namespace) . '\\';

        foreach ($result as $namespace => $item) {


            // 生成要保存的文件夹
            $class_name = $this->parseName(str_replace([$parent_root_namespace, '\\'], ['', '_'], $namespace), 1);
            $save_file = $save_dest_path . $class_name . '.php';
            $method = [];
            foreach ($item as $k => $row) {
                $real_function = str_replace([$class_prefix, $class_suffix], '', $row['class']);
                $method[$row['class']] = sprintf(self::METHOD_TEMPLATE, $row['class'], $real_function, $row['doc']);
            }

            $use_content = sprintf(self::USE_TEMPLATE, $namespace, implode(',', array_keys($method)));
            $method_content = implode("\r\n", array_values($method));

            file_put_contents($save_file, str_replace(['{%namespace%}', '{%className%}', '{%class_comment%}', '{%use%}',
                '{%method%}', '{%relnamespace%}', '{%prefix%}', '{%suffix%}'],
                [
                    $facade_namespace,
                    $class_name,
                    $namespace,
                    $use_content,
                    $method_content,
                    str_replace('\\', '\\\\', $namespace),
                    $class_prefix,
                    $class_suffix,
                ], $template_content));
        }

    }

    protected function getApppath()
    {

        if (defined('APP_PATH')) {
            return APP_PATH;
        }
        //think 5.1以上
        if (class_exists('think\\App')) {
            return call_user_func_array([(new \think\App()), 'getAppPath'], []);
        }
        return false;

    }

    /**
     * 获取模板文件
     * @return mixed
     */
    protected function getStub()
    {
        foreach ($this->stubs as $key => $filename) {
            $this->stubs[$key] = __DIR__ . DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR . $filename . '.stub';
        }
        return $this->stubs;
    }

    /**
     * 生成代理类的命名空间
     * @param $model
     * @return string
     */
    protected function getFacadeNamespace($model)
    {
        return 'app\\' . (empty($model) ? '' : $model) . '\\' . self::MODEL_NAME;
    }

    /**
     * 遍历文件夹
     * @param $dest_dir
     */
    protected function readDir($namespace, $dest_dir, &$result, $level = 0)
    {
        $files = scandir($dest_dir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $filename = $dest_dir . DS . $file;
            if (is_dir($filename) && $level < 3) {
                $this->readDir($namespace . '\\' . $file, $filename, $result, $level + 1);
            } else if ('.' . pathinfo($file, PATHINFO_EXTENSION) === CONF_EXT) {
                $classname = substr($file, 0, -4);
                $full_class = $namespace . '\\' . $classname;
                $parse_result = $this->parseClass($full_class);
                if (!empty($parse_result)) {
                    $parse_result['class'] = $classname;
                    $result[$parse_result['namespace']][] = $parse_result;
                }
            }

        }
    }

    /**
     * 解析类，返回类的基本信息
     * @param $class_name
     * @return array
     */
    protected function parseClass($class_name)
    {

        try {
            // 解析当前类
            $ref = new ReflectionClass($class_name);
            if ($ref->isAbstract() || $ref->isTrait() || $ref->isInterface()) {
                return [];
            }
            $result['name'] = $ref->getName();
            $result['namespace'] = $ref->getNamespaceName();
            $doc = $ref->getDocComment();

            if (!empty($doc)) {
                $doc = explode(PHP_EOL, str_replace(['/**' . PHP_EOL, ' * ', ' */'], '', $ref->getDocComment()))[0];
            }
            $result['doc'] = $doc;
            return $result;
        } catch (ReflectionException $e) {
            echo $e->getMessage() . "\r\n";
            return [];
        }
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
    public function parseName($name = null, $type = 0, $ucfirst = true)
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

