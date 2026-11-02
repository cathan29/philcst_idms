<?php

namespace App\Services;

use App\Models\ImportBatch;
use App\Models\Student;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use SplFileObject;

class StudentCsvImportService
{
    public const FIELDS = ['student_number', 'first_name', 'middle_name', 'last_name', 'suffix', 'status'];

    public function preview(UploadedFile $file): array
    {
        $path = $file->storeAs('imports/previews', Str::uuid().'.csv', 'local');
        $parsed = $this->parse(Storage::disk('local')->path($path));
        $parsed['stored_path'] = $path;
        $parsed['original_filename'] = $file->getClientOriginalName();

        return $parsed;
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
            if ($mode !== 'validate_only') {
                foreach ($parsed['valid_rows'] as $row) {
                    $student = Student::where('student_number', $row['student_number'])->first();
                    if (! $student) {
                        Student::create([...$row, 'record_source' => 'csv']);
                        $inserted++;

                        continue;
                    }
                    if ($mode === 'insert_only') {
                        $unchanged++;

                        continue;
                    }
                    $changes = collect($row)->except('student_number')->filter(fn ($value, $key) => $student->{$key} !== $value)->all();
                    if ($changes === []) {
                        $unchanged++;
                    } else {
                        $student->update([...$changes, 'record_source' => 'csv']);
                        $updated++;
                    }
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

    /** @return array<string, mixed> */
    private function parse(string $path, ?array $selectedMapping = null): array
    {
        $csv = new SplFileObject($path);
        $csv->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);
        $header = $csv->fgetcsv();
        if (! is_array($header) || count(array_filter($header)) === 0) {
            throw new RuntimeException('The CSV file does not contain a readable header row.');
        }
        $header = array_map(fn ($value) => strtolower(trim((string) $value)), $header);
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
            foreach ($header as $index => $name) {
                if (in_array($name, self::FIELDS, true)) {
                    $mapping[$name] = $index;
                }
            }
        }
        if ($selectedMapping !== null) {
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
            $errors = [];
            foreach (['student_number', 'first_name', 'last_name'] as $required) {
                if ($row[$required] === '') {
                    $errors[] = "{$required} is required";
                }
            }
            if (! in_array($row['status'], ['active', 'inactive', 'graduated', 'withdrawn'], true)) {
                $errors[] = 'status is invalid';
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
            $exists = Student::where('student_number', $row['student_number'])->exists();
            $state = $exists ? 'existing' : 'new';
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
                'existing' => count(array_filter($rows, fn ($row) => $row['state'] === 'existing')), 'duplicates' => $duplicates, 'invalid' => $invalid,
            ],
        ];
    }
}
