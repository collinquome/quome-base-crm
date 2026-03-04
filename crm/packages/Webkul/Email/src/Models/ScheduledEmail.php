<?php

namespace Webkul\Email\Models;

use Illuminate\Database\Eloquent\Model;

class ScheduledEmail extends Model
{
    protected $table = 'scheduled_emails';

    protected $fillable = [
        'email_id',
        'scheduled_at',
        'sent_at',
        'status',
        'error_message',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'sent_at'      => 'datetime',
    ];

    public function email()
    {
        return $this->belongsTo(EmailProxy::modelClass());
    }
}
