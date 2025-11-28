<?php

namespace app\controller;

class Index extends Base
{
    /**
     * 重写 initialize 方法，避免在未安装时触发数据库连接
     */
    protected function initialize()
    {
        if (!file_exists(app()->getRootPath() . 'install.lock')) {
            return;
        }

        parent::initialize();
    }
    
    public function index()
    {
        if (!file_exists(app()->getRootPath() . 'install.lock')) {
            $installHtml = app()->getRootPath() . 'view/install/index.html';
            if (file_exists($installHtml)) {
                return response(file_get_contents($installHtml))->contentType('text/html');
            }
            return response('<html><body><h1>安装向导</h1><p>系统未安装，请访问 /install 进行安装</p></body></html>');
        }

        $indexHtml = app()->getRootPath() . 'public/index.html';
        if (file_exists($indexHtml)) {
            return response(file_get_contents($indexHtml))->contentType('text/html');
        }

        return response('<html><body><h1>系统已安装</h1><p>请确保 public/index.html 文件存在</p></body></html>');
    }
}

