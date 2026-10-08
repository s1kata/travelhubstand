# SEO

## Ключевые файлы

| Файл | Назначение |
|------|------------|
| `robots.txt` | Правила для роботов, ссылка на sitemap |
| `frontend/window/sitemap.php` | Динамическая карта сайта |
| `.htaccess` | Редиректы, canonical-хост, кэш и безопасность |
| `backend/components/seo_meta.php` | Meta title, description, OG, Twitter |
| `backend/components/schema_org.php` | JSON-LD (Organization, WebSite, BreadcrumbList и др.) |

## На страницах

- Уникальные `title` и `description` через SEO-компоненты.
- Open Graph и Twitter Card для шаринга.
- Canonical URL — без дублей с www/без www (см. `.htaccess`).
- Schema.org: TravelAgency на главной, Place на страницах направлений, ItemList для списков.

## Юридические URL в sitemap

После обновления legal-страниц проверьте наличие в `sitemap.php`:

- `/frontend/window/consent.php`
- `/frontend/window/privacy.php`
- `/frontend/window/terms.php`

## Изображения

- Alt-тексты на контентных картинках.
- Lazy loading (`loading="lazy"`) для некритичных блоков.
- Hero — `fetchpriority="high"` где задано в разметке.

## После крупных изменений

1. Открыть `https://travelhub63.ru/sitemap.php` (или URL из `robots.txt`).
2. Переотправить sitemap в Яндекс.Вебмастер / Google Search Console.
3. Проверить rich results для главной и 2–3 страниц стран.
