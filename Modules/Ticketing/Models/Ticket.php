<?php

namespace Modules\Ticketing\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    protected $fillable = ['user_id', 'subject', 'message', 'priority', 'source','status'];
    public function user() { return $this->belongsTo(User::class); }
    public function replies() { return $this->hasMany(TicketReply::class); }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }
}
