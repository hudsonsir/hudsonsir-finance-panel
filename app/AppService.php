<?php
declare (strict_types = 1);

namespace app;

use think\Service;

/**
 * 应用服务类
 */
class AppService extends Service
{
    public function register()
    {
    }

    public function boot()
    {
        $this->commands([
            \app\command\SendExpireReminder::class,
        ]);
    }
}
