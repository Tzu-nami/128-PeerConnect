<x-layouts.landing>
    {{-- Header --}}
    <section class="grid grid-cols-1 md:grid-cols-2 px-6 md:px-20 py-10 gap-6">

        {{-- Title --}}
        <div class="flex flex-col gap-4 animate-fade-up">
            <div class="flex items-center gap-3 text-up-yellow text-xs tracking-widest font-bold uppercase">
                <span class="block w-8 h-px bg-up-yellow"></span>
                Our Team
            </div>
            <h1 class="font-heading text-up-maroon text-4xl md:text-5xl font-semibold tracking-wider">
                Meet the Staff
            </h1>
        </div>

        {{-- Description --}}
        <div class="text-text-brown leading-7 border-l-0 md:border-l border-up-yellow pl-0 md:pl-5 self-center animate-fade-up">
            The LRC staff oversee and manage the PeerConnect platform, ensuring that every session runs smoothly and that students get the support they need.
        </div>

    </section>

    {{-- Staff Cards --}}
    <section class="px-6 md:px-20 lg:px-40 xl:px-64 pb-20 border-t border-cream-border pt-12 animate-fade-up [animation-delay:150ms]">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 md:gap-10">

            {{-- LRC Head --}}
            <div class="flex flex-col border border-cream-border rounded-sm overflow-hidden">
                <div class="flex flex-col justify-center items-center gap-4 bg-up-green py-8 px-8">
                    <div class="w-20 h-20 rounded-full bg-white"></div>
                    <div class="text-center">
                        <div class="text-lg font-heading text-cream font-bold">[Head Name]</div>
                        <div class="text-cream/70 text-sm mt-1">LRC Head</div>
                    </div>
                </div>
                <div class="flex flex-col divide-y divide-cream-border text-sm text-text-brown">
                    <div class="flex items-center gap-3 px-6 py-3">
                        <svg class="w-4 h-4 text-up-maroon shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25H4.5a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5H4.5a2.25 2.25 0 00-2.25 2.25m19.5 0L12 13.5 2.25 6.75"/>
                        </svg>
                        <span class="truncate">[head@up.edu.ph]</span>
                    </div>
                    <div class="flex items-center gap-3 px-6 py-3">
                        <svg class="w-4 h-4 text-up-maroon shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span>Mon–Fri, 8:00 AM – 5:00 PM</span>
                    </div>
                </div>
            </div>

            {{-- Assistant --}}
            <div class="flex flex-col border border-cream-border rounded-sm overflow-hidden">
                <div class="flex flex-col justify-center items-center gap-4 bg-up-green py-8 px-8">
                    <div class="w-20 h-20 rounded-full bg-white"></div>
                    <div class="text-center">
                        <div class="text-lg font-heading text-cream font-bold">[Assistant Name]</div>
                        <div class="text-cream/70 text-sm mt-1">LRC Assistant</div>
                    </div>
                </div>
                <div class="flex flex-col divide-y divide-cream-border text-sm text-text-brown">
                    <div class="flex items-center gap-3 px-6 py-3">
                        <svg class="w-4 h-4 text-up-maroon shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25H4.5a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5H4.5a2.25 2.25 0 00-2.25 2.25m19.5 0L12 13.5 2.25 6.75"/>
                        </svg>
                        <span class="truncate">[assistant@up.edu.ph]</span>
                    </div>
                    <div class="flex items-center gap-3 px-6 py-3">
                        <svg class="w-4 h-4 text-up-maroon shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span>Mon–Fri, 8:00 AM – 5:00 PM</span>
                    </div>
                </div>
            </div>

            {{-- ========== ADD NEW STAFF CARD BELOW THIS LINE ========== --}}

        </div>
    </section>
</x-layouts.landing>
