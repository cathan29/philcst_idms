<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('id_releases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_card_id')->unique()->constrained('id_cards')->restrictOnDelete();
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('released_at')->index();
            $table->timestamp('acknowledged_at')->nullable();
            $table->string('claimant_name', 150)->nullable();
            $table->string('claimant_relationship', 80)->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        DB::table('id_cards')
            ->where(fn ($query) => $query->where('status', 'released')->orWhereNotNull('released_at'))
            ->orderBy('id')
            ->chunkById(200, function ($cards): void {
                foreach ($cards as $card) {
                    DB::table('id_releases')->insertOrIgnore([
                        'id_card_id' => $card->id,
                        'released_by' => $card->released_by,
                        'released_at' => $card->released_at ?? $card->updated_at,
                        'acknowledged_at' => $card->release_acknowledged_at ?? $card->released_at ?? $card->updated_at,
                        'claimant_name' => $card->claimant_name,
                        'claimant_relationship' => $card->claimant_relationship,
                        'remarks' => $card->release_remarks,
                        'created_at' => $card->released_at ?? $card->updated_at,
                        'updated_at' => $card->updated_at,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('id_releases');
    }
};