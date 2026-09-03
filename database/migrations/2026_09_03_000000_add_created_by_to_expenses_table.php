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
            // Who actually submitted the expense, which may differ from
            // user_id (the owner/payer it was recorded on behalf of) —
            // trip members can add an expense for another member, not just
            // themselves. Nullable because expenses created before this
            // column existed have no recorded submitter; those fall back to
            // treating the owner as the submitter (see Expense::createdBy()
            // callers).
            $table->foreignId('created_by')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
        });
    }
};
