<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function show(User $user): Response
    {
        return Inertia::render('Profile/Show', [
            'profile' => $user->only(['name', 'username', 'bio', 'avatar_url']),
        ]);
    }

    public function edit(Request $request): Response
    {
        return Inertia::render('Profile/Edit', [
            'profile' => $request->user()->only(['name', 'bio', 'avatar_url']),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'avatar' => ['nullable', 'image', 'max:2048'],
        ]);

        $user = $request->user();

        $user->name = $validated['name'];
        $user->bio = $validated['bio'] ?? null;

        if ($request->hasFile('avatar')) {
            if ($user->avatar_path) {
                Storage::disk('public')->delete($user->avatar_path);
            }

            $filename = Str::uuid().'.'.$request->file('avatar')->getClientOriginalExtension();
            $user->avatar_path = $request->file('avatar')->storeAs('avatars', $filename, 'public');
        }

        $user->save();

        return redirect()->route('profile.edit')->with('success', 'Profile updated.');
    }
}
