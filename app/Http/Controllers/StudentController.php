<?php

namespace App\Http\Controllers;

use App\Http\Requests\StudentRequest;
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
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'program' => ['nullable', 'integer'],
            'academic_year' => ['nullable', 'integer'],
            'year_level' => ['nullable', 'string', 'max:30'],
            'section' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'in:active,inactive,graduated,withdrawn'],
            'card_status' => ['nullable', 'in:pending,for_printing,printed,ready_for_release,released,on_hold,revoked'],
            'sort' => ['nullable', 'in:student_number,first_name,last_name,status,created_at'],
            'direction' => ['nullable', 'in:asc,desc'],
        ]);

        $search = trim((string) ($filters['search'] ?? ''));
        $students = Student::query()
            ->with(['latestEnrollment.program', 'latestEnrollment.academicYear', 'latestCard'])
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('student_number', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('middle_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%");
            }))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['card_status'] ?? null, fn ($query, $status) => $query->whereHas('idCards', fn ($cards) => $cards->where('status', $status)))
            ->when($filters['program'] ?? null, fn ($query, $program) => $query->whereHas('enrollments', fn ($enrollments) => $enrollments->where('program_id', $program)))
            ->when($filters['academic_year'] ?? null, fn ($query, $year) => $query->whereHas('enrollments', fn ($enrollments) => $enrollments->where('academic_year_id', $year)))
            ->when($filters['year_level'] ?? null, fn ($query, $level) => $query->whereHas('enrollments', fn ($enrollments) => $enrollments->where('year_level', $level)))
            ->when($filters['section'] ?? null, fn ($query, $section) => $query->whereHas('enrollments', fn ($enrollments) => $enrollments->where('section', 'like', "%{$section}%")))
            ->orderBy($filters['sort'] ?? 'last_name', $filters['direction'] ?? 'asc')
            ->paginate(25)
            ->withQueryString();

        return view('students.index', [
            'students' => $students,
            'filters' => $filters,
            'programs' => Program::where('is_active', true)->orderBy('code')->get(),
            'academicYears' => AcademicYear::orderByDesc('name')->get(),
            'metrics' => [
                'total' => Student::count(),
                'with_cards' => Student::whereHas('idCards')->count(),
                'review' => Student::where('status', 'inactive')->orWhereDoesntHave('idCards')->count(),
            ],
        ]);
    }

    public function show(Student $student): View
    {
        $student->load([
            'enrollments.program',
            'enrollments.academicYear',
            'idCards' => fn ($query) => $query->latest(),
            'idCards.histories' => fn ($query) => $query->latest('occurred_at'),
        ]);

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

    public function update(StudentRequest $request, Student $student): RedirectResponse
    {
        $student->update($request->validated());

        return redirect()->route('students.show', $student)->with('success', 'Student record updated.');
    }
}
