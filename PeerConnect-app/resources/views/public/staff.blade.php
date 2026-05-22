<x-layouts.landing>
    @php
        $staffList = \App\Models\StaffProfiles::all();

        $roleLabels = [
            'lrc_head' => 'LRC Head',
            'lrc_assistant' => 'LRC Assistant',
            'student_assistant' => 'Student Assistant',
        ];
    @endphp

    {{-- Header --}}
    <section class="grid grid-cols-1 md:grid-cols-2 px-6 md:px-20 py-10 gap-6">
        <div class="flex flex-col gap-4 animate-fade-up">
            <div class="flex items-center gap-3 text-up-yellow text-xs tracking-widest font-bold uppercase">
                <span class="block w-8 h-px bg-up-yellow"></span>
                Our Team
            </div>
            <h1 class="font-heading text-up-maroon text-4xl md:text-5xl font-semibold tracking-wider">
                Meet the Staff
            </h1>
        </div>

        <div class="text-text-brown leading-7 border-l-0 md:border-l border-up-yellow pl-0 md:pl-5 self-center animate-fade-up">
            The LRC staff oversee and manage the PeerConnect platform, ensuring that every session runs smoothly and that students get the support they need.
        </div>
    </section>

    {{-- Staff Cards --}}
    <section class="px-6 md:px-20 lg:px-40 xl:px-64 pb-20 border-t border-cream-border pt-12 animate-fade-up [animation-delay:150ms]">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 md:gap-10 items-stretch">
            @foreach($staffList as $staff)
                @php
                    $roleLabel = $roleLabels[$staff->role] ?? ucwords(str_replace('_', ' ', $staff->role));
                @endphp

                <div class="flex flex-col border border-cream-border rounded-sm overflow-hidden
                {{ $loop->last && $loop->odd ? 'sm:col-span-2 sm:w-1/2 sm:mx-auto sm:self-start' : '' }}">
                    <div class="flex flex-col justify-center items-center gap-4 bg-up-green py-4 px-8">
                        @if($staff->avatar)
                            <img src="{{ $staff->avatar }}" class="w-20 h-20 rounded-full object-cover" alt="{{ $staff->firstName }}">
                        @else
                            <div class="w-20 h-20 rounded-full bg-white"></div>
                        @endif

                        <div class="text-center">
                            <div class="text-lg font-heading text-cream font-bold">
                                {{ $staff->firstName }} {{ $staff->middleInitial ? $staff->middleInitial . '. ' : '' }}{{ $staff->lastName }}
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col flex-1 justify-center divide-y divide-cream-border text-sm text-text-brown">
                        <div class="flex items-center gap-3 px-6 py-3">
                            <i class="fa-solid fa-user text-up-maroon shrink-0"></i>
                            <span class="truncate">{{ $roleLabel }}</span>
                        </div>

                        <div class="flex items-center gap-3 px-6 py-3">
                            <i class="fa-regular fa-envelope text-up-maroon shrink-0"></i>
                            <span class="truncate">{{ $staff->email }}</span>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </section>
</x-layouts.landing>
