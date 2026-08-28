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
        Schema::table('trips', function (Blueprint $table) {
            $table->string('currency', 3)->nullable()->after('budget');
        });

        $this->backfillCurrencyForExistingTrips();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn('currency');
        });
    }

    /**
     * Every pre-existing trip predates the currency concept entirely, so
     * there's no per-row source of truth to derive it from — give every
     * trip with no currency yet the app-wide default.
     *
     * Public (rather than private) so it can be re-invoked directly in tests
     * to verify it is idempotent, without re-running Schema::table().
     */
    public function backfillCurrencyForExistingTrips(): void
    {
        DB::table('trips')
            ->whereNull('currency')
            ->update(['currency' => Currency::default()->value]);
    }
};
