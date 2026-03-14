<?php

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use function Livewire\Volt\{layout, state, mount};

layout('layouts.guest');

state([
    'firstName' => '',
    'lastName' => '',
    'middleInitial' => '',
    'email' => '',
    'password' => '',
    'password_confirmation' => '',
]);

$register = action(function () {
    $validated = $this->validate([
        'firstName' => ['required', 'string', 'max:255'],
        'lastName' => ['required', 'string', 'max:255'],
        'middleInitial' => ['nullable', 'string', 'max:2'],
        'email' => ['required', 'string', 'email', 'max:255', 'unique:user_profiles,email'],
        'password' => ['required', 'confirmed', Rules\Password::defaults()],
    ]);

    $user = User::create([
        'firstName' => $validated['firstName'],
        'lastName' => $validated['lastName'],
        'middleInitial' => $validated['middleInitial'] ?: null,
        'email' => $validated['email'],
        'password' => Hash::make($validated['password']),
        'user_roles' => 'student',
        'last_login_at' => now(),
    ]);

    event(new Registered($user));
    Auth::login($user);

    $this->redirect(route('dashboard', absolute: false), navigate: true);
});

?>

<div>
    <form wire:submit="register">
        <!-- Name -->
        <div>
            <x-input-label for="firstName" :value="First Name" />
            <x-text-input wire:model="firstName" id="firstName" class="block mt-1 w-full" type="text" name="firstName" required autofocus />
            <x-input-error :messages="$errors->get('firstName')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="lastName" :value="Last Name" />
            <x-text-input wire:model="lastName" id="lastName" class="block mt-1 w-full" type="text" name="lastName" required autofocus />
            <x-input-error :messages="$errors->get('lastName')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="middleInitial" :value="Middle Initial (optional)" />
            <x-text-input wire:model="middleInitial" id="middleInitial" class="block mt-1 w-full" type="text" name="middleInitial" maxlength="2" />
            <x-input-error :messages="$errors->get('middleInitial')" class="mt-2" />
        </div>

        <!-- Email Address -->
        <div class="mt-4">
            <x-input-label for="email" :value="School Email" />
            <x-text-input wire:model="email" id="email" class="block mt-1 w-full" type="email" required />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="Password" />

            <x-text-input wire:model="password" id="password" class="block mt-1 w-full"
                            type="password"
                            required />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Confirm Password -->
        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="Confirm Password" />

            <x-text-input wire:model="password_confirmation" id="password_confirmation" class="block mt-1 w-full"
                            type="password"
                            required />

            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <a class="underline text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-gray-800" href="{{ route('login') }}" wire:navigate>
                {{ __('Already registered?') }}
            </a>

            <x-primary-button class="ms-4">
                {{ __('Register') }}
            </x-primary-button>
        </div>
    </form>
</div>
