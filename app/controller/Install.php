<?php

namespace app\controller;


use app\lib\EnvOperation;
use app\lib\ExecSQL;
use PDO;
use think\Exception;
use think\facade\Cache;
use think\facade\Request;
use think\facade\Validate;
use think\facade\View;
use think\helper\Str;


class Install extends Base
{

    public function __destruct()
    {
        Cache::clear();
        reset_opcache();
    }

    public function initialize()
    {
        if (file_exists(app()->getRootPath() . 'install.lock')) {
            exit('你已安装成功，需要重新安装请删除 install.lock 文件');
        }
        Cache::clear();
        reset_opcache();
    }

    public function index()
    {
        $requirements = [
            'PHP >= 7.4' => version_compare(PHP_VERSION, '7.4.0', '>='),
            'PDO_MySQL' => extension_loaded("pdo_mysql"),
            'CURL' => extension_loaded("curl"),
            'ZipArchive' => class_exists("ZipArchive"),
            'runtime写入权限' => is_writable(app()->getRuntimePath()),
        ];
        reset_opcache();
        $step = Request::param('step');
        View::assign([
            'step' => $step,
            'requirements' => $requirements,
        ]);
        return view('../view/install/index.html');
    }

    public function database()
    {
        $params = Request::param();
        $rules = [
            'hostname' => 'require',
            'hostport' => 'require|integer',
            'username' => 'require',
            'password' => 'require',
            'database' => 'require',
        ];
        $validate = Validate::rule($rules);
        if (!$validate->check($params)) {
            return msg('error', $validate->getError());
        }

        // 安全改进：验证输入，防止注入
        $hostname = preg_replace('/[^a-zA-Z0-9._-]/', '', $params['hostname']); // 只允许字母数字点下划线横线
        $database = preg_replace('/[^a-zA-Z0-9_]/', '', $params['database']); // 只允许字母数字下划线
        $hostport = intval($params['hostport']);
        if($hostport < 1 || $hostport > 65535) {
            return msg('error', '端口范围不正确');
        }
        
        $dsn = 'mysql:host=' . $hostname . ';dbname=' . $database . ';port=' . $hostport . ';charset=utf8';
        try {
            new PDO($dsn, $params['username'], $params['password']);
        } catch (\Exception $e) {
            // 安全改进：不直接返回详细错误信息
            return msg('error', '数据库连接失败，请检查配置信息');
        }
        try {
            $envFile = file_get_contents(app()->getRootPath() . '.env.example');
            $envOperation = new EnvOperation($envFile);
            foreach (array_keys($rules) as $value) {
                $envOperation->set(mb_strtoupper($value), $params[$value]);
            }
            $envOperation->save();
        } catch (\Exception $e) {
            // 安全改进：记录详细错误到日志，但只返回通用错误信息
            \think\facade\Log::error('保存环境配置失败: ' . $e->getMessage());
            return msg('error', '保存配置失败，请检查文件权限');
        }
        return msg();
    }

    public function init_data()
    {
        try {

            $filename = app()->getRootPath() . 'install.sql';
            if (!is_file($filename)) {
                throw new Exception('数据库 install.sql 文件不存在');
            }
            $install_sql = file($filename);
            //写入数据库
            $execSQL = new ExecSQL();
            $install_sql = $execSQL->purify($install_sql);
            foreach ($install_sql as $sql) {
                $execSQL->exec($sql);
                if (!empty($execSQL->getErrors())) {
                    throw new Exception($execSQL->getErrors()[0]);
                }
            }
            
            // 在所有表创建完成后，设置默认邮件模板
            // 确保数据库前缀已设置
            if (file_exists(app()->getRootPath() . '.env')) {
                app()->loadEnv();
            }
            \think\facade\Db::setConfig(['prefix' => 'panel_'], 'mysql');
            
            // 更新默认邮件通道的模板
            $defaultTemplate = \app\service\EmailService::getDefaultTemplate();
            $emailChannel = \think\facade\Db::name('notification')
                ->where('channel_type', 'email')
                ->find();
            if ($emailChannel) {
                $config = json_decode($emailChannel['config'], true);
                $config['template'] = $defaultTemplate;
                \think\facade\Db::name('notification')
                    ->where('id', $emailChannel['id'])
                    ->update([
                        'config' => json_encode($config, JSON_UNESCAPED_UNICODE),
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);
            }
        } catch (\Exception $e) {
            // 安全改进：记录详细错误到日志，但只返回通用错误信息
            \think\facade\Log::error('数据库初始化失败: ' . $e->getMessage());
            return msg('error', '数据库初始化失败，请检查配置和权限');
        }
        return msg();
    }

    public function admin()
    {
        $params = Request::param();
        $rules = [
            'username|用户名' => 'require',
            'password|密码' => 'require',
        ];
        $validate = Validate::rule($rules);
        if (!$validate->check($params)) {
            return msg('error', $validate->getError());
        }
        config_set("admin_username", $params['username']);
        config_set("admin_password", password_hash($params['password'], PASSWORD_DEFAULT));
        config_set("syskey", Str::random(16));
        file_put_contents(app()->getRootPath() . 'install.lock', format_date());
        return msg();
    }

}
