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

// Feedback Table
Alpine.data('feedbackManagement', (initialFeedbacks = []) => ({
    feedbacks: initialFeedbacks,
    searchQuery: '',
    mentorFilter: [],
    ratingFilter: [],
    currentPage: 1,
    perPage: 5,
    showFeedbackPopup: false,
    showDetailModal: false,
    selectedFeedbackText: null,
    selectedFeedback: null,
    showMentorDropdown: false,
    showRatingDropdown: false,

    // Questions
    questionsList: [
        'The topics have been discussed very well.',
        'I have learned a lot from the Tutorial Session.',
        'The mentor is good enough in doing his/her tasks.',
        'The mentor was able to clearly explain the topics I do not understand.',
        'There were adequate exercises given.',
        'The mentor has mastery of the subject matter.',
        'The mentor introduces new techniques or simpler approach to the subject.',
        'I will recommend the Tutorial Sessions to my classmates.',
        'I am coming back to attend more Tutorial Sessions.',
    ],

    // Mentor filter
    get availableMentors() {
        const mentorsMap = new Map();
        this.feedbacks.forEach(fb => {
            if (fb.mentor_id && fb.mentor_name && fb.mentor_name !== '-') {
                mentorsMap.set(String(fb.mentor_id), fb.mentor_name);
            }
        });
        return Array.from(mentorsMap, ([id, name]) => ({ id, name })).sort((a, b) => a.name.localeCompare(b.name));
    },

    get allMentorsSelected() {
        return this.mentorFilter.length === 0;
    },

    toggleAllMentors() {
        this.mentorFilter = [];
        this.currentPage = 1;
    },

    onMentorFilterChange() {
        this.currentPage = 1;
    },

    // Rating filter
    get availableRatings() {
        const ratingsMap = new Map();
        this.feedbacks.forEach(fb => {
            if (fb.avgLabel) {
                ratingsMap.set(fb.avgLabel, fb.avgLabel);
            }
        });
        const order = {
            'Excellent': 1,
            'Good': 2,
            'Average': 3,
            'Poor': 4,
            'N/A': 5
        };
        return Array.from(ratingsMap, ([id, name]) => ({ id, name }))
            .sort((a, b) => (order[a.id] || 99) - (order[b.id] || 99));
    },

    get allRatingsSelected() {
        return this.ratingFilter.length === 0;
    },

    toggleAllRatings() {
        this.ratingFilter = [];
        this.currentPage = 1;
    },

    onRatingFilterChange() {
        this.currentPage = 1;
    },

    // Default: newest first
    sortCol: 'date',
    sortDir: 'desc',

    // Sort feature
    toggleSort(col) {
        if (this.sortCol === col) {
            this.sortDir = (this.sortDir === 'asc') ? 'desc' : 'asc';
        } else {
            this.sortCol = col;
            this.sortDir = col === 'date' ? 'desc' : 'asc';
        }
        this.currentPage = 1;
    },

    get filteredFeedbacks() {
        const term = this.searchQuery.toLowerCase();
        
        let result = this.feedbacks.filter(fb => {
            const matchesSearch = 
                (fb.feedback || '').toLowerCase().includes(term) || 
                (fb.subject || '').toLowerCase().includes(term) || 
                (fb.topic || '').toLowerCase().includes(term) || 
                (fb.mentor_name || '').toLowerCase().includes(term);
            
            const matchesMentor = this.mentorFilter.length === 0 || this.mentorFilter.includes(String(fb.mentor_id));
            const matchesRating = this.ratingFilter.length === 0 || this.ratingFilter.includes(fb.avgLabel);
            return matchesSearch && matchesMentor &&  matchesRating;
        });

        result = result.sort((a, b) => {
            let aVal, bVal;

            if (this.sortCol === 'date') {
                aVal = new Date(a.date_formatted).getTime() || 0;
                bVal = new Date(b.date_formatted).getTime() || 0;
            } else if (this.sortCol === 'rating') {
                // Compare numeric averages
                aVal = a.avg || 0;
                bVal = b.avg || 0;
            } else {
                aVal = String(a[this.sortCol] || '').toLowerCase();
                bVal = String(b[this.sortCol] || '').toLowerCase();
            }

            if (aVal < bVal) return this.sortDir === 'asc' ? -1 : 1;
            if (aVal > bVal) return this.sortDir === 'asc' ? 1 : -1;
            return 0;
        });

        return result;
    },

    get paginatedFeedbacks() {
        const start = (this.currentPage - 1) * this.perPage;
        return this.filteredFeedbacks.slice(start, start + this.perPage);
    },

    get pageStart() {
        return this.filteredFeedbacks.length === 0 ? 0 : (this.currentPage - 1) * this.perPage + 1;
    },

    get pageEnd() {
        return Math.min(this.currentPage * this.perPage, this.filteredFeedbacks.length);
    },

    get totalPages() {
        return Math.ceil(this.filteredFeedbacks.length / this.perPage) || 1;
    },

    get pages() {
        const total = this.totalPages;
        const current = this.currentPage;

        if(total <= 8) return Array.from({ length: total }, (_, i) => i + 1);
        if(current <= 4) return [1, 2, 3, 4, 5, '...', total];
        if(current >= total - 3) return [1, '...', total - 3, total - 2, total - 1, total];
        
        return [1, '...', current - 1, current, current + 1, '...', total];
    },

    // Popup Methods
    openFeedbackPopup(feedbackText) {
        this.selectedFeedbackText = feedbackText;
        this.showFeedbackPopup = true;
        document.body.style.overflow = 'hidden';
    },

    closeFeedbackPopup() {
        this.showFeedbackPopup = false;
        if (!this.showDetailModal) document.body.style.overflow = '';
    },

    openDetailModal(fbData) {
        this.selectedFeedback = fbData;
        this.showDetailModal = true;
        document.body.style.overflow = 'hidden';
    },

    closeDetailModal() {
        this.showDetailModal = false;
        if (!this.showFeedbackPopup) document.body.style.overflow = '';
    },

    getScore(index) {
        if (!this.selectedFeedback) return null;
        return this.selectedFeedback[`q${index + 1}`];
    },

    dotClass(score) { return ['','c1','c2','c3','c4','c5'][score] ?? ''; },
    numClass(score) { return ['','s1','s2','s3','s4','s5'][score] ?? ''; },
    
    barColor(avg) {
        if (avg >= 4.5) return '#16a34a';
        if (avg >= 3.5) return '#3b82f6';
        if (avg >= 2.5) return '#eab308';
        return '#ef4444';
    }
}));