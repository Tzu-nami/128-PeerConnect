<x-layout.landing>
    {{-- Title --}}
    <section class="grid grid-cols-2 px-6 md:px-20 py-10">
        {{-- Title --}}
        <div class="flex flex-col gap-4 animate-fade-up">
            <div class="flex items-center gap-3 text-up-yellow-dark text-xs tracking-widest font-bold uppercase">
                <span class="block w-8 h-px bg-up-yellow-dark font-bold"></span>
                Our Services
            </div>
            <h1 class="font-heading text-up-maroon text-5xl font-semibold tracking-wider">
                What do we Offer?
            </h1>
        </div>

        {{-- Description --}}
        <div class="text-text-brown leading-8 border-l border-up-yellow-dark pl-5 animate-fade-up [animation-delay:150ms]">
            The LRC offers three types of peer mentoring sessions led by trained student-mentors ready to help you succeed.
            <div class="flex gap-6 flex-wrap mt-2">
                <a href="#one-on-one" class="border border-text-brown rounded-[40px] px-4 py-1 hover:bg-cream">One-on-One Sessions</a>
                <a href="#group-session" class="border border-text-brown rounded-[40px] px-4 py-1 hover:bg-cream">Group Sessions</a>
                <a href="#review-classes" class="border border-text-brown rounded-[40px] px-4 py-1 hover:bg-cream">Review Classes</a>
            </div>
        </div>
    </section>

    {{-- Services --}}
    <section class="px-64 border-t border-cream-border pt-12 animate-fade-up [animation-delay:250ms]">
        <div class="flex flex-col gap-12">
            {{-- Row 1 --}}
            <div id="one-on-one" class="grid grid-cols-2 gap-14 scroll-mt-72 items-center">
                <img
                    src="https://cwpbwqcxlccbittkhasq.supabase.co/storage/v1/object/public/assets/images/landing-carousel/Image-7.jpeg"
                    alt="One on One Tutorial Session"
                    class="w-full aspect-[4/3] object-cover bg-cream-dark border border-cream-border">

                <div class="flex flex-col justify-center">
                    <h1 class="text-2xl font-heading text-up-maroon font-bold mb-3">One on One Sessions</h1>
                    <p class="leading-6 mb-3">
                        Get dedicated, personalized academic support from a trained peer mentor.
                        Work through challenging concepts at your own pace in a focused, supportive environment.
                    </p>
                    <ul class="list-disc list-inside marker:text-up-maroon">
                        <li class="border-t border-cream-border pt-3">Personalized session plan based on your needs</li>
                        <li>Available for most UPB undergraduate subjects</li>
                        <li>Flexible scheduling as you can choose your preferred date and time</li>
                        <li class="border-b border-cream-border pb-3">Conducted at the LRC, 2nd Floor, University Library</li>
                    </ul>
                    @if($shouldShowBookNow)
                        <a href="{{ $bookUrl }}"
                           class="text-up-maroon font-bold text-xs inline-flex items-center gap-1 tracking-widest uppercase border-b border-up-maroon pt-2 mt-4 hover:text-up-maroon/70 w-max">
                            Book a Session
                            <span class="material-symbols-outlined text-lg">arrow_right_alt</span>
                        </a>
                    @endif
                </div>
            </div>

            <hr>

            {{-- Row 2 --}}
            <div id="group-session" class="grid grid-cols-2 gap-14 scroll-mt-80 items-center">
                <div class="flex flex-col justify-center">
                    <h1 class="text-2xl font-heading text-up-maroon font-bold mb-3">Group Sessions</h1>
                    <p class="leading-6 mb-3">
                        Study smarter together.
                        Gather with classmates in a guided session led by a peer mentor — ideal for tackling challenging subjects collaboratively.
                    </p>
                    <ul class="list-disc list-inside marker:text-up-maroon">
                        <li class="border-t border-cream-border pt-3">Small groups for focused and productive discussion</li>
                        <li>Mentor facilitates and guides the group discussion</li>
                        <li>Great for subjects that benefit from peer explanation</li>
                        <li class="border-b border-cream-border pb-3">Encourages collaborative learning and diverse perspectives</li>
                    </ul>
                    @if($shouldShowBookNow)
                        <a href="{{ $bookUrl }}"
                           class="text-up-maroon font-bold text-xs inline-flex items-center gap-1 tracking-widest uppercase border-b border-up-maroon pt-2 mt-4 hover:text-up-maroon/70 w-max">
                            Book a Session
                            <span class="material-symbols-outlined text-lg">arrow_right_alt</span>
                        </a>
                    @endif
                </div>

                <img
                    src="https://cwpbwqcxlccbittkhasq.supabase.co/storage/v1/object/public/assets/images/landing-carousel/Image-8.jpg"
                    alt="Group Tutorial Session"
                    class="w-full aspect-[4/3] object-cover bg-cream-dark border border-cream-border">
            </div>

            <hr>

            {{-- Row 3 --}}
            <div id="review-classes" class="grid grid-cols-2 gap-14 scroll-mt-72 items-center">
                <img
                    src="https://cwpbwqcxlccbittkhasq.supabase.co/storage/v1/object/public/assets/images/landing-carousel/Image-6.jpg"
                    alt="Review Class"
                    class="w-full aspect-[4/3] object-cover bg-cream-dark border border-cream-border">

                <div class="flex flex-col justify-center">
                    <h1 class="text-2xl font-heading text-up-maroon font-bold mb-3">Review Classes</h1>
                    <p class="leading-6 mb-3">
                        Prepare effectively for major exams with structured review sessions led by experienced peer mentors.
                        Cover key topics and develop effective exam strategies.
                    </p>
                    <ul class="list-disc list-inside marker:text-up-maroon">
                        <li class="border-t border-cream-border pt-3">Structured around upcoming exams and key topics</li>
                        <li>Focuses on problem areas and common exam pitfalls</li>
                        <li>Includes practice problems and exam strategy tips</li>
                        <li class="border-b border-cream-border pb-3">Available before midterm and final examination periods</li>
                    </ul>
                    @if($shouldShowBookNow)
                        <a href="{{ $bookUrl }}"
                           class="text-up-maroon font-bold text-xs inline-flex items-center gap-1 tracking-widest uppercase border-b border-up-maroon pt-2 mt-4 hover:text-up-maroon/70 w-max">
                            Book a Session
                            <span class="material-symbols-outlined text-lg">arrow_right_alt</span>
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </section>

    {{-- FAQs --}}
    <section id="faqs" class="bg-up-green px-6 md:px-20 py-24 mt-40 scroll-mt-[110px]">
        <div class="flex flex-col gap-4 mb-12">
            <div class="flex items-center gap-3 text-up-yellow-dark text-xs tracking-widest font-bold uppercase">
                <span class="block w-8 h-px bg-up-yellow-dark"></span>
                FAQ
            </div>
            <h2 class="font-heading text-white text-5xl font-semibold tracking-wider">
                Frequently Asked Questions
            </h2>
            <p class="text-white/70 leading-8 border-l border-up-yellow-dark pl-5">
                Have questions about our services? Here are some answers to help you get started.
            </p>
        </div>

        {{-- FAQ Items --}}
        <div class="flex flex-col divide-y divide-white/20">
            <div class="py-5" x-data="{ open: false }">
                <button class="w-full flex justify-between items-center text-white font-semibold text-lg"
                        @click="open = !open">
                    <span>Who can avail of LRC services?</span>
                    <span class="material-symbols-outlined transition-transform duration-300" :class="open ? 'rotate-180' : ''">keyboard_arrow_down</span>
                </button>
                <div class="mt-3 text-white/70 leading-7" x-show="open" x-transition>
                    All currently enrolled UPB undergraduate students are eligible to book a session with the LRC.
                    Simply create an account and choose a session type that fits your needs.
                </div>
            </div>

            <div class="py-5" x-data="{ open: false }">
                <button class="w-full flex justify-between items-center text-left text-white font-semibold text-lg"
                        @click="open = !open">
                    <span>How do I book a session?</span>
                    <span class="material-symbols-outlined transition-transform duration-300" :class="open ? 'rotate-180' : ''">keyboard_arrow_down</span>
                </button>
                <div class="mt-3 text-white/70 leading-7" x-show="open" x-transition>
                    Log in to your account, go to the Bookings page, select a session type, choose an available mentor,
                    and pick your preferred date and time slot.
                </div>
            </div>

            <div class="py-5" x-data="{ open: false }">
                <button class="w-full flex justify-between items-center text-left text-white font-semibold text-lg"
                        @click="open = !open">
                    <span>Is there a fee for LRC sessions?</span>
                    <span class="material-symbols-outlined transition-transform duration-300" :class="open ? 'rotate-180' : ''">keyboard_arrow_down</span>
                </button>
                <div class="mt-3 text-white/70 leading-7" x-show="open" x-transition>
                    No. All LRC peer mentoring sessions are completely free for UPB students.
                </div>
            </div>



            <div class="py-5" x-data="{ open: false }">
                <button class="w-full flex justify-between items-center text-left text-white font-semibold text-lg"
                        @click="open = !open">
                    <span>Where are sessions held?</span>
                    <span class="material-symbols-outlined transition-transform duration-300" :class="open ? 'rotate-180' : ''">keyboard_arrow_down</span>
                </button>
                <div class="mt-3 text-white/70 leading-7" x-show="open" x-transition>
                    All sessions are conducted at the LRC, located on the 2nd Floor of the University Library.
                </div>
            </div>

            <div class="py-5" x-data="{ open: false }">
                <button class="w-full flex justify-between items-center text-left text-white font-semibold text-lg"
                        @click="open = !open">
                    <span>Can I cancel or reschedule a booking?</span>
                    <span class="material-symbols-outlined transition-transform duration-300" :class="open ? 'rotate-180' : ''">keyboard_arrow_down</span>
                </button>
                <div class="mt-3 text-white/70 leading-7" x-show="open" x-transition>
                    Yes. You can cancel or reschedule a session through your Bookings page, subject to availability
                    and the LRC's cancellation policy.
                </div>
            </div>

            <div class="py-5" x-data="{ open: false }">
                <button class="w-full flex justify-between items-center text-left text-white font-semibold text-lg"
                        @click="open = !open">
                    <span>How long is each session?</span>
                    <span class="material-symbols-outlined transition-transform duration-300" :class="open ? 'rotate-180' : ''">keyboard_arrow_down</span>
                </button>
                <div class="mt-3 text-white/70 leading-7" x-show="open" x-transition>
                    Session timing typically depends on the specific session type.
                    <ul class="list-disc list-inside marker:text-white/70 pl-4">
                        <li>
                            One-on-One Sessions: Scheduling is based on the mentor’s availability. Any requests for time extensions are also subject to the mentor's approval and schedule.
                        </li>
                        <li>
                            Review Classes: These sessions are held at a fixed date and time, which will be communicated in advance.

                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </section>
</x-layout.landing>
