<?php

namespace App\Http\Controllers;

use App\Http\Requests\StudentRequest;
use App\Models\AuditLog;
use App\Models\Program;
use App\Models\Student;
use App\Services\ActiveAcademicTerm;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class StudentController extends Controller
{
    public function index(Request $request, ActiveAcademicTerm $activeTerms): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'program' => ['nullable', 'integer'],
            'year_level' => ['nullable', 'string', 'max:30'],
            'status' => ['nullable', 'in:active,inactive,graduated,withdrawn'],
            'card_status' => ['nullable', 'in:pending,for_printing,printed,ready_for_release,released,on_hold,revoked'],
            'sort' => ['nullable', 'in:student_number,first_name,last_name,status,created_at'],
            'direction' => ['nullable', 'in:asc,desc'],
        ]);
        $search = trim((string) ($filters['search'] ?? ''));
        $activeTerm = $activeTerms->current();
        $termId = $activeTerm?->id;
        $termScope = fn ($query) => $termId
            ? $query->where('academic_term_id', $termId)
            : $query->whereRaw('1 = 0');

        $students = Student::query()
            ->with(['enrollments.program', 'enrollments.academicYear', 'enrollments.academicTerm.academicYear', 'latestCard'])
            ->whereHas('enrollments', $termScope)
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('student_number', 'like', "%{$search}%")
                ->orWhere('first_name', 'like', "%{$search}%")
                ->orWhere('middle_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")))
            ->when($filters['status'] ?? null, fn ($query, $value) => $query->where('status', $value))
            ->when($filters['card_status'] ?? null, fn ($query, $value) => $query->whereHas('idCards', fn ($cards) => $cards->where('academic_term_id', $termId)->where('status', $value)))
            ->when($filters['program'] ?? null, fn ($query, $value) => $query->whereHas('enrollments', fn ($enrollments) => $enrollments->where('academic_term_id', $termId)->where('program_id', $value)))
            ->when($filters['year_level'] ?? null, fn ($query, $value) => $query->whereHas('enrollments', fn ($enrollments) => $enrollments->where('academic_term_id', $termId)->where('year_level', $value)))
            ->orderBy($filters['sort'] ?? 'last_name', $filters['direction'] ?? 'asc')
            ->paginate(25)
            ->withQueryString();

        $students->getCollection()->each(function (Student $student) use ($termId): void {
            $student->setRelation('displayEnrollment', $student->enrollments->firstWhere('academic_term_id', $termId));
        });

        $base = Student::query()->whereHas('enrollments', $termScope);

        return view('students.index', [
            'students' => $students,
            'filters' => $filters,
            'programs' => Program::where('is_active', true)->orderBy('code')->get(),
            'activeTerm' => $activeTerm,
            'metrics' => [
                'total' => (clone $base)->count(),
                'with_cards' => (clone $base)->whereHas('idCards', fn ($cards) => $cards->where('academic_term_id', $termId))->count(),
                'active' => (clone $base)->where('status', 'active')->count(),
            ],
        ]);
    }

    public function show(Student $student, ActiveAcademicTerm $activeTerms): View
    {
        $termId = $this->activeEnrollmentTerm($student, $activeTerms);
        $student->load([
            'enrollments' => fn ($query) => $query->where('academic_term_id', $termId),
            'enrollments.program', 'enrollments.academicYear', 'enrollments.academicTerm.academicYear',
            'idCards' => fn ($query) => $query->where('academic_term_id', $termId)->with('release')->latest(),
            'idCards.histories' => fn ($query) => $query->latest('occurred_at'),
        ]);

        return view('students.show', compact('student'));
    }

    public function edit(Student $student, ActiveAcademicTerm $activeTerms): View
    {
        $this->activeEnrollmentTerm($student, $activeTerms);
        return view('students.edit', compact('student'));
    }

    public function update(StudentRequest $request, Student $student, ActiveAcademicTerm $activeTerms): RedirectResponse
    {
        $this->activeEnrollmentTerm($student, $activeTerms);
        $data = $request->validated();
        DB::transaction(function () use ($data, $request, $student): void {
            $old = $student->only(['student_number', 'first_name', 'middle_name', 'last_name', 'suffix', 'status']);
            $student->update($data);
            AuditLog::create([
                'user_id' => $request->user()->id,
                'event' => 'student.updated',
                'auditable_type' => Student::class,
                'auditable_id' => $student->id,
                'old_values' => $old,
                'new_values' => $student->fresh()->only(array_keys($old)),
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 1000, ''),
                'occurred_at' => now(),
            ]);
        }, 3);

        return redirect()->route('students.show', $student)->with('success', 'Student record updated.');
    }

    private function activeEnrollmentTerm(Student $student, ActiveAcademicTerm $activeTerms): int
    {
        $termId = $activeTerms->current()?->id;
        abort_unless($termId && $student->enrollments()->where('academic_term_id', $termId)->exists(), 404);

        return $termId;
    }}