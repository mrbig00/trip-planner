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
        Schema::table('locations', function (Blueprint $table) {
            $table->string('currency', 3)->nullable()->after('price');
        });

        $this->backfillCurrencyForExistingLocations();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn('currency');
        });
    }

    /**
     * Locations are informational only (nothing sums a location's price into
     * balance/settlement math), so every pre-existing location just inherits
     * its trip's currency for display purposes.
     *
     * Public (rather than private) so it can be re-invoked directly in tests
     * to verify it is idempotent, without re-running Schema::table().
     */
    public function backfillCurrencyForExistingLocations(): void
    {
        DB::table('locations')
            ->select('id', 'trip_id')
            ->whereNull('currency')
            ->orderBy('id')
            ->chunkById(200, function ($locations) {
                foreach ($locations as $location) {
                    $tripCurrency = DB::table('trips')->where('id', $location->trip_id)->value('currency')
                        ?? Currency::default()->value;

                    DB::table('locations')
                        ->where('id', $location->id)
                        ->update(['currency' => $tripCurrency]);
                }
            }, column: 'id');
    }
};
