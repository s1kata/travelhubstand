# Сборка CSS

По умолчанию страницы могут использовать Tailwind CDN. Для продакшена предпочтительнее собранный файл — меньше JS и быстрее первый рендер.

```bash
npm install
npm run build:css
```

Результат: `frontend/css/tailwind.min.css` (~80 KB).

Если файл существует, шаблоны подключают его вместо CDN (см. `backend/components/design_system_head.php`).

Запускайте перед деплоем или в CI при изменении Tailwind-классов в разметке.
