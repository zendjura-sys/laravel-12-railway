<?php

namespace Addons\Bonuses\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankTransfer extends Model
{
    protected $table = 'bank_transfers';

    protected $fillable = ['from_user_id', 'to_user_id', 'amount', 'note'];

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }
}
