<x-layout.landing>
    {{-- Hero --}}
    <section class="grid grid-cols-2 px-6 md:px-20 py-10">
        <div class="flex flex-col gap-4 animate-fade-up">
            <div class="flex items-center gap-3 text-up-yellow-dark text-xs tracking-widest font-bold uppercase">
                <span class="block w-8 h-px bg-up-yellow-dark"></span>
                About Us
            </div>
            <h1 class="font-heading text-up-maroon text-5xl font-semibold tracking-wider">
                What is PeerConnect?
            </h1>
        </div>

        <div class="text-text-brown leading-7 border-l border-up-yellow-dark pl-5 self-center animate-fade-up">
            LRC PeerConnect connects UPB students with trained peer mentors for enrichment sessions and academic support — simple, organized, and easy to book.
        </div>
    </section>

    {{-- Main Content --}}
    <section class="grid grid-cols-2 gap-20 border-t border-cream-border pt-12 px-6 md:px-20 pb-20 animate-fade-up [animation-delay:150ms]">

        {{-- LEFT COLUMN --}}
        <div class="flex flex-col gap-9">

            {{-- Stats --}}
            <div class="grid grid-cols-3 border border-cream-border">
                <div class="border-r border-cream-border text-center py-5">
                    <div class="font-heading text-3xl text-up-maroon mb-2">12</div>
                    <div class="text-text-brown-light text-xs font-bold tracking-widest uppercase">Mentors</div>
                </div>
                <div class="border-r border-cream-border text-center py-5">
                    <div class="font-heading text-3xl text-up-maroon mb-2">84</div>
                    <div class="text-text-brown-light text-xs font-bold tracking-widest uppercase">Sessions Held</div>
                </div>
                <div class="text-center py-5">
                    <div class="font-heading text-3xl text-up-maroon mb-2">10</div>
                    <div class="text-text-brown-light text-xs font-bold tracking-widest uppercase">Subjects Covered</div>
                </div>
            </div>

            {{-- Mission --}}
            <div class="flex flex-col gap-3">
                <div class="text-xs text-up-yellow-dark font-bold tracking-widest uppercase">Our Mission</div>
                <p class="text-text-brown leading-7">
                    The Learning Resource Center exists to empower every UPB student with the academic tools, guidance, and peer support they need to succeed — making quality learning assistance accessible to all.
                </p>
            </div>

            {{-- Quote --}}
            <div class="bg-cream-dark border border-cream-border px-7 py-5">
                <i class="fa-solid fa-quote-left text-3xl mb-3 text-cream-border"></i>
                <p class="italic text-text-brown mb-3 leading-7">
                    "Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.
                </p>
                <p class="text-text-brown-light text-xs text-right tracking-widest uppercase">— LRC Head</p>
            </div>

            {{-- Common FAQs --}}
{{--            <div class="flex flex-col gap-3">--}}
{{--                <div class="text-xs text-up-yellow-dark font-bold tracking-widest uppercase">Common Questions</div>--}}

{{--                <div class="border border-cream-border divide-y divide-cream-border">--}}
{{--                    <div class="px-5 py-4">--}}
{{--                        <div class="font-medium text-text-brown mb-1">Who can use PeerConnect?</div>--}}
{{--                        <div class="text-sm text-text-brown-light leading-6">Any currently enrolled UPB student can book a mentoring session through PeerConnect.</div>--}}
{{--                    </div>--}}
{{--                    <div class="px-5 py-4">--}}
{{--                        <div class="font-medium text-text-brown mb-1">Is it free?</div>--}}
{{--                        <div class="text-sm text-text-brown-light leading-6">Yes, all sessions are completely free for UPB students.</div>--}}
{{--                    </div>--}}
{{--                    <div class="px-5 py-4">--}}
{{--                        <div class="font-medium text-text-brown mb-1">How long is a session?</div>--}}
{{--                        <div class="text-sm text-text-brown-light leading-6">Sessions typically run for one hour, depending on the subject and mentor availability.</div>--}}
{{--                    </div>--}}
{{--                </div>--}}

{{--                <a href="{{ route('public.services') }}#faqs" class="text-xs text-up-maroon font-bold tracking-widest uppercase self-end hover:underline">--}}
{{--                    See all FAQs →--}}
{{--                </a>--}}
{{--            </div>--}}

            {{-- Developers --}}
            <div class="border-t border-cream-border pt-7">
                <div class="text-xs text-up-yellow-dark font-bold tracking-widest uppercase mb-4">Developed By</div>

                <div class="border border-cream-border">
                    <div class="grid grid-cols-2 divide-x divide-cream-border">
                        <div class="flex flex-col justify-center px-4 py-3">
                            <div class="font-medium">Ax'l Jhone David P. Conchada</div>
                            <div class="text-xs text-text-brown-light mt-1">3rd Year BS Computer Science</div>
                        </div>
                        <div class="flex flex-col justify-center px-4 py-3">
                            <div class="font-medium">Daniel Joco B. Dyoco</div>
                            <div class="text-xs text-text-brown-light mt-1">3rd Year BS Computer Science</div>
                        </div>
                    </div>
                    <div class="border-t border-cream-border grid grid-cols-2 divide-x divide-cream-border">
                        <div class="flex flex-col justify-center px-4 py-3">
                            <div class="font-medium">Rhona Shayne B. Lopez</div>
                            <div class="text-xs text-text-brown-light mt-1">3rd Year BS Computer Science</div>
                        </div>
                        <div class="flex flex-col justify-center px-4 py-3">
                            <div class="font-medium">Frian Karl C. Nabo</div>
                            <div class="text-xs text-text-brown-light mt-1">3rd Year BS Computer Science</div>
                        </div>
                    </div>
                </div>

                <div class="text-xs text-text-brown-light tracking-wide mt-2">
                    University of the Philippines Baguio | 2025 – 2026
                </div>
            </div>
        </div>

        {{-- RIGHT COLUMN --}}
        <div class="flex flex-col gap-7 animate-fade-up [animation-delay:250ms]">

            {{-- How it Works --}}
{{--            <div>--}}
{{--                <div class="text-xs text-up-yellow-dark font-bold tracking-widest uppercase mb-4">How it Works</div>--}}

{{--                <div class="border border-cream-border divide-y divide-cream-border">--}}
{{--                    <div class="py-4 px-5 flex items-start gap-5">--}}
{{--                        <div class="flex justify-center items-center flex-shrink-0 w-8 h-8 bg-up-maroon rounded-full text-cream text-xs font-semibold mt-[1px]">1</div>--}}
{{--                        <div>--}}
{{--                            <div class="font-bold mb-1">Log in</div>--}}
{{--                            <div class="text-text-brown-light text-sm leading-6">Sign in using your UP email account.</div>--}}
{{--                        </div>--}}
{{--                    </div>--}}
{{--                    <div class="py-4 px-5 flex items-start gap-5">--}}
{{--                        <div class="flex justify-center items-center flex-shrink-0 w-8 h-8 bg-up-maroon rounded-full text-cream text-xs font-semibold mt-[1px]">2</div>--}}
{{--                        <div>--}}
{{--                            <div class="font-bold mb-1">Book a session</div>--}}
{{--                            <div class="text-text-brown-light text-sm leading-6">Pick a mentor, subject, date, and time that works for you.</div>--}}
{{--                        </div>--}}
{{--                    </div>--}}
{{--                    <div class="py-4 px-5 flex items-start gap-5">--}}
{{--                        <div class="flex justify-center items-center flex-shrink-0 w-8 h-8 bg-up-maroon rounded-full text-cream text-xs font-semibold mt-[1px]">3</div>--}}
{{--                        <div>--}}
{{--                            <div class="font-bold mb-1">Wait for approval</div>--}}
{{--                            <div class="text-text-brown-light text-sm leading-6">Your booking is reviewed and confirmed by the LRC staff.</div>--}}
{{--                        </div>--}}
{{--                    </div>--}}
{{--                    <div class="py-4 px-5 flex items-start gap-5">--}}
{{--                        <div class="flex justify-center items-center flex-shrink-0 w-8 h-8 bg-up-maroon rounded-full text-cream text-xs font-semibold mt-[1px]">4</div>--}}
{{--                        <div>--}}
{{--                            <div class="font-bold mb-1">Attend your session</div>--}}
{{--                            <div class="text-text-brown-light text-sm leading-6">Show up, ask questions, and learn actively.</div>--}}
{{--                        </div>--}}
{{--                    </div>--}}
{{--                    <div class="py-4 px-5 flex items-start gap-5">--}}
{{--                        <div class="flex justify-center items-center flex-shrink-0 w-8 h-8 bg-up-maroon rounded-full text-cream text-xs font-semibold mt-[1px]">5</div>--}}
{{--                        <div>--}}
{{--                            <div class="font-bold mb-1">Leave a review</div>--}}
{{--                            <div class="text-text-brown-light text-sm leading-6">Rate your session to help improve the program for everyone.</div>--}}
{{--                        </div>--}}
{{--                    </div>--}}
{{--                </div>--}}
{{--            </div>--}}


            {{-- Common FAQs --}}
            <div class="flex flex-col gap-3">
                <div class="text-xs text-up-yellow-dark font-bold tracking-widest uppercase">Common Questions</div>

                <div class="border border-cream-border divide-y divide-cream-border">
                    <div class="px-5 py-4">
                        <div class="font-medium text-text-brown mb-1">Who can use PeerConnect?</div>
                        <div class="text-sm text-text-brown-light leading-6">Any currently enrolled UPB student can book a mentoring session through PeerConnect.</div>
                    </div>
                    <div class="px-5 py-4">
                        <div class="font-medium text-text-brown mb-1">Is it free?</div>
                        <div class="text-sm text-text-brown-light leading-6">Yes, all sessions are completely free for UPB students.</div>
                    </div>
                    <div class="px-5 py-4">
                        <div class="font-medium text-text-brown mb-1">How long is a session?</div>
                        <div class="text-sm text-text-brown-light leading-6">Sessions typically run for one hour, depending on the subject and mentor availability.</div>
                    </div>
                </div>

                <a href="{{ route('public.services') }}#faqs" class="text-xs text-up-maroon font-bold tracking-widest uppercase self-end hover:underline">
                    See all FAQs →
                </a>
            </div>

            {{-- Get in Touch --}}
            <div class="border border-cream-border">
                <div class="text-cream font-bold tracking-widest uppercase border-b border-cream-border bg-up-maroon py-2 px-4">
                    Get in Touch
                </div>
                <div class="divide-y divide-cream-border">
                    <div class="flex gap-5 items-center py-4 px-4">
                        <span class="material-symbols-outlined text-up-maroon text-2xl w-8 h-8 flex justify-center items-center flex-shrink-0">location_on</span>
                        <div class="flex flex-col text-sm leading-6">
                            <div>Learning Resource Center, University of the Philippines Baguio</div>
                            <div class="text-text-brown-light">2nd Floor, University Library</div>
                        </div>
                    </div>
                    <div class="flex gap-5 items-center py-4 px-4">
                        <span class="material-symbols-outlined text-up-maroon text-2xl w-8 h-8 flex justify-center items-center flex-shrink-0">schedule</span>
                        <div class="flex flex-col text-sm leading-6">
                            <div>Monday to Friday</div>
                            <div class="text-text-brown-light">8:00 AM – 5:00 PM</div>
                        </div>
                    </div>
                    <div class="flex gap-5 items-center py-4 px-4">
                        <span class="material-symbols-outlined text-up-maroon text-2xl w-8 h-8 flex justify-center items-center flex-shrink-0">mail</span>
                        <div class="text-sm">lrc.upbaguio@up.edu.ph</div>
                    </div>
                    <div class="flex gap-5 items-center py-4 px-4">
                        <span class="material-symbols-outlined text-up-maroon text-2xl w-8 h-8 flex justify-center items-center flex-shrink-0">call</span>
                        <div class="text-sm">(074) 444 8720</div>
                    </div>
                </div>
            </div>

        </div>
    </section>
</x-layout.landing>
