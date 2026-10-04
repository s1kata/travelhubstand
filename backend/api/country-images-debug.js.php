<?php
header('Content-Type: application/javascript; charset=utf-8');
header('Cache-Control: no-store, no-cache');
// Тот же origin и схема, что у страницы — без mixed content (не собирать http:// из $_SERVER за прокси).
?>
(function(){
  var api = '/backend/api/countries-with-images.php';
  console.log('[Country Images Debug] Fetching:', api);
  fetch(api)
    .then(function(r){ return r.text().then(function(t){ return {status: r.status, ok: r.ok, len: t.length, data: t}; }); })
    .then(function(o){
      console.log('[Country Images Debug] Response:', o.status, 'len:', o.len);
      if (!o.ok) { console.warn('[Country Images Debug] HTTP error:', o); return; }
      try {
        var d = JSON.parse(o.data);
        var countries = d.countries || [];
        var withImg = countries.filter(function(c){ return (c.images || []).length > 0; });
        console.log('[Country Images] countries:', countries.length, '| with_images:', withImg.length, '| slugs:', withImg.map(function(c){return c.slug;}));
        if (withImg.length === 0 && countries.length > 0) console.warn('[Country Images] No countries have images - check API/cache');
      } catch(e) { console.warn('[Country Images] Parse error:', e); }
    })
    .catch(function(e){ console.warn('[Country Images] Fetch error:', e); });
})();
