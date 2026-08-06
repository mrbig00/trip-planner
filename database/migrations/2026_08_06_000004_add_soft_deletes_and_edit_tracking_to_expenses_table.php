<?php

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
            // Who last edited / deleted the expense, so the activity feed can
            // attribute those events without guessing (the expense's own
            // user_id is the owner, not necessarily whoever made the change).
            $table->foreignId('updated_by')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->after('updated_by')->constrained('users')->nullOnDelete();

            // Soft-deleted expenses stay recorded (excluded from totals/breakdowns
            // by Eloquent's default scope) so a "deleted" event can still appear
            // in the activity feed.
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropConstrainedForeignId('deleted_by');
            $table->dropConstrainedForeignId('updated_by');
        });
    }
};
