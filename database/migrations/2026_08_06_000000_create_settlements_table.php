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
        Schema::create('settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->onDelete('cascade');
            // Restricted (not cascade), same reasoning as expense_shares.user_id: a user
            // with a recorded settlement can't be deleted out from under the trip's ledger.
            $table->foreignId('from_user_id')->constrained('users')->onDelete('restrict');
            $table->foreignId('to_user_id')->constrained('users')->onDelete('restrict');
            $table->unsignedInteger('amount_cents');
            // Audit trail only, not an authorization source: who clicked "mark as settled".
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['trip_id', 'from_user_id', 'to_user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settlements');
    }
};
