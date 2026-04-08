<?php

use function Livewire\Volt\{layout, mount};

layout('layouts.app');

mount(function () {
    abort_if(!auth()->user()->isStudent(), 403, 'Unauthorized Access');
});

?>

<div class="space-y-5">

    {{-- Page heading --}}
    <div>
        <h1 class="text-3xl font-black text-slate-800">About Us</h1>
        <p class="text-base text-gray-600 mt-1">Learn more about the LRC PeerConnect platform.</p>
    </div>

    {{-- Hero --}}
    <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-200 flex items-center gap-6">
        <div class="w-16 h-16 bg-[#1a3c2f] rounded-xl flex items-center justify-center flex-shrink-0">
            <i class="fa-solid fa-graduation-cap text-white text-2xl"></i>
        </div>
        <div>
            <h2 class="text-xl font-extrabold text-slate-800">LRC PeerConnect</h2>
            <p class="text-base text-gray-500">Connecting UPB students with mentors for academic success.</p>
        </div>
    </div>

    {{-- Main grid --}}
    <div class="grid grid-cols-3 gap-5">

        {{-- Left Column--}}
        <div class="col-span-2 flex flex-col gap-5">

            {{-- What is PeerConnect --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                <h2 class="text-lg font-bold text-slate-800 mb-3">What is PeerConnect?</h2>
                <p class="text-base text-gray-600 leading-relaxed mb-4">
                    LRC PeerConnect is a peer mentoring platform that connects UPB students with trained
                    student-mentors for enrichment sessions and academic support.
                </p>
                <div class="grid grid-cols-3 gap-4 mt-4">
                    @foreach([
                        ['fa-calendar-check', 'Easy Booking'],
                        ['fa-user-group',     'Peer Mentors'],
                        ['fa-clock-rotate-left', 'Track History'],
                    ] as [$icon, $label])
                        <div class="flex flex-col items-center text-center">
                            <div class="w-10 h-10 bg-[#1a3c2f]/10 text-[#1a3c2f] rounded-lg flex items-center justify-center mb-2">
                                <i class="fa-solid {{ $icon }}"></i>
                            </div>
                            <p class="text-sm font-semibold">{{ $label }}</p>
                        </div>
                    @endforeach
                </div>
                <div class="mt-5 bg-gray-50 border border-gray-100 rounded-lg p-3 text-sm text-gray-500">
                    All-in-one platform for booking, tracking, and connecting with mentors.
                </div>
            </div>

            {{-- How it works --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                <h2 class="text-lg font-bold text-slate-800 mb-4 flex items-center gap-2">
                    <i class="fa-solid fa-route text-[#7b1d1d]"></i> How It Works
                </h2>
                <div class="grid grid-cols-2 gap-4">
                    @foreach([
                        ['1', 'Complete Profile'],
                        ['2', 'Book Session'],
                        ['3', 'Wait Approval'],
                        ['4', 'Attend Session'],
                    ] as [$num, $title])
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 bg-[#7b1d1d] text-white rounded-full flex items-center justify-center text-sm font-bold flex-shrink-0">
                                {{ $num }}
                            </div>
                            <p class="text-sm font-semibold text-slate-700">{{ $title }}</p>
                        </div>
                    @endforeach
                </div>
            </div>

        </div>

        {{-- Right Column --}}
        <div class="flex flex-col gap-5">

            {{-- Contact --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                <h2 class="text-base font-bold text-slate-800 mb-3">Contact</h2>
                <ul class="space-y-3 text-sm text-gray-600">
                    <li>📍 Learning Resource Center, UPB</li>
                    <li>🕒 Mon – Fri, 8:00 AM – 5:00 PM</li>
                    <li>✉ lrc@up.edu.ph</li>
                </ul>
            </div>

            {{-- Tips --}}
            <div class="bg-yellow-50 border border-yellow-200 p-5 rounded-xl">
                <h2 class="text-sm font-bold text-yellow-800 mb-2">Student Tips</h2>
                <ul class="text-sm text-yellow-700 space-y-1">
                    <li>• Book early</li>
                    <li>• Be specific with topics</li>
                    <li>• Arrive on time</li>
                    <li>• Prepare questions</li>
                </ul>
            </div>
        </div>
    </div>
</div>
