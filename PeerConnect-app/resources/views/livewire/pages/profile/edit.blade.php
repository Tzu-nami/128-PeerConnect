<?php

use function Livewire\Volt\{layout};

layout('layouts.app');

?>

<div class="max-w-4xl mx-auto p-8 space-y-6">
    <!-- header -->
    <div class="flex items-center justify-between">

        <h1 class="text-2xl font-bold">
            Profile Settings
        </h1>

        <a href="/dashboard"
           class="flex items-center gap-2 bg-gray-800 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition">

            ← Return to Dashboard
        </a>

    </div>


    <div class="bg-white shadow rounded-xl p-6">
        <livewire:profile.update-profile-information-form />
    </div>

    <div class="bg-white shadow rounded-xl p-6">
        <livewire:profile.update-password-form />
    </div>

    <div class="bg-white shadow rounded-xl p-6">
        <livewire:profile.delete-user-form />
    </div>

</div>
