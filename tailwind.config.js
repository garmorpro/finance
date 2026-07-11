/** @type {import('tailwindcss').Config} */
module.exports = {
  content: ['./resources/views/**/*.php', './public/**/*.php'],
  darkMode: 'media',
  theme: {
    extend: {
      colors: {
        surface: {
          light: '#ffffff',
          dark: '#0f172a',
        },
      },
      fontFamily: {
        sans: ['system-ui', '-apple-system', 'BlinkMacSystemFont', 'Segoe UI', 'sans-serif'],
      },
    },
  },
  plugins: [require('@tailwindcss/forms')],
};
