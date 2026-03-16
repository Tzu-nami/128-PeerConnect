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
