import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './app/Livewire/**/*.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                heading: ['Marcellus', 'serif'],
                body: ['Nunito', 'sans-serif'],
            },
            colors: {
                // Up Color Palette
                'up-maroon':     '#7B1113',
                'up-maroon-dark':'#5A0D0F',
                'up-green':      '#014421',
                'up-yellow':     '#F3AA2C',
                'up-yellow-light':'#F8CB72',
                'up-yellow-dark':'#C28212',
                'up-black':      '#000000',

                // Others
                'cream':         '#FAF8F2',
                'cream-dark':    '#EEE8DA',
                'cream-border':  '#D6CFC0',
                'text-brown':    '#554444',
                'text-brown-light': '#998888',
                'sidebar-green':  '#1A3C2F'
            },
            keyframes: {
                fadeUp: {
                    '0%':   { opacity: '0', transform: 'translateY(20px)' },
                    '100%': { opacity: '1', transform: 'translateY(0)' },
                },
            },
            animation: {
                'fade-up': 'fadeUp 0.6s ease both',
            },
        },
    },

    plugins: [forms],
};
