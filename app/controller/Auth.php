<?php

namespace app\controller;

use think\facade\Db;
use think\facade\Request;
use think\facade\Session;
use think\facade\View;

class Auth extends Base
{

    public function login()
    {
        if(request()->islogin) {
            return $this->alert('success', '已登录', '/');
        }
        return view();
    }

    public function logout()
    {
        //session(null);
        cookie('user_token', null);
        return redirect(request()->header('referer') ?? '/');
    }

    public function adminlogin(){
        $username = input('post.username',null,'trim');
        $password = input('post.password',null,'trim');

        if(empty($username) || empty($password)){
            return msg('error', '用户名或密码不能为空');
        }

        $captcha_result = verify_captcha4_slide();
        if($captcha_result !== true){
            return msg('error', $captcha_result ? $captcha_result : '验证码验证失败，请重新验证');
        }

        if($username == config_get('admin_username') && password_verify($password, config_get('admin_password'))){
            $session = hash_hmac('sha256', $username . config_get('admin_password'), config_get('syskey'));
            $expiretime = time() + 7200; // 2小时，替代原来的30天
            $token = authcode("{$username}\t{$session}\t{$expiretime}", 'ENCODE', config_get('syskey'));
            cookie('admin_token', $token, ['expire' => $expiretime, 'httponly' => true]);
            config_set('admin_lastlogin', date('Y-m-d H:i:s'));
            return msg();
        }else{
            return msg('error', '用户名或密码错误');
        }
    }
    
    /**
     * 获取极验4.0配置（用于前端初始化）
     */
    public function geetestConfig(){
        $captcha_id = config_get('captcha_id');
        if(empty($captcha_id)){
            return msg('error', '极验配置未设置');
        }
        return msg('ok', 'success', ['captcha_id' => $captcha_id]);
    }

    public function adminlogout()
    {
        cookie('admin_token', null);
        return redirect('/admin/login.html');
    }
    
}