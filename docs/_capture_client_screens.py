# -*- coding: utf-8 -*-
from pathlib import Path
from playwright.sync_api import sync_playwright

out = Path(r"d:\работа\travelhub-v2\docs\client-report-screens")
out.mkdir(parents=True, exist_ok=True)

pages = [
    ("01-home-mobile.png", "https://travel63test.ru/frontend/index.php", 390, 844, "scroll_hotels"),
    ("02-home-desktop.png", "https://travel63test.ru/frontend/index.php", 1280, 900, "scroll_hotels"),
    ("03-popular-hotels-mobile.png", "https://travel63test.ru/frontend/window/popular-hotels.php", 390, 844, None),
    ("04-popular-hotels-desktop.png", "https://travel63test.ru/frontend/window/popular-hotels.php", 1280, 900, None),
    ("05-calendar-mobile.png", "https://travel63test.ru/frontend/window/tour-calendar.php", 390, 844, None),
    ("06-vip-mobile.png", "https://travel63test.ru/frontend/window/turkey-vip-hotels.php", 390, 844, None),
    ("07-promotions-mobile.png", "https://travel63test.ru/frontend/window/promotions.php", 390, 844, None),
    ("08-home-search-mobile.png", "https://travel63test.ru/frontend/index.php", 390, 844, "top"),
]


def dismiss_cookies(page):
    for label in ("Принять", "Accept"):
        loc = page.get_by_role("button", name=label)
        if loc.count():
            try:
                loc.first.click(timeout=1500)
                page.wait_for_timeout(400)
            except Exception:
                pass


with sync_playwright() as p:
    browser = p.chromium.launch(headless=True)
    for name, url, w, h, action in pages:
        context = browser.new_context(viewport={"width": w, "height": h}, device_scale_factor=2)
        page = context.new_page()
        page.goto(url, wait_until="domcontentloaded", timeout=90000)
        page.wait_for_timeout(2800)
        dismiss_cookies(page)
        if action == "scroll_hotels":
            page.evaluate(
                """() => {
              const el = document.querySelector('.th-hh, .th-home-hotels, #home-hotels-heading');
              if (el) el.scrollIntoView({block:'center'});
            }"""
            )
            page.wait_for_timeout(900)
        page.screenshot(path=str(out / name), full_page=False)
        print("saved", name)
        context.close()

    context = browser.new_context(viewport={"width": 390, "height": 844}, device_scale_factor=2)
    page = context.new_page()
    page.goto(
        "https://travel63test.ru/frontend/window/popular-hotels.php",
        wait_until="domcontentloaded",
        timeout=90000,
    )
    page.wait_for_timeout(8000)
    dismiss_cookies(page)
    link = page.locator("a.ph-card, .ph-grid a").first
    if link.count():
        href = link.get_attribute("href") or ""
        print("hotel href", href)
        if href:
            full = href if href.startswith("http") else "https://travel63test.ru" + href
            page.goto(full, wait_until="domcontentloaded", timeout=90000)
            page.wait_for_timeout(5500)
            dismiss_cookies(page)
            page.screenshot(path=str(out / "09-hotel-detail-mobile.png"), full_page=False)
            page.evaluate(
                """() => {
              const t = document.querySelector('.thh-offers, #thh-offers, .th-tour-card, .thh-sticky');
              if (t) t.scrollIntoView({block:'center'});
            }"""
            )
            page.wait_for_timeout(1200)
            page.screenshot(path=str(out / "10-hotel-tours-mobile.png"), full_page=False)
            print("saved hotel detail")
    context.close()
    browser.close()

print("done")
