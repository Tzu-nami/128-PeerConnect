import './bootstrap';

import Swiper from 'swiper';
import { Pagination } from 'swiper/modules';

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
        640: {
            slidesPerView: 2,
            initialSlide: 1,
        },
        1024: {
            slidesPerView: 3,
            initialSlide: 2,
        },
    },
});

document.getElementById('btn-prev').addEventListener('click', () => swiper.slidePrev());
document.getElementById('btn-next').addEventListener('click', () => swiper.slideNext());

document.addEventListener('DOMContentLoaded', () => {
    const sidebar       = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');

    if (sidebar && sidebarToggle) {
        sidebarToggle.addEventListener('click', () => {
            sidebar.classList.toggle('collapsed');

            setTimeout(() => {
                if (window.__dashboardCharts) {
                    window.__dashboardCharts.forEach(c => c.resize());
                }
            }, 310);
        });
    }

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

if (history.scrollRestoration) {
    history.scrollRestoration = 'manual';
}
