<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('id_cards', function (Blueprint $table) {
            $table->timestamp('release_acknowledged_at')->nullable()->after('released_by');
            $table->string('claimant_name', 150)->nullable()->after('release_acknowledged_at');
            $table->string('claimant_relationship', 80)->nullable()->after('claimant_name');
            $table->text('release_remarks')->nullable()->after('claimant_relationship');
        });
    }

    public function down(): void
    {
        Schema::table('id_cards', function (Blueprint $table) {
            $table->dropColumn([
                'release_acknowledged_at',
                'claimant_name',
                'claimant_relationship',
                'release_remarks',
            ]);
        });
    }
};
