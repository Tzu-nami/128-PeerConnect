import './bootstrap';
import Swiper from 'swiper';
import { Pagination } from 'swiper/modules';
// import Alpine from 'alpinejs';

if (history.scrollRestoration) {
    history.scrollRestoration = 'manual';
}

document.addEventListener('DOMContentLoaded', () => {
    // ── Swiper (only on pages that have it) ──────────────────────────────────
    const swiperEl = document.getElementById('activities-swiper');
    if (swiperEl) {
        const swiper = new Swiper('#activities-swiper', {
            modules: [Pagination],
            loop: true,
            centeredSlides: true,
            slidesPerView: 1,
            spaceBetween: 16,
            initialSlide: 0,
            watchSlidesProgress: true,
            pagination: {
                el: '.swiper-pagination',
                clickable: true,
            },
            breakpoints: {
                640: { slidesPerView: 2, initialSlide: 1 },
                1024: { slidesPerView: 3, initialSlide: 2 },
            },
        });

        document.getElementById('btn-prev')?.addEventListener('click', () => swiper.slidePrev());
        document.getElementById('btn-next')?.addEventListener('click', () => swiper.slideNext());
    }

    // ── Sidebar toggle (app layout only) ─────────────────────────────────────
    const sidebar       = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    if (sidebar && sidebarToggle) {
        sidebarToggle.addEventListener('click', () => {
            sidebar.classList.toggle('collapsed');
            setTimeout(() => {
                window.__dashboardCharts?.forEach(c => c.resize());
            }, 310);
        });
    }

    // ── Profile dropdown ──────────────────────────────────────────────────────
    const profileTrigger  = document.getElementById('profileTrigger');
    const profileDropdown = document.getElementById('profileDropdown');
    if (profileTrigger && profileDropdown) {
        profileTrigger.addEventListener('click', (e) => {
            e.stopPropagation();
            profileDropdown.classList.toggle('show');
        });
        window.addEventListener('click', () => {
            profileDropdown.classList.remove('show');
        });
    }

});

Alpine.data('tableManager', (initialData = [], perPage = 5) => ({
    search: '',
    filterStatuses: [],
    currentPage: 1,
    perPage: perPage,
    items: initialData,

    statusOrder: { accepted: 1, pending: 2, completed: 3, cancelled: 4, rejected: 5, no_show: 6 },

    get filteredItems() {
        const term = this.search.toLowerCase();
        const filtered = this.items.filter(item => {
            const matchSearch = Object.values(item).some(val =>
                val !== null && val !== undefined && String(val).toLowerCase().includes(term)
            );
            const matchStatus = this.filterStatuses.length === 0 ||
                (item.raw_status && this.filterStatuses.includes(item.raw_status));
            return matchSearch && matchStatus;
        });
        const order = this.statusOrder;
        return filtered.sort((a, b) => (order[a.raw_status] ?? 99) - (order[b.raw_status] ?? 99));
    },

    get paginatedItems() {
        const start = (this.currentPage - 1) * this.perPage;
        return this.filteredItems.slice(start, start + this.perPage);
    },

    get totalPages() {
        return Math.ceil(this.filteredItems.length / this.perPage) || 1;
    },

    get pageStart() {
        return this.filteredItems.length === 0 ? 0 : (this.currentPage - 1) * this.perPage + 1;
    },

    get pageEnd() {
        return Math.min(this.currentPage * this.perPage, this.filteredItems.length);
    },

    get pages() {
        const total = this.totalPages;
        const current = this.currentPage;
        if (total <= 8) return Array.from({ length: total }, (_, i) => i + 1);
        if (current <= 4) return [1, 2, 3, 4, 5, '...', total];
        if (current >= total - 3) return [1, '...', total - 3, total - 2, total - 1, total];
        return [1, '...', current - 1, current, current + 1, '...', total];
    },

    toggleAll() {
        this.filterStatuses = [];
        this.currentPage = 1;
    },

    handleStatusChange() {
        this.currentPage = 1;
    },
}));

Alpine.data('mentorDirectory', (initialMentors = [], perPage = 8) => ({
    mentors: initialMentors,
    searchQuery: '',
    selectedSubject: '',
    selectedDay: '',
    currentPage: 1,
    perPage: perPage,  // ← same pattern
    showModal: false,
    selectedMentor: null,
    weekDays: ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'],

    get filteredMentors() {
        const term = this.searchQuery.toLowerCase();
        return this.mentors.filter(mentor => {
            const fullName = (mentor.firstName + ' ' + mentor.lastName).toLowerCase();
            const matchesSearch = term === '' || fullName.includes(term);
            const matchesSub = this.selectedSubject === '' || mentor.subjects.some(s => s.id == this.selectedSubject);
            const matchesDay = this.selectedDay === '' || mentor.days.includes(this.selectedDay);
            return matchesSearch && matchesSub && matchesDay;
        });
    },

    get paginatedMentors() {
        const start = (this.currentPage - 1) * this.perPage;
        return this.filteredMentors.slice(start, start + this.perPage);
    },

    get totalPages() {
        return Math.ceil(this.filteredMentors.length / this.perPage) || 1;
    },

    get pageStart() {
        return this.filteredMentors.length === 0 ? 0 : (this.currentPage - 1) * this.perPage + 1;
    },

    get pageEnd() {
        return Math.min(this.currentPage * this.perPage, this.filteredMentors.length);
    },

    get pages() {
        const total = this.totalPages;
        const current = this.currentPage;
        if (total <= 8) return Array.from({ length: total }, (_, i) => i + 1);
        if (current <= 4) return [1, 2, 3, 4, 5, '...', total];
        if (current >= total - 3) return [1, '...', total - 3, total - 2, total - 1, total];
        return [1, '...', current - 1, current, current + 1, '...', total];
    },

    openModal(mentor) {
        this.selectedMentor = mentor;
        this.showModal = true;
    },
}));

// window.Alpine = Alpine;

// Alpine.start();



