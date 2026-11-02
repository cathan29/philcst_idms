<?php

namespace App\Http\Controllers;

use App\Models\IdHolder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class IdHolderController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));
        $holders = IdHolder::query()
            ->when($search, fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('id_number', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('department', 'like', "%{$search}%");
            }))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('holders.index', compact('holders', 'search'));
    }

    public function create(): View
    {
        return view('holders.create');
    }

    public function store(Request $request): RedirectResponse
    {
        IdHolder::create($this->validated($request));

        return redirect()->route('holders.index')->with('success', 'ID holder added successfully.');
    }

    public function edit(IdHolder $holder): View
    {
        return view('holders.edit', compact('holder'));
    }

    public function update(Request $request, IdHolder $holder): RedirectResponse
    {
        $holder->update($this->validated($request, $holder));

        return redirect()->route('holders.index')->with('success', 'ID holder updated successfully.');
    }

    public function destroy(IdHolder $holder): RedirectResponse
    {
        $holder->delete();

        return back()->with('success', 'ID holder archived.');
    }

    private function validated(Request $request, ?IdHolder $holder = null): array
    {
        $validated = $request->validate([
            'id_number' => ['required', 'string', 'max:50', Rule::unique('id_holders')->ignore($holder)],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'holder_type' => ['required', Rule::in(['student', 'employee', 'visitor'])],
            'department' => ['nullable', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $validated['id_number'] = strtoupper(trim($validated['id_number']));

        return $validated;
    }
}
