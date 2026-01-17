<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the user's full name
     */
    public function fullName(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $first = Str::substr($this->first_name ?? '', 0, 1);
        $last = Str::substr($this->last_name ?? '', 0, 1);

        return strtoupper($first.$last);
    }

    /**
     * Get the trips created by the user.
     */
    public function createdTrips(): HasMany
    {
        return $this->hasMany(Trip::class, 'user_id');
    }

    /**
     * Get the trips the user participates in.
     */
    public function trips(): BelongsToMany
    {
        return $this->belongsToMany(Trip::class, 'trip_user')
            ->withTimestamps();
    }

    /**
     * Get the locations the user has voted for.
     */
    public function votedLocations(): BelongsToMany
    {
        return $this->belongsToMany(\App\Models\Location::class, 'location_user')
            ->withTimestamps();
    }
}
