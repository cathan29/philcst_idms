<?php

namespace App\Services;

use App\Models\AcademicYear;
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

    public function preview(UploadedFile $file): array
    {
        $path = $file->storeAs('imports/previews', Str::uuid().'.csv', 'local');
        $absolutePath = Storage::disk('local')->path($path);
        $this->validateFile($absolutePath);
        $parsed = $this->parse($absolutePath);
        $parsed['stored_path'] = $path;
        $parsed['original_filename'] = $file->getClientOriginalName();
        $parsed['file_size'] = $file->getSize();

        return $parsed;
    }

    public function validateStored(string $path, array $mapping): array
    {
        $absolutePath = Storage::disk('local')->path($path);
        $this->validateFile($absolutePath);

        return $this->parse($absolutePath, $mapping);
    }

    public function commit(string $path, string $filename, int $userId, string $mode = 'insert_or_update', ?array $selectedMapping = null): ImportBatch
    {
        $absolutePath = Storage::disk('local')->path($path);
        if (! is_file($absolutePath)) {
            throw new RuntimeException('The import preview has expired. Please upload the CSV again.');
        }

        $parsed = $this->parse($absolutePath, $selectedMapping);
        $batch = DB::transaction(function () use ($parsed, $path, $filename, $userId, $mode) {
            $batch = ImportBatch::create([
                'id' => (string) Str::uuid(),
                'original_filename' => $filename,
                'stored_path' => $path,
                'file_hash' => hash_file('sha256', Storage::disk('local')->path($path)),
                'uploaded_by' => $userId,
                'mode' => $mode,
                'status' => 'processing',
                'total_rows' => $parsed['summary']['total'],
                'valid_rows' => $parsed['summary']['valid'],
                'duplicate_rows' => $parsed['summary']['duplicates'],
                'rejected_rows' => $parsed['summary']['invalid'],
                'column_mapping' => $parsed['mapping'],
            ]);

            foreach ($parsed['errors'] as $row) {
                if ($row['errors'] === []) {
                    continue;
                }
                DB::table('import_errors')->insert([
                    'import_batch_id' => $batch->id,
                    'row_number' => $row['number'],
                    'code' => str_contains(implode(' ', $row['errors']), 'duplicate') ? 'duplicate_row' : 'invalid_row',
                    'message' => implode('; ', $row['errors']),
                    'safe_row_data' => json_encode($row['data'], JSON_THROW_ON_ERROR),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $inserted = $updated = $unchanged = 0;
            foreach ($parsed['valid_rows'] as $row) {
                $studentData = Arr::only($row, ['student_number', 'first_name', 'middle_name', 'last_name', 'suffix', 'status']);
                $student = Student::where('student_number', $row['student_number'])->first();
                if (! $student) {
                    $student = Student::create([...$studentData, 'record_source' => 'csv']);
                    $this->syncEnrollment($student, $row);
                    $inserted++;

                    continue;
                }
                if ($mode === 'insert_only') {
                    $unchanged++;

                    continue;
                }
                $changes = collect($studentData)->except('student_number')->filter(fn ($value, $key) => $student->{$key} !== $value)->all();
                if ($changes !== []) {
                    $student->update([...$changes, 'record_source' => 'csv']);
                }
                $enrollmentChanged = $this->syncEnrollment($student, $row);
                if ($changes === [] && ! $enrollmentChanged) {
                    $unchanged++;
                } else {
                    $updated++;
                }
            }

            $batch->update([
                'status' => 'completed',
                'imported_rows' => $inserted,
                'updated_rows' => $updated,
                'unchanged_rows' => $unchanged,
            ]);

            return $batch->refresh();
        });

        return $batch;
    }

    private function syncEnrollment(Student $student, array $row): bool
    {
        if (blank($row['program']) || blank($row['academic_year']) || blank($row['year_level'])) {
            return false;
        }
        $program = Program::query()->whereRaw('LOWER(code) = ?', [strtolower($row['program'])])->orWhereRaw('LOWER(name) = ?', [strtolower($row['program'])])->firstOrFail();
        $academicYear = AcademicYear::where('name', $row['academic_year'])->firstOrFail();
        $enrollment = $student->enrollments()->updateOrCreate([
            'academic_year_id' => $academicYear->id,
            'semester' => filled($row['semester']) ? $row['semester'] : null,
        ], [
            'program_id' => $program->id,
            'year_level' => $row['year_level'],
            'section' => filled($row['section']) ? $row['section'] : null,
            'status' => 'enrolled',
        ]);

        return $enrollment->wasRecentlyCreated || $enrollment->wasChanged();
    }

    private function validateFile(string $path): void
    {
        if (! is_file($path) || ! is_readable($path) || filesize($path) === 0) {
            throw new RuntimeException('The CSV file is empty or cannot be read.');
        }
        $sample = file_get_contents($path, false, null, 0, 65536);
        if ($sample === false || ! mb_check_encoding($sample, 'UTF-8')) {
            throw new RuntimeException('The CSV must use UTF-8 encoding.');
        }
    }

    /** @return array<string, mixed> */
    private function parse(string $path, ?array $selectedMapping = null): array
    {
        $csv = new SplFileObject($path);
        $csv->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);
        $header = $csv->fgetcsv();
        if (! is_array($header) || count(array_filter($header)) === 0) {
            throw new RuntimeException('The CSV file does not contain a readable header row.');
        }
        $header = array_map(fn ($value) => strtolower(trim((string) $value, " \t\n\r\0\x0B\xEF\xBB\xBF")), $header);
        if (in_array('', $header, true)) {
            throw new RuntimeException('The CSV contains a blank column header. Name every column before uploading.');
        }
        if (count($header) !== count(array_unique($header))) {
            throw new RuntimeException('The CSV contains duplicate column headers. Each heading must be unique.');
        }
        $headerMap = array_flip($header);
        $mapping = [];
        if ($selectedMapping !== null) {
            foreach (self::FIELDS as $field) {
                $selected = strtolower(trim((string) ($selectedMapping[$field] ?? '')));
                if ($selected !== '' && array_key_exists($selected, $headerMap)) {
                    $mapping[$field] = $headerMap[$selected];
                }
            }
        } else {
            $aliases = [
                'student_number' => ['student_number', 'student number', 'student id', 'id number', 'id_number'],
                'first_name' => ['first_name', 'first name', 'given name', 'given'],
                'middle_name' => ['middle_name', 'middle name'],
                'last_name' => ['last_name', 'last name', 'surname', 'family name', 'family'],
                'suffix' => ['suffix', 'name suffix'],
                'status' => ['status', 'student status', 'state'],
                'program' => ['program', 'program code', 'course'],
                'year_level' => ['year_level', 'year level', 'year'],
                'section' => ['section', 'class section'],
                'academic_year' => ['academic_year', 'academic year', 'school year'],
                'semester' => ['semester', 'term'],
            ];
            foreach ($aliases as $field => $candidates) {
                foreach ($candidates as $candidate) {
                    if (array_key_exists($candidate, $headerMap)) {
                        $mapping[$field] = $headerMap[$candidate];
                        break;
                    }
                }
            }
        }
        if ($selectedMapping !== null) {
            $selectedColumns = array_filter(array_values($selectedMapping), fn ($value) => filled($value));
            if (count($selectedColumns) !== count(array_unique($selectedColumns))) {
                throw new RuntimeException('Each CSV column can only be mapped to one student field.');
            }
            foreach (['student_number', 'first_name', 'last_name'] as $required) {
                if (! array_key_exists($required, $mapping)) {
                    throw new RuntimeException("Required CSV column missing: {$required}.");
                }
            }
        }

        $rows = [];
        $errorRows = [];
        $validRows = [];
        $seen = [];
        $invalid = 0;
        $duplicates = 0;
        $rowNumber = 1;
        foreach ($csv as $values) {
            $rowNumber++;
            if (! is_array($values) || count(array_filter($values, fn ($value) => trim((string) $value) !== '')) === 0) {
                continue;
            }
            $row = [];
            foreach (self::FIELDS as $field) {
                $row[$field] = isset($mapping[$field]) ? trim((string) ($values[$mapping[$field]] ?? '')) : '';
            }
            $row['status'] = $row['status'] !== '' ? $row['status'] : 'active';
            $errors = count($values) === count($header) ? [] : ['row has a different number of columns than the header'];
            foreach (['student_number', 'first_name', 'last_name'] as $required) {
                if ($row[$required] === '') {
                    $errors[] = "{$required} is required";
                }
            }
            if (! in_array($row['status'], ['active', 'inactive', 'graduated', 'withdrawn'], true)) {
                $errors[] = 'status is invalid';
            }
            $hasEnrollmentData = collect(['program', 'year_level', 'section', 'academic_year', 'semester'])->contains(fn ($field) => filled($row[$field]));
            if ($hasEnrollmentData) {
                foreach (['program', 'year_level', 'academic_year'] as $requiredEnrollmentField) {
                    if (blank($row[$requiredEnrollmentField])) {
                        $errors[] = "{$requiredEnrollmentField} is required when enrollment data is mapped";
                    }
                }
                if (filled($row['year_level']) && ! preg_match('/^(?:[1-6](?:st|nd|rd|th)?(?:\s+year)?|graduate)$/i', $row['year_level'])) {
                    $errors[] = 'year_level is invalid';
                }
                if (filled($row['program']) && ! Program::query()->whereRaw('LOWER(code) = ?', [strtolower($row['program'])])->orWhereRaw('LOWER(name) = ?', [strtolower($row['program'])])->exists()) {
                    $errors[] = 'program does not match an authorized program record';
                }
                if (filled($row['academic_year']) && ! AcademicYear::where('name', $row['academic_year'])->exists()) {
                    $errors[] = 'academic_year does not match an authorized academic year record';
                }
            }
            if ($row['student_number'] !== '' && isset($seen[$row['student_number']])) {
                $errors[] = 'duplicate student number in this file';
            }
            if ($errors !== []) {
                if (isset($seen[$row['student_number']])) {
                    $duplicates++;
                } else {
                    $invalid++;
                }
                $rows[] = ['number' => $rowNumber, 'data' => $row, 'errors' => $errors, 'state' => 'invalid'];
                $errorRows[] = ['number' => $rowNumber, 'data' => $row, 'errors' => $errors, 'state' => 'invalid'];

                continue;
            }
            $seen[$row['student_number']] = true;
            $existing = Student::where('student_number', $row['student_number'])->first();
            $state = 'new';
            if ($existing) {
                $studentData = Arr::only($row, ['student_number', 'first_name', 'middle_name', 'last_name', 'suffix', 'status']);
                $changes = collect($studentData)->except('student_number')->filter(fn ($value, $key) => $existing->{$key} !== $value);
                $enrollmentChanged = false;
                if ($hasEnrollmentData) {
                    $program = Program::query()->whereRaw('LOWER(code) = ?', [strtolower($row['program'])])->orWhereRaw('LOWER(name) = ?', [strtolower($row['program'])])->first();
                    $academicYear = AcademicYear::where('name', $row['academic_year'])->first();
                    $enrollment = $existing->enrollments()->where('academic_year_id', $academicYear?->id)->where('semester', filled($row['semester']) ? $row['semester'] : null)->first();
                    $enrollmentChanged = ! $enrollment || $enrollment->program_id !== $program?->id || $enrollment->year_level !== $row['year_level'] || (string) $enrollment->section !== (string) $row['section'];
                }
                $state = $changes->isEmpty() && ! $enrollmentChanged ? 'unchanged' : 'changed';
            }
            $rows[] = ['number' => $rowNumber, 'data' => $row, 'errors' => [], 'state' => $state];
            $validRows[] = $row;
        }

        foreach ($rows as $row) {
            if (count($validRows) > 100 && count($rows) > 100) {
                break;
            }
        }

        return [
            'headers' => $header,
            'mapping' => $mapping,
            'rows' => array_slice($rows, 0, 100),
            'errors' => $errorRows,
            'valid_rows' => $validRows,
            'summary' => [
                'total' => count($rows), 'valid' => count($validRows), 'new' => count(array_filter($rows, fn ($row) => $row['state'] === 'new')),
                'existing' => count(array_filter($rows, fn ($row) => in_array($row['state'], ['changed', 'unchanged'], true))), 'changed' => count(array_filter($rows, fn ($row) => $row['state'] === 'changed')), 'unchanged' => count(array_filter($rows, fn ($row) => $row['state'] === 'unchanged')), 'duplicates' => $duplicates, 'invalid' => $invalid,
            ],
        ];
    }
}
