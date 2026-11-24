<?php

namespace App\Http\Controllers;

use App\Enums\CardStatus;
use App\Enums\IdIssuanceType;
use App\Http\Requests\StoreIdProductionRequest;
use App\Models\IdCard;
use App\Models\Program;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Services\ActiveAcademicTerm;
use App\Services\CardStatusTransitionService;
use App\Services\IdProductionService;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class IdProductionController extends Controller
{
    public function index(Request $request, ActiveAcademicTerm $activeTerms): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'program' => ['nullable', 'integer'],
            'year_level' => ['nullable', 'string', 'max:30'],
            'status' => ['nullable', Rule::enum(CardStatus::class)],
            'issuance_type' => ['nullable', Rule::enum(IdIssuanceType::class)],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'sort' => ['nullable', 'in:requested_at,student,status,issuance_type'],
            'direction' => ['nullable', 'in:asc,desc'],
        ]);
        $termId = $activeTerms->current()?->id ?? 0;
        $query = IdCard::query()
            ->where('academic_term_id', $termId)
            ->with(['student.enrollments.program', 'student.enrollments.academicTerm.academicYear', 'academicTerm.academicYear', 'requester', 'release'])
            ->when($filters['search'] ?? null, fn ($query, $value) => $query->where(fn ($query) => $query
                ->where('card_token', $value)
                ->orWhere('external_identifier', $value)
                ->orWhereHas('student', fn ($student) => $student
                    ->where('student_number', 'like', "%{$value}%")
                    ->orWhere('first_name', 'like', "%{$value}%")
                    ->orWhere('last_name', 'like', "%{$value}%"))))
            ->when($filters['program'] ?? null, fn ($query, $value) => $query->whereHas('student.enrollments', fn ($enrollment) => $enrollment
                ->where('program_id', $value)->where('academic_term_id', $termId)))
            ->when($filters['year_level'] ?? null, fn ($query, $value) => $query->whereHas('student.enrollments', fn ($enrollment) => $enrollment
                ->where('year_level', $value)->where('academic_term_id', $termId)))
            ->when($filters['status'] ?? null, fn ($query, $value) => $query->where('status', $value))
            ->when($filters['issuance_type'] ?? null, fn ($query, $value) => $query->where('issuance_type', $value))
            ->when($filters['date_from'] ?? null, fn ($query, $value) => $query->whereDate('requested_at', '>=', $value))
            ->when($filters['date_to'] ?? null, fn ($query, $value) => $query->whereDate('requested_at', '<=', $value));

        $sort = $filters['sort'] ?? 'requested_at';
        $direction = $filters['direction'] ?? 'desc';
        if ($sort === 'student') {
            $query->orderBy(Student::select('last_name')->whereColumn('students.id', 'id_cards.student_id'), $direction);
        } else {
            $query->orderBy($sort, $direction);
        }

        return view('production.index', [
            'cards' => $query->paginate(25)->withQueryString(),
            'filters' => $filters,
            'programs' => Program::where('is_active', true)->orderBy('code')->get(),
            'metrics' => collect(['pending', 'for_printing', 'printed', 'ready_for_release', 'on_hold'])
                ->mapWithKeys(fn ($status) => [$status => IdCard::where('academic_term_id', $termId)->where('status', $status)->count()]),
        ]);
    }

    public function create(Request $request): View
    {
        $initialStudent = null;
        if ($request->filled('student_id')) {
            $student = $this->studentQuery()->find($request->integer('student_id'));
            $initialStudent = $student ? $this->studentPayload($student) : null;
        }

        return view('production.create', ['initialStudent' => $initialStudent, 'issuanceTypes' => IdIssuanceType::cases()]);
    }

    public function searchStudents(Request $request): JsonResponse
    {
        $data = $request->validate(['q' => ['required', 'string', 'min:2', 'max:100']]);
        $terms = collect(preg_split('/\s+/', trim($data['q'])))->filter()->values();
        $students = $this->studentQuery()->where(function (Builder $query) use ($data, $terms): void {
            $query->where('student_number', 'like', "%{$data['q']}%");
            foreach ($terms as $term) {
                $query->orWhere(fn (Builder $part) => $part->where('student_number', 'like', "%{$term}%")
                    ->orWhere('first_name', 'like', "%{$term}%")
                    ->orWhere('middle_name', 'like', "%{$term}%")
                    ->orWhere('last_name', 'like', "%{$term}%")
                    ->orWhere('suffix', 'like', "%{$term}%"));
            }
        })->orderBy('last_name')->orderBy('first_name')->limit(20)->get()
            ->map(fn (Student $student) => $this->studentPayload($student));

        return response()->json(['data' => $students]);
    }

    public function store(StoreIdProductionRequest $request, IdProductionService $service): RedirectResponse
    {
        $data = $request->validated();
        $student = Student::findOrFail($data['student_id']);
        $enrollment = StudentEnrollment::where('student_id', $student->id)->findOrFail($data['student_enrollment_id']);
        try {
            $card = $service->createRequest(
                student: $student,
                enrollment: $enrollment,
                type: IdIssuanceType::from($data['issuance_type']),
                actor: $request->user(),
                notes: $data['internal_notes'] ?? null,
                replacesCardId: $data['existing_card_id'] ?? null,
                auditContext: ['ip_address' => $request->ip(), 'user_agent' => $request->userAgent()],
            );

            return redirect()->route('production.show', $card)->with('success', 'Card production record created with Pending status.');
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['student_id' => $e->getMessage()]);
        }
    }

    public function show(IdCard $card, ActiveAcademicTerm $activeTerms): View
    {
        abort_unless($card->academic_term_id === $activeTerms->current()?->id, 404);
        $card->load(['student.enrollments.program', 'student.enrollments.academicTerm.academicYear', 'academicTerm.academicYear', 'requester', 'assignedStaff', 'printedBy', 'releasedBy', 'release.releasedBy', 'histories.user']);
        $enrollment = $card->student->enrollments->firstWhere('academic_term_id', $card->academic_term_id);

        return view('production.show', compact('card', 'enrollment'));
    }

    public function transition(Request $request, IdCard $card, CardStatusTransitionService $service): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::enum(CardStatus::class)], 'reason' => ['nullable', 'string', 'max:500']]);
        $target = CardStatus::from($data['status']);
        abort_if($target === CardStatus::Released, 403, 'Use the authorized releasing workflow to record acknowledgement.');
        $permission = in_array($target, [CardStatus::Cancelled, CardStatus::Revoked], true) ? 'cards.manage' : 'cards.print';
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

    private function studentQuery(): Builder
    {
        $termId = app(ActiveAcademicTerm::class)->current()?->id ?? 0;
        return Student::query()
            ->whereHas('enrollments', fn ($query) => $query->where('academic_term_id', $termId))
            ->with([
                'enrollments' => fn ($query) => $query->where('academic_term_id', $termId),
                'enrollments.program', 'enrollments.academicTerm.academicYear',
                'idCards' => fn ($query) => $query->where('academic_term_id', $termId)->orderByDesc('requested_at')->orderByDesc('id'),
            ]);
    }

    private function studentPayload(Student $student): array
    {
        $cards = $student->idCards;
        return [
            'id' => $student->id,
            'student_number' => $student->student_number,
            'full_name' => $student->full_name,
            'display_name' => "{$student->student_number} - {$student->full_name}",
            'enrollments' => $student->enrollments->map(fn (StudentEnrollment $enrollment) => [
                'id' => $enrollment->id,
                'academic_term_id' => $enrollment->academic_term_id,
                'program_id' => $enrollment->program_id,
                'program' => $enrollment->program?->code,
                'year_level' => $enrollment->year_level,
                'label' => implode(' | ', array_filter([
                    $enrollment->academicTerm?->academicYear?->name ? 'AY '.$enrollment->academicTerm->academicYear->name : null,
                    $enrollment->academicTerm?->name,
                    trim(($enrollment->program?->code ?? '').' '.($enrollment->year_level ?? '')),
                ])),
            ])->values(),
            'cards' => $cards->map(fn (IdCard $card) => [
                'id' => $card->id,
                'status' => $card->status->value,
                'status_label' => $card->status->label(),
                'issuance_type' => $card->issuance_type,
                'requested_at' => $card->requested_at?->format('M j, Y g:i A'),
                'eligible_reference' => in_array($card->status, [CardStatus::Released, CardStatus::Revoked], true),
            ])->values(),
            'has_active_request' => $cards->contains(fn (IdCard $card) => in_array($card->status, IdProductionService::ACTIVE_PRODUCTION, true)),
            'has_physical_history' => $cards->contains(fn (IdCard $card) => in_array($card->status, IdProductionService::PHYSICALLY_ISSUED, true)),
        ];
    }
}