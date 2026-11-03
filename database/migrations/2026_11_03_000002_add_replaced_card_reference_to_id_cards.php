<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('id_cards', function (Blueprint $table) {
            $table->foreignId('replaces_card_id')->nullable()->after('student_id')->constrained('id_cards')->nullOnDelete();
            $table->index(['student_id', 'replaces_card_id']);
        });
    }

    public function down(): void
    {
        Schema::table('id_cards', function (Blueprint $table) {
            $table->dropForeign(['replaces_card_id']);
            $table->dropIndex(['student_id', 'replaces_card_id']);
            $table->dropColumn('replaces_card_id');
        });
    }
};
