<?php

namespace App\Http\Controllers;

use App\Models\JournalEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class JournalController extends Controller
{
    public function index(Request $request): Response
    {
        $entries = JournalEntry::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get(['id', 'content', 'created_at']);

        return Inertia::render('Journal/Index', [
            'entries' => $entries,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'content' => ['required', 'string', 'max:5000'],
        ]);

        JournalEntry::create([
            'user_id' => $request->user()->id,
            'content' => $validated['content'],
        ]);

        return redirect()->route('journal.index');
    }

    public function update(Request $request, JournalEntry $entry): RedirectResponse
    {
        abort_unless($entry->user_id === $request->user()->id, 403);

        $validated = $request->validate([
            'content' => ['required', 'string', 'max:5000'],
        ]);

        $entry->update(['content' => $validated['content']]);

        return redirect()->route('journal.index');
    }

    public function destroy(Request $request, JournalEntry $entry): RedirectResponse
    {
        abort_unless($entry->user_id === $request->user()->id, 403);

        $entry->delete();

        return redirect()->route('journal.index');
    }
}
