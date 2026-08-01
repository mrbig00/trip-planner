<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProvider extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'provider',
        'provider_id',
    ];

    /**
     * Get the user this provider link belongs to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
