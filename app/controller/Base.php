<?php


namespace app\controller;


use app\BaseController;
use think\facade\View;

class Base extends BaseController
{
    protected $clientip;
    
    protected function initialize()
    {
        // 检查是否已安装，如果未安装则跳过需要数据库的操作
        if (!file_exists(app()->getRootPath() . 'install.lock')) {
            $this->clientip = real_ip(0);
        } else {
            $this->clientip = real_ip(config_get('ip_type')??0);
        }
        parent::initialize();
    }

    protected function alert($code, $msg = '', $url = null, $wait = 3)
    {
        if ($url) {
            $url = (strpos($url, '://') || 0 === strpos($url, '/')) ? $url : (string)$this->app->route->buildUrl($url);
        }
        if(empty($msg)) $msg = '未知错误';
        
        View::assign([
            'code' => $code,
            'msg' => $msg,
            'url' => $url,
            'wait' => $wait,
        ]);
        return View::fetch(app()->getRootPath().'view/dispatch_jump.html');
    }

}