<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Trip extends Model
{
    /** @use HasFactory<\Database\Factories\TripFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'description',
        'user_id',
    ];

    /**
     * Get the user that created the trip.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the users (participants) that belong to the trip.
     */
    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'trip_user')
            ->withTimestamps();
    }

    /**
     * Get the locations for the trip.
     */
    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    /**
     * Get the expenses for the trip.
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }
}
