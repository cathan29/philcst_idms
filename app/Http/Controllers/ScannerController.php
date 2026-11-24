<?php

namespace App\Http\Controllers;

use App\Enums\CardStatus;
use App\Http\Requests\VerifyQrRequest;
use App\Models\IdCard;
use App\Models\ScanLog;
use App\Services\ActiveAcademicTerm;
use App\Services\CardStatusTransitionService;
use App\Services\ScanVerificationService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ScannerController extends Controller
{
    public function index(Request $request, ActiveAcademicTerm $activeTerms): View
    {
        $termId = $activeTerms->current()?->id ?? 0;
        $logs = ScanLog::with(['student', 'card'])
            ->whereNotNull('operator_id')
            ->where(fn ($query) => $query
                ->whereNull('id_card_id')
                ->orWhereHas('card', fn ($card) => $card->where('academic_term_id', $termId)))
            ->when(! $request->user()->hasPermission('audit.view'), fn ($query) => $query->where('operator_id', $request->user()->id))
            ->latest('scanned_at')
            ->limit(5)
            ->get();

        $modes = ['verification' => 'Verification'];
        if ($request->user()->hasPermission('cards.print')) {
            $modes['production'] = 'Production';
        }
        if ($request->user()->hasPermission('cards.release')) {
            $modes['releasing'] = 'Releasing';
        }

        return view('scanner.index', compact('logs', 'modes'));
    }

    public function verify(VerifyQrRequest $request, ScanVerificationService $service): JsonResponse
    {
        $data = $request->validated();
        $this->authorizeMode($request, $data['mode']);

        try {
            return response()->json($service->verify($data['payload'], $data['mode'], $request->user()));
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'ok' => false,
                'result' => 'error',
                'message' => 'Verification could not be completed. Try again.',
            ], 500);
        }
    }

    public function transition(
        Request $request,
        IdCard $card,
        CardStatusTransitionService $service,
    ): JsonResponse {
        $data = $request->validate([
            'mode' => ['required', Rule::in(['production', 'releasing'])],
            'target' => ['required', Rule::enum(CardStatus::class)],
            'release_acknowledged' => ['exclude_unless:target,released', 'required', 'accepted'],
            'claimant_name' => ['nullable', 'string', 'max:150'],
            'claimant_relationship' => ['nullable', 'string', 'max:80'],
            'release_remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $target = CardStatus::from($data['target']);

        if ($data['mode'] === 'production') {
            abort_unless(
                $request->user()->hasPermission('cards.print')
                    && in_array($target, [CardStatus::Printed, CardStatus::ReadyForRelease], true),
                403,
            );
        } else {
            abort_unless(
                $request->user()->hasPermission('cards.release')
                    && $target === CardStatus::Released,
                403,
            );
        }

        try {
            $context = [
                'claimant_name' => $data['claimant_name'] ?? null,
                'claimant_relationship' => $data['claimant_relationship'] ?? null,
                'release_remarks' => $data['release_remarks'] ?? null,
            ];
            $updated = $service->transition($card, $target, $request->user(), null, $context);

            return response()->json([
                'ok' => true,
                'status' => $updated->status->value,
                'message' => 'Card status updated successfully.',
            ]);
        } catch (DomainException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    private function authorizeMode(Request $request, string $mode): void
    {
        if ($mode === 'production') {
            abort_unless($request->user()->hasPermission('cards.print'), 403);
        }

        if ($mode === 'releasing') {
            abort_unless($request->user()->hasPermission('cards.release'), 403);
        }
    }
}
