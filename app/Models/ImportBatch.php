<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportBatch extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'original_filename', 'stored_path', 'file_hash', 'uploaded_by', 'academic_term_id', 'coverage_program_id', 'mode', 'status', 'total_rows', 'valid_rows', 'imported_rows', 'updated_rows', 'unchanged_rows', 'duplicate_rows', 'rejected_rows', 'column_mapping'];

    protected function casts(): array
    {
        return ['column_mapping' => 'array'];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    public function coverageProgram(): BelongsTo
    {
        return $this->belongsTo(Program::class, 'coverage_program_id');
    }
}
