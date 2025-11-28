<?php
// +----------------------------------------------------------------------
// | Cookie设置
// +----------------------------------------------------------------------
return [
    // cookie 保存时间
    'expire'    => 0,
    // cookie 保存路径
    'path'      => '/',
    // cookie 有效域名
    'domain'    => '',
    //  cookie 启用安全传输（HTTPS环境应设置为true）
    'secure'    => env('cookie.secure', false),
    // httponly设置（防止XSS攻击）
    'httponly'  => true,
    // 是否使用 setcookie
    'setcookie' => true,
    // samesite 设置，支持 'strict' 'lax'（防止CSRF攻击）
    'samesite'  => env('cookie.samesite', 'Lax'),
];
