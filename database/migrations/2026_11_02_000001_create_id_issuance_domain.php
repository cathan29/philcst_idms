<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('deactivated_at')->nullable();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });
        Schema::create('permission_role', function (Blueprint $table) {
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->primary(['permission_id', 'role_id']);
        });
        Schema::create('role_user', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['role_id', 'user_id']);
        });

        Schema::create('programs', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 150);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
        Schema::create('academic_years', function (Blueprint $table) {
            $table->id();
            $table->string('name', 20)->unique();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->boolean('is_current')->default(false)->index();
            $table->timestamps();
        });
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->string('student_number', 64)->unique();
            $table->string('first_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('last_name', 100);
            $table->string('suffix', 30)->nullable();
            $table->string('status', 30)->default('active')->index();
            $table->string('record_source', 40)->default('manual');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['last_name', 'first_name']);
        });
        Schema::create('student_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->string('semester', 30)->nullable();
            $table->string('year_level', 30);
            $table->string('section', 50)->nullable();
            $table->string('status', 30)->default('enrolled')->index();
            $table->timestamps();
            $table->unique(['student_id', 'academic_year_id', 'semester']);
            $table->index(['program_id', 'academic_year_id', 'year_level']);
        });
        Schema::create('scanner_devices', function (Blueprint $table) {
            $table->id();
            $table->uuid('identifier')->unique();
            $table->string('name', 100);
            $table->string('purpose', 30)->index();
            $table->boolean('is_enabled')->default(true)->index();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();
        });
        Schema::create('id_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('card_token')->unique();
            $table->string('external_identifier', 100)->nullable()->unique();
            $table->string('semester', 30)->nullable();
            $table->string('issuance_type', 30)->default('initial');
            $table->unsignedSmallInteger('replacement_sequence')->default(0);
            $table->string('status', 30)->default('pending')->index();
            $table->timestamp('printed_at')->nullable()->index();
            $table->foreignId('printed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('ready_at')->nullable()->index();
            $table->timestamp('released_at')->nullable()->index();
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->text('revocation_reason')->nullable();
            $table->timestamps();
            $table->unique(['student_id', 'academic_year_id', 'semester', 'replacement_sequence'], 'id_cards_issuance_unique');
            $table->index(['status', 'created_at']);
        });
        Schema::create('id_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_card_id')->constrained()->cascadeOnDelete();
            $table->string('previous_status', 30)->nullable();
            $table->string('new_status', 30)->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('scanner_device_id')->nullable()->constrained()->nullOnDelete();
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();
        });
        Schema::create('import_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('original_filename');
            $table->string('stored_path');
            $table->char('file_hash', 64)->index();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('mode', 30);
            $table->string('status', 30)->index();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('valid_rows')->default(0);
            $table->unsignedInteger('imported_rows')->default(0);
            $table->unsignedInteger('updated_rows')->default(0);
            $table->unsignedInteger('unchanged_rows')->default(0);
            $table->unsignedInteger('duplicate_rows')->default(0);
            $table->unsignedInteger('rejected_rows')->default(0);
            $table->json('column_mapping')->nullable();
            $table->timestamps();
        });
        Schema::create('import_errors', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('import_batch_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->string('field')->nullable();
            $table->string('code', 50);
            $table->text('message');
            $table->json('safe_row_data')->nullable();
            $table->timestamps();
            $table->index(['import_batch_id', 'row_number']);
        });
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event', 100)->index();
            $table->nullableMorphs('auditable');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();
        });
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('type', 20)->default('string');
            $table->boolean('is_sensitive')->default(false);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['system_settings', 'audit_logs', 'import_errors', 'import_batches', 'id_status_histories', 'id_cards', 'scanner_devices', 'student_enrollments', 'students', 'academic_years', 'programs', 'role_user', 'permission_role', 'permissions', 'roles'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'deactivated_at']);
        });
    }
};
