<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback(): RedirectResponse
    {
        $googleUser = Socialite::driver('google')->user();

        $user = User::where('google_id', $googleUser->getId())
            ->orWhere('email', $googleUser->getEmail())
            ->first();

        if ($user) {
            if (! $user->google_id) {
                $user->google_id = $googleUser->getId();
            }

            if (! $user->hasVerifiedEmail()) {
                $user->email_verified_at = now();
            }

            $user->save();
        } else {
            $user = User::create([
                'name' => $googleUser->getName() ?: $googleUser->getNickname() ?: 'Student',
                'username' => $this->generateUsername($googleUser),
                'email' => $googleUser->getEmail(),
                'google_id' => $googleUser->getId(),
                'password' => null,
                'role' => 'student',
                'email_verified_at' => now(),
            ]);
        }

        Auth::login($user);

        return redirect()->route('feed');
    }

    private function generateUsername(\Laravel\Socialite\Contracts\User $googleUser): string
    {
        $base = Str::slug($googleUser->getName() ?: Str::before($googleUser->getEmail(), '@'), '_') ?: 'student';
        $username = $base;
        $suffix = 1;

        while (User::where('username', $username)->exists()) {
            $username = $base.'_'.$suffix++;
        }

        return $username;
    }
}
