<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('id_cards', function (Blueprint $table) {
            $table->foreignId('academic_term_id')->nullable()->after('academic_year_id')->constrained()->nullOnDelete();
            $table->foreignId('requested_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_staff_id')->nullable()->after('requested_by')->constrained('users')->nullOnDelete();
            $table->timestamp('requested_at')->nullable()->after('assigned_staff_id')->index();
            $table->timestamp('printing_started_at')->nullable()->after('requested_at')->index();
            $table->string('hold_previous_status', 30)->nullable();
            $table->string('hold_reason', 100)->nullable();
            $table->timestamp('held_at')->nullable();
            $table->foreignId('held_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('hold_resolved_at')->nullable();
            $table->foreignId('hold_resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('internal_notes')->nullable();
            $table->index(['academic_term_id', 'status'], 'id_cards_term_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('id_cards', function (Blueprint $table) {
            $table->dropIndex('id_cards_term_status_index');
            foreach (['academic_term_id', 'requested_by', 'assigned_staff_id', 'held_by', 'hold_resolved_by'] as $column) {
                $table->dropConstrainedForeignId($column);
            }$table->dropColumn(['requested_at', 'printing_started_at', 'hold_previous_status', 'hold_reason', 'held_at', 'hold_resolved_at', 'internal_notes']);
        });
    }
};
