<x-layout.landing>
    <main class="mt-[83px] px-6 md:px-20 pt-10 pb-20 overflow-hidden">
        <section class="flex flex-col gap-10">
            {{-- Title --}}
            <div class="flex flex-col gap-4 animate-fade-up">
                <div class="flex items-center gap-3 text-up-yellow-dark text-xs tracking-widest font-bold uppercase">
                    <span class="block w-8 h-px bg-up-yellow-dark font-bold"></span>
                    About Us
                </div>
                <h1 class="font-heading text-up-maroon text-5xl font-semibold tracking-wider">
                    What is PeerConnect?
                </h1>
            </div>

            {{-- Content --}}
            <div class="grid grid-cols-2 gap-20">
                {{-- Left Column --}}
                <div class="flex flex-col gap-9 animate-fade-up [animation-delay:150ms]">
                    {{-- Description --}}
                    <div class="text-base text-text-brown leading-8 border-l border-up-yellow-dark pl-5">
                        LRC PeerConnect is a peer mentoring platform that connects UPB students with trained student-mentors for enrichment sessions and academic support, all within a simple and easy-to-use booking system.
                        It aims to make seeking help more accessible, organized, and convenient for students looking to improve their academic performance and learning experience.
                    </div>

                    {{-- Statistics --}}
                    <div class="grid grid-cols-3 border border-cream-border">
                        <div class="border-r border-cream-border text-center py-5">
                            <div class="font-heading text-3xl text-up-maroon mb-2">1</div>
                            <div class="text-text-brown-light text-xs font-bold tracking-widest uppercase">Mentors</div>
                        </div>

                        <div class="border-r border-cream-border text-center py-5">
                            <div class="font-heading text-3xl text-up-maroon mb-2">2</div>
                            <div class="text-text-brown-light text-xs font-bold tracking-widest uppercase">Sessions Held</div>
                        </div>

                        <div class="text-center py-5">
                            <div class="font-heading text-3xl text-up-maroon mb-2">3</div>
                            <div class="text-text-brown-light text-xs font-bold tracking-widest uppercase">Subjects Covered</div>
                        </div>
                    </div>

                    {{-- Quote --}}
                    <div class="bg-cream-dark border border-cream-border px-7 py-5">
                        <i class="fa-solid fa-quote-left text-3xl mb-2 text-cream-border"></i>
                        <p class="italic text-text-brown mb-3">
                            Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.
                            Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.
                        </p>
                        <p class="text-text-brown-light text-xs text-right tracking-widest uppercase ">— LRC Head</p>
                    </div>

                    {{-- Developers --}}
                    <div class="border-t border-cream-border pt-7">
                        <div class="text-xs text-up-yellow-dark font-bold tracking-widest uppercase mb-4">
                            Developed By
                        </div>

                        <div class="border border-cream-border">
                            <div class="grid grid-cols-2 divide-x divide-cream-border">
                                <div class="flex flex-col justify-center px-4 py-2">
                                    <div class="font-medium">Ax'l Jhone David P. Conchada</div>
                                    <div class="text-xs text-text-brown-light mt-1">3rd Year BS Computer Science</div>
                                </div>

                                <div class="flex flex-col justify-center px-4 py-2">
                                    <div class="font-medium">Daniel Joco B. Dyoco</div>
                                    <div class="text-xs text-text-brown-light mt-1">3rd Year BS Computer Science</div>
                                </div>
                            </div>

                            <div class="border-t border-cream-border grid grid-cols-2 divide-x divide-cream-border">
                                <div class="flex flex-col justify-center px-4 py-2">
                                    <div class="font-medium">Rhona Shayne B. Lopez</div>
                                    <div class="text-xs text-text-brown-light mt-1">3rd Year BS Computer Science</div>
                                </div>

                                <div class="flex flex-col justify-center px-4 py-2">
                                    <div class="font-medium">Frian Karl C. Nabo</div>
                                    <div class="text-xs text-text-brown-light mt-1">3rd Year BS Computer Science</div>
                                </div>
                            </div>
                        </div>

                        <div class="text-xs text-text-brown-light tracking-wide mt-1">
                            University of the Philippines Baguio | 2025 - 2026
                        </div>
                    </div>
                </div>

                {{-- Right Column --}}
                <div class="flex flex-col gap-7 animate-fade-up [animation-delay:250ms]">
                    {{-- Process --}}
                    <div>
                        <div class="text-up-yellow-dark font-bold tracking-widest uppercase mb-4">
                            How it Works
                        </div>

                        <div class="border border-cream-border divide-y divide-cream-border">
                            <div class="py-4 px-4 flex items-start gap-5">
                                <div class="flex justify-center items-center flex-shrink-0 w-8 h-8 bg-up-maroon rounded-full text-cream text-xs font-semibold mt-[1px]">1</div>
                                <div>
                                    <div class="text-lg font-bold mb-1">Login</div>
                                    <div class="text-text-brown-light text-sm">Login to the platform using your UP email account.</div>
                                </div>
                            </div>

                            <div class="py-4 px-4 flex items-start gap-5">
                                <div class="flex justify-center items-center flex-shrink-0 w-8 h-8 bg-up-maroon rounded-full text-cream text-xs font-semibold mt-[1px]">2</div>
                                <div>
                                    <div class="text-lg font-bold mb-1">Book a session</div>
                                    <div class="text-text-brown-light text-sm">Choose a mentor, subject, date and time that works for you.</div>
                                </div>
                            </div>

                            <div class="py-4 px-4 flex items-start gap-5">
                                <div class="flex justify-center items-center flex-shrink-0 w-8 h-8 bg-up-maroon rounded-full text-cream text-xs font-semibold mt-[1px]">3</div>
                                <div>
                                    <div class="text-lg font-bold mb-1">Wait for approval</div>
                                    <div class="text-text-brown-light text-sm">Your booking will be reviewed and confirmed by the LRC.</div>
                                </div>
                            </div>

                            <div class="py-4 px-4 flex items-start gap-5">
                                <div class="flex justify-center items-center flex-shrink-0 w-8 h-8 bg-up-maroon rounded-full text-cream text-xs font-semibold mt-[1px]">4</div>
                                <div>
                                    <div class="text-lg font-bold mb-1">Attend</div>
                                    <div class="text-text-brown-light text-sm">Show up, engage, ask questions, and learn actively.</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Location --}}
                    <div class="border border-cream-border">
                        <div class="text-cream font-bold tracking-widest uppercase border-b border-cream-border bg-up-maroon py-2 px-4">
                            Get in Touch
                        </div>

                        <div class="divide-y divide-cream-border">
                            <div class="flex gap-5 items-center py-4 px-4">
                                <span class="material-symbols-outlined text-up-maroon text-2xl w-8 h-8 flex justify-center items-center flex-shrink-0">location_on</span>
                                <div class="flex flex-col">
                                    <div>Learning Resource Center, University of the Philippines Baguio</div>
                                    <div>2nd Floor, University Library</div>
                                </div>
                            </div>

                            <div class="flex gap-5 items-center py-4 px-4">
                                <span class="material-symbols-outlined text-up-maroon text-2xl w-8 h-8 flex justify-center items-center flex-shrink-0">schedule</span>
                                <div class="flex flex-col">
                                    <div>Monday to Friday</div>
                                    <div>8:00 AM - 5:00 PM</div>
                                </div>
                            </div>

                            <div class="flex gap-5 items-center py-4 px-4">
                                <span class="material-symbols-outlined text-up-maroon text-2xl w-8 h-8 flex justify-center items-center flex-shrink-0">mail</span>
                                <div class="flex flex-col">
                                    <div>lrc.upbaguio@up.edu.ph</div>
                                </div>
                            </div>

                            <div class="flex gap-5 items-center py-4 px-4">
                                <span class="material-symbols-outlined text-up-maroon text-2xl w-8 h-8 flex justify-center items-center flex-shrink-0">call</span>
                                <div class="flex flex-col">
                                    <div>(074) 444 8720</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>
</x-layout.landing>
