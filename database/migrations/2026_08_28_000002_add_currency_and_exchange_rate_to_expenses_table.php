<?php

use App\Enums\Currency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->string('currency', 3)->nullable()->after('quantity');
            // How many units of the TRIP's currency one unit of this expense's
            // own currency is worth, i.e. amount_in_trip_currency =
            // amount_in_expense_currency * exchange_rate. Null means the
            // expense is already in the trip's currency (an implicit rate of
            // 1) — never store 1.000000 explicitly, so "null" has exactly one
            // meaning app-wide. See App\Models\Expense::convertToTripCurrencyCents().
            $table->decimal('exchange_rate', 12, 6)->nullable()->after('currency');
        });

        $this->backfillCurrencyForExistingExpenses();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn(['currency', 'exchange_rate']);
        });
    }

    /**
     * Every pre-existing expense predates the currency concept entirely, and
     * was — by construction — already denominated in whatever its trip's
     * currency becomes, so it needs no exchange rate.
     *
     * Public (rather than private) so it can be re-invoked directly in tests
     * to verify it is idempotent, without re-running Schema::table().
     */
    public function backfillCurrencyForExistingExpenses(): void
    {
        DB::table('expenses')
            ->select('id', 'trip_id')
            ->whereNull('currency')
            ->orderBy('id')
            ->chunkById(200, function ($expenses) {
                foreach ($expenses as $expense) {
                    $tripCurrency = DB::table('trips')->where('id', $expense->trip_id)->value('currency')
                        ?? Currency::default()->value;

                    DB::table('expenses')
                        ->where('id', $expense->id)
                        ->update(['currency' => $tripCurrency, 'exchange_rate' => null]);
                }
            }, column: 'id');
    }
};
