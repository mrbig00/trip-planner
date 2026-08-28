<?php

namespace App\Models;

use App\Enums\Currency;
use App\Enums\ExpenseSplitType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Expense extends Model
{
    /** @use HasFactory<\Database\Factories\ExpenseFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'description',
        'link',
        'unit_price',
        'quantity',
        'trip_id',
        'user_id',
        'updated_by',
        'deleted_by',
        'split_type',
        'currency',
        'exchange_rate',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'quantity' => 'integer',
            'split_type' => ExpenseSplitType::class,
            'currency' => Currency::class,
            'exchange_rate' => 'decimal:6',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Get the trip that the expense belongs to.
     */
    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    /**
     * Get the user who owns this expense.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the per-participant shares of this expense.
     */
    public function shares(): HasMany
    {
        return $this->hasMany(ExpenseShare::class);
    }

    /**
     * Get the user who last edited this expense, if any.
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get the user who deleted this expense, if any.
     */
    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    /**
     * Calculate the total price for this expense.
     */
    public function getTotalAttribute(): float
    {
        return $this->unit_price * $this->quantity;
    }

    /**
     * Calculate the total price for this expense in integer cents, in this
     * expense's OWN currency (not the trip's — see convertToTripCurrencyCents()).
     */
    public function getTotalInCentsAttribute(): int
    {
        return (int) bcmul(bcmul((string) $this->unit_price, '100', 0), (string) $this->quantity, 0);
    }

    /**
     * Convert a decimal amount denominated in this expense's OWN currency
     * (e.g. an expense_shares.amount, or this expense's own total) into
     * integer cents of the TRIP's currency, using exchange_rate (1 unit of
     * expense currency = exchange_rate units of trip currency). A null
     * exchange_rate means this expense is already in the trip's currency, an
     * implicit rate of 1 — see 2026_08_28_000002_add_currency_and_exchange_rate_to_expenses_table.
     *
     * bcmath only (no floats), matching total_in_cents/Money's precision style.
     */
    public function convertToTripCurrencyCents(string $decimalAmountInExpenseCurrency): int
    {
        $rate = $this->exchange_rate !== null ? (string) $this->exchange_rate : '1';
        $convertedDecimal = bcmul($decimalAmountInExpenseCurrency, $rate, 6);

        return (int) bcmul($convertedDecimal, '100', 0);
    }

    /**
     * This expense's total, converted into the trip's currency. This is what
     * balance/settlement/budget math should always use instead of
     * total_in_cents, so mixed-currency expenses stay commensurable within a
     * trip.
     */
    public function getConvertedTotalCentsAttribute(): int
    {
        return $this->convertToTripCurrencyCents((string) $this->total);
    }
}
