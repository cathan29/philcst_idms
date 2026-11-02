<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->string('code', 30);
            $table->string('name', 80);
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
            $table->unique(['academic_year_id', 'code']);
        });
        Schema::table('student_enrollments', function (Blueprint $table) {
            $table->foreignId('academic_term_id')->nullable()->after('academic_year_id')->constrained()->restrictOnDelete();
            $table->index(['academic_term_id', 'program_id', 'year_level'], 'enrollments_term_lookup');
        });
        Schema::table('import_batches', function (Blueprint $table) {
            $table->foreignId('academic_term_id')->nullable()->after('uploaded_by')->constrained()->restrictOnDelete();
            $table->foreignId('coverage_program_id')->nullable()->after('academic_term_id')->constrained('programs')->nullOnDelete();
        });
        DB::table('student_enrollments')->select('academic_year_id', 'semester')->whereNotNull('semester')->distinct()->orderBy('academic_year_id')->each(function ($row) {
            $code = str($row->semester)->lower()->replace([' ', '-'], '_')->toString();
            $termId = DB::table('academic_terms')->insertGetId(['academic_year_id' => $row->academic_year_id, 'code' => $code, 'name' => $row->semester, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
            DB::table('student_enrollments')->where('academic_year_id', $row->academic_year_id)->where('semester', $row->semester)->update(['academic_term_id' => $termId]);
        });
    }

    public function down(): void
    {
        Schema::table('import_batches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('coverage_program_id');
            $table->dropConstrainedForeignId('academic_term_id');
        });
        Schema::table('student_enrollments', function (Blueprint $table) {
            $table->dropIndex('enrollments_term_lookup');
            $table->dropConstrainedForeignId('academic_term_id');
        });
        Schema::dropIfExists('academic_terms');
    }
};
