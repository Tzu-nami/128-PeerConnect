import './bootstrap';

import Swiper from 'swiper';
import { Navigation, Pagination } from 'swiper/modules';

const swiper = new Swiper('.swiper', {
    modules: [Navigation, Pagination],

    loop: true,
    centeredSlides: true,
    slidesPerView: 3,
    spaceBetween: 15,
    initialSlide: 2,
    watchSlidesProgress: true,

    pagination: {
        el: '.swiper-pagination',
        clickable: true,
    },

    navigation: {
        nextEl: '.swiper-button-next',
        prevEl: '.swiper-button-prev',
    },
});

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
