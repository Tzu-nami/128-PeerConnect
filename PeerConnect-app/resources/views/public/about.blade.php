<x-layout.landing>

    {{-- Header --}}
    <section class="grid grid-cols-1 md:grid-cols-2 px-6 md:px-20 py-10 gap-6">

        {{-- Title --}}
        <div class="flex flex-col gap-4 animate-fade-up">
            <div class="flex items-center gap-3 text-up-yellow text-xs tracking-widest font-bold uppercase">
                <span class="block w-8 h-px bg-up-yellow"></span>
                About Us
            </div>
            <h1 class="font-heading text-up-maroon text-4xl md:text-5xl font-semibold tracking-wider">
                What is PeerConnect?
            </h1>
        </div>

        {{-- Description --}}
        <div class="text-text-brown leading-7 border-l-0 md:border-l border-up-yellow pl-0 md:pl-5 self-center animate-fade-up">
            LRC PeerConnect connects UPB students with trained peer mentors for enrichment sessions and academic support — simple, organized, and easy to book.
        </div>

    </section>

    {{-- Hero Image --}}
    <div class="w-full h-64 md:h-96 bg-cream-dark border-b border-cream-border overflow-hidden animate-fade-up [animation-delay:100ms]">
        <img
            src="https://cwpbwqcxlccbittkhasq.supabase.co/storage/v1/object/public/assets/images/landing-page/library.jpeg"
            alt="LRC PeerConnect"
            class="w-full h-full object-cover brightness-125">
    </div>

    {{-- Stats Band --}}
    <section class="grid grid-cols-3 border-b border-cream-border animate-fade-up [animation-delay:150ms]">
        <div class="border-r border-cream-border text-center py-8 sm:py-12 px-1 sm:px-4">
            <div class="font-heading text-4xl sm:text-5xl md:text-6xl text-up-maroon mb-2">{{ $stats['mentors'] }}</div>
            <div class="text-up-yellow text-[10px] sm:text-xs font-bold tracking-wider sm:tracking-widest uppercase">Mentors</div>
        </div>
        <div class="border-r border-cream-border text-center py-8 sm:py-12 px-1 sm:px-4">
            <div class="font-heading text-4xl sm:text-5xl md:text-6xl text-up-maroon mb-2">{{ $stats['bookings'] }}</div>
            <div class="text-up-yellow text-[10px] sm:text-xs font-bold tracking-wider sm:tracking-widest uppercase">Sessions Held</div>
        </div>
        <div class="text-center py-8 sm:py-12 px-1 sm:px-4">
            <div class="font-heading text-4xl sm:text-5xl md:text-6xl text-up-maroon mb-2">{{ $stats['subjects'] }}</div>
            <div class="text-up-yellow text-[10px] sm:text-xs font-bold tracking-wider sm:tracking-widest uppercase leading-tight">Subjects<br class="sm:hidden"> Covered</div>
        </div>
    </section>

    <div class="w-full max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">

        {{-- Mission --}}
        <section class="py-16 border-b border-cream-border animate-fade-up [animation-delay:200ms]">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 items-center">
                <div>
                    <div class="text-up-yellow text-xs font-bold tracking-widest uppercase mb-4">Our Mission</div>
                    <p class="text-text-brown leading-8 text-lg">
                        The Learning Resource Center exists to empower every UPB student with the academic tools, guidance, and peer support they need to succeed — making quality learning assistance accessible to all.
                    </p>
                </div>
                <div class="aspect-[4/3] bg-cream-dark border border-cream-border overflow-hidden">
                    <img
                        src="https://cwpbwqcxlccbittkhasq.supabase.co/storage/v1/object/public/assets/images/landing-page/about-us/mission.jpg"
                        alt="Our Mission"
                        class="w-full h-full object-cover">
                </div>
            </div>
        </section>

        {{-- Quote --}}
        <section class="py-16 border-b border-cream-border animate-fade-up [animation-delay:225ms]">
            <div class="max-w-3xl mx-auto text-center">
                <i class="fa-solid fa-quote-left text-4xl text-cream-border block mb-6"></i>
                <p class="italic text-text-brown leading-9 text-xl md:text-2xl mb-6">
                    "Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua."
                </p>
                <div class="w-8 h-px bg-up-yellow mx-auto mb-4"></div>
                <p class="text-text-brown-light text-xs tracking-widest uppercase">— LRC Head</p>
            </div>
        </section>

        {{-- How it Works --}}
        <section class="py-16 border-b border-cream-border animate-fade-up [animation-delay:250ms]">
            <div class="text-up-yellow text-xs font-bold tracking-widest uppercase mb-10">How it Works</div>

            <div class="grid grid-cols-1 md:grid-cols-5 gap-0">
                @foreach ([
                    ['number' => 1, 'title' => 'Log in', 'desc' => 'Sign in using your UP email account.'],
                    ['number' => 2, 'title' => 'Book a session', 'desc' => 'Pick a mentor, subject, date, and time.'],
                    ['number' => 3, 'title' => 'Wait for approval', 'desc' => 'Your booking is reviewed by LRC staff.'],
                    ['number' => 4, 'title' => 'Attend', 'desc' => 'Show up, ask questions, and learn actively.'],
                    ['number' => 5, 'title' => 'Leave a review', 'desc' => 'Rate your session to help others.'],
                ] as $i => $step)
                    <div class="flex md:flex-col items-start md:items-center gap-4 md:gap-3 md:text-center px-0 md:px-3 py-6 md:py-0 {{ $i !== 4 ? 'border-b md:border-b-0 md:border-r border-cream-border' : '' }}">
                        <div class="flex-shrink-0 w-10 h-10 rounded-full bg-up-maroon text-cream text-sm font-semibold flex items-center justify-center">
                            {{ $step['number'] }}
                        </div>
                        <div>
                            <div class="font-medium text-text-brown">{{ $step['title'] }}</div>
                            <div class="text-sm text-text-brown-light leading-5 mt-1">{{ $step['desc'] }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- Who are Mentors --}}
        <section class="py-16 border-b border-cream-border animate-fade-up [animation-delay:275ms]">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 items-center">
                <div class="aspect-[4/3] bg-cream-dark border border-cream-border overflow-hidden order-last lg:order-first">
                    <img
                        src="https://cwpbwqcxlccbittkhasq.supabase.co/storage/v1/object/public/assets/images/landing-page/about-us/mentors.jpg"
                        alt="Our Mentors"
                        class="w-full h-full object-cover brightness-110">
                </div>
                <div>
                    <div class="text-up-yellow text-xs font-bold tracking-widest uppercase mb-4">Who are Mentors?</div>
                    <p class="text-text-brown leading-7 mb-6">
                        Peer mentors are trained UPB students who have excelled in their fields. They undergo preparation to guide fellow students through academic challenges in a supportive, relatable environment.
                    </p>

                    <div class="flex flex-col gap-4">
                        @foreach ([
                            ['icon' => 'verified', 'text' => 'Trained and screened by LRC staff'],
                            ['icon' => 'school', 'text' => 'Currently enrolled UPB students'],
                            ['icon' => 'menu_book', 'text' => 'Experts in their own fields of study'],
                            ['icon' => 'favorite', 'text' => 'Committed to peer-driven learning'],
                        ] as $item)
                            <div class="flex items-center gap-3">
                                <span class="material-symbols-outlined text-up-maroon text-xl flex-shrink-0">{{ $item['icon'] }}</span>
                                <div class="text-sm text-text-brown">{{ $item['text'] }}</div>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-6">
                        <a href="{{ route('public.mentors') }}" class="text-up-maroon font-bold text-xs inline-flex items-center gap-1 tracking-widest uppercase border-b border-up-maroon pt-2 hover:text-up-maroon/70 w-max">
                            Meet our Mentors
                            <span class="material-symbols-outlined text-lg">arrow_right_alt</span>
                        </a>
                    </div>
                </div>
            </div>
        </section>

        {{-- Common FAQs --}}
        <section class="py-10 border-b border-cream-border animate-fade-up [animation-delay:300ms]">
            <div class="text-up-yellow text-xs font-bold tracking-widest uppercase mb-4">Common Questions</div>

            <div class="border border-cream-border divide-y divide-cream-border">
                <div class="px-5 py-4" x-data="{ open: false }">
                    <button class="font-medium text-text-brown mb-1 w-full flex justify-between items-center"
                         @click="open = !open">
                        <span>Who can avail of LRC services?</span>
                        <span class="material-symbols-outlined transition-transform duration-300" :class="open ? 'rotate-180' : ''">keyboard_arrow_down</span>
                    </button>
                    <div class="text-sm text-text-brown-light leading-6"
                         x-show="open" x-transition>
                        All currently enrolled UPB undergraduate students are eligible to book a session with the LRC.
                    </div>
                </div>

                <div class="px-5 py-4" x-data="{ open: false }">
                    <button class="font-medium text-text-brown mb-1 w-full flex justify-between items-center"
                            @click="open = !open">
                        <span>How do I book a session?</span>
                        <span class="material-symbols-outlined transition-transform duration-300" :class="open ? 'rotate-180' : ''">keyboard_arrow_down</span>

                    </button>
                    <div class="text-sm text-text-brown-light leading-6"
                         x-show="open" x-transition>
                        Log in to your account, go to the Bookings page, select a session type, choose an available mentor,
                        and pick your preferred date and time slot.
                    </div>
                </div>

                <div class="px-5 py-4" x-data="{ open: false }">
                    <button class="font-medium text-text-brown mb-1 w-full flex justify-between items-center"
                            @click="open = !open">
                        <span>Is there a fee for LRC sessions?</span>
                        <span class="material-symbols-outlined transition-transform duration-300" :class="open ? 'rotate-180' : ''">keyboard_arrow_down</span>
                    </button>
                    <div class="text-sm text-text-brown-light leading-6"
                         x-show="open" x-transition>
                        No. All LRC peer mentoring sessions are completely free for UPB students.
                    </div>
                </div>
            </div>

            <div class="mt-3 text-right">
                <a href="{{ route('public.services') }}#faqs" class="text-xs text-up-maroon font-bold tracking-widest uppercase hover:underline">
                    See all FAQs →
                </a>
            </div>
        </section>

        {{-- Developers --}}
        <section class="py-8 animate-fade-up [animation-delay:325ms]">
            <div class="text-up-yellow text-xs font-bold tracking-widest uppercase mb-4">Developed By</div>

            <div class="border border-cream-border divide-y divide-cream-border">
                <div class="grid grid-cols-1 sm:grid-cols-2 divide-y sm:divide-y-0 sm:divide-x divide-cream-border">
                    <div class="px-5 py-4">
                        <div class="font-medium text-sm">Ax'l Jhone David P. Conchada</div>
                        <div class="text-xs text-text-brown-light mt-1">3rd Year BS Computer Science</div>
                    </div>
                    <div class="px-5 py-4">
                        <div class="font-medium text-sm">Daniel Joco B. Dyoco</div>
                        <div class="text-xs text-text-brown-light mt-1">3rd Year BS Computer Science</div>
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 divide-y sm:divide-y-0 sm:divide-x divide-cream-border">
                    <div class="px-5 py-4">
                        <div class="font-medium text-sm">Rhona Shayne B. Lopez</div>
                        <div class="text-xs text-text-brown-light mt-1">3rd Year BS Computer Science</div>
                    </div>
                    <div class="px-5 py-4">
                        <div class="font-medium text-sm">Frian Karl C. Nabo</div>
                        <div class="text-xs text-text-brown-light mt-1">3rd Year BS Computer Science</div>
                    </div>
                </div>
            </div>

            <div class="text-xs text-text-brown-light tracking-wide mt-3">
                University of the Philippines Baguio | 2025 – 2026
            </div>
        </section>

    </div>

</x-layout.landing>
