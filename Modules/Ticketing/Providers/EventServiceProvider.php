<?php

namespace Modules\Ticketing\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Ticketing\Events\TicketReplied;
use Modules\TelegramBot\Listeners\SendTelegramReplyNotification;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        // فقط این قسمت باید اینجا باشد
        TicketReplied::class => [
            SendTelegramReplyNotification::class,
        ],
    ];

    public function boot(): void
    {
        parent::boot();
    }
}
