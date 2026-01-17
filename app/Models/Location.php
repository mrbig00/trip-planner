<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Location extends Model
{
    /** @use HasFactory<\Database\Factories\LocationFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'price',
        'latitude',
        'longitude',
        'link',
        'picture',
        'accepted',
        'trip_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'accepted' => 'boolean',
        ];
    }

    /**
     * Get the trip that the location belongs to.
     */
    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    /**
     * Get the users who voted for this location.
     */
    public function votes(): BelongsToMany
    {
        return $this->belongsToMany(\App\Models\User::class, 'location_user')
            ->withTimestamps();
    }

    /**
     * Get the comments for this location.
     */
    public function comments(): HasMany
    {
        return $this->hasMany(LocationComment::class)->latest();
    }

    /**
     * Accept this location and unaccept all other locations in the trip.
     */
    public function accept(): void
    {
        // Unaccept all locations in this trip
        $this->trip->locations()->where('id', '!=', $this->id)->update(['accepted' => false]);

        // Accept this location
        $this->update(['accepted' => true]);
    }

    /**
     * Toggle vote for the given user.
     */
    public function toggleVote(\App\Models\User $user): bool
    {
        if ($this->votes()->where('user_id', $user->id)->exists()) {
            $this->votes()->detach($user->id);

            return false;
        }

        $this->votes()->attach($user->id);

        return true;
    }

    /**
     * Check if the given user has voted for this location.
     */
    public function hasVoteFrom(\App\Models\User $user): bool
    {
        return $this->votes()->where('user_id', $user->id)->exists();
    }
}
