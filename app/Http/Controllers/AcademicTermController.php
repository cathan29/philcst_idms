<?php

namespace App\Http\Controllers;

use App\Enums\SemesterCode;
use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\Program;
use App\Services\ActiveAcademicTerm;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AcademicTermController extends Controller
{
    public function index(ActiveAcademicTerm $activeTerms): View
    {
        return view('academic-terms.index', [
            'programs' => Program::orderBy('code')->get(),
            'years' => AcademicYear::with(['terms' => fn ($q) => $q->orderBy('starts_on')])->orderByDesc('name')->get(),
            'activeTerm' => $activeTerms->current(),
            'semesters' => SemesterCode::cases(),
        ]);
    }

    public function storeProgram(Request $request): RedirectResponse
    {
        Program::create($request->validate([
            'code' => ['required', 'string', 'max:30', 'unique:programs,code'],
            'name' => ['required', 'string', 'max:150'],
        ]));

        return back()->with('success', 'Program created.');
    }

    public function updateProgram(Request $request, Program $program): RedirectResponse
    {
        $program->update($request->validate([
            'name' => ['required', 'string', 'max:150'],
            'is_active' => ['required', 'boolean'],
        ]));

        return back()->with('success', 'Program updated.');
    }

    public function storeYear(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:20', 'unique:academic_years,name'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
        ]);
        AcademicYear::create($data);

        return back()->with('success', 'Academic year created.');
    }

    public function store(Request $request, ActiveAcademicTerm $activeTerms): RedirectResponse
    {
        $data = $request->validate([
            'academic_year_id' => ['required', 'exists:academic_years,id'],
            'semester' => ['required', Rule::in(['0', '1', '2'])],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'status' => ['required', 'in:active,closed'],
        ]);
        $semester = SemesterCode::fromInput($data['semester']);
        if (AcademicTerm::where('academic_year_id', $data['academic_year_id'])->where('code', $semester->databaseCode())->exists()) {
            return back()->withInput()->withErrors(['semester' => 'That semester already exists for the selected academic year.']);
        }

        $term = AcademicTerm::create([
            'academic_year_id' => $data['academic_year_id'],
            'code' => $semester->databaseCode(),
            'name' => $semester->label(),
            'starts_on' => $data['starts_on'] ?? null,
            'ends_on' => $data['ends_on'] ?? null,
            'status' => 'closed',
        ]);

        if ($data['status'] === 'active') {
            $activeTerms->activate($term);
        } else {
            $activeTerms->forget();
        }

        return back()->with('success', 'Academic term created.');
    }

    public function update(Request $request, AcademicTerm $academicTerm, ActiveAcademicTerm $activeTerms): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'status' => ['required', 'in:active,closed'],
        ]);

        $academicTerm->update([
            'name' => $data['name'],
            'starts_on' => $data['starts_on'] ?? null,
            'ends_on' => $data['ends_on'] ?? null,
        ]);

        if ($data['status'] === 'active') {
            $activeTerms->activate($academicTerm);
        } else {
            $academicTerm->update(['status' => 'closed']);
            $activeTerms->forget();
        }

        return back()->with('success', 'Academic term updated.');
    }
}