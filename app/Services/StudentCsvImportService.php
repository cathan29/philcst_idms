<?php

namespace App\Services;

use App\Models\AcademicTerm;
use App\Models\ImportBatch;
use App\Models\Program;
use App\Models\Student;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use SplFileObject;

class StudentCsvImportService
{
    public const FIELDS = ['student_number', 'first_name', 'middle_name', 'last_name', 'suffix', 'status', 'program', 'year_level', 'section', 'academic_year', 'semester'];

    private const ALIASES = ['student_number' => ['student_number', 'student number', 'student id', 'id number', 'id_number'], 'first_name' => ['first_name', 'first name', 'given name', 'given'], 'middle_name' => ['middle_name', 'middle name'], 'last_name' => ['last_name', 'last name', 'surname', 'family name', 'family'], 'suffix' => ['suffix', 'name suffix'], 'status' => ['status', 'student status', 'state'], 'program' => ['program', 'program code', 'course'], 'year_level' => ['year_level', 'year level', 'year'], 'section' => ['section', 'class section', 'block'], 'academic_year' => ['academic_year', 'academic year', 'school year'], 'semester' => ['semester', 'term']];

    public function preview(UploadedFile $file, array $context): array
    {
        $path = $file->storeAs('imports/previews', Str::uuid().'.csv', 'local');
        $absolute = Storage::disk('local')->path($path);
        $this->validateFile($absolute);
        $parsed = $this->parse($absolute, null, $context);
        $term = $this->term($context);

        return [...$parsed, 'stored_path' => $path, 'original_filename' => $file->getClientOriginalName(), 'file_size' => $file->getSize(), 'context' => $context, 'term' => $this->termData($term)];
    }

    public function validateStored(string $path, array $mapping, array $context): array
    {
        $absolute = Storage::disk('local')->path($path);
        $this->validateFile($absolute);

        return $this->parse($absolute, $mapping, $context);
    }

    public function commit(string $path, string $filename, int $userId, string $mode, array $mapping, array $context): ImportBatch
    {
        $absolute = Storage::disk('local')->path($path);
        if (! is_file($absolute)) {
            throw new RuntimeException('The import preview expired. Upload the CSV again.');
        }$parsed = $this->parse($absolute, $mapping, $context, true);
        $term = $this->term($context);

        return DB::transaction(function () use ($parsed, $path, $filename, $userId, $mode, $mapping, $context, $term) {
            $batch = ImportBatch::create(['id' => (string) Str::uuid(), 'original_filename' => $filename, 'stored_path' => $path, 'file_hash' => hash_file('sha256', Storage::disk('local')->path($path)), 'uploaded_by' => $userId, 'academic_term_id' => $term->id, 'coverage_program_id' => $context['coverage_program_id'] ?? null, 'mode' => $mode, 'status' => 'processing', 'total_rows' => $parsed['summary']['total'], 'valid_rows' => $parsed['summary']['valid'], 'duplicate_rows' => $parsed['summary']['duplicates'], 'rejected_rows' => $parsed['summary']['invalid'], 'column_mapping' => $mapping]);
            foreach ($parsed['errors'] as $row) {
                DB::table('import_errors')->insert(['import_batch_id' => $batch->id, 'row_number' => $row['number'], 'code' => str_contains(implode(' ', $row['errors']), 'duplicate') ? 'duplicate_row' : 'invalid_row', 'message' => implode('; ', $row['errors']), 'safe_row_data' => json_encode($row['data'], JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()]);
            }$inserted = $updated = $unchanged = 0;
            foreach ($parsed['valid_rows'] as $row) {
                $identity = Arr::only($row, ['student_number', 'first_name', 'middle_name', 'last_name', 'suffix', 'status']);
                $student = Student::where('student_number', $row['student_number'])->first();
                if (! $student) {
                    $student = Student::create([...$identity, 'record_source' => 'csv']);
                    $this->syncEnrollment($student, $row, $term);
                    $inserted++;

                    continue;
                }
                if ($mode === 'insert_only') {
                    if ($student->enrollments()->where('academic_term_id', $term->id)->exists()) {
                        $unchanged++;
                    } else {
                        $this->syncEnrollment($student, $row, $term);
                        $updated++;
                    }

                    continue;
                }
                $changes = collect($identity)->except('student_number')->filter(fn ($value, $key) => (string) $student->{$key} !== (string) $value)->all();
                if ($changes) {
                    $student->update([...$changes, 'record_source' => 'csv']);
                }$enrollmentChanged = $this->syncEnrollment($student, $row, $term);
                if (! $changes && ! $enrollmentChanged) {
                    $unchanged++;
                } else {
                    $updated++;
                }
            } $batch->update(['status' => 'completed', 'imported_rows' => $inserted, 'updated_rows' => $updated, 'unchanged_rows' => $unchanged]);

            return $batch->refresh();
        });
    }

    private function syncEnrollment(Student $student, array $row, AcademicTerm $term): bool
    {
        $program = $this->program($row['program']);
        $enrollment = $student->enrollments()->updateOrCreate(['academic_term_id' => $term->id], ['program_id' => $program->id, 'academic_year_id' => $term->academic_year_id, 'semester' => $term->name, 'year_level' => $row['year_level'], 'section' => filled($row['section']) ? $row['section'] : null, 'status' => 'enrolled']);

        return $enrollment->wasRecentlyCreated || $enrollment->wasChanged();
    }

    private function validateFile(string $path): void
    {
        if (! is_file($path) || ! is_readable($path) || filesize($path) === 0) {
            throw new RuntimeException('The CSV file is empty or cannot be read.');
        }$sample = file_get_contents($path, false, null, 0, 65536);
        if ($sample === false || ! mb_check_encoding($sample, 'UTF-8')) {
            throw new RuntimeException('The CSV must use UTF-8 encoding.');
        }
    }

    private function parse(string $path, ?array $selectedMapping, array $context, bool $collectValid = false): array
    {
        $term = $this->term($context);
        $coverageProgram = filled($context['coverage_program_id'] ?? null) ? Program::whereKey($context['coverage_program_id'])->where('is_active', true)->firstOrFail() : null;
        $csv = new SplFileObject($path);
        $csv->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);
        $header = $csv->fgetcsv();
        if (! is_array($header) || ! array_filter($header)) {
            throw new RuntimeException('The CSV does not contain a readable header row.');
        }$header = array_map(fn ($v) => strtolower(trim((string) $v, " \t\n\r\0\x0B\xEF\xBB\xBF")), $header);
        if (in_array('', $header, true)) {
            throw new RuntimeException('The CSV contains a blank header.');
        }if (count($header) !== count(array_unique($header))) {
            throw new RuntimeException('The CSV contains duplicate headers.');
        }$headerMap = array_flip($header);
        $mapping = [];
        if ($selectedMapping !== null) {
            $selected = array_filter(array_values($selectedMapping), fn ($v) => filled($v));
            if (count($selected) !== count(array_unique($selected))) {
                throw new RuntimeException('Each CSV column can only be mapped once.');
            }foreach (self::FIELDS as $field) {
                $column = strtolower(trim((string) ($selectedMapping[$field] ?? '')));
                if ($column !== '' && array_key_exists($column, $headerMap)) {
                    $mapping[$field] = $headerMap[$column];
                }
            }
        } else {
            foreach (self::ALIASES as $field => $aliases) {
                foreach ($aliases as $alias) {
                    if (array_key_exists($alias, $headerMap)) {
                        $mapping[$field] = $headerMap[$alias];
                        break;
                    }
                }
            }
        }foreach (['student_number', 'first_name', 'last_name'] as $required) {
            if ($selectedMapping !== null && ! isset($mapping[$required])) {
                throw new RuntimeException("Required CSV column missing: {$required}.");
            }
        }
        $rows = [];
        $errors = [];
        $validRows = [];
        $seen = [];
        $summary = ['total' => 0, 'valid' => 0, 'new_students' => 0, 'new_enrollments' => 0, 'modified_enrollments' => 0, 'unchanged_enrollments' => 0, 'duplicates' => 0, 'invalid' => 0];
        $number = 1;
        foreach ($csv as $values) {
            $number++;
            if (! is_array($values) || ! array_filter($values, fn ($v) => trim((string) $v) !== '')) {
                continue;
            }$summary['total']++;
            $row = [];
            foreach (self::FIELDS as $field) {
                $row[$field] = isset($mapping[$field]) ? trim((string) ($values[$mapping[$field]] ?? '')) : '';
            }$row['status'] = $row['status'] ?: 'active';
            if ($coverageProgram) {
                $row['program'] = $coverageProgram->code;
            }$rowErrors = count($values) === count($header) ? [] : ['row has a different number of columns than the header'];
            foreach (['student_number', 'first_name', 'last_name', 'program', 'year_level'] as $required) {
                if (blank($row[$required])) {
                    $rowErrors[] = "{$required} is required";
                }
            }if (! in_array($row['status'], ['active', 'inactive', 'graduated', 'withdrawn'], true)) {
                $rowErrors[] = 'status is invalid';
            }if (filled($row['year_level']) && ! preg_match('/^(?:[1-6](?:st|nd|rd|th)?(?:\s+year)?|graduate)$/i', $row['year_level'])) {
                $rowErrors[] = 'year_level is invalid';
            }$program = null;
            if (filled($row['program'])) {
                $program = $this->findProgram($row['program']);
                if (! $program) {
                    $rowErrors[] = 'program does not match an authorized program';
                }if ($coverageProgram && $program?->id !== $coverageProgram->id) {
                    $rowErrors[] = 'program conflicts with selected coverage';
                }
            }if (filled($row['academic_year']) && strcasecmp($row['academic_year'], $term->academicYear->name) !== 0) {
                $rowErrors[] = 'academic_year conflicts with selected import term';
            }if (filled($row['semester']) && ! $this->sameTerm($row['semester'], $term)) {
                $rowErrors[] = 'semester conflicts with selected import term';
            }if (filled($row['student_number']) && isset($seen[$row['student_number']])) {
                $rowErrors[] = 'duplicate student number in this file';
            }if ($rowErrors) {
                $duplicate = collect($rowErrors)->contains(fn ($e) => str_contains($e, 'duplicate'));
                $summary[$duplicate ? 'duplicates' : 'invalid']++;
                $entry = ['number' => $number, 'data' => $row, 'errors' => $rowErrors, 'state' => 'invalid'];
                if (count($rows) < 100) {
                    $rows[] = $entry;
                }$errors[] = $entry;

                continue;
            }$seen[$row['student_number']] = true;
            $existing = Student::where('student_number', $row['student_number'])->first();
            $state = 'new_student';
            if (! $existing) {
                $summary['new_students']++;
            } else {
                $enrollment = $existing->enrollments()->where('academic_term_id', $term->id)->first();
                if (! $enrollment) {
                    $state = 'new_enrollment';
                    $summary['new_enrollments']++;
                } elseif ($enrollment->program_id !== $program?->id || (string) $enrollment->year_level !== (string) $row['year_level'] || (string) $enrollment->section !== (string) $row['section']) {
                    $state = 'modified_enrollment';
                    $summary['modified_enrollments']++;
                } else {
                    $state = 'unchanged_enrollment';
                    $summary['unchanged_enrollments']++;
                }
            }$summary['valid']++;
            if (count($rows) < 100) {
                $rows[] = ['number' => $number, 'data' => $row, 'errors' => [], 'state' => $state];
            }if ($collectValid) {
                $validRows[] = $row;
            }
        }
        $summary['new'] = $summary['new_students'];
        $summary['existing'] = $summary['new_enrollments'] + $summary['modified_enrollments'] + $summary['unchanged_enrollments'];
        $summary['changed'] = $summary['new_enrollments'] + $summary['modified_enrollments'];
        $summary['unchanged'] = $summary['unchanged_enrollments'];

        return ['headers' => $header, 'mapping' => $mapping, 'rows' => $rows, 'errors' => $errors, 'valid_rows' => $validRows, 'summary' => $summary, 'context' => $context, 'term' => $this->termData($term)];
    }

    private function term(array $context): AcademicTerm
    {
        $term = AcademicTerm::with('academicYear')->whereKey($context['academic_term_id'] ?? null)->first();
        if (! $term) {
            throw new RuntimeException('Select a valid academic term.');
        }if ($term->status !== 'active') {
            throw new RuntimeException('The selected academic term is closed.');
        }

        return $term;
    }

    private function termData(AcademicTerm $term): array
    {
        return ['id' => $term->id, 'academic_year' => $term->academicYear->name, 'name' => $term->name, 'label' => $term->academicYear->name.' · '.$term->name];
    }

    private function findProgram(string $value): ?Program
    {
        return Program::where('is_active', true)->where(fn ($q) => $q->whereRaw('LOWER(code)=?', [strtolower($value)])->orWhereRaw('LOWER(name)=?', [strtolower($value)]))->first();
    }

    private function program(string $value): Program
    {
        return $this->findProgram($value) ?? throw new RuntimeException("Unknown program: {$value}");
    }

    private function sameTerm(string $value, AcademicTerm $term): bool
    {
        $normalize = fn ($v) => str((string) $v)->lower()->replace([' ', '-', '_'], '')->toString();

        return in_array($normalize($value), [$normalize($term->name), $normalize($term->code)], true);
    }
}
