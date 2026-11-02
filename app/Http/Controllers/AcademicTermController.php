<?php

namespace App\Http\Controllers;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\Program;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AcademicTermController extends Controller
{
    public function index(): View
    {
        return view('academic-terms.index', ['programs' => Program::orderBy('code')->get(),
            'years' => AcademicYear::with(['terms' => fn ($q) => $q->orderBy('starts_on')])->orderByDesc('name')->get()]);
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
        $data = $request->validate(['name' => ['required', 'string', 'max:20', 'unique:academic_years,name'], 'starts_on' => ['nullable', 'date'], 'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on']]);
        AcademicYear::create($data);

        return back()->with('success', 'Academic year created.');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['academic_year_id' => ['required', 'exists:academic_years,id'], 'code' => ['required', 'string', 'max:30', Rule::unique('academic_terms')->where('academic_year_id', $request->integer('academic_year_id'))], 'name' => ['required', 'string', 'max:80'], 'starts_on' => ['nullable', 'date'], 'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'], 'status' => ['required', 'in:active,closed']]);
        AcademicTerm::create($data);

        return back()->with('success', 'Academic term created.');
    }

    public function update(Request $request, AcademicTerm $academicTerm): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:80'], 'starts_on' => ['nullable', 'date'], 'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'], 'status' => ['required', 'in:active,closed']]);
        $academicTerm->update($data);

        return back()->with('success', 'Academic term updated.');
    }
}
