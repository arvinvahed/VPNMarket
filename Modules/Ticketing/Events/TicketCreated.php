<?php

namespace Modules\Ticketing\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Ticketing\Models\Ticket;

class TicketCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $ticket; // تیکت ایجاد شده رو عمومی می‌کنیم

    /**
     * Create a new event instance.
     *
     * @param \Modules\Ticketing\Models\Ticket $ticket
     * @return void
     */
    public function __construct(Ticket $ticket)
    {
        $this->ticket = $ticket;
    }


}
