<x-layout.landing>
    {{-- Header --}}
    <section class="grid grid-cols-1 md:grid-cols-2 px-6 md:px-20 py-10 gap-6">

        {{-- Title --}}
        <div class="flex flex-col gap-4 animate-fade-up">
            <div class="flex items-center gap-3 text-up-yellow-dark text-xs tracking-widest font-bold uppercase">
                <span class="block w-8 h-px bg-up-yellow-dark"></span>
                Our Team
            </div>
            <h1 class="font-heading text-up-maroon text-4xl md:text-5xl font-semibold tracking-wider">
                Meet the Mentors
            </h1>
        </div>

        {{-- Description --}}
        <div class="text-text-brown leading-7 border-l-0 md:border-l border-up-yellow-dark pl-0 md:pl-5 self-center animate-fade-up">
            Our peer mentors are trained UPB students ready to help you succeed academically.
            Browse available mentors, check their subjects and schedules, then log in to book a session
        </div>

    </section>

    {{-- Filters and Mentor Cards --}}
    <section class="px-6 md:px-20 pb-20">
        <livewire:mentor-index />
    </section>
</x-layout.landing>
