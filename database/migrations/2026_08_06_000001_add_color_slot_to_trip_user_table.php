<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * The fixed color-slot cycle participants are assigned from, in order.
     * Slot 1 is reserved for the trip creator (not a trip_user row) and is
     * never assigned here — see Trip::colorSlotFor().
     */
    private const SLOT_CYCLE = [2, 3, 5, 7, 1];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('trip_user', function (Blueprint $table) {
            $table->unsignedTinyInteger('color_slot')->nullable()->after('user_id');
        });

        $this->backfillColorSlotsForExistingParticipants();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trip_user', function (Blueprint $table) {
            $table->dropColumn('color_slot');
        });
    }

    /**
     * Assign a color slot to every pre-existing trip_user row, in attach
     * order per trip, following the same cycle used for newly-attached
     * participants (App\Livewire\Trips\Show::addParticipant()).
     *
     * Public (rather than private) so it can be re-invoked directly in tests
     * to verify it is idempotent, without re-running Schema::table().
     */
    public function backfillColorSlotsForExistingParticipants(): void
    {
        DB::table('trip_user')
            ->select('id', 'trip_id')
            ->orderBy('trip_id')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->groupBy('trip_id')
            ->each(function ($rows) {
                foreach ($rows->values() as $index => $row) {
                    DB::table('trip_user')
                        ->where('id', $row->id)
                        ->update(['color_slot' => self::SLOT_CYCLE[$index % 5]]);
                }
            });
    }
};
