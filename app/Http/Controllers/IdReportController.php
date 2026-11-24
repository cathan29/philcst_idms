<?php

namespace App\Http\Controllers;

use App\Enums\CardStatus;
use App\Enums\SemesterCode;

use App\Models\StudentEnrollment;
use App\Services\ActiveAcademicTerm;
use App\Services\OperationalReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IdReportController extends Controller
{
    public function __construct(
        private OperationalReportService $reports,
        private ActiveAcademicTerm $activeTerms,
    ) {}

    public function index(Request $request): View
    {
        $filters = $this->filters($request);
        $activeTerm = $this->activeTerms->current();
        $query = $this->filteredQuery($filters, $activeTerm?->id);
        $records = $query->paginate(25)->withQueryString();

        return view('reports.index', [
            'records' => $records,
            'rows' => collect($records->items())->map(fn ($record) => $this->row($record)),
            'summary' => $activeTerm ? $this->reports->summary($activeTerm->id) : $this->emptySummary(),
            'filters' => $filters,
            'activeTerm' => $activeTerm,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);
        $activeTerm = $this->activeTerms->current();
        $filename = 'philcst-'.($filters['report_type'] ?? 'enrolled').'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($filters, $activeTerm): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Student Number', 'Student Name', 'Program', 'Year Level', 'Academic Year', 'Semester', 'Card Record', 'Issuance Type', 'Printed At', 'Released At']);
            if ($activeTerm) {
                $this->filteredQuery($filters, $activeTerm->id)->lazyById(200)->each(function ($record) use ($out): void {
                    $row = $this->row($record);
                    fputcsv($out, array_map($this->csvValue(...), [
                        $row['student_number'], $row['student_name'], $row['program'], $row['year_level'],
                        $row['academic_year'], $row['semester'], $row['card_id'], $row['issuance_type'],
                        $row['printed_at'], $row['released_at'],
                    ]));
                });
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function print(Request $request): View
    {
        return view('reports.print', $this->outputData($request));
    }

    public function pdf(Request $request): Response
    {
        $data = $this->outputData($request);
        $limit = max(1, (int) config('idms.report_pdf_limit', 500));
        if ($data['rows']->count() > $limit) {
            return response("PDF export is limited to {$limit} records. Narrow the report filters or use CSV.", 422, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        return Pdf::loadView('reports.print', $data)->setPaper('a4', 'landscape')->download('philcst-id-report-'.now()->format('Ymd-His').'.pdf');
    }

    private function outputData(Request $request): array
    {
        $filters = $this->filters($request);
        $activeTerm = $this->activeTerms->current();
        $rows = $activeTerm
            ? $this->filteredQuery($filters, $activeTerm->id)->limit(max(1, (int) config('idms.report_pdf_limit', 500)) + 1)->get()->map(fn ($record) => $this->row($record))
            : collect();

        return ['rows' => $rows, 'summary' => $activeTerm ? $this->reports->summary($activeTerm->id) : $this->emptySummary(), 'filters' => $filters, 'activeTerm' => $activeTerm, 'generatedAt' => now()];
    }

    private function filteredQuery(array $filters, ?int $termId): Builder
    {
        if (! $termId) {
            return StudentEnrollment::query()->whereRaw('1 = 0');
        }

        $query = $this->reports->dataset($filters['report_type'] ?? 'enrolled', $termId);
        $isEnrollment = $query->getModel() instanceof StudentEnrollment;
        $query->when($filters['search'] ?? null, function (Builder $query, string $value) use ($isEnrollment): void {
            $relation = $isEnrollment ? 'student' : 'student';
            $query->whereHas($relation, fn (Builder $student) => $student
                ->where('student_number', 'like', '%'.addcslashes($value, '%_\\').'%')
                ->orWhere('first_name', 'like', '%'.addcslashes($value, '%_\\').'%')
                ->orWhere('last_name', 'like', '%'.addcslashes($value, '%_\\').'%'));
        });
        $query->when($filters['semester'] ?? null, function (Builder $query, string $value): void {
            $code = SemesterCode::fromInput($value)->databaseCode();
            $query->whereHas('academicTerm', fn (Builder $term) => $term->where('code', $code));
        });
        if (! $isEnrollment) {
            $query->when($filters['status'] ?? null, fn (Builder $query, string $value) => $query->where('status', $value))
                ->when($filters['date_from'] ?? null, fn (Builder $query, string $value) => $query->whereDate('printed_at', '>=', $value))
                ->when($filters['date_to'] ?? null, fn (Builder $query, string $value) => $query->whereDate('printed_at', '<=', $value));
        }

        return $query->orderByDesc('id');
    }

    private function row(mixed $record): array
    {
        $enrollment = $record instanceof StudentEnrollment ? $record : $record->student->enrollments->firstWhere('academic_term_id', $record->academic_term_id);
        $card = $record instanceof StudentEnrollment ? null : $record;
        $student = $card?->student ?? $enrollment?->student;

        return [
            'student_number' => $student?->student_number,
            'student_name' => $student?->full_name,
            'program' => $enrollment?->program?->code,
            'year_level' => $enrollment?->year_level,
            'academic_year' => $enrollment?->academicTerm?->academicYear?->name,
            'semester' => $enrollment?->academicTerm?->name,
            'card_id' => $card?->id,
            'issuance_type' => $card ? Str::headline($card->issuance_type) : null,
            'status' => $card?->status?->value,
            'printed_at' => $card?->printed_at?->toDateTimeString(),
            'released_at' => $card?->release?->released_at?->toDateTimeString(),
        ];
    }

    private function filters(Request $request): array
    {
        return $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(CardStatus::class)],
            'semester' => ['nullable', Rule::in(['0', '1', '2', 'summer', 'first_semester', 'second_semester'])],
            'report_type' => ['nullable', Rule::in(['enrolled', 'printed_cards', 'released_cards', 'printed_not_released', 'without_printed', 'without_released'])],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);
    }

    private function emptySummary(): array
    {
        return array_fill_keys(['enrolled_students', 'printed_cards', 'released_cards', 'printed_not_released', 'unique_students_printed', 'students_without_printed', 'unique_students_released', 'students_without_released'], 0);
    }

    private function csvValue(mixed $value): string
    {
        $value = trim((string) $value);
        return preg_match('/^[=+\-@]/', $value) ? "'{$value}" : $value;
    }
}