<?php

namespace App\Http\Controllers;

use App\Models\ScanLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ScanLogController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));
        $status = (string) $request->query('status');
        $date = (string) $request->query('date');

        $logs = ScanLog::with('idHolder')
            ->when($search, fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('scanned_code', 'like', "%{$search}%")
                    ->orWhereHas('idHolder', fn ($holder) => $holder
                        ->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%"));
            }))
            ->when(in_array($status, ['accepted', 'rejected'], true), fn ($query) => $query->where('status', $status))
            ->when($date, fn ($query) => $query->whereDate('scanned_at', $date))
            ->latest('scanned_at')
            ->paginate(20)
            ->withQueryString();

        return view('logs.index', compact('logs', 'search', 'status', 'date'));
    }
}
