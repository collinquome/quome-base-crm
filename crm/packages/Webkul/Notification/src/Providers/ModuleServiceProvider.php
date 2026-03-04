<?php

namespace Webkul\Notification\Providers;

use Konekt\Concord\BaseModuleServiceProvider;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected $models = [
        \Webkul\Notification\Models\CrmNotification::class,
        \Webkul\Notification\Models\Comment::class,
    ];
}
