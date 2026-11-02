<?php

namespace App\Http\Controllers;

use App\Http\Requests\StudentImportRequest;
use App\Models\ImportBatch;
use App\Services\StudentCsvImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentImportController extends Controller
{
    public function create(): View
    {
        return view('imports.students.create');
    }

    public function preview(StudentImportRequest $request, StudentCsvImportService $service): View|RedirectResponse
    {
        try {
            $preview = $service->preview($request->file('csv'));
            session([
                'student_import_preview' => $preview,
                'student_import_validated' => null,
                'student_import_token' => null,
            ]);

            return view('imports.students.mapping', compact('preview'));
        } catch (RuntimeException $exception) {
            return back()->withErrors(['csv' => $exception->getMessage()]);
        }
    }

    public function mapping(): View|RedirectResponse
    {
        $preview = session('student_import_preview');
        if (! is_array($preview)) {
            return redirect()->route('imports.students.create')->withErrors(['csv' => 'Upload a CSV file before mapping columns.']);
        }

        return view('imports.students.mapping', compact('preview'));
    }

    public function validateMapping(Request $request, StudentCsvImportService $service): View|RedirectResponse
    {
        $preview = session('student_import_preview');
        abort_unless(is_array($preview) && isset($preview['stored_path']), 419, 'Import preview expired.');
        $mapping = $request->validate([
            'mapping' => ['required', 'array'],
            'mapping.student_number' => ['required', 'string'],
            'mapping.first_name' => ['required', 'string'],
            'mapping.last_name' => ['required', 'string'],
            'mapping.middle_name' => ['nullable', 'string'],
            'mapping.suffix' => ['nullable', 'string'],
            'mapping.status' => ['nullable', 'string'],
            'mapping.program' => ['nullable', 'string'],
            'mapping.year_level' => ['nullable', 'string'],
            'mapping.section' => ['nullable', 'string'],
            'mapping.academic_year' => ['nullable', 'string'],
            'mapping.semester' => ['nullable', 'string'],
        ])['mapping'];

        try {
            $validated = $service->validateStored($preview['stored_path'], $mapping);
            $validated['stored_path'] = $preview['stored_path'];
            $validated['original_filename'] = $preview['original_filename'];
            $validated['file_size'] = $preview['file_size'] ?? null;
            $validated['selected_mapping'] = $mapping;
            session(['student_import_validated' => $validated, 'student_import_token' => null]);

            return view('imports.students.review', ['preview' => $validated]);
        } catch (RuntimeException $exception) {
            return back()->withInput()->withErrors(['mapping' => $exception->getMessage()]);
        }
    }

    public function review(): View|RedirectResponse
    {
        $preview = session('student_import_validated');
        if (! is_array($preview)) {
            return redirect()->route('imports.students.mapping')->withErrors(['mapping' => 'Map and validate the required columns first.']);
        }

        return view('imports.students.review', compact('preview'));
    }

    public function confirm(Request $request): View|RedirectResponse
    {
        $preview = session('student_import_validated');
        if (! is_array($preview)) {
            return redirect()->route('imports.students.mapping')->withErrors(['mapping' => 'Validation must be completed before confirmation.']);
        }
        $mode = $request->validate(['mode' => ['required', 'in:insert_only,insert_or_update']])['mode'];
        $token = (string) Str::uuid();
        session(['student_import_token' => $token, 'student_import_mode' => $mode]);

        return view('imports.students.confirm', compact('preview', 'mode', 'token'));
    }

    public function commit(Request $request, StudentCsvImportService $service): RedirectResponse
    {
        $preview = session('student_import_validated');
        abort_unless(is_array($preview) && isset($preview['stored_path'], $preview['selected_mapping']), 419, 'Validated import state expired.');
        $data = $request->validate([
            'mode' => ['required', 'in:insert_only,insert_or_update'],
            'confirmation_token' => ['required', 'string'],
        ]);
        $sessionToken = session()->pull('student_import_token');
        abort_unless(is_string($sessionToken) && hash_equals($sessionToken, $data['confirmation_token']), 419, 'This import confirmation has already been used or expired.');

        try {
            $batch = $service->commit(
                $preview['stored_path'],
                $preview['original_filename'],
                auth()->id(),
                $data['mode'],
                $preview['selected_mapping'],
            );
            session()->forget(['student_import_preview', 'student_import_validated', 'student_import_mode']);

            return redirect()->route('imports.students.results', $batch);
        } catch (RuntimeException $exception) {
            return redirect()->route('imports.students.review')->withErrors(['csv' => $exception->getMessage()]);
        }
    }

    public function results(ImportBatch $batch): View
    {
        abort_unless($batch->uploaded_by === auth()->id(), 403);

        return view('imports.students.results', compact('batch'));
    }

    public function errors(ImportBatch $batch): StreamedResponse
    {
        abort_unless($batch->uploaded_by === auth()->id(), 403);
        $rows = DB::table('import_errors')->where('import_batch_id', $batch->id)->orderBy('row_number')->get();

        return response()->streamDownload(function () use ($rows) {
            $output = fopen('php://output', 'wb');
            fputcsv($output, ['row_number', 'code', 'message']);
            foreach ($rows as $row) {
                fputcsv($output, [$row->row_number, $row->code, $row->message]);
            }
            fclose($output);
        }, 'philcst-import-errors.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function template(): Response
    {
        return response("student_number,first_name,middle_name,last_name,suffix,status\n00000001,Juan,,Dela Cruz,,active\n", 200, [
            'Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="philcst-student-template.csv"',
        ]);
    }
}
