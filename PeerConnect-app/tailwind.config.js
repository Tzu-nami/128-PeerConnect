import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                heading: ['Marcellus', 'serif'],
                body: ['Nunito', 'sans-serif'],
            },
            colors: {
                'up-maroon':     '#7B1113',
                'up-maroon-dark':'#5A0D0F',
                'up-green':      '#014421',
                'up-yellow':     '#F3AA2C',
                'up-yellow-light':'#F8CB72',
                'up-black':      '#000000',
                'cream':         '#FAF8F2',
                'cream-dark':    '#EEE8DA',
                'text-brown':    '#5A3C3E',
            },
        },
    },

    plugins: [forms],
};
