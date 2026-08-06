<?php

namespace App\Models;

use App\Enums\ExpenseSplitType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Expense extends Model
{
    /** @use HasFactory<\Database\Factories\ExpenseFactory> */
    use HasFactory;

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
        'split_type',
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
     * Calculate the total price for this expense.
     */
    public function getTotalAttribute(): float
    {
        return $this->unit_price * $this->quantity;
    }

    /**
     * Calculate the total price for this expense in integer cents.
     */
    public function getTotalInCentsAttribute(): int
    {
        return (int) bcmul(bcmul((string) $this->unit_price, '100', 0), (string) $this->quantity, 0);
    }
}
