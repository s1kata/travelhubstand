/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    './frontend/**/*.php',
    './frontend/**/*.html',
    './backend/**/*.php',
    './backend/components/**/*.php'
  ],
  theme: {
    extend: {
      fontFamily: {
        sans: ['system-ui', '-apple-system', 'BlinkMacSystemFont', 'Segoe UI', 'Roboto', 'sans-serif']
      }
    }
  },
  plugins: []
}
