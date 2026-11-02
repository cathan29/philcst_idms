<?php

namespace App\Http\Controllers;

use App\Http\Requests\StudentImportRequest;
use App\Models\AcademicTerm;
use App\Models\ImportBatch;
use App\Models\Program;
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
        return view('imports.students.create', ['terms' => AcademicTerm::with('academicYear')->where('status', 'active')->orderByDesc('id')->get(), 'programs' => Program::where('is_active', true)->orderBy('code')->get(), 'maxMegabytes' => config('idms.import_max_kilobytes', 25600) / 1024]);
    }

    public function preview(StudentImportRequest $request, StudentCsvImportService $service): View|RedirectResponse
    {
        try {
            $context = ['academic_term_id' => $request->integer('academic_term_id'), 'coverage_program_id' => $request->input('coverage') === 'specific' ? $request->integer('coverage_program_id') : null];
            $preview = $service->preview($request->file('csv'), $context);
            session(['student_import_preview' => $preview, 'student_import_validated' => null, 'student_import_token' => null]);

            return view('imports.students.mapping', compact('preview'));
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['csv' => $e->getMessage()]);
        }
    }

    public function mapping(): View|RedirectResponse
    {
        $preview = session('student_import_preview');

        return is_array($preview) ? view('imports.students.mapping', compact('preview')) : redirect()->route('imports.students.create')->withErrors(['csv' => 'Select an academic term and upload a CSV first.']);
    }

    public function validateMapping(Request $request, StudentCsvImportService $service): View|RedirectResponse
    {
        $preview = session('student_import_preview');
        abort_unless(is_array($preview) && isset($preview['stored_path'],$preview['context']), 419, 'Import preview expired.');
        $mapping = $request->validate(['mapping' => ['required', 'array'], 'mapping.student_number' => ['required', 'string'], 'mapping.first_name' => ['required', 'string'], 'mapping.last_name' => ['required', 'string'], 'mapping.middle_name' => ['nullable', 'string'], 'mapping.suffix' => ['nullable', 'string'], 'mapping.status' => ['nullable', 'string'], 'mapping.program' => ['nullable', 'string'], 'mapping.year_level' => ['nullable', 'string'], 'mapping.section' => ['nullable', 'string'], 'mapping.academic_year' => ['nullable', 'string'], 'mapping.semester' => ['nullable', 'string']])['mapping'];
        try {
            $validated = $service->validateStored($preview['stored_path'], $mapping, $preview['context']);
            foreach (['stored_path', 'original_filename', 'file_size', 'context', 'term'] as $key) {
                $validated[$key] = $preview[$key] ?? null;
            } $validated['selected_mapping'] = $mapping;
            session(['student_import_validated' => $validated, 'student_import_token' => null]);

            return view('imports.students.review', ['preview' => $validated]);
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['mapping' => $e->getMessage()]);
        }
    }

    public function review(): View|RedirectResponse
    {
        $preview = session('student_import_validated');

        return is_array($preview) ? view('imports.students.review', compact('preview')) : redirect()->route('imports.students.mapping')->withErrors(['mapping' => 'Map and validate the required columns first.']);
    }

    public function confirm(Request $request): View|RedirectResponse
    {
        $preview = session('student_import_validated');
        if (! is_array($preview)) {
            return redirect()->route('imports.students.mapping');
        } $mode = $request->validate(['mode' => ['required', 'in:insert_only,insert_or_update']])['mode'];
        $token = (string) Str::uuid();
        session(['student_import_token' => $token, 'student_import_mode' => $mode]);

        return view('imports.students.confirm', compact('preview', 'mode', 'token'));
    }

    public function commit(Request $request, StudentCsvImportService $service): RedirectResponse
    {
        $preview = session('student_import_validated');
        abort_unless(is_array($preview) && isset($preview['stored_path'],$preview['selected_mapping'],$preview['context']), 419);
        $data = $request->validate(['mode' => ['required', 'in:insert_only,insert_or_update'], 'confirmation_token' => ['required', 'string']]);
        $sessionToken = session()->pull('student_import_token');
        abort_unless(is_string($sessionToken) && hash_equals($sessionToken, $data['confirmation_token']), 419, 'This confirmation was already used or expired.');
        try {
            $batch = $service->commit($preview['stored_path'], $preview['original_filename'], auth()->id(), $data['mode'], $preview['selected_mapping'], $preview['context']);
            session()->forget(['student_import_preview', 'student_import_validated', 'student_import_mode']);

            return redirect()->route('imports.students.results', $batch);
        } catch (RuntimeException $e) {
            return redirect()->route('imports.students.review')->withErrors(['csv' => $e->getMessage()]);
        }
    }

    public function results(ImportBatch $batch): View
    {
        abort_unless($batch->uploaded_by === auth()->id(), 403);
        $batch->load('academicTerm.academicYear', 'coverageProgram');

        return view('imports.students.results', compact('batch'));
    }

    public function errors(ImportBatch $batch): StreamedResponse
    {
        abort_unless($batch->uploaded_by === auth()->id(), 403);
        $rows = DB::table('import_errors')->where('import_batch_id', $batch->id)->orderBy('row_number')->cursor();

        return response()->streamDownload(function () use ($rows) {
            $o = fopen('php://output', 'wb');
            fputcsv($o, ['row_number', 'code', 'message']);
            foreach ($rows as $row) {
                fputcsv($o, [$row->row_number, $row->code, $row->message]);
            }fclose($o);
        }, 'philcst-import-errors.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function template(): Response
    {
        return response("student_number,first_name,middle_name,last_name,suffix,program,year_level,section,academic_year,semester\n00000001,Juan,,Dela Cruz,,BSIT,1st Year,A,2026-2027,First Semester\n",200,['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="philcst-enrollment-template.csv"']);
    }
}
