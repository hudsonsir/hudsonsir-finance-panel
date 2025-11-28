<?php
declare (strict_types=1);

namespace app\middleware;

use think\facade\Db;

class AuthUser
{
    public function handle($request, \Closure $next)
    {
        $islogin = false;
        $cookie = cookie('user_token');
        $user = null;
        if($cookie){
            $token=authcode($cookie, 'DECODE', config_get('syskey'));
            if($token){
                $tokenParts = explode("\t", $token);
                if(count($tokenParts) === 3) {
                    list($uid, $sid, $expiretime) = $tokenParts;
                    // 验证UID是否为数字，防止SQL注入
                    if(!is_numeric($uid)) {
                        return $next($request);
                    }
                    $user = Db::name('user')->where('id', intval($uid))->find();
                    if($user && $user['enable']==1){
                        // 安全改进：使用hash_hmac替代md5
                        $session = hash_hmac('sha256', $user['id'] . $user['password'], config_get('syskey'));
                        if($session==$sid && $expiretime>time()) {
                            if(!$user['avatar_url']) $user['avatar_url'] = '/static/images/user.png';
                            $islogin = true;
                        }
                    }elseif($user && $user['enable']==0 && !session('user_block')){
                        session('user_block', '1');
                    }
                }
            }
        }
        $request->islogin = $islogin;
        $request->user = $user;
        /*if (!$islogin) {
            if ($request->isAjax() || !$request->isGet()) {
                return msg('error','请登录');
            }
            return redirect((string)url('/login'));
        }*/
        return $next($request);
    }
}
