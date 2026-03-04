<?php

namespace Webkul\Notification\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webkul\Notification\Contracts\CrmNotification as CrmNotificationContract;
use Webkul\User\Models\UserProxy;

class CrmNotification extends Model implements CrmNotificationContract
{
    protected $table = 'crm_notifications';

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'body',
        'data',
        'read_at',
    ];

    protected $casts = [
        'data'    => 'array',
        'read_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(UserProxy::modelClass());
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }
}
