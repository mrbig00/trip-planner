<?php

namespace App\Models;

use App\Support\Money;
use App\Enums\Currency;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Trip extends Model
{
    /** @use HasFactory<\Database\Factories\TripFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        // trip_documents.trip_id cascades at the database level, which would
        // delete the rows directly — bypassing TripDocument::booted()'s own
        // `deleting` hook that removes the underlying stored file. Walk them
        // through Eloquent explicitly first so uploads never outlive the
        // trip on disk.
        static::deleting(function (Trip $trip) {
            $trip->documents()->get()->each->delete();
        });
    }

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
        'budget',
        'currency',
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
            'budget' => 'decimal:2',
            'currency' => Currency::class,
        ];
    }

    /**
     * Scope a query to trips the given user created or participates in.
     */
    public function scopeVisibleTo(Builder $query, int $userId): Builder
    {
        return $query->where(function (Builder $query) use ($userId) {
            $query->where('user_id', $userId)
                ->orWhereHas('participants', function (Builder $query) use ($userId) {
                    $query->where('user_id', $userId);
                });
        });
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
            ->withPivot(['color_slot'])
            ->withTimestamps();
    }

    /**
     * Get the given user's fixed identity color slot for this trip (one of
     * 1/2/3/5/7 — see resources/css/app.css's --color-participant-* tokens),
     * or null if they aren't a member. The creator always gets slot 1; every
     * other participant's slot is assigned once, at attach time, and never
     * changes — see App\Livewire\Trips\Show::addParticipant().
     */
    public function colorSlotFor(User $user): ?int
    {
        if ($user->id === $this->user_id) {
            return 1;
        }

        $participant = $this->participants->firstWhere('id', $user->id);

        // Cast explicitly: the pivot's color_slot has no declared cast, and the
        // Postgres driver returns unwrapped numeric columns as strings.
        return $participant?->pivot->color_slot !== null
            ? (int) $participant->pivot->color_slot
            : null;
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
     * Get the documents attached to the trip, newest first.
     */
    public function documents(): HasMany
    {
        return $this->hasMany(TripDocument::class)->latest();
    }

    /**
     * Get the trip's creator and participants as a single deduplicated collection of users.
     */
    public function members(): Collection
    {
        return $this->participants->concat([$this->creator])->filter()->unique('id')->sortBy('id')->values();
    }

    /**
     * Get a short "where this trip stands in time" label for the header
     * chip, or null when neither date is set (the chip simply doesn't
     * render — no fabricated threshold).
     */
    public function countdownLabel(): ?string
    {
        if (! $this->start_date && ! $this->end_date) {
            return null;
        }

        $today = today();

        if ($this->end_date && $today->greaterThan($this->end_date)) {
            return __('Trip ended');
        }

        if ($this->start_date && $today->equalTo($this->start_date)) {
            return __('Starting today');
        }

        if ($this->start_date && $today->lessThan($this->start_date)) {
            $days = (int) $today->diffInDays($this->start_date);
            $unit = $days === 1 ? __('day') : __('days');

            return __(':days :unit to go', ['days' => $days, 'unit' => $unit]);
        }

        return __('Happening now');
    }

    /**
     * Calculate each member's balance in integer cents, in this trip's own
     * currency: positive means the member is owed money, negative means the
     * member owes money. Requires `expenses.shares` to be eager-loaded. Nets
     * out recorded settlements, so this always reflects who owes whom *right
     * now*, not the gross figure before anyone paid anyone back.
     *
     * Expenses may be recorded in a different currency than the trip's — each
     * expense converts its own total/shares into the trip's currency via its
     * exchange_rate before being netted here, so this always returns a
     * single-currency figure. Shares are converted together via
     * Expense::convertedShareCentsByUserId(), not individually, so their sum
     * always matches converted_total_cents exactly — converting each share
     * on its own can drift by a cent or two from independent truncation,
     * which would otherwise silently break the zero-sum invariant below.
     */
    public function balances(): Collection
    {
        $balances = $this->members()->mapWithKeys(fn (User $member) => [$member->id => 0]);

        foreach ($this->expenses as $expense) {
            if ($expense->user_id && $balances->has($expense->user_id)) {
                $balances[$expense->user_id] += $expense->converted_total_cents;
            }

            foreach ($expense->convertedShareCentsByUserId() as $userId => $cents) {
                if ($balances->has($userId)) {
                    $balances[$userId] -= $cents;
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

    /**
     * Get the trip's total spend across all expenses, converted into the
     * trip's own currency (see Expense::convertToTripCurrencyCents()) so
     * expenses recorded in a different currency are still commensurable.
     * Requires `expenses` to be eager-loaded to avoid N+1s.
     */
    public function getTotalSpentAttribute(): float
    {
        return (float) Money::fromCents(
            $this->expenses->sum(fn (Expense $expense) => $expense->converted_total_cents)
        );
    }

    /**
     * Get a summary of this trip's budget status, or null if no budget has
     * been set. `remaining` is negative once the trip is over budget.
     * `percentUsed` is capped at 100 (for progress-bar widths) and treats a
     * $0 budget as fully used regardless of spend, since there's no room
     * left to spend against it. `percentRaw` is left uncapped (and left at
     * 0 for a $0 budget with nothing spent) so callers ranking trips by "how
     * over budget" can still tell a trip 10% over from one 200% over.
     * Requires `expenses` to be eager-loaded.
     *
     * @return array{spent: float, budget: float, remaining: float, percentUsed: float, percentRaw: float, overBudget: bool}|null
     */
    public function getBudgetSummaryAttribute(): ?array
    {
        if ($this->budget === null) {
            return null;
        }

        $spent = $this->total_spent;
        $budget = (float) $this->budget;
        $percentRaw = $budget > 0 ? ($spent / $budget) * 100 : ($spent > 0 ? INF : 0.0);

        return [
            'spent' => $spent,
            'budget' => $budget,
            'remaining' => $budget - $spent,
            'percentUsed' => $budget > 0 ? min(100.0, $percentRaw) : 100.0,
            'percentRaw' => $percentRaw,
            'overBudget' => $spent > $budget,
        ];
    }
}
