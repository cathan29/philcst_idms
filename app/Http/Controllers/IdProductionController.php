<?php

namespace App\Http\Controllers;

use App\Enums\CardStatus;
use App\Models\AcademicTerm;
use App\Models\IdCard;
use App\Models\Program;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Services\CardStatusTransitionService;
use App\Services\IdProductionService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class IdProductionController extends Controller
{
    public function index(Request $request): View
    {
        $f = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'academic_term' => ['nullable', 'integer'], 'program' => ['nullable', 'integer'], 'year_level' => ['nullable', 'string', 'max:30'], 'status' => ['nullable', Rule::enum(CardStatus::class)], 'issuance_type' => ['nullable', 'in:initial,replacement,lost,damaged,reissue,other'], 'date_from' => ['nullable', 'date'], 'date_to' => ['nullable', 'date', 'after_or_equal:date_from'], 'sort' => ['nullable', 'in:requested_at,student,status,issuance_type'], 'direction' => ['nullable', 'in:asc,desc']]);
        $q = IdCard::query()->with(['student.latestEnrollment.program', 'student.enrollments.program', 'student.enrollments.academicTerm.academicYear', 'academicTerm.academicYear', 'requester'])->when($f['search'] ?? null, fn ($q, $v) => $q->where(fn ($q) => $q->where('card_token', $v)->orWhere('external_identifier', $v)->orWhereHas('student', fn ($s) => $s->where('student_number', 'like', "%{$v}%")->orWhere('first_name', 'like', "%{$v}%")->orWhere('last_name', 'like', "%{$v}%"))))->when($f['academic_term'] ?? null, fn ($q, $v) => $q->where('academic_term_id', $v))->when($f['program'] ?? null, fn ($q, $v) => $q->whereHas('student.enrollments', fn ($e) => $e->where('program_id', $v)->when($f['academic_term'] ?? null, fn ($e, $t) => $e->where('academic_term_id', $t))))->when($f['year_level'] ?? null, fn ($q, $v) => $q->whereHas('student.enrollments', fn ($e) => $e->where('year_level', $v)))->when($f['status'] ?? null, fn ($q, $v) => $q->where('status', $v))->when($f['issuance_type'] ?? null, fn ($q, $v) => $q->where('issuance_type', $v))->when($f['date_from'] ?? null, fn ($q, $v) => $q->whereDate('requested_at', '>=', $v))->when($f['date_to'] ?? null, fn ($q, $v) => $q->whereDate('requested_at', '<=', $v));
        $sort = $f['sort'] ?? 'requested_at';
        $direction = $f['direction'] ?? 'desc';
        if ($sort === 'student') {
            $q->orderBy(Student::select('last_name')->whereColumn('students.id', 'id_cards.student_id'), $direction);
        } else {
            $q->orderBy($sort, $direction);
        }

        return view('production.index', ['cards' => $q->paginate(25)->withQueryString(), 'filters' => $f, 'terms' => AcademicTerm::with('academicYear')->orderByDesc('id')->get(), 'programs' => Program::where('is_active', true)->orderBy('code')->get(), 'metrics' => collect(['pending', 'for_printing', 'printed', 'ready_for_release', 'on_hold'])->mapWithKeys(fn ($s) => [$s => IdCard::where('status', $s)->count()])]);
    }

    public function create(Request $request): View
    {
        $search = trim((string) $request->query('student_search'));
        $students = Student::with(['latestEnrollment.program', 'latestEnrollment.academicTerm.academicYear', 'enrollments.program', 'enrollments.academicTerm.academicYear', 'idCards' => fn ($q) => $q->latest()])->when($search, fn ($q) => $q->where(fn ($q) => $q->where('student_number', 'like', "%{$search}%")->orWhere('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%")))->orderBy('last_name')->limit(30)->get();

        return view('production.create', compact('students', 'search'));
    }

    public function store(Request $request, IdProductionService $service): RedirectResponse
    {
        $data = $request->validate(['student_id' => ['required', 'exists:students,id'], 'student_enrollment_id' => ['nullable', 'exists:student_enrollments,id'], 'issuance_type' => ['required', 'in:initial,replacement,lost,damaged,reissue,other'], 'internal_notes' => ['nullable', 'string', 'max:1000']]);
        $student = Student::findOrFail($data['student_id']);
        $enrollment = filled($data['student_enrollment_id'] ?? null) ? StudentEnrollment::where('student_id', $student->id)->findOrFail($data['student_enrollment_id']) : $student->latestEnrollment;
        try {
            $card = $service->createRequest($student, $enrollment, $data['issuance_type'], $request->user(), $data['internal_notes'] ?? null);

            return redirect()->route('production.show', $card)->with('success', 'ID production request created.');
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['student_id' => $e->getMessage()]);
        }
    }

    public function show(IdCard $card): View
    {
        $card->load(['student.enrollments.program', 'student.enrollments.academicTerm.academicYear', 'academicTerm.academicYear', 'requester', 'assignedStaff', 'printedBy', 'histories.user']);

        $enrollment = $card->academic_term_id ? $card->student->enrollments->firstWhere('academic_term_id', $card->academic_term_id) : $card->student->enrollments->sortByDesc('id')->first();

        return view('production.show', compact('card', 'enrollment'));
    }

    public function transition(Request $request, IdCard $card, CardStatusTransitionService $service): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::enum(CardStatus::class)], 'reason' => ['nullable', 'string', 'max:500']]);
        $target = CardStatus::from($data['status']);
        $permission = $target === CardStatus::Cancelled ? 'cards.manage' : 'cards.print';
        abort_unless($request->user()->hasPermission($permission), 403);
        try {
            $service->transition($card, $target, $request->user(), $data['reason'] ?? null);

            return back()->with('success', 'Production status updated.');
        } catch (DomainException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }
    }

    public function bulk(Request $request, IdProductionService $service): RedirectResponse
    {
        $data = $request->validate(['card_ids' => ['required', 'array', 'min:1'], 'card_ids.*' => ['integer', 'exists:id_cards,id'], 'status' => ['required', 'in:for_printing,printed,ready_for_release'], 'reason' => ['nullable', 'string', 'max:500']]);
        $result = $service->bulk($data['card_ids'], CardStatus::from($data['status']), $request->user(), $data['reason'] ?? null);

        return back()->with('bulk_result', $result)->with('success', "Bulk operation completed: {$result['eligible']} updated, {$result['ineligible']} ineligible.");
    }
}
