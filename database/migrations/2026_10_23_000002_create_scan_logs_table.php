<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scan_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_holder_id')->nullable()->constrained()->nullOnDelete();
            $table->string('scanned_code', 50)->index();
            $table->string('action', 20);
            $table->string('status', 20)->index();
            $table->string('remarks')->nullable();
            $table->timestamp('scanned_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scan_logs');
    }
};
