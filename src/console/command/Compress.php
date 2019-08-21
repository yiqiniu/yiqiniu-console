<?php
/**
 * Created by PhpStorm.
 * User: gjianbo
 * Date: 2016-09-25
 * Time: 9:10
 */

namespace yiqiniu\console\command;


use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;


class Compress extends Command
{
    protected $_config = [
        //跳过的文件和目录
        'skip' => [
            'file' => ['command.php', 'build.php', '.htaccess'],
            'dir' => ['console'],
        ],
        //不加密文件名和目录
        'ignore' => [
            'file' => ['config.php', 'database.php', '.htaccess'],
            'dir' => [],
        ]
    ];

    function zipJs($js)
    {
        $h1 = 'http://';
        $s1 = '【:??】';    //标识“http://”,避免将其替换成空
        $h2 = 'https://';
        $s2 = '【s:??】';    //标识“https://”
        /*preg_match_all('#include\("([^"]*)"([^)]*)\);#isU',$js,$arr);
        if(isset($arr[1])){
            foreach ($arr[1] as $k=>$inc){
                $path = "http://www.xxx.com/";          //这里是你自己的域名路径
                $temp = file_get_contents($path.$inc);
                $js = str_replace($arr[0][$k],$temp,$js);
            }
        }*/

        $js = preg_replace('#function include([^}]*)}#isU', '', $js);//include函数体
        $js = preg_replace('#\/\*.*\*\/#isU', '', $js);//块注释
        $js = str_replace($h1, $s1, $js);
        $js = str_replace($h2, $s2, $js);
        $js = preg_replace('#\/\/[^\n]*#', '', $js);//行注释
        $js = preg_replace("/<!--[^!]*-->/", '', $js);//HTML注释
        $js = str_replace($s1, $h1, $js);
        $js = str_replace($s2, $h2, $js);
        $js = str_replace("\t", "", $js);//tab
        $js = preg_replace('#\s?(=|>=|\?|:|==|\+|\|\||\+=|>|<|\/|\-|,|\()\s?#', '$1', $js);//字符前后多余空格
        $js = str_replace("\t", "", $js);//tab
        $js = str_replace("\r\n", "", $js);//回车
        $js = str_replace("\r", "", $js);//换行
        $js = str_replace("\n", "", $js);//换行
        $js = preg_replace('/\s{2,}/', ' ', $js);    //两个或两个以上的空格
        //$js = preg_replace('/\s{3,}/','',$js);
        $js = trim($js, " ");
        return $js;
    }

    protected function configure()
    {
        $this
            ->setName('compress')
            ->setDefinition([
                new Option('output', null, Option::VALUE_OPTIONAL, "compress output dir name"),
                new Option('module', null, Option::VALUE_OPTIONAL, "specified compress module name"),
            ])
            ->setDescription('compress scoure file');
    }

    protected function execute(Input $input, Output $output)
    {
        if (defined('APP_PATH')) {
            $indir = APP_PATH;
        } else {
            $indir = \think\facade\Env::get('app_path');
        }
        //要加密的文件夹

        $appname = basename($indir);

        //加密输出的文件夹
        if ($input->hasOption('output')) {
            $outname = $input->getOption('output');
        } else {
            $outname = basename($indir) . '_en';
        }
        $outdir = str_replace($appname, $outname, $indir);
        if (!file_exists($outdir)) {
            if (!mkdir($outdir, 0777, true) && !is_dir($outdir)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $outdir));
            }
        }

        //指定的模块
        if ($input->hasOption('module')) {
            $module = $input->getOption('module');
            if (is_dir($indir . $module)) {
                $indir .= $module;
                $outdir .= $module;
            } else {
                echo 'module not found';
                return;
            }
        }
        $this->startEncrypt($indir, $outdir);
        echo 'compress finish. output dir ' . $outdir;
    }

    /**
     * 开始加密
     * @param $indir        要加密的文件夹
     * @param $outdir       加密输出的文件夹
     */
    protected function startEncrypt($indir, $outdir)
    {
        if (is_dir($indir)) {
            if ($dh = opendir($indir)) {
                while (($file = readdir($dh)) !== false) {
                    if ((is_dir($indir . "/" . $file)) && $file != "." && $file != "..") {
                        if (in_array($file, $this->_config['skip']['dir'])) {
                            continue;
                        }
                        if (in_array($file, $this->_config['ignore']['dir'])) {
                            $this->copy_dir($indir . $file, $outdir . $file);
                            continue;
                        }
                        if (!is_dir($outdir . $file)) {
                            if (!mkdir($concurrentDirectory = $outdir . $file, 0777, true) && !is_dir($concurrentDirectory)) {
                                throw new \RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
                            }
                        }
                        $this->startEncrypt($indir . $file . '/', $outdir . $file . '/');
                    } else {
                        if ($file != "." && $file != "..") {
                            if (in_array($file, $this->_config['skip']['file'])) {
                                continue;
                            }
                            if (in_array($file, $this->_config['ignore']['file'])) {
                                copy($indir . $file, $outdir . $file);
                                continue;
                            }
                            //如果是PHP的话
                            if (strtolower(substr($file, -3)) == 'php') {
                                $this->encrypt($indir . $file, $outdir . $file);
                            } else
                                $this->compress_html($indir . $file, $outdir . $file);

                        }
                    }
                }
                closedir($dh);
            }
        }
    }

    /**
     * 复制文件夹
     * @param $src 源文件夹
     * @param $dst 目标谇夹
     */
    private function copy_dir($src, $dst)
    {
        $dir = opendir($src);
        if (!is_dir($dst)) {
            if (!mkdir($dst, 0777, true) && !is_dir($dst)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $dst));
            }
        }
        while (false !== ($file = readdir($dir))) {
            if (($file != '.') && ($file != '..')) {
                if (is_dir($src . '/' . $file)) {
                    $this->copy_dir($src . '/' . $file, $dst . '/' . $file);
                    continue;
                } else {
                    copy($src . '/' . $file, $dst . '/' . $file);
                }
            }
        }
        closedir($dir);
    }

    /**
     * @param $infile
     * @param $outfile
     */
    private function encrypt($infile, $outfile)
    {
        $content = php_strip_whitespace($infile);
        //$content = $this->phpencode(trim(substr($content, 2)));
        file_put_contents($outfile, $content);
    }

    /**
     * 压缩html : 清除换行符,清除制表符,去掉注释标记
     * @param $string
     * @return 压缩后的$string
     * */
    function compress_html($infile, $outfile)
    {
        $js = file_get_contents($infile);
        $h1 = 'http://';
        $s1 = '【:??】';    //标识“http://”,避免将其替换成空
        $h2 = 'https://';
        $s2 = '【s:??】';    //标识“https://”
        $js = preg_replace('#function include([^}]*)}#isU', '', $js);//include函数体
        $js = preg_replace('#\/\*.*\*\/#isU', '', $js);//块注释
        $js = str_replace($h1, $s1, $js);
        $js = str_replace($h2, $s2, $js);
        $js = preg_replace('#\/\/[^\n]*#', '', $js);//行注释
        $js = preg_replace("/<!--[^!]*-->/", '', $js);//HTML注释
        $js = str_replace($s1, $h1, $js);
        $js = str_replace($s2, $h2, $js);
        $js = str_replace("\t", "", $js);//tab
        $js = preg_replace('#\s?(=|>=|\?|:|==|\+|\|\||\+=|>|<|\/|\-|,|\()\s?#', '$1', $js);//字符前后多余空格
        $js = str_replace("\t", "", $js);//tab
        $js = str_replace("\r\n", "", $js);//回车
        $js = str_replace("\r", "", $js);//换行
        $js = str_replace("\n", "", $js);//换行
        $js = preg_replace('/\s{2,}/', ' ', $js);    //两个或两个以上的空格
        $js = trim($js, " ");
        file_put_contents($outfile, $js);
    }

    /**
     * 对PHP源码进行base64 gzdeflate 编码
     * @param $code
     * @return string
     */
    private function phpencode($code)
    {
        $code = str_replace(array('<?php', '?>', '<?PHP'), array('', '', ''), $code);
        $encode = base64_encode(gzdeflate($code));// 开始编码
        $encode = '<?php' . "\neval(gzinflate(base64_decode(" . "'" . $encode . "'" . ")));\n?>";
        return $encode;
    }
    //压缩JS文件并替换JS嵌套include文件

    /**
     * 对编码后的PHP进行解码
     * @param $code
     * @return string
     */
    private function phpdecode($code)
    {
        $code = str_replace(array('<?php', '<?PHP', "eval(gzinflate(base64_decode('", "')));", '?>'), array('', '', '', '', '', ''), $code);
        $decode = base64_decode($code);
        $decode = @gzinflate($decode);
        return $decode;
    }
}