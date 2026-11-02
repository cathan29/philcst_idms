<?php

namespace App\Http\Controllers;

use App\Models\IdHolder;
use App\Models\ScanLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ScanController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'id_number' => ['required', 'string', 'max:50'],
        ]);

        $code = strtoupper(trim($validated['id_number']));
        $holder = IdHolder::where('id_number', $code)->first();

        if (! $holder) {
            ScanLog::create([
                'scanned_code' => $code,
                'action' => 'denied',
                'status' => 'rejected',
                'remarks' => 'ID number is not registered.',
                'scanned_at' => now(),
            ]);

            return back()->with('scan_error', "Unknown ID: {$code}");
        }

        if ($holder->status !== 'active') {
            ScanLog::create([
                'id_holder_id' => $holder->id,
                'scanned_code' => $code,
                'action' => 'denied',
                'status' => 'rejected',
                'remarks' => 'ID is inactive.',
                'scanned_at' => now(),
            ]);

            return back()->with('scan_error', "{$holder->full_name}'s ID is inactive.");
        }

        $lastScan = $holder->scanLogs()->where('status', 'accepted')->latest('scanned_at')->first();

        if ($lastScan && $lastScan->scanned_at->diffInSeconds(now()) < 20) {
            return back()->with('scan_warning', 'Duplicate scan ignored. Please wait a few seconds.');
        }

        $action = $lastScan?->action === 'entry' ? 'exit' : 'entry';

        ScanLog::create([
            'id_holder_id' => $holder->id,
            'scanned_code' => $code,
            'action' => $action,
            'status' => 'accepted',
            'remarks' => $action === 'entry' ? 'Time in recorded.' : 'Time out recorded.',
            'scanned_at' => now(),
        ]);

        return back()->with('scan_success', [
            'name' => $holder->full_name,
            'id_number' => $holder->id_number,
            'action' => $action,
            'time' => now()->format('h:i:s A'),
        ]);
    }
}
