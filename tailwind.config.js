/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./src/Views/**/*.php",
    "./public/assets/js/**/*.js"
  ],
  theme: {
    extend: {
      colors: {
        tea: {
          50: '#f2f7f4',
          100: '#dfece3',
          200: '#c0d9c8',
          300: '#94bea2',
          400: '#649d79',
          500: '#3f7f58',
          600: '#2d6a4f',
          700: '#245540',
          800: '#1e4434',
          900: '#18372b'
        },
        cream: {
          50: '#fdfbf7',
          100: '#f8f3e9',
          200: '#efe5d0'
        },
        amberwarm: {
          50: '#fff8eb',
          100: '#fdecc8',
          400: '#e9a83b',
          500: '#d98c1c',
          600: '#b86f12'
        }
      },
      fontFamily: {
        sans: ['Inter', 'Noto Sans Bengali', 'system-ui', '-apple-system', 'BlinkMacSystemFont', 'Segoe UI', 'Roboto', 'sans-serif']
      }
    }
  },
  plugins: []
};
