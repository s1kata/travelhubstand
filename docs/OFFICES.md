# Офисы и фото

## Каталог офисов

Конфигурация: `backend/config/offices_catalog.php`  
Привязка slug → папка фото: `backend/config/office_photo_folders.php`

| Slug / папка | Офис |
|--------------|------|
| `samara/fun-sun` | Fun&Sun — ТЦ «Парк Хаус» |
| `samara/fun-sun-gudok` | Fun&Sun — ТЦ «Гудок» |
| `samara/anex-tour-moskovskoe-81b` | Anex Tour — Московское шоссе |
| `samara/anex-apelsin` | Anex Tour — ТЦ «Апельсин» |
| `samara/coral-travel` | Coral Travel — ТЦ «Эль Рио» |
| `moscow/coral-elite-service` | Coral Elite Service |
| `moscow/anex-tour` | Anex Tour — Москва |

## Где лежат фото

**Единственный источник для сайта и админки:**

```
frontend/window/img/offices/{город}/{slug}/
```

- Обложка карточки и hero — файл `01-cover.*` в папке офиса.
- Дополнительные фото — любые имена; сортировка: `01-cover` → `*-01` → по имени.
- Загрузка через админку: `backend/admin/manage-office-photos.php`

Код: `backend/components/office_folder_photos.php`

## Legacy

- Папка `img/offices/` в корне репозитория **не используется** фронтом.
- Перенос старых файлов: `php backend/scripts/normalize_office_photos.php`
- Не использовать `samara/anex-tour` (legacy, не привязан к офису в каталоге).
- Не класть новые фото в `samara/*.jpg` — это старый flat-каталог для `samara.php`.

## После смены фото

Достаточно положить файлы в нужную папку slug и задеплоить. Поле `fallback_slug` в каталоге не используется — обложка берётся только из папки офиса.
