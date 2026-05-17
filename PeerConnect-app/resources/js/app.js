import './bootstrap';
import Splide from '@splidejs/splide';
import GLightbox from 'glightbox';

// Prevent automatic scrolling down of pages
if (history.scrollRestoration) {
    history.scrollRestoration = 'manual';
}

// Swiper for Image Carousel
document.addEventListener('DOMContentLoaded', () => {
    const splideEl = document.getElementById('activities-splide');
    if (splideEl) {
        new Splide('#activities-splide', {
            type    : 'loop',
            padding : '15rem',
            perPage : 1,
            gap     : '1rem',
            focus   : 'center',
            breakpoints: {
                768: {
                    padding : '0',
                    gap     : '0',
                    arrows  : false,
                },
                1024: {
                    padding : '3rem',
                    gap     : '1rem',
                },
            }
        }).mount();
    }

    GLightbox({
        selector    : '[data-glightbox]',
        loop        : true,
    });

    document.addEventListener('click', (e) => {
        const toggleBtn = e.target.closest('#sidebarToggle');
        if (!toggleBtn) return;
        const sidebar = document.getElementById('sidebar');
        if (!sidebar) return;
        sidebar.classList.toggle('collapsed');
        setTimeout(() => {
            window.__dashboardCharts?.forEach(c => c.resize());
        }, 310);
    });
});

// Close modal on esc keypress
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        closeFeedbackPopup();
        closeDetailModal();
    }
});

// Event listener for Alpine dropdowns
document.addEventListener('alpine:init', () => {
    if (!Alpine.store('dropdowns')) {
        Alpine.store('dropdowns', {
            active: null,
            toggle(name) {
                this.active = this.active === name ? null : name;
                // Close mobile nav when any dropdown opens
                if (this.active !== null) {
                    Alpine.store('sidebar').close();
                }
            },
            close() {
                this.active = null;
            },
        });
    }

    Alpine.store('sidebar', {
        open: false,
        toggle() {
            this.open = !this.open;
            if (this.open) {
                Alpine.store('dropdowns').close();
            }
        },
        close() { this.open = false; },
    });
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

// Mentor Feedbacks
Alpine.data('mentorFeedbacks', (initialData = [], subjects = [], perPage = 5) => ({
    search: '',
    filterSubjects: [],
    allSubjects: subjects,
    currentPage: 1,
    perPage: perPage,
    items: initialData,

    sortCol: 'rawDate',
    sortDir: 'desc',

    toggleSort(col) {
        if (this.sortCol === col) {
            this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            this.sortCol = col;
            // Numeric cols default descending, text cols ascending
            this.sortDir = (col === 'rawDate' || col === 'avg') ? 'desc' : 'asc';
        }
        this.currentPage = 1;
    },

    get filteredItems() {
        const term = this.search.toLowerCase();

        let result = this.items.filter(item => {
            const matchSearch = term === '' || [item.subject, item.topic, item.feedback].some(val =>
                val && String(val).toLowerCase().includes(term)
            );
            const matchSubject = this.filterSubjects.length === 0 ||
                this.filterSubjects.includes(item.subject);
            return matchSearch && matchSubject;
        });

        const col = this.sortCol;
        const dir = this.sortDir;

        result = [...result].sort((a, b) => {
            let aVal, bVal;

            if (col === 'avg') {
                // Numeric sort — nulls always last
                aVal = a.avg ?? -Infinity;
                bVal = b.avg ?? -Infinity;
                return dir === 'asc' ? aVal - bVal : bVal - aVal;
            }

            aVal = String(a[col] ?? '').toLowerCase();
            bVal = String(b[col] ?? '').toLowerCase();

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

    openDetailModal(fb) {
        openDetailModal(fb);
    },

    openFeedbackPopup(fb) {
        openFeedbackPopup(fb);
    },
}));

// Mentor Tutorial Sessions
Alpine.data('tutorialSessions', (initialData = [], perPage = 5) => ({
    search: '',
    filterStatuses: [],
    currentPage: 1,
    perPage,
    items: initialData,

    sortCol: 'rawDate',
    sortDir: 'desc',

    toggleSort(col) {
        if (this.sortCol === col) {
            this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            this.sortCol = col;
            this.sortDir = col === 'rawDate' ? 'desc' : 'asc';
        }
        this.currentPage = 1;
    },

    getStatusColor(status) {
        const map = {
            pending:   'text-yellow-500',
            accepted:  'text-green-600',
            completed: 'text-gray-500',
            rejected:  'text-red-900',
            cancelled: 'text-red-600',
            closed:    'text-purple-700',
            no_show:   'text-orange-600',
        };
        return map[status] ?? 'text-gray-500';
    },

    getStatusLabel(status) {
        const map = {
            no_show:   'No Show',
            accepted:  'Accepted',
            completed: 'Completed',
            closed:    'Closed',
            rejected:  'Rejected',
            cancelled: 'Cancelled',
            pending:   'Pending',
        };
        return map[status] ?? status.charAt(0).toUpperCase() + status.slice(1);
    },

    get filteredItems() {
        const term = this.search.toLowerCase();

        let result = this.items.filter(s => {
            const searchable = [
                s.student, s.subject, s.subjectName, s.topic,
                s.date, s.time, s.mode, s.status,
                s.degreeProgram, s.yearLevel,
            ].join(' ').toLowerCase();

            const matchSearch = term === '' || searchable.includes(term);
            const matchStatus = this.filterStatuses.length === 0 || this.filterStatuses.includes(s.status);
            return matchSearch && matchStatus;
        });

        const col = this.sortCol;
        const dir = this.sortDir;

        const statusOrder = {
            accepted: 1, pending: 2, completed: 3,
            cancelled: 4, no_show: 5, rejected: 6,
        };

        result = [...result].sort((a, b) => {
            let aVal, bVal;

            if (col === 'status') {
                aVal = statusOrder[a.status] ?? 999;
                bVal = statusOrder[b.status] ?? 999;
                return dir === 'asc' ? aVal - bVal : bVal - aVal;
            }

            if (col === 'rawDate') {
                aVal = (a.rawDate ?? '') + ' ' + (a.start ?? '');
                bVal = (b.rawDate ?? '') + ' ' + (b.start ?? '');
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

    updateStatus(id, status) {
        updateStatus(id, status, this.items);
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

// Admin Sessions Table
Alpine.data('sessionManagement', (initialSessions = [], initialCounts = {}) => ({
    sessions: initialSessions,
    counts: initialCounts,
    searchQuery: '',
    statusFilter: [],
    showStatusDropdown: false,

    currentPage: 1,
    perPage: 5,

    // Default: newest first
    sortCol: 'date',
    sortDir: 'desc',

    // Confirmation Modal
    showConfirmModal: false,
    isConfirming: false,
    confirmConfig: {},
    sessionToUpdate: null,
    newStatusToApply: '',

    // Session status notification
    banner: { show: false, message: '', type: 'success', timer: null },

    triggerBanner(message, type = 'success') {
        this.banner.message = message;
        this.banner.type = type;
        this.banner.show = true;

        clearTimeout(this.banner.timer);

        this.banner.timer = setTimeout(() => {
            this.banner.show = false;
        }, 5000);
    },

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

    get filteredSessions() {
        const term = this.searchQuery.toLowerCase();
        let result = this.sessions.filter(s => {
            const searchable = [s.student, s.mentor, s.subject, s.topic, s.date, s.time, s.mode, s.status, s.degreeProgram, s.yearLevel].join(' ').toLowerCase();
            const matchesSearch = searchable.includes(term);
            const matchesStatus = this.statusFilter.length === 0 || this.statusFilter.includes(s.status);
            return matchesSearch && matchesStatus;
        });
        const statusOrder = { accepted: 1, pending: 2, completed: 3, cancelled: 4, no_show: 5, rejected: 6 };

        result = result.sort((a, b) => {
            let valA = a[this.sortCol];
            let valB = b[this.sortCol];

            if (this.sortCol === 'status') {
                valA = statusOrder[valA] ?? 999;
                valB = statusOrder[valB] ?? 999;
            } else if (this.sortCol === 'date') {
                valA = new Date(a.date + ' ' + a.start).getTime() || 0;
                valB = new Date(b.date + ' ' + b.start).getTime() || 0;
            } else {
                valA = String(valA || '').toLowerCase();
                valB = String(valB || '').toLowerCase();
            }

            if (valA < valB) return this.sortDir === 'asc' ? -1 : 1;
            if (valA > valB) return this.sortDir === 'asc' ? 1 : -1;
            return 0;
        });

        return result;
    },

    // Pagination
    get paginatedSessions() {
        const start = (this.currentPage - 1) * this.perPage;
        return this.filteredSessions.slice(start, start + this.perPage);
    },

    get pageStart() {
        return this.filteredSessions.length === 0 ? 0 : (this.currentPage - 1) * this.perPage + 1;
    },

    get pageEnd() {
        return Math.min(this.currentPage * this.perPage, this.filteredSessions.length);
    },

    get totalPages() {
        return Math.ceil(this.filteredSessions.length / this.perPage) || 1;
    },

    get pages() {
        const total = this.totalPages;
        const current = this.currentPage;

        if(total <= 8) return Array.from({ length: total }, (_, i) => i + 1);
        if(current <= 4) return [1, 2, 3, 4, 5, '...', total];
        if(current >= total - 3) return [1, '...', total - 3, total - 2, total - 1, total];

        return [1, '...', current - 1, current, current + 1, '...', total];
    },

    // UI HELPERS (Colors, Labels, Icons)
    getStatusLabel(status) {
        if (!status) return '—';
        if (status === 'no_show') return 'No Show';
        return status.charAt(0).toUpperCase() + status.slice(1);
    },

    formatHours(s) {
        if (!s || !s.duration) return '';
        const match = String(s.duration).match(/\((.*?)\)/);
        return match ? `(${match[1]})` : '';
    },

    getStatusColor(status) {
        const colors = {
            pending: 'text-yellow-600 border-yellow-200',
            accepted: 'text-green-600 border-green-200',
            completed: 'text-gray-500 border-gray-200',
            rejected: 'text-red-800 border-red-200',
            cancelled: 'text-red-600 border-red-200',
            no_show: 'text-orange-600 border-orange-200'
        };
        return colors[status] || 'text-gray-500 border-gray-200 bg-gray-50';
    },

    getIdleIndicatorColor(s) {
        if (s.status === 'pending') return s.is_open ? 'bg-purple-400' : 'bg-yellow-400';
        if (s.status === 'accepted') return 'bg-green-400';
        return 'bg-gray-300';
    },

    // Actions and check conflicts
    hasConflict(newReq) {
        const toMin = (t) => {
            const [h, m] = t.split(':').map(Number);
            return h * 60 + m;
        };
        return this.sessions.some(s => {
            if (s.id === newReq.id) return false;
            if (newReq.group_ids && newReq.group_ids.includes(s.id)) return false;
            if (!['accepted', 'completed'].includes(s.status)) return false;
            if (s.mentor !== newReq.mentor) return false;

            const sStart = toMin(s.start), sEnd = toMin(s.end);
            const rStart = toMin(newReq.start), rEnd = toMin(newReq.end);
            return rStart < sEnd && rEnd > sStart;
        });
    },

    promptUpdateStatus(session, newStatus) {
        // Cannot accept or reject ANY choice
        if (session.is_open && (newStatus === 'accepted' || newStatus === 'rejected')) {
            this.triggerBanner("This session is open to any peer mentor (first-come, first-serve). Admins cannot manually accept or reject it.", 'error');
            return;
        }

        if (newStatus === 'accepted' && this.hasConflict(session)) {
            this.triggerBanner("Cannot approve: This session overlaps with an already-accepted booking on this date.", 'error');
            return;
        }

        this.sessionToUpdate = session;
        this.newStatusToApply = newStatus;

        const isReverting = newStatus === 'accepted' && ['completed', 'no-show', 'rejected'].includes(session.status);

        // Base Configuration
        let cfg = {
            title: 'Confirm action', body: 'Are you sure?', variant: 'neutral',
            iconHtml: '<i class="fa-solid fa-circle-info text-gray-600"></i>',
            iconBgClass: 'bg-gray-100', btnClass: 'bg-gray-600', confirmText: 'Confirm', loadingText: 'Processing...'
        };

        // Custom config based on the action
        if (newStatus === 'accepted') {
            if (isReverting) {
                cfg = { title: 'Revert to accepted?', body: 'This will restore the session back to an accepted state.', iconHtml: '<i class="fa-solid fa-rotate-left text-gray-600"></i>', iconBgClass: 'bg-gray-100', btnClass: 'bg-gray-700 hover:bg-gray-800', confirmText: 'Revert', loadingText: 'Reverting...' };
            } else {
                cfg = { title: 'Accept booking?', body: 'The student will be notified that their session has been approved.', iconHtml: '<i class="fa-solid fa-check text-emerald-600"></i>', iconBgClass: 'bg-emerald-100', btnClass: 'bg-emerald-600 hover:bg-emerald-700', confirmText: 'Accept', loadingText: 'Accepting...' };
            }
        } else if (newStatus === 'rejected') {
            cfg = { title: 'Reject booking?', body: 'The student will be notified that their session request was declined.', iconHtml: '<i class="fa-solid fa-xmark text-red-600"></i>', iconBgClass: 'bg-red-100', btnClass: 'bg-red-600 hover:bg-red-700', confirmText: 'Reject', loadingText: 'Rejecting...' };
        } else if (newStatus === 'completed') {
            cfg = { title: 'Mark as completed?', body: 'This will mark the session as done.', iconHtml: '<i class="fa-solid fa-flag-checkered text-gray-600"></i>', iconBgClass: 'bg-gray-100', btnClass: 'bg-gray-700 hover:bg-gray-800', confirmText: 'Mark Complete', loadingText: 'Saving...' };
        } else if (newStatus === 'no_show') {
            cfg = { title: 'Mark as no-show?', body: 'This will record that the student did not attend the session.', iconHtml: '<i class="fa-solid fa-user-slash text-red-600"></i>', iconBgClass: 'bg-red-100', btnClass: 'bg-red-600 hover:bg-red-700', confirmText: 'Mark No-show', loadingText: 'Saving...' };
        } else if (newStatus === 'cancelled') {
            cfg = { title: 'Cancel session?', body: 'This will cancel the accepted session.', iconHtml: '<i class="fa-solid fa-ban text-red-600"></i>', iconBgClass: 'bg-red-100', btnClass: 'bg-red-600 hover:bg-red-700', confirmText: 'Cancel Session', loadingText: 'Cancelling...' };
        }

        // Attach Session details to the modal HTML
        cfg.metaHtml = `
            <div class="flex justify-between items-start gap-2 mb-1">
                <span class="text-gray-400">Student</span>
                <span class="font-medium text-gray-700 text-right truncate" title="${session.studentNames || session.student}">${session.studentNames || session.student}</span>
            </div>
            <div class="flex justify-between items-start gap-2 mb-1">
                <span class="text-gray-400">Subject</span>
                <span class="font-medium text-gray-700 text-right truncate">${session.subject}</span>
            </div>
            <div class="flex justify-between items-start gap-2 mb-1">
                <span class="text-gray-400">Date</span>
                <span class="font-medium text-gray-700 text-right">${session.date}</span>
            </div>
            <div class="flex justify-between items-start gap-2">
                <span class="text-gray-400">Time</span>
                <span class="font-medium text-gray-700 text-right">${session.time}</span>
            </div>
        `;

        this.confirmConfig = cfg;
        this.showConfirmModal = true;
    },

    closeConfirmModal() {
        this.showConfirmModal = false;
    },

    async executeConfirm() {
        this.isConfirming = true;
        const idsToUpdate = this.sessionToUpdate.group_ids || [this.sessionToUpdate.id];

        try {
            await Promise.all(idsToUpdate.map(async (bookingId) => {
                const formData = new FormData();
                formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);
                formData.append('booking_id', bookingId);
                formData.append('booking_status', this.newStatusToApply);

                const res = await fetch('/admin/sessions/update', { method: 'POST', body: formData });
                if (!res.ok) throw new Error('Request failed');
            }));

            this.sessionToUpdate.status = this.newStatusToApply;
            this.recalculateCounts();

        } catch (err) {
            this.triggerBanner('Update failed. Please check your connection.', 'error');
        } finally {
            this.isConfirming = false;
            this.closeConfirmModal();
            this.triggerBanner('Session status updated successfully.', 'success');
        }
    },

    // Stat cards info
    recalculateCounts() {
        this.counts.total = this.sessions.length;
        this.counts.accepted = this.sessions.filter(s => s.status === 'accepted').length;
        this.counts.pending = this.sessions.filter(s => s.status === 'pending').length;
        this.counts.completed = this.sessions.filter(s => s.status === 'completed').length;

        const completedSessions = this.sessions.filter(s => s.status === 'completed');
        const rawHours = completedSessions.reduce((sum, s) => sum + (s.durationHours || 0), 0);
        this.counts.totalHours = rawHours.toFixed(2);
    },



    // Edit Modal
    showEditModal: false,
    editSession: null,
    editEndTime: '',
    editEndTimeError: '',
    isSavingEdit: false,

    openEditModal(s) {
        this.editSession = s;
        this.editEndTime = s.end;
        this.editEndTimeError = '';
        this.showEditModal = true;
    },

    closeEditModal() {
        this.showEditModal = false;
        this.editSession = null;
        this.editEndTime = '';
        this.editEndTimeError = '';
    },

    formatTo12h(time24) {
        if (!time24) return '—';
        const [h, m] = time24.split(':').map(Number);
        const ampm = h >= 12 ? 'PM' : 'AM';
        const h12 = h % 12 || 12;
        return `${h12}:${String(m).padStart(2, '0')} ${ampm}`;
    },

    computeNewDuration() {
        if (!this.editSession || !this.editEndTime) return '—';
        const [sh, sm] = this.editSession.start.split(':').map(Number);
        const [eh, em] = this.editEndTime.split(':').map(Number);
        const diff = (eh * 60 + em) - (sh * 60 + sm);
        if (diff <= 0) return 'Invalid';
        const hrs = Math.floor(diff / 60);
        const mins = diff % 60;
        if (hrs === 0) return `${mins} min`;
        if (mins === 0) return `${hrs} hr`;
        return `${hrs} hr ${mins} min`;
    },

    async saveEndTime() {
        this.editEndTimeError = '';
        const [sh, sm] = this.editSession.start.split(':').map(Number);
        const [eh, em] = this.editEndTime.split(':').map(Number);
        if ((eh * 60 + em) <= (sh * 60 + sm)) {
            this.editEndTimeError = 'End time must be after the start time.';
            return;
        }
        
        this.isSavingEdit = true;
        const idsToUpdate = this.editSession.group_ids || [this.editSession.id];

        try {
            await Promise.all(idsToUpdate.map(async (bookingId) => {
                const formData = new FormData();
                formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);
                formData.append('booking_id', bookingId);
                formData.append('end_time', this.editEndTime);

                const res = await fetch('/admin/sessions/update-end-time', { method: 'POST', body: formData });
                if (!res.ok) throw new Error('Request failed');
            }));

            this.editSession.end = this.editEndTime;
            this.editSession.time = this.formatTo12h(this.editSession.start) + ' – ' + this.formatTo12h(this.editEndTime);
            this.closeEditModal();
            this.triggerBanner('Time updated successfully.', 'success');
        } catch (err) {
            this.editEndTimeError = 'Failed to save. Please try again.';
        } finally {
            this.isSavingEdit = false;
        }
    },
}));

document.querySelectorAll('.nav-item[data-tooltip]').forEach(item => {
    item.addEventListener('mouseenter', () => {
        const rect = item.getBoundingClientRect();
        item.style.setProperty('--tooltip-top', `${rect.top + rect.height / 2}px`);
    });
});

// Mentor CRUD applications
document.addEventListener('alpine:init', () => {
    Alpine.data('mentorManagement', (initialMentors, wire)  => ({
        mentors: initialMentors,
        searchQuery: '',
        currentPage: 1,
        perPage: 5,
        sortColumn: 'name',
        sortDirection: 'asc',
        showViewModal: false,
        selectedMentor: null,
        showEditModal: false,
        editingMentor: null,
        editForm: { subjects: [], availabilities: [] },
        originalForm:  { subjects: [], availabilities: [] },
        showDeleteConfirm: false,
        mentorToDelete: null,
        weekDays: ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'],

        banner: { show: false, message: '', type: 'success', timer: null },

        init() {

            window.addEventListener('click', () => {
                document.getElementById('mentorDropdown')?.classList.add('hidden');
            });
        },

        triggerBanner(message, type = 'success') {
            this.banner.message = message;
            this.banner.type = type;
            this.banner.show = true;

            clearTimeout(this.banner.timer);
            this.banner.timer = setTimeout(() => { this.banner.show = false; }, 5000);
        },

        setSort(col) {
            if (this.sortColumn === col) {
                this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
            } else {
                this.sortColumn = col;
                this.sortDirection = 'asc';
            }
            this.currentPage = 1;
        },

        sortIndicator(col) {
            if (this.sortColumn !== col) return '';
            return this.sortDirection === 'asc' ? ' ↑' : ' ↓';
        },

        get filteredMentors() {
            const q = this.searchQuery.toLowerCase();
            let list = this.mentors.filter(m => {
                const str = [m.firstName, m.lastName, m.email, m.student_num || '', m.subjectsTable, m.degreeProgram, m.yearLevel].join(' ').toLowerCase();
                return str.includes(q);
            });

            const col = this.sortColumn;
            const dir = this.sortDirection === 'asc' ? 1 : -1;

            return list.sort((a, b) => {
                let valA, valB;
                if (col === 'name') {
                    valA = (a.lastName + ' ' + a.firstName).toLowerCase();
                    valB = (b.lastName + ' ' + b.firstName).toLowerCase();
                } else if (col === 'student_num') {
                    valA = (a.student_num || '').toLowerCase();
                    valB = (b.student_num || '').toLowerCase();
                } else if (col === 'email') {
                    valA = (a.email || '').toLowerCase();
                    valB = (b.email || '').toLowerCase();
                } else {
                    valA = ''; valB = '';
                }
                if (valA < valB) return -1 * dir;
                if (valA > valB) return 1 * dir;
                return 0;
            });
        },

        get paginatedMentors() {
            const start = (this.currentPage - 1) * this.perPage;
            return this.filteredMentors.slice(start, start + this.perPage);
        },

        get pageStart() {
            return this.filteredMentors.length === 0 ? 0 : (this.currentPage - 1) * this.perPage + 1;
        },

        get pageEnd() {
            return Math.min(this.currentPage * this.perPage, this.filteredMentors.length);
        },

        get totalPages() {
            return Math.ceil(this.filteredMentors.length / this.perPage) || 1;
        },

        get pages() {
            const total = this.totalPages;
            const current = this.currentPage;

            if(total <= 8) return Array.from({ length: total }, (_, i) => i + 1);
            if(current <= 4) return [1, 2, 3, 4, 5, '...', total];
            if(current >= total - 3) return [1, '...', total - 3, total - 2, total - 1, total];

            return [1, '...', current - 1, current, current + 1, '...', total];
        },

        convertTime(timeStr) {
            if (!timeStr) return '';
            const [time, modifier] = timeStr.split(' ');
            let [hours, minutes] = time.split(':');
            if (hours === '12') hours = '00';
            if (modifier === 'PM') hours = (parseInt(hours, 10) + 12).toString();
            return `${hours.padStart(2, '0')}:${minutes}`;
        },

        openViewModal(mentor) {
            this.selectedMentor = mentor;
            this.showViewModal = true;
        },

        openEditModal(mentor) {
            this.editingMentor = mentor;
            this.$wire.editMentorFirstName = mentor.firstName;
            this.$wire.editMentorLastName = mentor.lastName;
            this.$wire.editMentorMiddleInitial = mentor.middleInitial ? mentor.middleInitial.replace('.', '').trim() : '';
            this.editForm.subjects = mentor.subjects.map(s => s.id.toString());
            let avails = [];
            for (const day in mentor.schedule) {
                mentor.schedule[day].slots.forEach(slot => {
                    avails.push({
                        id: Date.now() + Math.random(),
                        day_of_week: day.toLowerCase(),
                        start_time: this.convertTime(slot.start),
                        end_time: this.convertTime(slot.end)
                    });
                });
            }
            if (avails.length === 0) {
                avails.push({ id: Date.now() + Math.random(), day_of_week: '', start_time: '', end_time: '' });
            }
            this.editForm.availabilities = avails;

            // Check if there are any new inputs
            this.originalForm = {
                firstName: mentor.firstName,
                lastName: mentor.lastName,
                middleInitial: mentor.middleInitial ? mentor.middleInitial.replace('.', '').trim() : '',
                subjects: [...this.editForm.subjects],
                availabilities: this.editForm.availabilities.map(a => ({
                    day_of_week: a.day_of_week,
                    start_time: a.start_time,
                    end_time: a.end_time,
                }))
            };

            this.showEditModal = true;
            this.$nextTick(() => {
                const scrollBox = document.getElementById('editModalScroll');
                if (scrollBox) scrollBox.scrollTop = 0;
            });
        },

        openDeleteModal(mentor) {
            this.mentorToDelete = mentor;
            this.showDeleteConfirm = true;
        }
    }));

    Alpine.data('mentorTimePicker', () => ({
        timeValue: '',
        open: false,
        hour: 8,
        minute: 0,
        ampm: 'AM',
        selectedTime: '',

        init() {
            // Watch for changes coming from the array (e.g., loading an Edit Modal)
            this.$watch('timeValue', val => {
                if (val && val !== this.getFormattedTime()) {
                    const [h, m] = val.split(':').map(Number);
                    this.ampm   = h >= 12 ? 'PM' : 'AM';
                    this.hour   = h % 12 || 12;
                    this.minute = m;
                    this.updateDisplay();
                }
            });

            // Setup initial display if data is already present
            if (this.timeValue) {
                const [h, m] = this.timeValue.split(':').map(Number);
                this.ampm   = h >= 12 ? 'PM' : 'AM';
                this.hour   = h % 12 || 12;
                this.minute = m;
                this.updateDisplay();
            }
        },

        toggle() {
            if (this.open) { this.close(); return; }
            this.open = true;
            this.$nextTick(() => this.position());
        },

        position() {
            const trigger = this.$el.querySelector('.custom-time-display');
            const drop    = this.$el.querySelector('.time-picker-dropdown');
            if (!trigger || !drop) return;
            const rect  = trigger.getBoundingClientRect();
            const dropH = drop.offsetHeight || 240;
            const dropW = drop.offsetWidth  || 220;

            // Position directly below the input
            drop.style.top  = (rect.bottom + 4) + 'px';
            let left = rect.left;
            if (left + dropW > window.innerWidth - 8) left = window.innerWidth - dropW - 8;
            drop.style.left = Math.max(8, left) + 'px';
            drop.style.position = 'fixed';
        },

        close() { this.open = false; },

        changeHour(dir) { this.hour = ((this.hour - 1 + dir + 12) % 12) + 1; this.syncHourInput(); this.commit(); },
        changeMin(dir)  { this.minute = (this.minute + dir * 15 + 60) % 60; this.syncMinInput(); this.commit(); },
        setAmpm(val)    {
            if (this.ampm === val) return;

            this.ampm = val;

            if (val === 'PM' && this.hour !== 12 && this.hour >= 7) {
                this.hour = 12;
            }

            if (val === 'AM' && (this.hour === 12 || this.hour <= 6)) {
                this.hour = 8;
            }

            this.commit();
        },

        onHourInput(e) {
            let val = parseInt(e.target.value) || 1;
            if (val < 1)  val = 1;
            if (val > 12) val = 12;
            this.hour = val;
            e.target.value = String(val).padStart(2, '0');
            this.commit();
        },

        onMinInput(e) {
            let val = parseInt(e.target.value);
            if (isNaN(val) || val < 0) val = 0;
            if (val > 59) val = 59;
            this.minute = val;
            e.target.value = String(val).padStart(2, '0');
            this.commit();
        },

        syncHourInput() { const el = this.$el.querySelector('.tp-hour-input'); if (el) el.value = String(this.hour).padStart(2, '0'); },
        syncMinInput()  { const el = this.$el.querySelector('.tp-min-input');  if (el) el.value = String(this.minute).padStart(2, '0'); },

        getFormattedTime() {
            let h24 = this.hour % 12;
            if (this.ampm === 'PM') h24 += 12;
            return `${String(h24).padStart(2, '0')}:${String(this.minute).padStart(2, '0')}`;
        },

        clampTime() {
            // Optional clamp, limits bounds to 8 AM to 6 PM
            let h24 = (this.hour % 12) + (this.ampm === 'PM' ? 12 : 0);
            let totalMins = h24 * 60 + this.minute;
            const MIN_MINS = 8 * 60;
            const MAX_MINS = 18 * 60;

            if (totalMins < MIN_MINS) totalMins = MIN_MINS;
            if (totalMins > MAX_MINS) totalMins = MAX_MINS;

            let newH24 = Math.floor(totalMins / 60);
            this.minute = totalMins % 60;
            this.ampm = newH24 >= 12 ? 'PM' : 'AM';
            this.hour = newH24 % 12 || 12;
        },

        commit() {
            this.clampTime();
            this.syncHourInput();
            this.syncMinInput();

            // This automatically updates row.start_time / row.end_time via x-modelable!
            this.timeValue = this.getFormattedTime();
            this.updateDisplay();
        },

        updateDisplay() {
            const h = String(this.hour).padStart(2, '0');
            const m = String(this.minute).padStart(2, '0');
            this.selectedTime = `${h}:${m} ${this.ampm}`;
        },
    }));

    Alpine.data('staffManagement', (initialStaff, wire) => ({
        staff: initialStaff,
        showViewModal: false,
        selectedStaff: null,
        showEditModal: false,
        editingStaff: null,
        editForm: { availabilities: [] },
        originalForm: { availabilities: [] },
        showDeleteConfirm: false,
        staffToDelete: null,
        weekDays: ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'],

        convertTime(timeStr) {
            if (!timeStr) return '';
            const [time, modifier] = timeStr.split(' ');
            let [hours, minutes] = time.split(':');
            if (hours === '12') hours = '00';
            if (modifier === 'PM') hours = (parseInt(hours, 10) + 12).toString();
            return `${hours.padStart(2, '0')}:${minutes}`;
        },

        openViewModal(staff) {
            this.selectedStaff = staff;
            this.showViewModal = true;
        },

        openEditModal(staff) {
            this.editingStaff = staff;
            this.$wire.editFirstName = staff.firstName;
            this.$wire.editLastName = staff.lastName;
            this.$wire.editMiddleInitial = staff.middleInitial ? staff.middleInitial.replace('.', '').trim() : '';
            this.$wire.editEmail = staff.email;
            this.$wire.editRole = staff.role;

            let avails = [];
            for (const day in staff.schedule) {
                staff.schedule[day].slots.forEach(slot => {
                    avails.push({
                        id: Date.now() + Math.random(),
                        day_of_week: day.toLowerCase(),
                        start_time: this.convertTime(slot.start),
                        end_time: this.convertTime(slot.end),
                    });
                });
            }
            if (avails.length === 0) {
                avails.push({ id: Date.now() + Math.random(), day_of_week: '', start_time: '', end_time: '' });
            }

            this.editForm.availabilities = avails;
            this.originalForm = {
                firstName: staff.firstName,
                lastName: staff.lastName,
                middleInitial: staff.middleInitial ? staff.middleInitial.replace('.', '').trim() : '',
                email: staff.email,
                role: staff.role,
                availabilities: avails.map(a => ({
                    day_of_week: a.day_of_week,
                    start_time: a.start_time,
                    end_time: a.end_time,
                })),
            };

            this.showEditModal = true;
        },

        openDeleteModal(staff) {
            this.staffToDelete = staff;
            this.showDeleteConfirm = true;
        },
    }));

});


// Courses table
window.closeConfirmModal = function() {
    document.getElementById('confirmModal').style.display = 'none';
    document.getElementById('confirmOkBtn').onclick = null;
}

window.openConfirmModal = function({ title, body, meta, variant, confirmText, loadingText, onConfirm }) {
    const confirmModal     = document.getElementById('confirmModal');
    const confirmModalBox  = document.getElementById('confirmModalBox');
    const confirmTitle     = document.getElementById('confirmTitle');
    const confirmBody      = document.getElementById('confirmBody');
    const confirmMeta      = document.getElementById('confirmMeta');
    const confirmOkBtn     = document.getElementById('confirmOkBtn');
    const confirmCancelBtn = document.getElementById('confirmCancelBtn');
    const confirmIconWrap  = document.getElementById('confirmIconWrap');

    confirmModal.onclick = (e) => { if (!confirmModalBox.contains(e.target)) closeConfirmModal(); };
    confirmCancelBtn.onclick = closeConfirmModal;

    const variants = {
        accept:  { iconHtml: iconCheck('#059669'), iconBg: '#d1fae5', btnClass: 'bg-emerald-600 hover:bg-emerald-700' },
        edit:    { iconHtml: iconCheck('#2563eb'), iconBg: '#d7e0ff', btnClass: 'bg-blue-600 hover:bg-blue-700'       },
        reject:  { iconHtml: iconX('#dc2626'),     iconBg: '#fee2e2', btnClass: 'bg-red-600 hover:bg-red-700'         },
        neutral: { iconHtml: iconInfo('#64748b'),  iconBg: '#f1f5f9', btnClass: 'bg-gray-700 hover:bg-gray-800'       },
    };
    const v = variants[variant] || variants.neutral;

    confirmIconWrap.style.background = v.iconBg;
    confirmIconWrap.innerHTML        = v.iconHtml;
    confirmTitle.textContent         = title;
    confirmBody.innerHTML            = body;
    confirmMeta.innerHTML            = meta || '';
    confirmMeta.style.display        = meta ? 'block' : 'none';

    confirmOkBtn.className   = `px-4 py-2 text-sm font-medium text-white rounded-lg transition-colors ${v.btnClass}`;
    confirmOkBtn.textContent = confirmText || 'Confirm';

    confirmOkBtn.onclick = async () => {
        const originalText = confirmOkBtn.textContent;
        // Turn button into a loading spinner
        confirmOkBtn.innerHTML = `<i class="fa-solid fa-spinner fa-spin mr-2"></i>${loadingText || 'Processing...'}`;
        confirmOkBtn.classList.add('opacity-70', 'cursor-not-allowed');
        confirmOkBtn.style.pointerEvents = 'none';

        confirmCancelBtn.disabled = true;
        confirmCancelBtn.classList.add('opacity-50', 'cursor-not-allowed');

        try {
            const result = onConfirm();
            if (result && typeof result.then === 'function') await result;
        } catch (err) {
            // Revert state if something fails
            console.error("Action failed:", err);
            alert("Something went wrong. Please try again.");
        } finally {
            // Restore button state
            confirmOkBtn.textContent = originalText;
            confirmOkBtn.classList.remove('opacity-70', 'cursor-not-allowed');
            confirmOkBtn.style.pointerEvents = 'auto';
            confirmCancelBtn.disabled = false;
            confirmCancelBtn.classList.remove('opacity-50', 'cursor-not-allowed');
            closeConfirmModal();
        }
    };

    // Make the modal visible
    confirmModal.style.display = 'flex';
}

function iconCheck(color) { return `<svg width="18" height="18" viewBox="0 0 20 20" fill="none"><path d="M4 10l4.5 4.5L16 6" stroke="${color}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>`; }
function iconX(color)     { return `<svg width="18" height="18" viewBox="0 0 20 20" fill="none"><path d="M5 5l10 10M15 5L5 15" stroke="${color}" stroke-width="2" stroke-linecap="round"/></svg>`; }
function iconInfo(color)  { return `<svg width="18" height="18" viewBox="0 0 20 20" fill="none"><circle cx="10" cy="10" r="8.5" stroke="${color}" stroke-width="1.5"/><path d="M10 9v5" stroke="${color}" stroke-width="1.5" stroke-linecap="round"/><circle cx="10" cy="6.5" r="0.8" fill="${color}"/></svg>`; }

/* ── Alpine Component Registration ── */
document.addEventListener('alpine:init', () => {
    Alpine.data('courseManagement', (initialSubjects) => ({
        subjects: initialSubjects,
        searchQuery: '',
        mentorFilter: [],
        sortColumn: 'code',
        sortDirection: 'asc',
        currentPage: 1,
        perPage: 5,
        profileOpen: false,
        sidebarCollapsed: false,
        showViewModal: false,
        showSubjectModal: false,
        selectedSubject: null,
        showEditModal: false,
        editingSubject: null,
editForm: { code: '', name: '' },
originalForm: { code: '', name: '' },
showDeleteConfirm: false,
subjectToDelete: null,
deletingSubjectId: null,

init() {
            window.addEventListener('mentor-filter-changed', (e) => {
                this.mentorFilter = e.detail;
                this.currentPage = 1;
            });
            window.addEventListener('click', () => {
                this.profileOpen = false;
                document.getElementById('mentorDropdown')?.classList.add('hidden');
            });
        },

        setSort(column) {
            if (this.sortColumn === column) {
                this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
            } else {
                this.sortColumn = column;
                this.sortDirection = 'asc';
            }
            this.currentPage = 1;
        },

        get filteredSubjects() {
            const term = this.searchQuery.toLowerCase();
            let result = this.subjects.filter(s => {
                const matchSearch = (s.code + ' ' + s.name).toLowerCase().includes(term);
                const matchFilter = this.mentorFilter.length === 0
                    || (this.mentorFilter.includes('with_mentors') && s.mentorCount > 0)
                    || (this.mentorFilter.includes('no_mentors')   && s.mentorCount === 0);
                return matchSearch && matchFilter;
            });

            result = [...result].sort((a, b) => {
                let valA = a[this.sortColumn];
                let valB = b[this.sortColumn];
                if (typeof valA === 'string') { valA = valA.toLowerCase(); valB = valB.toLowerCase(); }
                if (valA < valB) return this.sortDirection === 'asc' ? -1 : 1;
                if (valA > valB) return this.sortDirection === 'asc' ?  1 : -1;
                return 0;
            });
            return result;
        },

        get paginatedSubjects() {
            const start = (this.currentPage - 1) * this.perPage;
            return this.filteredSubjects.slice(start, start + this.perPage);
        },

        get pageStart() {
            return this.filteredSubjects.length === 0 ? 0 : (this.currentPage - 1) * this.perPage + 1;
        },

        get pageEnd() {
            return Math.min(this.currentPage * this.perPage, this.filteredSubjects.length);
        },

        get totalPages() {
            return Math.max(1, Math.ceil(this.filteredSubjects.length / this.perPage));
        },

        get pages() {
            const total   = this.totalPages;
            const current = this.currentPage;
            if (total <= 8) return Array.from({ length: total }, (_, i) => i + 1);
            if (current <= 4) return [1, 2, 3, 4, 5, '...', total];
            if (current >= total - 3) return [1, '...', total - 3, total - 2, total - 1, total];
            return [1, '...', current - 1, current, current + 1, '...', total];
        },

        openViewModal(sub) {
            this.selectedSubject = sub;
            this.showViewModal = true;
        },

        openEditModal(sub) {
            this.editingSubject = sub;
            this.editForm.code  = sub.code;
            this.editForm.name  = sub.name;
            this.originalForm = {
                code: this.editForm.code,
                name: this.editForm.name
            };
            this.showEditModal  = true;
        },

openDeleteModal(sub) {
    this.subjectToDelete = sub;
    this.showDeleteConfirm = true;
    this.deletingSubjectId = sub.id;
    this.$watch('showDeleteConfirm', val => {
        if (!val) this.deletingSubjectId = null;
    });
},
    }));
});
