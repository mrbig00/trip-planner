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
        Schema::table('locations', function (Blueprint $table) {
            // updated_at isn't safe to reuse for "when was this accepted" — it
            // changes on any unrelated edit too. This is set explicitly by
            // Location::accept() and left null for locations never accepted
            // (including ones already accepted before this column existed).
            $table->timestamp('accepted_at')->nullable()->after('accepted');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn('accepted_at');
        });
    }
};
