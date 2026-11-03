<?php

namespace App\Http\Controllers;

use App\Enums\CardStatus;
use App\Enums\IdIssuanceType;
use App\Http\Requests\StoreIdProductionRequest;
use App\Models\AcademicTerm;
use App\Models\IdCard;
use App\Models\Program;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Services\CardStatusTransitionService;
use App\Services\IdProductionService;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class IdProductionController extends Controller
{
    public function index(Request $request): View
    {
        $f = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'academic_term' => ['nullable', 'integer'], 'program' => ['nullable', 'integer'], 'year_level' => ['nullable', 'string', 'max:30'], 'status' => ['nullable', Rule::enum(CardStatus::class)], 'issuance_type' => ['nullable', Rule::enum(IdIssuanceType::class)], 'date_from' => ['nullable', 'date'], 'date_to' => ['nullable', 'date', 'after_or_equal:date_from'], 'sort' => ['nullable', 'in:requested_at,student,status,issuance_type'], 'direction' => ['nullable', 'in:asc,desc']]);
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
        $initialStudent = null;
        if ($request->filled('student_id')) {
            $student = $this->studentQuery()->find($request->integer('student_id'));
            $initialStudent = $student ? $this->studentPayload($student) : null;
        }

        return view('production.create', [
            'initialStudent' => $initialStudent,
            'issuanceTypes' => IdIssuanceType::cases(),
        ]);
    }

    public function searchStudents(Request $request): JsonResponse
    {
        $data = $request->validate(['q' => ['required', 'string', 'min:2', 'max:100']]);
        $terms = collect(preg_split('/\s+/', trim($data['q'])))->filter()->values();
        $students = $this->studentQuery()->where(function (Builder $query) use ($data, $terms): void {
            $query->where('student_number', 'like', "%{$data['q']}%");
            foreach ($terms as $term) {
                $query->orWhere(fn (Builder $part) => $part->where('student_number', 'like', "%{$term}%")
                    ->orWhere('first_name', 'like', "%{$term}%")->orWhere('middle_name', 'like', "%{$term}%")
                    ->orWhere('last_name', 'like', "%{$term}%")->orWhere('suffix', 'like', "%{$term}%"));
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

            return redirect()->route('production.show', $card)->with('success', 'ID production request created with Pending status.');
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

    private function studentQuery(): Builder
    {
        return Student::query()->with([
            'enrollments.program',
            'enrollments.academicTerm.academicYear',
            'idCards' => fn ($q) => $q->orderByDesc('requested_at')->orderByDesc('id'),
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
            'enrollments' => $this->sortEnrollments($student->enrollments)->map(function (StudentEnrollment $enrollment): array {
                $year = $enrollment->academicTerm?->academicYear?->name;
                $term = $enrollment->academicTerm?->name;
                $details = array_filter([$enrollment->program?->code, $enrollment->year_level, $enrollment->section]);

                return [
                    'id' => $enrollment->id,
                    'academic_term_id' => $enrollment->academic_term_id,
                    'program_id' => $enrollment->program_id,
                    'program' => $enrollment->program?->code,
                    'year_level' => $enrollment->year_level,
                    'section' => $enrollment->section,
                    'label' => implode(' | ', array_filter([$year ? "AY {$year}" : null, $term, implode(' ', $details)])),
                ];
            })->values(),
            'cards' => $cards->map(fn (IdCard $card): array => [
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

    private function sortEnrollments(Collection $enrollments): Collection
    {
        return $enrollments->sortByDesc(function (StudentEnrollment $enrollment): string {
            $year = $enrollment->academicTerm?->academicYear;
            $term = $enrollment->academicTerm;
            $yearOrder = $year?->starts_on?->timestamp ?? (int) preg_replace('/\D+/', '', (string) ($year?->name ?? $year?->year ?? 0));
            $termOrder = $term?->starts_on?->timestamp ?? match (strtolower((string) ($term?->code ?? $term?->name))) {
                'summer', 'summer term' => 3,
                'second', 'second semester', '2nd', '2nd semester' => 2,
                'first', 'first semester', '1st', '1st semester' => 1,
                default => 0,
            };

            return sprintf('%020d-%020d-%020d', $yearOrder, $termOrder, $enrollment->id);
        });
    }
}
