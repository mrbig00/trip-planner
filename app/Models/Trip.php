<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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
        'start_date',
        'end_date',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

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

    /**
     * Get the recorded settlements for the trip.
     */
    public function settlements(): HasMany
    {
        return $this->hasMany(Settlement::class);
    }

    /**
     * Get the trip's creator and participants as a single deduplicated collection of users.
     */
    public function members(): Collection
    {
        return $this->participants->concat([$this->creator])->filter()->unique('id')->sortBy('id')->values();
    }

    /**
     * Calculate each member's balance in integer cents: positive means the
     * member is owed money, negative means the member owes money. Requires
     * `expenses.shares` to be eager-loaded. Nets out recorded settlements, so
     * this always reflects who owes whom *right now*, not the gross figure
     * before anyone paid anyone back.
     */
    public function balances(): Collection
    {
        $balances = $this->members()->mapWithKeys(fn (User $member) => [$member->id => 0]);

        foreach ($this->expenses as $expense) {
            if ($expense->user_id && $balances->has($expense->user_id)) {
                $balances[$expense->user_id] += $expense->total_in_cents;
            }

            foreach ($expense->shares as $share) {
                if ($balances->has($share->user_id)) {
                    $balances[$share->user_id] -= Money::toCents((string) $share->amount);
                }
            }
        }

        foreach ($this->settlements as $settlement) {
            // A settlement of amount_cents from A to B means A already paid B:
            // A's remaining debt shrinks, B's remaining receivable shrinks.
            if ($balances->has($settlement->from_user_id)) {
                $balances[$settlement->from_user_id] += $settlement->amount_cents;
            }

            if ($balances->has($settlement->to_user_id)) {
                $balances[$settlement->to_user_id] -= $settlement->amount_cents;
            }
        }

        return $balances;
    }
}
