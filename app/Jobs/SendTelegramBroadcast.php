<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\TelegramBot\Http\Controllers\WebhookController; // <-- از کنترلر ربات استفاده می‌کنیم

class SendTelegramBroadcast implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $message;

    /**
     * Create a new job instance.
     */
    public function __construct(string $message)
    {
        $this->message = $message;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $controller = new WebhookController();

        User::whereNotNull('telegram_chat_id')
            ->select('telegram_chat_id')
            ->chunk(100, function ($users) use ($controller) {
                foreach ($users as $user) {

                    $controller->sendBroadcastMessage($user->telegram_chat_id, $this->message);


                    usleep(50000);
                }
            });
    }
}
