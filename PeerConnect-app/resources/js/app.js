import './bootstrap';
import Swiper from 'swiper';
import { Pagination } from 'swiper/modules';

// Prevent automatic scrolling down of pages
if (history.scrollRestoration) {
    history.scrollRestoration = 'manual';
}

// Swiper for Image Carousel
document.addEventListener('DOMContentLoaded', () => {
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

    // Sidebar toggle
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
});

// Event listener for Alpine dropdowns
document.addEventListener('alpine:init', () => {
    if (!Alpine.store('dropdowns')) {
        Alpine.store('dropdowns', {
            active: null,
            toggle(name) {
                this.active = this.active === name ? null : name;
            },
            close() {
                this.active = null;
            },
        });
    }
});

// Auto fade for Display Messages
Alpine.data('autoFade', () => ({
    show:true,
    init() {
        setTimeout(() => this.show = false, 5000);
    }
}));

// Session History Script
Alpine.data('sessionHistory', (initialData = [], perPage = 5) => ({
    search: '',
    filterStatuses: [],
    currentPage: 1,
    perPage: perPage,
    items: initialData,

    // Default: newest first
    sortCol: 'date',
    sortDir: 'desc',

    toggleSort(col) {
        if (this.sortCol === col) {
            this.sortDir = (this.sortDir === 'asc') ? 'desc' : 'asc';
        } else {
            this.sortCol = col;
            this.sortDir = col === 'date' ? 'desc' : 'asc';
        }
        this.currentPage = 1;
    },

    get filteredItems() {
        const term = this.search.toLowerCase();

        let result = this.items.filter(item => {
            const matchSearch = term === '' || Object.values(item).some(val =>
                val !== null && val !== undefined && String(val).toLowerCase().includes(term)
            );
            const matchStatus = this.filterStatuses.length === 0 ||
                (item.raw_status && this.filterStatuses.includes(item.raw_status));
            return matchSearch && matchStatus;
        });

        const col = this.sortCol;
        const dir = this.sortDir;

        result = [...result].sort((a, b) => {
            let aVal, bVal;

            if (col === 'date') {
                // Combine Y-m-d + H:i for a stable chronological comparison
                aVal = (a.rawDate ?? '') + ' ' + (a.rawTime ?? '');
                bVal = (b.rawDate ?? '') + ' ' + (b.rawTime ?? '');
            } else {
                aVal = String(a[col] ?? '').toLowerCase();
                bVal = String(b[col] ?? '').toLowerCase();
            }

            if (aVal < bVal) return dir === 'asc' ? -1 : 1;
            if (aVal > bVal) return dir === 'asc' ?  1 : -1;
            return 0;
        });

        return result;
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
        const total   = this.totalPages;
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

// Mentor Table
Alpine.data('mentorDirectory', (initialMentors = [], perPage = 8) => ({
    mentors: initialMentors,
    searchQuery: '',
    selectedSubject: '',
    selectedDay: '',
    currentPage: 1,
    perPage: perPage,
    showModal: false,
    selectedMentor: null,
    weekDays: ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'],

    get filteredMentors() {
        const term = this.searchQuery.toLowerCase();
        return this.mentors.filter(mentor => {
            const fullName     = (mentor.firstName + ' ' + mentor.lastName).toLowerCase();
            const matchesSearch  = term === '' || fullName.includes(term);
            const matchesSub   = this.selectedSubject === '' || mentor.subjects.some(s => s.id == this.selectedSubject);
            const matchesDay   = this.selectedDay === '' || mentor.days.includes(this.selectedDay);
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
        const total   = this.totalPages;
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
