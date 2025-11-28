<?php
declare (strict_types=1);

namespace app\middleware;


class AuthAdmin
{
    public function handle($request, \Closure $next)
    {
        $islogin = false;
        $cookie = cookie('admin_token');
        if($cookie){
            $token=authcode($cookie, 'DECODE', config_get('syskey'));
            if($token){
                $tokenParts = explode("\t", $token);
                if(count($tokenParts) === 3) {
                    list($user, $sid, $expiretime) = $tokenParts;
                    // 安全改进：使用hash_hmac替代md5
                    $session = hash_hmac('sha256', config_get('admin_username') . config_get('admin_password'), config_get('syskey'));
                    if($session==$sid && $expiretime>time()) {
                        $islogin = true;
                    }
                }
            }
        }
        if (!$islogin) {
            if ($request->isAjax() || !$request->isGet()) {
                return msg('error', '请登录')->code(401);
            }
            return redirect((string)url('/admin/login.html'));
        }
        return $next($request);
    }
}
