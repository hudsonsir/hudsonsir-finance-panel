<?php
declare (strict_types = 1);

namespace app\middleware;

use think\facade\Db;
use think\facade\Config;
use think\Response;

class LoadConfig
{
    /**
     * 处理请求
     *
     * @param \think\Request $request
     * @param \Closure       $next
     * @return Response
     */
    public function handle($request, \Closure $next)
    {
        if (!file_exists(app()->getRootPath().'.env')){
            $path = $request->pathinfo();
            $path = trim($path, '/');

            if ($path !== 'install' && strpos($path, 'install/') !== 0) {
                $response = Response::create('', 'html', 302);
                $response->header('Location', '/install');
                return $response;
            }

            return $next($request);
        }

        try {
            $res = Db::name('config')->cache('configs',0)->column('value','key');
            Config::set($res, 'sys');
        } catch (\Exception $e) {
        }

        return $next($request);
    }
}
