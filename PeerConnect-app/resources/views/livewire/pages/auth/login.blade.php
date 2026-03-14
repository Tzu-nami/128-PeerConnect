<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;
use function Livewire\Volt\{form, layout};

layout('layouts.guest');

form(LoginForm::class);

$login = function () {
    //$this->validate();

    //$this->form->authenticate();

    $user = \App\Models\User::where('email', $this->form->email)->first();
    \Illuminate\Support\Facades\Auth::login($user);

    Session::regenerate();

    $user = auth()->user();

    $user->update(['last_login_at' => now()]);

    $redirect = match($user->user_roles) {
        'admin' => route('admin.dashboard', absolute: false),
        'mentor' => route('mentor.dashboard', absolute: false),
        default => route('student.dashboard', absolute: false),
    };

    $this->redirectIntended(default: $redirect, navigate: true);
}; ?>
<div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form wire:submit="login">
        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input wire:model="form.email" id="email" class="block mt-1 w-full" type="email" name="email" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('form.email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />

            <x-text-input wire:model="form.password" id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />

            <x-input-error :messages="$errors->get('form.password')" class="mt-2" />
        </div>

        <!-- Remember Me -->
        <div class="block mt-4">
            <label for="remember" class="inline-flex items-center">
                <input wire:model="form.remember" id="remember" type="checkbox" class="rounded dark:bg-gray-900 border-gray-300 dark:border-gray-700 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:focus:ring-indigo-600 dark:focus:ring-offset-gray-800" name="remember">
                <span class="ms-2 text-sm text-gray-600 dark:text-gray-400">{{ __('Remember me') }}</span>
            </label>
        </div>
            <div style="display: flex; justify-content: center;">
            <x-primary-button class="ms-3">
                {{ __('Log in') }}
            </x-primary-button>
            </div>
        </div>
    </form>
</div>
