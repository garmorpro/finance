/** @type {import('tailwindcss').Config} */
module.exports = {
  content: ['./resources/views/**/*.php', './public/**/*.php'],
  darkMode: 'media',
  theme: {
    extend: {
      colors: {
        terracotta: {
          50: '#fdf4f2',
          100: '#fbe4dc',
          200: '#f7c9b9',
          300: '#f0a488',
          400: '#e88562',
          500: '#e2694b',
          600: '#c94f32',
          700: '#a53d27',
          800: '#832f1e',
          900: '#692817',
          950: '#38130a',
        },
      },
      fontFamily: {
        sans: ['system-ui', '-apple-system', 'BlinkMacSystemFont', 'Segoe UI', 'sans-serif'],
      },
    },
  },
  plugins: [require('@tailwindcss/forms')],
};
