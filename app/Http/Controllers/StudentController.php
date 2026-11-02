<?php

namespace App\Http\Controllers;

use App\Http\Requests\StudentRequest;
use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\Program;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StudentController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'program' => ['nullable', 'integer'], 'academic_year' => ['nullable', 'integer'], 'academic_term' => ['nullable', 'integer', 'exists:academic_terms,id'], 'year_level' => ['nullable', 'string', 'max:30'], 'section' => ['nullable', 'string', 'max:50'], 'status' => ['nullable', 'in:active,inactive,graduated,withdrawn'], 'card_status' => ['nullable', 'in:pending,for_printing,printed,ready_for_release,released,on_hold,revoked'], 'sort' => ['nullable', 'in:student_number,first_name,last_name,status,created_at'], 'direction' => ['nullable', 'in:asc,desc']]);
        $search = trim((string) ($filters['search'] ?? ''));
        $termId = $filters['academic_term'] ?? null;
        $students = Student::query()->with(['enrollments.program', 'enrollments.academicYear', 'enrollments.academicTerm.academicYear', 'latestCard'])->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('student_number', 'like', "%{$search}%")->orWhere('first_name', 'like', "%{$search}%")->orWhere('middle_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%")))->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))->when($filters['card_status'] ?? null, fn ($q, $v) => $q->whereHas('idCards', fn ($c) => $c->where('status', $v)))->when($termId, fn ($q, $v) => $q->whereHas('enrollments', fn ($e) => $e->where('academic_term_id', $v)))->when(! $termId && ($filters['academic_year'] ?? null), fn ($q) => $q->whereHas('enrollments', fn ($e) => $e->where('academic_year_id', $filters['academic_year'])))->when($filters['program'] ?? null, fn ($q, $v) => $q->whereHas('enrollments', fn ($e) => $e->where('program_id', $v)->when($termId, fn ($e) => $e->where('academic_term_id', $termId))))->when($filters['year_level'] ?? null, fn ($q, $v) => $q->whereHas('enrollments', fn ($e) => $e->where('year_level', $v)->when($termId, fn ($e) => $e->where('academic_term_id', $termId))))->when($filters['section'] ?? null, fn ($q, $v) => $q->whereHas('enrollments', fn ($e) => $e->where('section', 'like', "%{$v}%")->when($termId, fn ($e) => $e->where('academic_term_id', $termId))))->orderBy($filters['sort'] ?? 'last_name', $filters['direction'] ?? 'asc')->paginate(25)->withQueryString();
        $students->getCollection()->each(function ($student) use ($termId) {
            $student->setRelation('displayEnrollment', $termId ? $student->enrollments->firstWhere('academic_term_id', (int) $termId) : $student->enrollments->sortByDesc('id')->first());
        });
        $base = Student::query()->when($termId, fn ($q) => $q->whereHas('enrollments', fn ($e) => $e->where('academic_term_id', $termId)));

        return view('students.index', ['students' => $students, 'filters' => $filters, 'programs' => Program::where('is_active', true)->orderBy('code')->get(), 'academicYears' => AcademicYear::orderByDesc('name')->get(), 'academicTerms' => AcademicTerm::with('academicYear')->orderByDesc('id')->get(), 'metrics' => ['total' => (clone $base)->count(), 'with_cards' => (clone $base)->whereHas('idCards')->count(), 'active' => (clone $base)->where('status', 'active')->count()]]);
    }

    public function show(Student $student): View
    {
        $student->load(['enrollments.program', 'enrollments.academicYear', 'enrollments.academicTerm.academicYear', 'idCards' => fn ($q) => $q->latest(), 'idCards.histories' => fn ($q) => $q->latest('occurred_at')]);

        return view('students.show', compact('student'));
    }

    public function create(): View
    {
        return view('students.create');
    }

    public function store(StudentRequest $request): RedirectResponse
    {
        $student = Student::create([...$request->validated(), 'record_source' => 'manual']);

        return redirect()->route('students.show', $student)->with('success', 'Student record created.');
    }

    public function edit(Student $student): View
    {
        return view('students.edit', compact('student'));
    }

    public function update(StudentRequest $request,Student $student): RedirectResponse
    {
        $student->update($request->validated());

        return redirect()->route('students.show',$student)->with('success','Student record updated.');
    }
}
