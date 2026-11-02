<?php

namespace App\Http\Controllers;

use App\Http\Requests\StudentImportRequest;
use App\Services\StudentCsvImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;
use RuntimeException;

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
            session(['student_import_preview' => $preview]);

            return view('imports.students.preview', compact('preview'));
        } catch (RuntimeException $exception) {
            return back()->withErrors(['csv' => $exception->getMessage()]);
        }
    }

    public function commit(StudentCsvImportService $service): RedirectResponse
    {
        $preview = session('student_import_preview');
        abort_unless(is_array($preview) && isset($preview['stored_path']), 419, 'Import preview expired.');
        try {
            $batch = $service->commit($preview['stored_path'], $preview['original_filename'], auth()->id(), request('mode', 'insert_or_update'), request('mapping'));
            session()->forget('student_import_preview');

            return redirect()->route('students.index')->with('success', "Import completed: {$batch->imported_rows} inserted, {$batch->updated_rows} updated, {$batch->unchanged_rows} unchanged.");
        } catch (RuntimeException $exception) {
            return back()->withErrors(['csv' => $exception->getMessage()]);
        }
    }

    public function template(): Response
    {
        return response("student_number,first_name,middle_name,last_name,suffix,status\n00000001,Juan,,Dela Cruz,,active\n", 200, [
            'Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="philcst-student-template.csv"',
        ]);
    }
}
