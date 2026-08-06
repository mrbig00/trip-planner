<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('expense_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_id')->constrained()->onDelete('cascade');
            // Restricted (not cascade): a user with recorded shares can't be deleted
            // out from under an expense, which would silently break the invariant
            // that an expense's shares always sum to its total. See DeleteUserForm.
            $table->foreignId('user_id')->constrained()->onDelete('restrict');
            $table->decimal('amount', 10, 2);
            $table->decimal('percentage', 5, 2)->nullable();
            $table->timestamps();

            $table->unique(['expense_id', 'user_id']);
        });

        $this->backfillEqualSharesForExistingExpenses();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expense_shares');
    }

    /**
     * Give every pre-existing expense an equal-split share among the trip's
     * creator and participants at the time this migration runs, so historical
     * expenses aren't silently ignored by balance calculations.
     *
     * Public (rather than private) so it can be re-invoked directly in tests
     * to verify it is idempotent, without re-running Schema::create().
     */
    public function backfillEqualSharesForExistingExpenses(): void
    {
        DB::table('expenses')
            ->select('id', 'trip_id', 'unit_price', 'quantity')
            ->orderBy('id')
            ->chunkById(200, function ($expenses) {
                foreach ($expenses as $expense) {
                    if (DB::table('expense_shares')->where('expense_id', $expense->id)->exists()) {
                        continue;
                    }

                    $creatorId = DB::table('trips')->where('id', $expense->trip_id)->value('user_id');

                    $memberIds = DB::table('trip_user')
                        ->where('trip_id', $expense->trip_id)
                        ->pluck('user_id')
                        ->push($creatorId)
                        ->filter()
                        ->unique()
                        ->sort()
                        ->values();

                    if ($memberIds->isEmpty()) {
                        continue;
                    }

                    $totalCents = (int) bcmul(bcmul((string) $expense->unit_price, '100', 0), (string) $expense->quantity, 0);
                    $memberCount = $memberIds->count();
                    $base = intdiv($totalCents, $memberCount);
                    $remainder = $totalCents % $memberCount;
                    $now = now();

                    $rows = $memberIds->values()->map(function ($userId, $index) use ($expense, $base, $remainder, $now) {
                        $cents = $base + ($index < $remainder ? 1 : 0);

                        return [
                            'expense_id' => $expense->id,
                            'user_id' => $userId,
                            'amount' => number_format($cents / 100, 2, '.', ''),
                            'percentage' => null,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    })->all();

                    DB::table('expense_shares')->insert($rows);
                }
            }, column: 'id');
    }
};
