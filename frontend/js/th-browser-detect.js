/**
 * Синхронный детект браузера/платформы — до paint CSS.
 * Классы на <html>: th-is-mobile, th-yandex, th-yandex-mobile,
 * th-browser-{yandex|safari|chrome|firefox|samsung|edge|other}
 */
(function () {
  var u = navigator.userAgent || '';
  var root = document.documentElement;
  var mob = false;
  try { mob = window.matchMedia('(max-width:767.98px)').matches; } catch (e) {}
  if (!mob) mob = /Mobile|Android|iPhone|iPod|iPad|Opera Mini|IEMobile/i.test(u);

  var ya = /YaBrowser|YaSearchBrowser|YandexSearch|YandexMobile|Yowser/i.test(u);
  var samsung = /SamsungBrowser/i.test(u);
  var firefox = /Firefox|FxiOS/i.test(u);
  var edge = /Edg\//i.test(u);
  var chrome = !edge && !firefox && !ya && /Chrome|CriOS/i.test(u);
  var safari = !chrome && !firefox && !ya && !samsung && /Safari/i.test(u);

  var browser = 'other';
  if (ya) browser = 'yandex';
  else if (samsung) browser = 'samsung';
  else if (firefox) browser = 'firefox';
  else if (edge) browser = 'edge';
  else if (chrome) browser = 'chrome';
  else if (safari) browser = 'safari';

  root.classList.toggle('th-is-mobile', mob);
  root.classList.toggle('th-yandex', ya);
  root.classList.toggle('th-yandex-mobile', ya && mob);
  root.classList.add('th-browser-' + browser);
  root.setAttribute('data-th-browser', browser);
})();
