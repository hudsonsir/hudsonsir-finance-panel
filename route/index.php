<?php

use think\facade\Route;

// 首页路由 - 检测安装状态
Route::get('/', 'Index/index');

// 安装路由
Route::get('/install', 'Install/index');
Route::post('/install/database', 'Install/database');
Route::post('/install/init_data', 'Install/init_data');
Route::post('/install/admin', 'Install/admin');

// 极验配置接口 - 独立出来，不需要认证
Route::get('/auth/geetest/config', 'Auth/geetestConfig')
    ->middleware(\app\middleware\LoadConfig::class);

Route::group(function () {
    Route::any('/login', 'login')->middleware(\app\middleware\ViewOutput::class);
    Route::get('/logout', 'logout');

    Route::post('/adminlogin', 'adminlogin');
    Route::get('/adminlogout', 'adminlogout');
})->prefix('Auth/')
    ->middleware(\app\middleware\LoadConfig::class)
    ->middleware(\app\middleware\AuthUser::class);

