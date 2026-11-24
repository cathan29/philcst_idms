<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scan_logs', function (Blueprint $table) {
            $table->foreignId('student_id')->nullable()->after('id_holder_id')->constrained()->nullOnDelete();
            $table->foreignId('id_card_id')->nullable()->after('student_id')->constrained()->nullOnDelete();
            $table->foreignId('operator_id')->nullable()->after('id_card_id')->constrained('users')->nullOnDelete();
            $table->string('station_mode', 20)->default('verification')->after('operator_id')->index();
            $table->string('result', 40)->nullable()->after('status')->index();
            $table->string('payload_hash', 64)->nullable()->after('scanned_code')->index();
        });
    }

    public function down(): void
    {
        Schema::table('scan_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('operator_id');
            $table->dropConstrainedForeignId('id_card_id');
            $table->dropConstrainedForeignId('student_id');
            $table->dropColumn(['station_mode', 'result', 'payload_hash']);
        });
    }
};
