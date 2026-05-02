<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GoogleController extends Controller
{
    // Handles redirection to google
    public function redirect() {
        return Socialite::driver('google')->redirect();
    }

    // Handles google callback to fetch data from google
    public function callback() {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Exception $e)  {
            return redirect()->route('login')->withErrors(['google' => 'Google Authentication failed. Please try again.']);
        }

        //Block not up emails
        if (!Str::endsWith($googleUser->getEmail(), '@up.edu.ph')) {
            return redirect()->route('login')->with('error', 'Only UP Mail accounts are allowed.');
        }

        // Extract name
        $firstName = $googleUser->user['given_name'] ?? '';
        $lastName = $googleUser->user['family_name'] ?? '';

        // Find user by google id and then by email if it exists
        $user = User::where('google_id', $googleUser->getId())->first() ?? User::where('email', $googleUser->getEmail())->first();

        if ($user) {
            // Check if user already exists in database
            $user->update([
                'google_id' => $googleUser->getId(),
                'firstName' => $firstName,
                'lastName' => $lastName,
                'avatar' => (empty($user->avatar) || Str::contains($user->avatar, 'googleusercontent.com')) ? $googleUser->getAvatar() : $user->avatar,
                'last_login_at' => now(),
                'user_roles' => $user->user_roles ?? 'student',
            ]);
        } else {
            // Create new user
            $user = User::create([
                'google_id' => $googleUser->getId(),
                'firstName' => $firstName,
                'lastName' => $lastName,
                'email' => $googleUser->getEmail(),
                'avatar' => $googleUser->getAvatar(),
                'last_login_at' => now(),
                'user_roles' => 'student',
                'password' => null,
            ]);
        }

        // Redirect based on role
        Auth::login($user, remember: true);
        return redirect()->intended($this->redirectBasedOnRole($user));
    }

    // Redirect to dashboard based on role
    private function redirectBasedOnRole(User $user): string {
        return match($user->user_roles) {
            'admin' => route('admin.dashboard'),
            'mentor' => route('mentor.dashboard'),
            default => route('student.dashboard'),
        };
    }
}
