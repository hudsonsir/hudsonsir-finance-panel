<?php
declare (strict_types=1);

namespace app\middleware;

use think\facade\View;

class ViewOutput
{
    /**
     * 处理请求
     *
     * @param \think\Request $request
     * @param \Closure $next
     * @return Response
     */
    public function handle($request, \Closure $next)
    {
        View::assign('islogin', $request->islogin);
        View::assign('user', $request->user);
        View::assign('cdn_cdnjs', config_get('cdn_cdnjs', 'https://s4.zstatic.net/ajax/libs/'));
        View::assign('cdn_npm', config_get('cdn_npm', 'https://unpkg.com/'));
        View::config(['view_path' => app()->getRootPath() . config("view.view_dir_name") . '/index/default' . DIRECTORY_SEPARATOR]);
        return $next($request)->header([
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
        ]);
    }
}
