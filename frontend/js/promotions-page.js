/**
 * Акции (promotions.php): загрузка данных с существующих эндпоинтов прокси/API.
 * Конфиг задаётся в window.__TH_PROMO_PAGE__ (см. promotions.php). Логика запросов и отображения без изменений относительно прежнего inline-скрипта.
 */
(function () {
    'use strict';

    var cfg = window.__TH_PROMO_PAGE__;
    if (!cfg || !cfg.step) {
        return;
    }

    var TH_FEATURE_PROMO_DIRECT = !!(cfg && cfg.thFeaturePromoDirectFlightsThVn);
    var TH_FEATURE_VN_NEAREST = !!(cfg && cfg.thFeaturePromoVietnamNearestFallback);
    var YM_ID = (cfg.ymId && String(cfg.ymId).replace(/\D/g, '')) ? parseInt(String(cfg.ymId).replace(/\D/g, ''), 10) : 0;
    /** Основной хелпер Метрики (использует ID из конфига PHP). */
    function thYmReachGoal(goalName) {
        try {
            if (YM_ID && typeof ym === 'function') ym(YM_ID, 'reachGoal', goalName);
        } catch (eYm) {}
    }
    /** Хелпер с резервным ID 109291068 (дефолтный счётчик; дублирует в YM_ID из конфига, если отличается). */
    function promoYm(goalName) {
        try { if (typeof ym === 'function') ym(109291068, 'reachGoal', goalName); } catch (_) {}
        /* Дублируем и в основной счётчик, если он задан и отличается */
        if (YM_ID && YM_ID !== 109291068) thYmReachGoal(goalName);
    }

    /** Абхазия/Россия: без UI и без отсечения по звёздам (см. promotions.php). */
    var PROMO_NO_STAR_FILTERS = !!cfg.promoNoStarFilters;
    /** false: везде promo-search → файл data/promo_cache_*.json; при промахе сервер делает live search и пишет кэш. */
    var PROMO_LIVE_ONLY = false;
    /** Пока грузятся туры выбранной страны — не дергаем promo-search для плиток (лимит Tourvisor). */
    function promoSetPrefetchPaused(on) {
        window.__promoPrefetchPaused = !!on;
    }

    /**
     * Окно поиска ГОРЯЩИХ туров (onlyPromo=1).
     * Акции = ближайшие 7 дней, чтобы не пересекаться с обычными турами на странице «Страны».
     * Страны = обычный поиск без onlyPromo, dateFrom=сегодня, dateTo=+30 дней.
     * При изменении дат — синхронизировать: promo_tours_refresh, tourvisor_promo_sync, yandex_feed_sync.
     * Диапазон ночей: клиент шлёт несколько запросов (PROMO_NIGHT_WINDOWS), т.к. Tourvisor принимает max 10 ночей за вызов.
     */
    var PROMO_DATE_PLUS_FROM = 0;   /* сегодня: настоящие горящие туры */
    var PROMO_DATE_PLUS_TO   = 7;   /* до +7 дней — только реальные горящие (было 60) */
    /** Вьетнам (id 16): шире окно дат — иначе мало «горящих» вылетов. */
    var PROMO_COUNTRY_ID_VIETNAM = '16';
    var PROMO_COUNTRY_ID_THAILAND = '2';
    var PROMO_COUNTRY_ID_SRI_LANKA = '12';
    /** Плитка «Сочи» = Tourvisor countryId 47 (Россия); в выдаче только курорты Сочи. */
    var PROMO_COUNTRY_ID_SOCHI = '47';
    /** Плитка «Фукуок» = виртуальный id 16104 (Вьетнам 16 + region 104). */
    var PROMO_COUNTRY_ID_PHUQUOC = '16104';
    var PROMO_VIRTUAL_DESTINATIONS = (cfg && cfg.promoVirtualDestinations && typeof cfg.promoVirtualDestinations === 'object')
        ? cfg.promoVirtualDestinations : {};
    var PROMO_COUNTRY_ID_MALDIVES = '8';
    /**
     * Tourvisor: за один запрос не более 10 ночей в диапазоне. Раньше 7–14 обрезало страны с другой длительностью.
     * Три окна покрывают 1–28 ночей без «фильтра по ночам» в смысле одного узкого диапазона.
     */
    var PROMO_NIGHT_WINDOWS = [[1, 11], [12, 22], [23, 28]];
    var PROMO_COUNTRY_IDS_TR = ['4'];
    var PROMO_COUNTRY_IDS_EG = ['1', '13'];
    /** Меньше этого числа — добираем обычный поиск (акции = min price + бейдж). */
    var PROMO_BLEND_MIN_HOTELS = 8;

    function isPromoTrOrEg(countryId) {
        var s = String(countryId != null ? countryId : (typeof COUNTRY_ID !== 'undefined' ? COUNTRY_ID : ''));
        return PROMO_COUNTRY_IDS_TR.indexOf(s) >= 0 || PROMO_COUNTRY_IDS_EG.indexOf(s) >= 0;
    }

    function isPromoTurkeyCountry(countryId) {
        return PROMO_COUNTRY_IDS_TR.indexOf(String(countryId != null ? countryId : '')) >= 0;
    }

    function isPromoEgyptCountry(countryId) {
        return PROMO_COUNTRY_IDS_EG.indexOf(String(countryId != null ? countryId : '')) >= 0;
    }

    /** Турция/Египет: ночи 6–13; на карточке — min price среди прошедших фильтр туров. */
    function promoTrEgNightsRange(countryId) {
        return isPromoTrOrEg(countryId) ? { min: 6, max: 13 } : null;
    }

    function promoMinNightsForCountry(countryId) {
        if (isPromoTrOrEg(countryId)) return 6;
        if (String(countryId) === PROMO_COUNTRY_ID_SOCHI) return 5;
        return 0;
    }

    function promoMaxNightsForCountry(countryId) {
        if (isPromoTrOrEg(countryId)) return 13;
        if (String(countryId) === PROMO_COUNTRY_ID_SOCHI) return 14;
        return 0;
    }

    function promoNightWindowsForCountry(countryId) {
        if (isPromoTrOrEg(countryId)) return [[6, 13]];
        if (String(countryId) === PROMO_COUNTRY_ID_SOCHI) return [[5, 14]];
        if (isVietnamPromoCountry(countryId) || isPhuQuocPromoCountry(countryId)) return [[6, 14], [1, 11], [15, 21]];
        return PROMO_NIGHT_WINDOWS;
    }

    function promoFilterHotelsMinNights(hotels, countryId) {
        var minN = promoMinNightsForCountry(countryId);
        var maxN = promoMaxNightsForCountry(countryId);
        if (!minN || !hotels || !hotels.length) return hotels;
        return hotels.map(function (h) {
            var tours = (h.tours || []).filter(function (t) {
                var n = parseInt(String(t.nights || ''), 10);
                if (!n) return true;
                if (n < minN) return false;
                if (maxN && n > maxN) return false;
                return true;
            });
            return tours.length ? Object.assign({}, h, { tours: tours }) : null;
        }).filter(Boolean);
    }

    function promoFilterHotelsTrEgStars(hotels, countryId) {
        if (!isPromoTrOrEg(countryId) || !hotels || !hotels.length) return hotels;
        var minStars = isPromoTurkeyCountry(countryId) ? 4 : (isPromoEgyptCountry(countryId) ? 3 : 0);
        if (minStars <= 0) return hotels;
        var out = hotels.filter(function (h) {
            var stars = getHotelStarCategory(h);
            return stars !== null && stars >= minStars && stars <= 5;
        });
        out.sort(function (a, b) { return promoHotelListPrice(a) - promoHotelListPrice(b); });
        return out;
    }

    function promoCountHotelsInStarRange(hotels, minStars, maxStars) {
        var n = 0;
        if (!hotels || !hotels.length) return 0;
        for (var i = 0; i < hotels.length; i++) {
            var c = getHotelStarCategory(hotels[i]);
            if (c !== null && c >= minStars && c <= maxStars) n++;
        }
        return n;
    }

    /**
     * После postProcess пусто, но API вернул отели — для Турции/Египта не показываем «сырые» 3★
     * (раньше оставляли только promoFilterHotelsWithTours и на экране были одни тройки).
     */
    function promoCoerceHotelsAfterPostProcess(apiData, countryId) {
        var raw = Array.isArray(apiData) ? apiData : [];
        if (!raw.length) return [];
        var list = promoPostProcessHotelList(raw, countryId);
        if (list.length) return list;
        if (isPromoTrOrEg(countryId)) return [];
        return promoFilterHotelsWithTours(raw);
    }

    /** Добор 4★/5★ (Турция) и 5★ (Египет) через search-cached — как на бэкенде в promo_finalize. */
    function promoApplyTrEgStarBoostFetch(list, countryId) {
        if (!isPromoTrOrEg(countryId)) return Promise.resolve(list);
        list = Array.isArray(list) ? list : [];
        var needBoost = false;
        if (isPromoTurkeyCountry(countryId)) {
            needBoost = promoCountHotelsInStarRange(list, 4, 5) < PROMO_BLEND_MIN_HOTELS;
        } else if (isPromoEgyptCountry(countryId)) {
            needBoost = promoCountHotelsInStarRange(list, 5, 5) < 6;
        }
        if (!needBoost) return Promise.resolve(list);
        var primaryDep = String(DEPARTURE_ID || DEFAULT_DEPARTURE_ID || '7');
        var cats = isPromoTurkeyCountry(countryId) ? [4, 5] : (isPromoEgyptCountry(countryId) ? [5] : []);
        if (!cats.length) return Promise.resolve(list);
        var windows = promoNightWindowsForCountry(countryId);
        var urls = [];
        cats.forEach(function (cat) {
            windows.forEach(function (w) {
                urls.push(promoRegularSearchUrlForCountry(countryId, w[0], w[1], primaryDep, {
                    preferCache: true,
                    hotelCategory: cat
                }));
            });
        });
        return Promise.all(urls.map(function (url) {
            return fetch(url, { method: 'GET', cache: 'no-store' })
                .then(function (r) { return r.text().then(function (t) { return { ok: r.ok, text: t }; }); })
                .then(parseTourvisorSearchJsonResponse)
                .catch(function () { return { success: false, data: [] }; });
        })).then(function (results) {
            var arrays = results.map(function (x) {
                return (x && x.success && Array.isArray(x.data)) ? x.data : [];
            });
            var boosted = mergePromoHotelDataArrays([list].concat(arrays), String(countryId));
            boosted.sort(function (a, b) { return promoHotelListPrice(a) - promoHotelListPrice(b); });
            return boosted.length ? boosted : list;
        }).catch(function () { return list; });
    }

    function promoDatePlusTo(countryId) {
        var s = String(countryId != null ? countryId : '');
        if (isVietnamPromoCountry(s) || isPhuQuocPromoCountry(s) || s === PROMO_COUNTRY_ID_THAILAND || isSochiPromoCountry(s) || s === PROMO_COUNTRY_ID_MALDIVES) return 21;
        if (s === '9' || s === '12' || s === '46') return 21;
        if (isPromoTrOrEg(s)) return 21;
        return PROMO_DATE_PLUS_TO;
    }

    function promoDepartureNameForFlights() {
        return DEPARTURE_NAME || DEFAULT_DEPARTURE_NAME || 'Самара';
    }

    function promoDepartureIdForFlights() {
        return DEPARTURE_ID || DEFAULT_DEPARTURE_ID || 7;
    }

    function promoClearFlightsCache() {
        window.__promoFlightsByTourId = {};
        window.__thFlightsByTourId = {};
        window.__thFlightsLoadGen = (window.__thFlightsLoadGen || 0) + 1;
    }

    function promoFlightsCacheGet(tourId) {
        if (!tourId) return null;
        var depNm = promoDepartureNameForFlights();
        if (typeof thFlightsCacheGet === 'function') {
            return thFlightsCacheGet(tourId, depNm);
        }
        var key = String(tourId) + '@' + String(depNm || '').trim().toLowerCase();
        return (window.__promoFlightsByTourId && window.__promoFlightsByTourId[key]) || null;
    }

    /** Прогретые рейсы из promo_cache_* (cron) → клиентский кэш карточек. */
    function promoApplyFlightsFromSearchResponse(j) {
        if (!j || !j.flightsByTourId || typeof j.flightsByTourId !== 'object') return;
        var depNm = promoDepartureNameForFlights();
        var depIdFl = promoDepartureIdForFlights();
        Object.keys(j.flightsByTourId).forEach(function (tourId) {
            var json = j.flightsByTourId[tourId];
            if (!tourId || !json) return;
            try {
                if (typeof thFlightsCacheFromJson === 'function') {
                    thFlightsCacheFromJson(tourId, json, depNm, depIdFl);
                }
            } catch (eFl) {}
        });
    }

    function isThailandPromoCountry(cid) {
        return String(cid != null ? cid : '') === PROMO_COUNTRY_ID_THAILAND;
    }

    /** Карточки «Акция» для подставленных обычных туров (Таиланд, Мальдивы, Сочи, Фукуок/VN). */
    function promoMarkHotelsForPromoDisplay(hotels) {
        return (hotels || []).map(function (h) {
            if (!h) return h;
            var copy;
            try {
                copy = JSON.parse(JSON.stringify(h));
            } catch (e) {
                copy = h;
            }
            copy.__promoShowBadge = true;
            if (Array.isArray(copy.tours)) {
                copy.tours = copy.tours.map(function (t) {
                    if (!t || typeof t !== 'object') return t;
                    return Object.assign({}, t, { isPromo: true });
                });
            }
            return copy;
        });
    }

    function promoShouldMarkPromoDisplayBadge(countryId) {
        return promoAlwaysBlendRegularWithPromo(countryId)
            || isThailandPromoCountry(countryId)
            || isPhuQuocPromoCountry(countryId)
            || isVietnamPromoCountry(countryId);
    }

    /** Финальная обработка: метка «Акция»; regular-догрузка — если мало туров или promo-search пустой. */
    function promoFinalizeTourResults(data, countryId, opts) {
        opts = opts || {};
        var list = Array.isArray(data) ? data.slice() : [];
        return promoApplyTrEgStarBoostFetch(list, countryId).then(function (listBoosted) {
        var chain;
        var needBlend = listBoosted.length < PROMO_BLEND_MIN_HOTELS
            && (promoAlwaysBlendRegularWithPromo(countryId) || usesNearestPromoFallback(countryId));
        if (listBoosted.length >= PROMO_BLEND_MIN_HOTELS) {
            chain = Promise.resolve(listBoosted);
        } else if (needBlend) {
            chain = applyNearestFallbackIfNeeded(listBoosted, countryId);
        } else if (listBoosted.length > 0) {
            chain = Promise.resolve(listBoosted);
        } else if (usesNearestPromoFallback(countryId)) {
            chain = applyNearestFallbackIfNeeded(listBoosted, countryId);
        } else {
            chain = Promise.resolve(listBoosted);
        }
        return chain.then(function (out) {
            var prepared = promoPostProcessHotelList(Array.isArray(out) ? out : [], countryId);
            if (promoShouldMarkPromoDisplayBadge(countryId)) {
                prepared = promoMarkHotelsForPromoDisplay(prepared);
            }
            return filterDirectFlightsPromoIfNeeded(prepared, countryId);
        });
        });
    }

    function isVietnamPromoCountry(cid) {
        var s = String(cid != null ? cid : '');
        return s === PROMO_COUNTRY_ID_VIETNAM || s === '18';
    }
    function isSochiPromoCountry(cid) {
        return String(cid != null ? cid : '') === PROMO_COUNTRY_ID_SOCHI;
    }
    function isPhuQuocPromoCountry(cid) {
        return String(cid != null ? cid : '') === PROMO_COUNTRY_ID_PHUQUOC;
    }
    function promoVirtualDestinationConfig(countryId) {
        var s = String(countryId != null ? countryId : '');
        return PROMO_VIRTUAL_DESTINATIONS[s] || null;
    }
    /** TourVisor countryId в ответе API (для виртуальных плиток — базовая страна). */
    function promoExpectedHotelCountryId(promoCountryId) {
        var s = String(promoCountryId != null ? promoCountryId : '');
        var vd = promoVirtualDestinationConfig(s);
        if (vd && vd.tvCountryId != null && vd.tvCountryId !== '') return String(vd.tvCountryId);
        return s;
    }
    /** Абхазия: без фильтра звёзд (гостевые дома). Сочи — фильтр как у остальных стран. */
    function promoSkipsStarFilterForCountry(countryId) {
        return String(countryId != null ? countryId : '') === '46';
    }
    /** Сочи, Мальдивы, Таиланд, VN/Фукуок, Абхазия, Шри-Ланка, ОАЭ, Турция: подмешиваем обычный поиск. */
    function usesNearestPromoFallback(countryId) {
        if (isSochiPromoCountry(countryId)) return true;
        if (String(countryId) === PROMO_COUNTRY_ID_MALDIVES) return true;
        if (isThailandPromoCountry(countryId)) return true;
        if (isPhuQuocPromoCountry(countryId)) return true;
        if (isVietnamPromoCountry(countryId)) return true; /* Самара→VN promo часто пуст */
        if (isPromoTurkeyCountry(countryId)) return true;
        if (String(countryId) === '46' || String(countryId) === '12' || String(countryId) === '9') return true;
        return false;
    }
    function promoAlwaysBlendRegularWithPromo(countryId) {
        return isSochiPromoCountry(countryId)
            || String(countryId) === PROMO_COUNTRY_ID_MALDIVES
            || isThailandPromoCountry(countryId)
            || isPhuQuocPromoCountry(countryId)
            || isVietnamPromoCountry(countryId)
            || isPromoTurkeyCountry(countryId)
            || String(countryId) === '46'
            || String(countryId) === '12'
            || String(countryId) === '9';
    }
    /** Прямые рейсы в акциях: только Таиланд, Шри-Ланка, Фукуок. Остальные — любой перелёт. */
    function isPromoDirectFlightFilterCountry(cid) {
        var s = String(cid != null ? cid : '');
        return s === PROMO_COUNTRY_ID_THAILAND
            || s === PROMO_COUNTRY_ID_SRI_LANKA
            || isPhuQuocPromoCountry(s);
    }
    function isSriLankaPromoCountry(cid) {
        return String(cid != null ? cid : '') === PROMO_COUNTRY_ID_SRI_LANKA;
    }
    function promoMinListPriceForCountry(countryId) {
        if (isSriLankaPromoCountry(countryId)) {
            return 55000;
        }
        return 0;
    }
    function promoFilterHotelsMinListPrice(list, countryId) {
        var floor = promoMinListPriceForCountry(countryId);
        if (!floor || !list || !list.length) return list || [];
        var minAdults = thPromoAdultsCount();
        var out = [];
        list.forEach(function (h) {
            if (!h || !Array.isArray(h.tours) || !h.tours.length) return;
            var kept = h.tours.filter(function (t) {
                if (!t) return false;
                var tourAdults = (t.adults != null && t.adults !== '') ? parseInt(String(t.adults), 10) : minAdults;
                if (isNaN(tourAdults) || tourAdults < minAdults) return false;
                var p = promoHotelListPrice({ tours: [t], price: t.price, minPrice: t.totalPrice || t.price });
                return p >= floor;
            });
            if (!kept.length) return;
            var copy;
            try {
                copy = JSON.parse(JSON.stringify(h));
            } catch (eCopy) {
                copy = Object.assign({}, h);
            }
            copy.tours = kept;
            var minP = 0;
            kept.forEach(function (t) {
                var p = promoHotelListPrice({ tours: [t], price: t.price, minPrice: t.totalPrice || t.price });
                if (p > 0 && (minP === 0 || p < minP)) minP = p;
            });
            if (minP > 0) {
                copy.price = minP;
                copy.minPrice = minP;
            }
            out.push(copy);
        });
        return out;
    }
    function promoActiveCountryId() {
        if (typeof window !== 'undefined' && window.__promoActiveCountryId) {
            return String(window.__promoActiveCountryId);
        }
        return String(COUNTRY_ID != null ? COUNTRY_ID : '');
    }
    function tourIsStrictOnlyPromo(tour, hotel) {
        if (!tour) return false;
        if (Object.prototype.hasOwnProperty.call(tour, 'onlyPromo')) {
            var op = tour.onlyPromo;
            return op === 1 || op === '1' || op === true;
        }
        if (Object.prototype.hasOwnProperty.call(tour, 'onlypromo')) {
            var op2 = tour.onlypromo;
            return op2 === 1 || op2 === '1' || op2 === true;
        }
        if (hotel) {
            if (Object.prototype.hasOwnProperty.call(hotel, 'onlyPromo')) {
                var hop = hotel.onlyPromo;
                return hop === 1 || hop === '1' || hop === true;
            }
            if (Object.prototype.hasOwnProperty.call(hotel, 'onlypromo')) {
                var hop2 = hotel.onlypromo;
                return hop2 === 1 || hop2 === '1' || hop2 === true;
            }
        }
        return false;
    }
    function listHasStrictOnlyPromo(data) {
        if (!data || !data.length) return false;
        for (var hi = 0; hi < data.length; hi++) {
            var h = data[hi];
            var tours = h && h.tours;
            if (!tours || !tours.length) continue;
            for (var ti = 0; ti < tours.length; ti++) {
                if (tourIsStrictOnlyPromo(tours[ti], h)) return true;
            }
        }
        return false;
    }
    function promoRegularSearchUrlForCountry(countryId, nightsFrom, nightsTo, departureIdOverride, opts) {
        opts = opts || {};
        var dFrom = new Date(); dFrom.setDate(dFrom.getDate() + PROMO_DATE_PLUS_FROM);
        var dTo = new Date(); dTo.setDate(dTo.getDate() + promoDatePlusTo(countryId));
        var tvCountryId = promoExpectedHotelCountryId(countryId);
        var depId = departureIdOverride != null && departureIdOverride !== ''
            ? departureIdOverride
            : (DEPARTURE_ID || DEFAULT_DEPARTURE_ID);
        var params = {
            type: 'search-cached',
            departureId: String(depId),
            countryId: String(tvCountryId),
            dateFrom: formatLocalYMD(dFrom),
            dateTo: formatLocalYMD(dTo),
            nightsFrom: String(nightsFrom),
            nightsTo: String(nightsTo),
            adults: String(thPromoAdultsCount()),
            _t: String(Date.now())
        };
        if (opts.preferCache) {
            params.cacheOnly = '1';
        }
        if (opts.hotelCategory != null && opts.hotelCategory !== '') {
            params.hotelCategory = String(opts.hotelCategory);
        }
        var vd = promoVirtualDestinationConfig(countryId);
        /* skipRegion: широкий поиск по стране, потом клиентский фильтр Фукуока */
        if (!opts.skipRegion) {
            if (vd && vd.regionId) params.regionIds = String(vd.regionId);
            if (vd && Array.isArray(vd.regionIds) && vd.regionIds.length) {
                params.regionIds = vd.regionIds.join(',');
            }
        }
        if (String(countryId) !== String(tvCountryId)) {
            params.promoTileId = String(countryId);
        }
        var b = TV_API_BASE.replace(/\/$/, '');
        var s = b.indexOf('?') >= 0 ? '&' : '?';
        return b + s + new URLSearchParams(params).toString();
    }
    function applyNearestFallbackIfNeeded(mergedFlat, countryId) {
        try {
            if (!usesNearestPromoFallback(countryId)) {
                return Promise.resolve(mergedFlat);
            }
            var alwaysBlend = promoAlwaysBlendRegularWithPromo(countryId);
            if (!alwaysBlend && listHasStrictOnlyPromo(mergedFlat)) {
                return Promise.resolve(mergedFlat);
            }
        } catch (eFbGate) {
            return Promise.resolve(mergedFlat);
        }
        function mergeRegular(results) {
            try {
                var anyOk = results.some(function (x) { return x && x.success; });
                if (!anyOk) return [];
                var regularMerged = mergePromoHotelDataArrays(results.map(function (x) {
                    return (x && x.success && Array.isArray(x.data)) ? x.data : [];
                }), String(countryId));
                /* Фукуок: после широкого VN-поиска оставляем только курорт */
                if (isPhuQuocPromoCountry(countryId)) {
                    regularMerged = promoFilterPhuQuocDestinationHotels(regularMerged, countryId);
                }
                regularMerged.sort(function (a, b) {
                    var ta = (a && a.tours && a.tours[0]) ? promoTourStartYmd(a.tours[0]) : '';
                    var tb = (b && b.tours && b.tours[0]) ? promoTourStartYmd(b.tours[0]) : '';
                    if (ta && tb && ta !== tb) return ta < tb ? -1 : 1;
                    return promoHotelListPrice(a) - promoHotelListPrice(b);
                });
                return regularMerged;
            } catch (eFbMerge) {
                return [];
            }
        }
        function fetchRegularWindows(depId, searchOpts) {
            return Promise.all(promoNightWindowsForCountry(countryId).map(function (w) {
                return fetch(promoRegularSearchUrlForCountry(countryId, w[0], w[1], depId, searchOpts), { method: 'GET', cache: 'no-store' })
                    .then(function (r) { return r.text().then(function (t) { return { ok: r.ok, text: t }; }); })
                    .then(parseTourvisorSearchJsonResponse)
                    .catch(function () { return { success: false, error: 'network', data: [] }; });
            })).then(mergeRegular);
        }
        function combineWithPromo(regularMerged, resultDepId) {
            if (resultDepId) {
                try { window.__promoResultDepartureId = resultDepId; } catch (eDep) {}
            }
            if (mergedFlat && mergedFlat.length && regularMerged.length) {
                var combined = mergePromoHotelDataArrays([mergedFlat, regularMerged], String(countryId));
                combined.sort(function (a, b) {
                    return promoHotelListPrice(a) - promoHotelListPrice(b);
                });
                if (!combined.length) return mergedFlat;
                return combined;
            }
            if (regularMerged.length) return regularMerged;
            return mergedFlat;
        }
        /* Только выбранный вылет (Самара и т.д.) — без подмены на Москву; search-cached без live (из файлового кэша). */
        var primaryDep = String(DEPARTURE_ID || DEFAULT_DEPARTURE_ID || '7');
        var blendSearchOpts = { preferCache: true };
        return fetchRegularWindows(primaryDep, blendSearchOpts).then(function (regularMerged) {
            if (regularMerged.length) {
                return combineWithPromo(regularMerged, primaryDep);
            }
            /* Фукуок regionIds часто пуст из Самары — обычный VN, потом фильтр курорта */
            if (!isPhuQuocPromoCountry(countryId)) {
                return mergedFlat;
            }
            return fetchRegularWindows(primaryDep, Object.assign({ skipRegion: true }, blendSearchOpts)).then(function (wideMerged) {
                if (!wideMerged.length) return mergedFlat;
                return combineWithPromo(wideMerged, primaryDep);
            });
        }).catch(function () { return mergedFlat; });
    }
    function promoTourvisorPackageIsDirect(pkg) {
        if (!pkg) return false;
        var fw = pkg.forward;
        var bw = pkg.backward || pkg.back;
        if (!Array.isArray(fw) || fw.length !== 1) return false;
        if (Array.isArray(bw) && bw.length > 1) return false;
        return true;
    }
    function promoTourFlightsUrl(tourId) {
        var b = TV_API_BASE.replace(/\/$/, '');
        var sep = b.indexOf('?') >= 0 ? '&' : '?';
        return b + sep + new URLSearchParams({ type: 'tour-flights', tourId: String(tourId), currency: 'RUB', _t: String(Date.now()) }).toString();
    }
    function promoFetchTourIsDirectOnce(tourId) {
        var depNm = promoDepartureNameForFlights();
        var depIdFl = promoDepartureIdForFlights();
        return safeFetchJsonWithTimeout(promoTourFlightsUrl(tourId), { success: false }, 14000).then(function (j) {
            try {
                var flights = (j && Array.isArray(j.flights)) ? j.flights : (j && j.data && j.data.flights && Array.isArray(j.data.flights)) ? j.data.flights : [];
                var pick = (typeof window !== 'undefined' && typeof window.thPickTourvisorFlightPackage === 'function')
                    ? window.thPickTourvisorFlightPackage(flights, depNm, depIdFl)
                    : (flights && flights.length ? flights[0] : null);
                var isDirect = promoTourvisorPackageIsDirect(pick);
                try {
                    if (tourId && pick) {
                        if (typeof thFlightsCacheFromJson === 'function') {
                            thFlightsCacheFromJson(tourId, { success: true, flights: flights }, depNm, depIdFl);
                            var cached = (typeof thFlightsCacheGet === 'function')
                                ? thFlightsCacheGet(tourId, depNm)
                                : promoFlightsCacheGet(tourId);
                            if (cached) cached.direct = isDirect;
                        } else if (typeof thFlightMetaFromPackage === 'function') {
                            var meta = thFlightMetaFromPackage(pick, depNm);
                            if (meta) {
                                meta.direct = isDirect;
                                if (typeof thFlightsCacheSet === 'function') {
                                    thFlightsCacheSet(tourId, meta, depNm);
                                } else {
                                    window.__promoFlightsByTourId = window.__promoFlightsByTourId || {};
                                    window.__promoFlightsByTourId[String(tourId) + '@' + String(depNm || '').trim().toLowerCase()] = meta;
                                }
                            }
                        }
                    }
                } catch (eCache) {}
                return isDirect;
            } catch (e) {
                return null;
            }
        }).catch(function () { return null; });
    }
    function promoFetchTourIsDirect(tourId) {
        return promoFetchTourIsDirectOnce(tourId).then(function (ok) {
            if (ok === true || ok === false) return ok;
            return promoFetchTourIsDirectOnce(tourId);
        });
    }
    function runLimitedConcurrency(ids, limit, worker) {
        return new Promise(function (resolve) {
            if (!ids || !ids.length) { resolve([]); return; }
            var results = new Array(ids.length);
            var nextIndex = 0;
            var active = 0;
            var finished = 0;
            function runNext() {
                while (active < limit && nextIndex < ids.length) {
                    var idx = nextIndex++;
                    var id = ids[idx];
                    active++;
                    worker(id, idx).then(function (res) {
                        results[idx] = res;
                        active--;
                        finished++;
                        if (finished >= ids.length) resolve(results);
                        else runNext();
                    }).catch(function () {
                        results[idx] = false;
                        active--;
                        finished++;
                        if (finished >= ids.length) resolve(results);
                        else runNext();
                    });
                }
            }
            runNext();
        });
    }
    /** Список туров: прямой перелёт — только TH / Шри-Ланка / Фукуок. */
    function filterDirectFlightsPromoIfNeeded(data, countryId) {
        if (!TH_FEATURE_PROMO_DIRECT || !isPromoDirectFlightFilterCountry(countryId) || !data || !data.length) {
            return Promise.resolve(data);
        }
        var seen = {};
        var ids = [];
        data.forEach(function (h) {
            var tid = (function () {
                var tour = (h && h.tours && h.tours[0]) ? h.tours[0] : {};
                return (tour.id != null && tour.id !== '') ? String(tour.id) : '';
            })();
            if (!tid || seen[tid]) return;
            seen[tid] = true;
            ids.push(tid);
        });
        if (!ids.length) return Promise.resolve(data);
        return runLimitedConcurrency(ids, 5, function (tid) { return promoFetchTourIsDirect(tid); }).then(function (flags) {
            var map = {};
            ids.forEach(function (id, i) { map[id] = flags[i]; });
            var filtered = data.filter(function (h) {
                var tour = (h && h.tours && h.tours[0]) ? h.tours[0] : {};
                var tid = (tour.id != null && tour.id !== '') ? String(tour.id) : '';
                if (!tid) return false;
                return map[tid] === true;
            });
            if (filtered.length === 0 && ids.length > 0) {
                var anyKnown = ids.some(function (id) { return map[id] === true || map[id] === false; });
                /* Нет данных по рейсам или все «не прямые» — не обнуляем выдачу акций */
                if (!anyKnown) return data;
                if (filtered.length === 0) return data;
            }
            return filtered;
        });
    }

    var TV_API_BASE = cfg.tvApiBase;
    var TV_IMAGE_PROXY = cfg.tvImageProxy;
    if (typeof location !== 'undefined' && location.protocol === 'https:' && typeof TV_API_BASE === 'string' && TV_API_BASE.indexOf('http://') === 0) {
        TV_API_BASE = 'https:' + TV_API_BASE.substring(5);
    }
    if (typeof location !== 'undefined' && location.protocol === 'https:' && typeof TV_IMAGE_PROXY === 'string' && TV_IMAGE_PROXY.indexOf('http://') === 0) {
        TV_IMAGE_PROXY = 'https:' + TV_IMAGE_PROXY.substring(5);
    }
    var DEPARTURE_ID = cfg.departureId;
    var DEPARTURE_NAME = cfg.departureName;
    var DEFAULT_DEPARTURE_ID = cfg.defaultDepartureId;
    var DEFAULT_DEPARTURE_NAME = cfg.defaultDepartureName;

    function promoNormDepartureName(n) {
        return String(n || '').toLowerCase().replace(/ё/g, 'е').trim();
    }
    function promoDepartureIsBlocked(n) {
        var s = promoNormDepartureName(n);
        return s === 'красноярск' || s === 'krasnoyarsk';
    }

    var PROMO_DEPARTURE_ROUTE_CODES = {
        '7': ['KUF'],
        '1': ['DME', 'SVO', 'VKO', 'ZIA', 'MOW']
    };

    function promoNormalizeDepartureId(id) {
        var n = parseInt(String(id != null ? id : ''), 10);
        if (isNaN(n) || n <= 0) return parseInt(String(DEFAULT_DEPARTURE_ID || '7'), 10) || 7;
        if (n === 12) return parseInt(String(DEFAULT_DEPARTURE_ID || '7'), 10) || 7;
        if (n === 28) return 1;
        return n;
    }

    function promoTourNameIsBlocked(name) {
        var s = String(name || '');
        if (!s) return false;
        if (/\bKJA\b/i.test(s)) return true;
        if (/\bKrasnoyarsk\b/i.test(s)) return true;
        if (/краснояр/i.test(s)) return true;
        if (/емельяново/i.test(s)) return true;
        return false;
    }

    function promoTourMatchesDeparture(tour, departureId) {
        if (!tour) return false;
        var name = String(tour.name || '');
        if (promoTourNameIsBlocked(name)) return false;
        var depKey = String(departureId != null ? departureId : DEPARTURE_ID || '');
        /* Самара→дальние (Фукуок): в name часто MOW-PQC при departureId=7 — не вычищаем по IATA */
        if (depKey === '7') return true;
        var allowed = PROMO_DEPARTURE_ROUTE_CODES[depKey];
        var re = /(?:^|[^A-Z])([A-Z]{3})-([A-Z]{3})(?:[^A-Z]|$)/g;
        var m;
        while ((m = re.exec(name.toUpperCase())) !== null) {
            if (m[1] === 'KJA') return false;
            if (allowed && allowed.indexOf(m[1]) < 0) return false;
        }
        return true;
    }

    function promoFilterHotelsForDeparture(data, departureId) {
        var depId = departureId != null ? departureId : DEPARTURE_ID;
        var list = Array.isArray(data) ? data : [];
        if (!depId || !list.length) return list.slice();
        var out = [];
        list.forEach(function (h) {
            if (!h || !Array.isArray(h.tours)) return;
            var kept = h.tours.filter(function (t) { return promoTourMatchesDeparture(t, depId); });
            if (!kept.length) return;
            var copy = Object.assign({}, h, { tours: kept });
            var min = 0;
            kept.forEach(function (t) {
                var p = Math.round(parseInt(String(t.totalPrice || t.price || ''), 10) || 0);
                if (p > 0 && (!min || p < min)) min = p;
            });
            if (min > 0) copy.price = min;
            out.push(copy);
        });
        return out;
    }
    function promoApplyDepartureDefaults() {
        DEPARTURE_ID = promoNormalizeDepartureId(DEPARTURE_ID);
        if (promoDepartureIsBlocked(DEPARTURE_NAME)) {
            DEPARTURE_ID = DEFAULT_DEPARTURE_ID;
            DEPARTURE_NAME = DEFAULT_DEPARTURE_NAME;
            try {
                localStorage.setItem('th_departure_id', String(DEPARTURE_ID));
                localStorage.setItem('th_departure_name', String(DEPARTURE_NAME));
            } catch (eLsDep) {}
        }
        if (!DEPARTURE_ID || String(DEPARTURE_ID) === '0') {
            DEPARTURE_ID = DEFAULT_DEPARTURE_ID;
        }
        DEPARTURE_ID = promoNormalizeDepartureId(DEPARTURE_ID);
        if (!DEPARTURE_NAME || !String(DEPARTURE_NAME).trim()) {
            DEPARTURE_NAME = DEFAULT_DEPARTURE_NAME;
        }
    }
    function promoSyncDepartureFromSitePreference() {
        try {
            var urlDep = typeof location !== 'undefined' && location.search
                ? new URLSearchParams(location.search).get('departureId')
                : null;
            if (urlDep) return;
            var lid = localStorage.getItem('th_departure_id');
            var lnm = localStorage.getItem('th_departure_name');
            if (lid && lnm && !promoDepartureIsBlocked(lnm)) {
                var x = parseInt(String(lid), 10);
                if (!isNaN(x) && x > 0) {
                    DEPARTURE_ID = x;
                    DEPARTURE_NAME = String(lnm);
                    return;
                }
            }
            if (window.TH_DEPARTURE && window.TH_DEPARTURE.id && !promoDepartureIsBlocked(window.TH_DEPARTURE.name)) {
                DEPARTURE_ID = window.TH_DEPARTURE.id;
                DEPARTURE_NAME = String(window.TH_DEPARTURE.name || DEFAULT_DEPARTURE_NAME);
            }
        } catch (eSyncDep) {}
        promoApplyDepartureDefaults();
    }
    promoSyncDepartureFromSitePreference();

    function promoAllowedDeparture(id, name) {
        id = promoNormalizeDepartureId(id);
        if (id === 1) return { id: 1, name: 'Москва', gen: 'Москвы' };
        return { id: 7, name: 'Самара', gen: 'Самары' };
    }
    (function applyAllowedDeparture() {
        var picked = promoAllowedDeparture(DEPARTURE_ID, DEPARTURE_NAME);
        DEPARTURE_ID = picked.id;
        DEPARTURE_NAME = picked.name;
    })();

    function promoUpdateDepartureUi(picked) {
        picked = picked || promoAllowedDeparture(DEPARTURE_ID, DEPARTURE_NAME);
        var label = document.getElementById('promo-departure-label');
        var heroGen = document.getElementById('promo-hero-departure-gen');
        if (label) label.textContent = picked.name;
        if (heroGen) heroGen.textContent = picked.gen;
        document.querySelectorAll('.promo-departure-menu__item').forEach(function (btn) {
            var active = String(btn.getAttribute('data-departure-id')) === String(picked.id);
            btn.classList.toggle('is-active', active);
            btn.setAttribute('aria-selected', active ? 'true' : 'false');
        });
    }

    function initPromoDeparturePicker(onChange) {
        var trigger = document.getElementById('promo-departure-trigger');
        var menu = document.getElementById('promo-departure-menu');
        if (!trigger || !menu || trigger.__thDepPickerBound) return;
        trigger.__thDepPickerBound = true;
        promoUpdateDepartureUi();

        function closeMenu() {
            menu.hidden = true;
            trigger.setAttribute('aria-expanded', 'false');
        }
        function openMenu() {
            menu.hidden = false;
            trigger.setAttribute('aria-expanded', 'true');
        }
        trigger.addEventListener('click', function (e) {
            e.stopPropagation();
            if (menu.hidden) openMenu();
            else closeMenu();
        });
        menu.querySelectorAll('.promo-departure-menu__item').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id = parseInt(btn.getAttribute('data-departure-id') || '0', 10);
                var name = btn.getAttribute('data-departure-name') || '';
                closeMenu();
                if (typeof onChange === 'function') onChange(id, name);
            });
        });
        document.addEventListener('click', function (e) {
            if (!e.target.closest('.promo-departure-picker') && !e.target.closest('.promo-departure-bar')) closeMenu();
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeMenu();
        });
    }

    var COUNTRY_ID = cfg.countryId;
    var COUNTRY_NAME = cfg.countryName;
    /** Винительный падеж для заголовка «в …» (см. promotions.php). */
    var COUNTRY_NAME_ACC = cfg.countryNameAcc || cfg.countryName || '';

    var PROMO_EXCLUDED_IDS = {};
    (cfg.promoExcludedCountryIds || []).forEach(function (id) {
        PROMO_EXCLUDED_IDS[String(id)] = true;
    });
    function promoIsExcludedCountryId(countryId) {
        return !!PROMO_EXCLUDED_IDS[String(countryId != null ? countryId : '')];
    }
    function promoFilterExcludedCountries(list) {
        return (list || []).filter(function (c) {
            return c && c.id != null && !promoIsExcludedCountryId(c.id);
        });
    }

    var PROMO_CACHE_INDEX_BY_DEP = (cfg && cfg.promoCacheIndexByDeparture && typeof cfg.promoCacheIndexByDeparture === 'object')
        ? cfg.promoCacheIndexByDeparture : {};
    var PROMO_INSTANT_CACHE_DEFAULT_IDS = ['4', '1', '9', '16', '16104', '2', '47', '46', '8', '12'];
    var PROMO_INSTANT_CACHE_COUNTRY_IDS = (cfg && Array.isArray(cfg.promoInstantCacheCountryIds))
        ? cfg.promoInstantCacheCountryIds.map(function (x) { return String(x); }) : PROMO_INSTANT_CACHE_DEFAULT_IDS.slice();
    var PROMO_TOURS_PREFETCH_MAX_MS = 24 * 60 * 60 * 1000;
    window.__promoPrefetchStore = window.__promoPrefetchStore || {};
    var PROMO_CPTILE_RESOLVED = {};
    var PROMO_POPULAR_CFG_IDS = (function () {
        var s = {};
        ((cfg && cfg.popularCountries) || []).forEach(function (p) {
            if (p && p.id != null) s[String(p.id)] = true;
        });
        return s;
    })();

    function promoInstantCountryIds() {
        return PROMO_INSTANT_CACHE_COUNTRY_IDS.filter(function (id) {
            return id && !promoIsExcludedCountryId(id);
        });
    }
    function promoIsInstantCacheCountry(countryId) {
        return promoInstantCountryIds().indexOf(String(countryId != null ? countryId : '')) >= 0;
    }
    function promoWarmDepartureFallbackKey() {
        /* Без подмены города: только текущий вылет (Самара по умолчанию). */
        return promoCurrentDepartureKey();
    }
    function promoClearFallbackDepartureNotice() {
        try { window.__promoFallbackDepartureNotice = null; } catch (e0) {}
        try { window.__promoResultDepartureId = null; } catch (e1) {}
        var el = document.getElementById('promo-fallback-dep-notice');
        if (el) el.remove();
    }
    function promoShowFallbackDepartureNoticeIfAny() {
        /* Москва как запасной вылет отключена */
    }
    function promoCurrentDepartureKey() {
        var key = String(DEPARTURE_ID != null && DEPARTURE_ID !== '' ? DEPARTURE_ID : (DEFAULT_DEPARTURE_ID || '7'));
        return key === '0' ? String(DEFAULT_DEPARTURE_ID || '7') : key;
    }
    function promoGetManifestBlock() {
        var key = promoCurrentDepartureKey();
        var block = PROMO_CACHE_INDEX_BY_DEP[key];
        if (block && typeof block === 'object' && Object.keys(block).length > 0) {
            return block;
        }
        return (block && typeof block === 'object') ? block : null;
    }
    function promoGetManifestEntry(countryId) {
        var id = String(countryId != null ? countryId : '');
        var key = promoCurrentDepartureKey();
        var block = PROMO_CACHE_INDEX_BY_DEP[key];
        var ent = (block && block[id]) ? block[id] : null;
        if (ent && (ent.has || (ent.minPrice && ent.minPrice > 0))) return ent;
        return ent || { has: false, minPrice: 0 };
    }

    /** Сразу цены/бейджи на плитках из манифеста (без API). */
    function promoApplyManifestToCountries(countries, isPopular) {
        if (PROMO_LIVE_ONLY || !promoGetManifestBlock()) return;
        (countries || []).forEach(function (c) {
            var id = (c && c.id != null) ? c.id : c;
            if (id == null || promoIsExcludedCountryId(id)) return;
            var ent = promoGetManifestEntry(id);
            var pop = (isPopular !== undefined && isPopular !== null)
                ? !!isPopular
                : promoCptileIsPopularCountry(id);
            var has = !!(ent.has || (ent.minPrice && ent.minPrice > 0));
            promoApplyCptilePromoState(id, has, ent.minPrice || 0, pop, { fromManifest: true });
        });
    }
    function promoPrefetchStoreKey(departureId, countryId) {
        return String(departureId) + ':' + String(countryId);
    }
    function promoToursSwrMaxAgeMs(countryId) {
        return promoIsInstantCacheCountry(countryId) ? PROMO_TOURS_PREFETCH_MAX_MS : PROMO_TOURS_SWR_MAX_MS;
    }

    /** «Акционные туры в …» — только Турция: Турция → Турцию. */
    function promoTitleCountryName(countryId, countryName) {
        var n = (countryName || '').trim();
        if (n === 'Турция' || String(countryId) === '4') return 'Турцию';
        return n;
    }

    /** Локальная дата YYYY-MM-DD (без UTC-сдвига, как у toISOString). */
    function formatLocalYMD(d) {
        if (!d || !(d instanceof Date) || isNaN(d.getTime())) return '';
        var y = d.getFullYear();
        var m = String(d.getMonth() + 1);
        if (m.length === 1) m = '0' + m;
        var day = String(d.getDate());
        if (day.length === 1) day = '0' + day;
        return y + '-' + m + '-' + day;
    }

    /**
     * Список стран для плиток на странице акций.
     * Полный type=countries — справочник Tourvisor; с departureId — только направления с вылетом из города
     * (иногда без Турции/Египта и т.д. при сбое или узкой выдаче API).
     * При выбранном городе вылета: объединяем выдачу по вылету со всем полным справочником (приоритет у записи из API по вылету),
     * чтобы блок «Все направления» не схлопывался до одной страны и популярные направления не терялись.
     */
    function fetchPromoCountriesForTiles(apiBase, timeoutMs) {
        var base = (apiBase || '').replace(/\/$/, '');
        var sep = base.indexOf('?') >= 0 ? '&' : '?';
        var urlFull = base + sep + 'type=countries';
        var did = DEPARTURE_ID != null && DEPARTURE_ID !== '' ? parseInt(String(DEPARTURE_ID), 10) : 0;
        if (isNaN(did) || did <= 0) {
            did = DEFAULT_DEPARTURE_ID != null && DEFAULT_DEPARTURE_ID !== '' ? parseInt(String(DEFAULT_DEPARTURE_ID), 10) : 0;
        }
        var urlDep = (!isNaN(did) && did > 0) ? (base + sep + 'type=countries&departureId=' + encodeURIComponent(String(did))) : '';

        function fetchOne(url) {
            if (timeoutMs != null && timeoutMs > 0) {
                return safeFetchJsonWithTimeout(url, { success: false, data: [] }, timeoutMs);
            }
            return safeFetchJson(url, { success: false, data: [] });
        }

        function normalizeCountriesData(j) {
            if (!j || typeof j !== 'object') return [];
            return Array.isArray(j.data) ? j.data : [];
        }

        function mergeDepartureAndPopularFull(depList, fullList) {
            var byId = {};
            depList.forEach(function (c) {
                if (!c || c.id == null) return;
                byId[String(c.id)] = c;
            });
            fullList.forEach(function (c) {
                if (!c || c.id == null) return;
                var id = String(c.id);
                if (!byId[id]) byId[id] = c;
            });
            if (Object.keys(byId).length === 0) {
                if (fullList.length) return fullList.filter(function (c) { return c && c.id != null; });
                return depList.filter(function (c) { return c && c.id != null; });
            }
            return Object.keys(byId).map(function (k) { return byId[k]; });
        }

        /* Нет departureId в URL — только полный справочник */
        if (!urlDep) {
            return fetchOne(urlFull);
        }

        /* Есть город вылета — полный справочник + список по вылету (объединение, без усечения до «только популярные») */
        return Promise.all([fetchOne(urlFull), fetchOne(urlDep)]).then(function (arr) {
            var fullList = normalizeCountriesData(arr[0]);
            var depList = normalizeCountriesData(arr[1]);
            var merged = mergeDepartureAndPopularFull(depList, fullList);
            if (merged.length === 0) {
                return { success: false, data: [] };
            }
            return { success: true, data: merged };
        });
    }

    function promoBuildImgMap(imagesRes) {
        var imgMap = {};
        ((imagesRes && imagesRes.countries) || []).forEach(function (c) {
            if (!c.images || !c.images[0]) return;
            var img = c.images[0];
            imgMap[c.id] = img;
            if (c.name) {
                imgMap[c.name] = img;
                imgMap[(c.name + '').toLowerCase().trim()] = img;
            }
            if (c.slug) imgMap[c.slug] = img;
        });
        return imgMap;
    }

    function promoIndexCountriesById(list) {
        var byId = {};
        (list || []).forEach(function (c) {
            if (!c || c.id == null) return;
            byId[String(c.id)] = c;
        });
        return byId;
    }

    /** Популярные направления: сразу из конфига; при наличии API — запись справочника. */
    function promoBuildPopularList(cfg, byId) {
        byId = byId || {};
        var popular = [];
        var defs = (cfg && Array.isArray(cfg.popularCountries)) ? cfg.popularCountries : [];
        defs.forEach(function (p) {
            if (!p || p.id == null) return;
            if (promoIsExcludedCountryId(p.id)) return;
            var id = String(p.id);
            var c = byId[id] || { id: p.id, name: p.name || '', russianName: p.name || '' };
            popular.push({ c: c, displayName: (p.name || c.name || c.russianName || '').toString() });
        });
        return popular;
    }

    function promoPopularIdSet(popular) {
        var s = {};
        (popular || []).forEach(function (p) {
            if (p && p.c && p.c.id != null) s[String(p.c.id)] = true;
        });
        return s;
    }

    function promoCountriesOnlyOther(list, popularIdSet) {
        return (list || []).filter(function (c) {
            return c && c.id != null && !popularIdSet[String(c.id)];
        });
    }

    function promoMinPriceFromHotels(hotels) {
        var min = 0;
        (hotels || []).forEach(function (h) {
            if (!h || !Array.isArray(h.tours) || !h.tours.length) return;
            var p = promoHotelListPrice(h);
            if (p > 0 && (min === 0 || p < min)) min = p;
        });
        return min;
    }

    function promoCptileMinPriceFromPrefetch(countryId) {
        var depId = promoCurrentDepartureKey();
        var storeKey = promoPrefetchStoreKey(depId, String(countryId));
        var pref = window.__promoPrefetchStore[storeKey];
        if (!pref || !Array.isArray(pref.data) || !pref.data.length) return 0;
        return promoMinPriceFromHotels(pref.data);
    }

    function promoSetCptilePriceBadge(countryId, price) {
        if (!price || price <= 0) return;
        document.querySelectorAll('.promo-cptile[data-uid="' + String(countryId) + '"]').forEach(function (tile) {
            var badge = tile.querySelector('.promo-cptile-price');
            if (!badge) {
                badge = document.createElement('div');
                badge.className = 'promo-cptile-price';
                tile.appendChild(badge);
            }
            badge.textContent = 'от ' + formatPrice(price);
        });
    }

    function promoCptileIsPopularCountry(countryId) {
        var id = String(countryId);
        var ent = PROMO_CPTILE_RESOLVED[id];
        if (ent && ent.isPopular) return true;
        if (PROMO_POPULAR_CFG_IDS[id]) return true;
        return !!document.querySelector('#promo-popular-grid .promo-cptile[data-uid="' + id + '"]');
    }

    function promoPaintCptileDom(countryId, hasPromo, minPrice, isPopular) {
        var id = String(countryId);
        var price = (minPrice && minPrice > 0) ? minPrice : 0;
        document.querySelectorAll('.promo-cptile[data-uid="' + id + '"]').forEach(function (tile) {
            var chk = tile.querySelector('[data-uchecking="' + id + '"]') || tile.querySelector('.promo-cptile-checking');
            if (chk) chk.remove();
            if (!hasPromo) {
                if (isPopular) {
                    /* Популярные всегда кликабельны — иначе «нет туров» блокировал открытие
                       и живой promo-search (баг: Абхазия…Шри-Ланка казались пустыми). */
                    tile.classList.remove('promo-cptile-nopromo');
                    tile.classList.add('opacity-60');
                    var badge = tile.querySelector('.promo-cptile-price');
                    if (!badge) {
                        badge = document.createElement('div');
                        badge.className = 'promo-cptile-price';
                        tile.appendChild(badge);
                    }
                    if (!price) badge.textContent = 'уточняем…';
                    return;
                }
                tile.remove();
                return;
            }
            tile.classList.remove('promo-cptile-nopromo');
            tile.classList.remove('opacity-60');
            if (price > 0) promoSetCptilePriceBadge(id, price);
            var cName = tile.getAttribute('data-uname') || tile.querySelector('.promo-cptile-name');
            cName = (typeof cName === 'string') ? cName : (cName ? cName.textContent : '');
            if (!tile.querySelector('[data-promo-country-callback]')) {
                var cb = document.createElement('button');
                cb.type = 'button';
                cb.className = 'promo-country-callback';
                cb.setAttribute('data-promo-country-callback', '1');
                cb.setAttribute('data-country-id', id);
                cb.setAttribute('data-country-name', String(cName || '').trim());
                cb.textContent = 'Перезвонить';
                tile.appendChild(cb);
            }
        });
    }

    function promoRepaintCptilesForIds(ids) {
        (ids || []).forEach(function (cid) {
            var ent = PROMO_CPTILE_RESOLVED[String(cid)];
            if (ent) promoPaintCptileDom(String(cid), ent.hasPromo, ent.minPrice, ent.isPopular);
        });
    }

    function promoSyncCptileMinPrice(countryId, hotels) {
        if (!countryId) return;
        var list = promoPostProcessHotelList(Array.isArray(hotels) ? hotels : [], countryId);
        if (!list.length) return;
        var minP = promoMinPriceFromHotels(list);
        promoApplyCptilePromoState(countryId, true, minP, promoCptileIsPopularCountry(countryId), { fromTours: true });
    }

    /** Цена на плитке: nearest/regular merge без фильтра прямых рейсов (он только для списка туров TH/VN). */
    function promoSyncCptileForTile(countryId, hotels) {
        if (!countryId || !hotels || !hotels.length) return Promise.resolve(null);
        return applyNearestFallbackIfNeeded(hotels, countryId).then(function (list) {
            var use = (list && list.length) ? list : hotels;
            if (use.length) promoSyncCptileMinPrice(countryId, use);
            return use;
        });
    }

    function promoPrefetchHotelsForCountry(countryId) {
        if (PROMO_LIVE_ONLY) return null;
        var sk = promoPrefetchStoreKey(promoCurrentDepartureKey(), String(countryId));
        var pref = window.__promoPrefetchStore[sk];
        return (pref && Array.isArray(pref.data) && pref.data.length) ? pref.data : null;
    }

    function promoDebugLog(label, payload) {
        try {
            if (payload !== undefined) {
                console.log('%c[Акции DEBUG] ' + label, 'color:#f59e0b;font-weight:bold', payload);
            } else {
                console.log('%c[Акции DEBUG] ' + label, 'color:#f59e0b;font-weight:bold');
            }
        } catch (eDbg) {}
    }

    function promoTourOperatorLabel(tour) {
        if (!tour) return '';
        if (tour.operatorName) return String(tour.operatorName);
        var op = tour.operator;
        if (typeof op === 'string') return op;
        if (op && typeof op === 'object') return String(op.name || op.russianName || op.fullName || '');
        return '';
    }

    var PROMO_OPERATOR_ALIASES_GENERAL = [
        ['Fun Sun', 'Fun&Sun', 'FunSun', 'Фансан', 'Фан Сан'],
        ['Anex', 'Anex Tour', 'Анекс'],
        ['Coral', 'Coral Travel', 'Корал'],
        ['Sunmar', 'Санмар'],
        ['Pegas', 'Pegas Touristik', 'Пегас'],
        ['Русский экспресс', 'Russian Express', 'Russ Express'],
        ['Loti', 'Лоти'],
        ['Библио глобус', 'Библио-Глобус', 'Biblio Globus', 'BiblioGlobus'],
        ['Paks', 'Пакс'],
        ["Let's fly", 'Lets fly', 'Летс флай', 'ЛетсФлай'],
        ['Интурист', 'Intourist'],
        ['Амботис', 'Ambotis']
    ];
    /** Только Самара→Фукуок (не для остальных стран). */
    var PROMO_OPERATOR_ALIASES_SPACE_TRAVEL = [
        ['Space Travel', 'SpaceTravel', 'Спейс Тревел', 'СпейсТревел', 'Спейс трэвел']
    ];

    var PROMO_OPERATOR_ALIASES_TR_EG = [
        ['Fun Sun', 'Fun&Sun', 'FunSun', 'Фансан'],
        ['Coral', 'Coral Travel', 'Корал'],
        ['Anex', 'Anex Tour', 'Анекс'],
        ['Sunmar', 'Санмар'],
        ['Pegas', 'Pegas Touristik', 'Пегас'],
        ['Интурист', 'Intourist'],
        ['Библио глобус', 'Библио-Глобус', 'Biblio Globus', 'BiblioGlobus']
    ];

    function promoNormalizeOperatorLabel(label) {
        if (!label) return '';
        var s = String(label).trim().toLowerCase().replace(/ё/g, 'е');
        try {
            return s.replace(/[^\p{L}\p{N}]+/gu, '');
        } catch (e) {
            return s.replace(/[^a-z0-9а-я]+/gi, '');
        }
    }

    function promoIsTurkeyOrEgypt(countryId, countryName) {
        var id = String(countryId || '');
        if (id === '1' || id === '4') return true;
        var n = promoNormalizeOperatorLabel(countryName || '');
        if (!n) return false;
        return n.indexOf('турция') >= 0
            || n.indexOf('turkey') >= 0
            || n.indexOf('turkiye') >= 0
            || n.indexOf('türkiye') >= 0
            || n.indexOf('trkiye') >= 0
            || n.indexOf('египет') >= 0
            || n.indexOf('egypt') >= 0;
    }

    function promoAllowedOperatorTokens(countryId, countryName) {
        var groups = promoIsTurkeyOrEgypt(countryId, countryName)
            ? PROMO_OPERATOR_ALIASES_TR_EG
            : PROMO_OPERATOR_ALIASES_GENERAL.slice();
        /* Space Travel — только Самара→Фукуок */
        var depKey = String(DEPARTURE_ID || DEFAULT_DEPARTURE_ID || '7');
        if (isPhuQuocPromoCountry(countryId) && depKey === '7') {
            groups = groups.concat(PROMO_OPERATOR_ALIASES_SPACE_TRAVEL);
        }
        var tokens = {};
        groups.forEach(function (aliases) {
            (aliases || []).forEach(function (a) {
                var norm = promoNormalizeOperatorLabel(a);
                if (norm) tokens[norm] = true;
            });
        });
        return Object.keys(tokens);
    }

    /** Фильтр ТО в акциях: белый список + TR/EG исключения (как backend). */
    function promoTourOperatorAllowed(tour, countryId, countryName) {
        var label = promoTourOperatorLabel(tour);
        var norm = promoNormalizeOperatorLabel(label);
        if (!norm) return true;
        var tokens = promoAllowedOperatorTokens(countryId, countryName);
        if (!tokens.length) return true;
        for (var i = 0; i < tokens.length; i++) {
            var t = tokens[i];
            if (!t) continue;
            if (norm.indexOf(t) >= 0 || t.indexOf(norm) >= 0) return true;
        }
        return false;
    }

    function promoFilterHotelsByAllowedOperators(data, countryId, countryName) {
        var out = [];
        (Array.isArray(data) ? data : []).forEach(function (h) {
            if (!h || !Array.isArray(h.tours)) return;
            var kept = h.tours.filter(function (t) { return promoTourOperatorAllowed(t, countryId, countryName); });
            if (!kept.length) return;
            var copy = Object.assign({}, h, { tours: kept });
            var min = 0;
            kept.forEach(function (t) {
                var p = Math.round(parseInt(String(t.totalPrice || t.price || ''), 10) || 0);
                if (p > 0 && (!min || p < min)) min = p;
            });
            if (min > 0) copy.price = min;
            out.push(copy);
        });
        return out;
    }

    function promoDebugSummarizeHotels(hotels, label) {
        hotels = Array.isArray(hotels) ? hotels : [];
        var tourCount = 0;
        var promoFlagCount = 0;
        var operators = {};
        var nights = {};
        hotels.forEach(function (h) {
            (h.tours || []).forEach(function (t) {
                if (!t) return;
                tourCount++;
                if (t.isPromo) promoFlagCount++;
                var op = promoTourOperatorLabel(t);
                if (op) operators[op] = (operators[op] || 0) + 1;
                var n = parseInt(String(t.nights || ''), 10);
                if (n > 0) nights[n] = (nights[n] || 0) + 1;
            });
        });
        promoDebugLog(label, {
            hotels: hotels.length,
            tours: tourCount,
            toursWithIsPromo: promoFlagCount,
            operators: operators,
            nightsDistribution: nights,
            sampleTours: hotels.slice(0, 2).map(function (h) {
                var t = (h.tours && h.tours[0]) ? h.tours[0] : {};
                return {
                    hotel: h.name,
                    tourId: t.id,
                    tourName: t.name,
                    nights: t.nights,
                    isPromo: t.isPromo,
                    operator: promoTourOperatorLabel(t),
                    price: t.price || t.totalPrice
                };
            })
        });
    }

    /** Плитка unified: популярные — «нет туров»; остальные без акций убираются из сетки. */
    function promoApplyCptilePromoState(countryId, hasPromo, minPrice, isPopular, opts) {
        opts = opts || {};
        var id = String(countryId);
        var price = (minPrice && minPrice > 0) ? minPrice : 0;
        var pop = (isPopular !== undefined && isPopular !== null) ? !!isPopular : promoCptileIsPopularCountry(id);
        var prev = PROMO_CPTILE_RESOLVED[id];
        var prefHotels = promoPrefetchHotelsForCountry(id);
        if (!hasPromo && prefHotels && prefHotels.length) {
            hasPromo = true;
            if (!price) price = promoMinPriceFromHotels(prefHotels);
        }
        if (!price && hasPromo) price = promoCptileMinPriceFromPrefetch(countryId);
        /* Популярные: без акции в манифесте — не скрываем, но цену из манифеста всё равно рисуем ниже. */
        if (!hasPromo && opts.fromManifest && pop && !opts.force && !(price > 0)) {
            return;
        }
        if (!hasPromo && opts.fromManifest && promoIsInstantCacheCountry(id) && !prefHotels && !opts.force) {
            return;
        }
        if (!hasPromo && prev && prev.hasPromo && (prev.fromTours || opts.fromTours) && !opts.force) {
            promoPaintCptileDom(id, prev.hasPromo, prev.minPrice, prev.isPopular);
            return;
        }
        if (!hasPromo && prev && prev.hasPromo && opts.fromManifest && !opts.force) {
            promoPaintCptileDom(id, prev.hasPromo, prev.minPrice, prev.isPopular);
            return;
        }
        /* Цена из promo-search (fromTours) важнее манифеста: там те же фильтры, что в списке туров. */
        if (opts.fromManifest && prev && prev.fromTours && prev.minPrice > 0) {
            price = prev.minPrice;
            hasPromo = true;
            opts = Object.assign({}, opts, { fromTours: true });
        } else if (hasPromo && prev && prev.hasPromo && prev.minPrice > 0 && price > 0) {
            if (opts.fromTours || prev.fromTours) {
                if (!opts.fromTours || price <= 0) {
                    price = prev.minPrice;
                }
            } else {
                price = Math.min(prev.minPrice, price);
            }
        }
        PROMO_CPTILE_RESOLVED[id] = {
            hasPromo: !!hasPromo,
            minPrice: price,
            isPopular: pop,
            fromTours: !!(opts.fromTours || (prev && prev.fromTours))
        };
        promoPaintCptileDom(id, !!hasPromo, price, pop);
    }

    var COUNTRIES_WITH_IMAGES_URL = '/backend/api/countries-with-images.php';
    var LOCAL_COUNTRY_IMAGES = cfg.localCountryImages || {};
    var PROMO_COUNTRY_FALLBACK_IMAGES = cfg.promoCountryFallbackImages || {};
    var DEPARTURE_CITY_IMAGES = cfg.departureCityImages || {};
    function regionDisplayName(r) {
        if (!r) return '';
        return (r.name || r.russianName || '').toString().trim();
    }

    function getImageUrl(src) {
        if (!src) return 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=400';
        if (window.THTourCard && typeof window.THTourCard.mapTourvisorImageUrl === 'function') {
            var mapped = window.THTourCard.mapTourvisorImageUrl(src, TV_IMAGE_PROXY);
            return mapped || 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=400';
        }
        var s = String(src).trim();
        if (/^\/\//.test(s)) {
            s = (typeof location !== 'undefined' && location.protocol === 'https:' ? 'https:' : 'http:') + s;
        }
        if (/^https?:\/\/static\.tourvisor\.ru\//i.test(s)) {
            return TV_IMAGE_PROXY + '?url=' + encodeURIComponent(s.replace(/^https:/i, 'http:'));
        }
        if (/^static\.tourvisor\.ru\//i.test(s)) {
            return TV_IMAGE_PROXY + '?url=' + encodeURIComponent('http://' + s);
        }
        if (/^\/hotel_pics\//i.test(s) || /^hotel_pics\//i.test(s)) {
            return TV_IMAGE_PROXY + '?path=' + encodeURIComponent(s.replace(/^\/+/, ''));
        }
        if (!/^https?:\/\//i.test(s) && /^hotel_pics\//i.test(s)) {
            return getImageUrl('https://static.tourvisor.ru/' + s.replace(/^\/+/, ''));
        }
        return s;
    }

    function promoCardPrimaryImage(h) {
        var raw = thPromoHotelPhotoUrls(h);
        for (var i = 0; i < raw.length; i++) {
            var u = getImageUrl(raw[i]);
            if (u && u.indexOf('unsplash.com') === -1) return u;
        }
        return raw.length ? getImageUrl(raw[0]) : getImageUrl('');
    }
    function formatPrice(n) {
        var num = Number(n);
        if (isNaN(num)) return '0 ₽';
        return new Intl.NumberFormat('ru-RU', { style: 'currency', currency: 'RUB', maximumFractionDigits: 0 }).format(num);
    }

    /** Количество взрослых из фильтра главной (localStorage), по умолчанию 2. */
    function thPromoAdultsCount() {
        try {
            var raw = localStorage.getItem('tv_tourists');
            if (raw) {
                var j = JSON.parse(raw);
                if (j && typeof j.adults === 'number' && j.adults >= 1 && j.adults <= 9) return j.adults;
            }
        } catch (e) {}
        return 2;
    }
    function thPromoPriceSuffix(adults) {
        var n = (adults >= 1 && adults <= 9) ? adults : 2;
        return 'за ' + n + ' взрослых';
    }
    function thPromoTourPhotoNormalizeKey(u) {
        if (!u || typeof u !== 'string') return '';
        var s = u.trim();
        if (!s) return '';
        try {
            var abs = /^https?:/i.test(s) ? s : (s.indexOf('//') === 0 ? 'https:' + s : 'https://' + s.replace(/^\/+/, ''));
            var x = new URL(abs);
            var host = x.hostname.toLowerCase().replace(/^www\./, '');
            var path = (x.pathname || '/').replace(/\/+/g, '/');
            if (path.length > 1) path = path.replace(/\/+$/, '');
            return host + path + (x.search || '');
        } catch (e) {
            return s.toLowerCase().replace(/\/+$/, '');
        }
    }
    /** До 6 уникальных фото отеля (TourVisor: picturelink + pictures[]), без повторов по URL. */
    function thPromoHotelPhotoUrls(h) {
        var raw = [];
        if (window.THTourCard && typeof window.THTourCard.collectHotelPhotoRawUrls === 'function') {
            raw = window.THTourCard.collectHotelPhotoRawUrls(h);
        } else if (h) {
            raw.push((h.picturelink || h.pictureLink || '').toString());
            var pics = h.pictures;
            if (pics && Array.isArray(pics)) {
                pics.forEach(function (p) {
                    if (typeof p === 'string') raw.push(p);
                    else if (p && typeof p === 'object') raw.push((p.src || p.url || p.link || p.picturelink || p.pictureLink || '').toString());
                });
            }
        }
        var urls = [];
        var seen = {};
        raw.forEach(function (u) {
            if (!u || typeof u !== 'string') return;
            u = u.trim();
            if (!u) return;
            var k = thPromoTourPhotoNormalizeKey(u);
            if (!k || seen[k]) return;
            seen[k] = true;
            urls.push(u);
        });
        if (urls.length === 0) {
            urls.push('https://images.unsplash.com/photo-1566073771259-6a8506099945?w=400');
        }
        return urls.slice(0, (window.THTourCard && window.THTourCard.PHOTO_SLIDE_MAX) ? window.THTourCard.PHOTO_SLIDE_MAX : 6);
    }

    /** Первое положительное число из списка (Tourvisor отдаёт цену в разных полях). */
    function promoPickFirstPriceNum() {
        var args = Array.prototype.slice.call(arguments);
        for (var i = 0; i < args.length; i++) {
            var v = args[i];
            if (v == null || v === '') continue;
            var n = Number(v);
            if (!isNaN(n) && n > 0) return n;
        }
        return 0;
    }

    /**
     * Цена для карточки и сортировки: минимум по всем турам отеля (после merge нескольких окон ночей).
     */
    function promoTourPrice(tour, hotel) {
        if (!tour) return 0;
        var n = promoPickFirstPriceNum(
            tour.totalPrice,
            tour.price,
            tour.priceRub,
            tour.cost
        );
        if (n > 0) return n;
        if (hotel) {
            n = promoPickFirstPriceNum(hotel.price);
            if (n > 0) return n;
        }
        return 0;
    }

    function promoHotelListPrice(h) {
        if (!h) return 0;
        if (h.tours && h.tours.length) {
            var min = 0;
            for (var pi = 0; pi < h.tours.length; pi++) {
                var p = promoTourPrice(h.tours[pi], h);
                if (p > 0 && (min === 0 || p < min)) min = p;
            }
            if (min > 0) return Math.round(min);
            var fallback = promoPickFirstPriceNum(h.price);
            return fallback > 0 ? Math.round(fallback) : 0;
        }
        return Math.round(promoPickFirstPriceNum(h.price, h.minPrice, h.minprice));
    }

    function promoToursFromToday(h) {
        if (!h || !Array.isArray(h.tours) || !h.tours.length) return [];
        var today = formatLocalYMD(new Date());
        return h.tours.filter(function (t) {
            if (!t) return false;
            var ymd = promoTourStartYmd(t);
            return !ymd || ymd >= today;
        });
    }

    function promoPickBestTour(h) {
        var tours = promoToursFromToday(h);
        if (!tours.length) return {};
        var best = tours[0];
        var bestPrice = promoTourPrice(best, h);
        for (var bi = 1; bi < tours.length; bi++) {
            var t = tours[bi];
            var bp = promoTourPrice(t, h);
            if (bp > 0 && (bestPrice === 0 || bp < bestPrice)) {
                best = t;
                bestPrice = bp;
            }
        }
        return best || {};
    }

    function promoPromoteCheapestTour(h) {
        if (!h || !h.tours || h.tours.length < 2) return h;
        var today = formatLocalYMD(new Date());
        var bestIdx = -1;
        var bestPrice = 0;
        for (var ci = 0; ci < h.tours.length; ci++) {
            var ymd = promoTourStartYmd(h.tours[ci]);
            if (ymd && ymd < today) continue;
            var cp = promoTourPrice(h.tours[ci], h);
            if (bestIdx < 0 || (cp > 0 && (bestPrice === 0 || cp < bestPrice))) {
                bestIdx = ci;
                bestPrice = cp;
            }
        }
        if (bestIdx < 0) return h;
        if (bestIdx > 0) {
            var picked = h.tours[bestIdx];
            h.tours.splice(bestIdx, 1);
            h.tours.unshift(picked);
        }
        return h;
    }

    function promoPrepareHotelListForDisplay(hotels) {
        return (hotels || []).map(function (h) {
            if (!h) return h;
            var copy;
            try {
                copy = JSON.parse(JSON.stringify(h));
            } catch (e) {
                copy = h;
            }
            return promoPromoteCheapestTour(copy);
        });
    }

    function updatePromoHotelCardPrices(hotels) {
        if (!hotels || !hotels.length) return;
        var priceMap = {};
        hotels.forEach(function (h) {
            if (!h) return;
            var hotelId = h.id != null && h.id !== '' ? String(h.id) : (h.hotel_id != null ? String(h.hotel_id) : '');
            if (!hotelId) return;
            var p = promoHotelListPrice(h);
            if (p > 0 && (!priceMap[hotelId] || p < priceMap[hotelId])) {
                priceMap[hotelId] = p;
            }
        });
        Object.keys(priceMap).forEach(function (hotelId) {
            var minPrice = priceMap[hotelId];
            var escId = hotelId.replace(/\\/g, '\\\\').replace(/"/g, '\\"');
            document.querySelectorAll('.th-tour-card[data-th-hotel-id="' + escId + '"]').forEach(function (card) {
                if (card.classList.contains('th-tour-card--promo')) return;
                var priceEl = card.querySelector('.th-tour-card__price');
                if (!priceEl) return;
                var curDigits = String(priceEl.textContent || '').replace(/\D/g, '');
                var cur = curDigits ? parseInt(curDigits, 10) : 0;
                if (cur === minPrice) return;
                if (cur > 0 && cur < minPrice) return;
                priceEl.textContent = formatPrice(minPrice);
            });
        });
    }

    function formatDateRu(dateStr) {
        if (!dateStr) return '';
        try {
            var iso = dateStr;
            if (/^\d{4}-\d{2}-\d{2}$/.test(String(dateStr).trim())) {
                iso = String(dateStr).trim() + 'T12:00:00';
            }
            return new Date(iso).toLocaleDateString('ru-RU', { day: 'numeric', month: 'long', year: 'numeric' });
        } catch (e) { return dateStr; }
    }

    /** Дата вылета тура (YYYY-MM-DD) из ответа Tourvisor. */
    function promoTourStartYmd(tour) {
        if (!tour) return '';
        var raw = tour.date || tour.startDate || tour.departureDate || tour.flydate || tour.flyDate || '';
        var s = (raw != null) ? String(raw).trim() : '';
        if (!s) return '';
        var m = s.match(/^(\d{4}-\d{2}-\d{2})/);
        if (m) return m[1];
        try {
            var d = new Date(s);
            if (!isNaN(d.getTime())) return formatLocalYMD(d);
        } catch (e2) {}
        return '';
    }

    /** Дата возвращения: первая ночь = date, checkout после nights ночей. */
    function promoReturnYmd(startYmd, nightsNum) {
        if (!startYmd || !(nightsNum > 0)) return '';
        var p = startYmd.split('-');
        if (p.length !== 3) return '';
        var d = new Date(parseInt(p[0], 10), parseInt(p[1], 10) - 1, parseInt(p[2], 10), 12, 0, 0);
        if (isNaN(d.getTime())) return '';
        d.setDate(d.getDate() + nightsNum);
        return formatLocalYMD(d);
    }

    /** countryId отеля в ответе поиска Tourvisor (поля различаются). */
    function hotelSearchCountryId(h) {
        if (!h) return '';
        if (h.country && h.country.id != null && h.country.id !== '') return String(h.country.id);
        if (h.countryId != null && h.countryId !== '') return String(h.countryId);
        if (h.country_id != null && h.country_id !== '') return String(h.country_id);
        return '';
    }

    /**
     * Слияние отелей из нескольких ответов API (разные окна ночей).
     * Ключ: countryId + hotelId — id отеля у Tourvisor не глобально уникален, иначе туры «едут» между странами.
     * expectedCountryId: отбрасываем чужие отели (битый кэш/перемешанные ответы).
     * Если строгий фильтр по стране обнуляет выдачу — повтор без него (иначе Москва и др. теряли туры).
     */
    function mergePromoHotelDataArrays(dataArrays, expectedCountryId) {
        var ec = expectedCountryId != null && expectedCountryId !== '' ? String(expectedCountryId) : '';
        function mergePass(skipCountryFilter) {
            var expectedHotelCid = ec ? promoExpectedHotelCountryId(ec) : '';
            var byKey = {};
            dataArrays.forEach(function (arr) {
                if (!arr || !Array.isArray(arr)) return;
                arr.forEach(function (h) {
                    if (!h || h.id == null) return;
                    if (ec && !skipCountryFilter) {
                        var cid = hotelSearchCountryId(h);
                        if (cid !== '' && cid !== expectedHotelCid) return;
                    }
                    var hid = String(h.id);
                    var key = ec ? (ec + '_' + hid) : hid;
                    if (!byKey[key]) {
                        try {
                            byKey[key] = JSON.parse(JSON.stringify(h));
                        } catch (e) {
                            byKey[key] = h;
                        }
                        return;
                    }
                    var merged = byKey[key];
                    var tourIds = {};
                    (merged.tours || []).forEach(function (t) {
                        if (t && t.id != null) tourIds[String(t.id)] = true;
                    });
                    (h.tours || []).forEach(function (t) {
                        if (!t || t.id == null) return;
                        var tid = String(t.id);
                        if (tourIds[tid]) return;
                        tourIds[tid] = true;
                        if (!merged.tours) merged.tours = [];
                        merged.tours.push(t);
                    });
                });
            });
            return Object.keys(byKey).map(function (k) { return byKey[k]; });
        }
        var merged = mergePass(false);
        if (ec && merged.length === 0) {
            var loose = mergePass(true);
            if (loose.length > 0) return promoFilterVirtualDestinationHotels(loose, ec);
        }
        return promoFilterVirtualDestinationHotels(merged, ec);
    }

    /** Разбор ответа fetch для search-cached (общий для нескольких параллельных запросов по ночам). */
    function parseTourvisorSearchJsonResponse(o) {
        var t = (o.text || '').trim();
        if (!o.ok) return { success: false, error: 'HTTP ' + (o.status || 500), data: [] };
        if (!t) return { success: false, error: 'Empty response', data: [] };
        try {
            var j = JSON.parse(t);
            var src = j.promoSearchSource || o.promoSource || null;
            if (!src && o.searchMode === 'cache') {
                src = o.cacheRead === 'promo_country' ? 'promo_country_file' : (o.cacheRead === 'promo' ? 'search_cache' : 'cache');
            }
            if (!src && o.searchMode === 'live') src = 'live_api';
            if (src) j.promoSearchSource = src;
            return j;
        } catch (e) {
            return { success: false, error: 'Invalid JSON', data: [] };
        }
    }

    function promoSearchRequestLogTitle(j) {
        var src = (j && j.promoSearchSource) ? j.promoSearchSource : (j && j.fromCache ? 'promo_speed_file' : 'live_api');
        if (src === 'promo_speed_file' || (j && j.fromCache)) {
            return 'promo-search (файловый кэш)';
        }
        if (src === 'tour_hots_api') {
            return 'promo-search (горящие /tours/hots, live)';
        }
        return 'promo-search (live API)';
    }

    function promoLogSearchResponse(label, j, meta) {
        meta = meta || {};
        var src = (j && j.promoSearchSource) ? j.promoSearchSource : (j && j.fromCache ? 'cache' : 'live_api');
        var srcLabel = {
            promo_country_file: 'файловый кэш акций (promo_cache)',
            promo_speed_file: 'файловый кэш data/promo_cache_*',
            promo_speed_merged: 'живой promo-search (legacy)',
            tour_hots_api: 'горящие туры GET /tours/hots',
            search_cache: 'кэш поиска (search)',
            live_api: 'живой запрос Tourvisor API',
            cache: 'кэш'
        }[src] || src;
        var style = (src === 'promo_speed_merged' || src === 'live_api') ? 'color:#f59e0b;font-weight:bold' : 'color:#22c55e;font-weight:bold';
        console.groupCollapsed('%c[Акции: поиск] ' + label + ' — ' + srcLabel, style);
        console.log('meta:', meta);
        console.log('success:', !!(j && j.success), 'error:', j && j.error);
        console.log('promoSearchSource:', src, 'fromCache:', !!(j && j.fromCache));
        console.log('hotels:', (j && j.data && j.data.length) || 0);
        if (PROMO_LIVE_ONLY && j && j.fromCache) {
            console.warn('[Акции] Неожиданный fromCache при режиме live-only');
        }
        console.log('raw response:', j);
        promoDebugSummarizeHotels(j && j.data, 'сводка ответа');
        console.groupEnd();
    }

    function promoSearchUrlForCountry(countryId, nightsFrom, nightsTo) {
        var dFrom = new Date(); dFrom.setDate(dFrom.getDate() + PROMO_DATE_PLUS_FROM);
        var dTo = new Date(); dTo.setDate(dTo.getDate() + promoDatePlusTo(countryId));
        var params = {
            type: 'search-cached',
            departureId: String(DEPARTURE_ID || DEFAULT_DEPARTURE_ID),
            countryId: String(countryId),
            dateFrom: formatLocalYMD(dFrom),
            dateTo: formatLocalYMD(dTo),
            nightsFrom: String(nightsFrom),
            nightsTo: String(nightsTo),
            adults: String(thPromoAdultsCount()),
            onlyPromo: '1',
            _t: String(Date.now())
        };
        var b = TV_API_BASE.replace(/\/$/, '');
        var s = b.indexOf('?') >= 0 ? '&' : '?';
        return b + s + new URLSearchParams(params).toString();
    }

    /** promo-search: сервер отдаёт кэш (promo_speed_file), при промахе — live и запись в кэш. */
    function promoBundledSearchParams(countryId, departureId, searchOpts) {
        searchOpts = searchOpts || {};
        var dFrom = new Date();
        dFrom.setDate(dFrom.getDate() + PROMO_DATE_PLUS_FROM);
        var dTo = new Date();
        dTo.setDate(dTo.getDate() + promoDatePlusTo(countryId));
        var params = {
            type: 'promo-search',
            departureId: String(departureId != null && departureId !== '' ? departureId : (DEPARTURE_ID || DEFAULT_DEPARTURE_ID)),
            countryId: String(countryId),
            dateFrom: formatLocalYMD(dFrom),
            dateTo: formatLocalYMD(dTo),
            adults: String(thPromoAdultsCount()),
            _t: String(Date.now())
        };
        if (PROMO_LIVE_ONLY) {
            params.live = '1';
            params.bypassCache = '1';
        } else if (searchOpts.cacheOnly) {
            params.cacheOnly = '1';
        }
        return params;
    }

    function fetchPromoSearchForDeparture(countryId, departureId, timeoutMs, searchOpts) {
        var base = TV_API_BASE.replace(/\/$/, '');
        var sep = base.indexOf('?') >= 0 ? '&' : '?';
        var params = promoBundledSearchParams(countryId, departureId, searchOpts);
        var url = base + sep + new URLSearchParams(params).toString();
        promoDebugLog('promo-search REQUEST', { url: url, params: params, timeoutMs: timeoutMs || 120000 });
        var t0 = Date.now();
        return safeFetchJsonWithTimeout(url, { success: false, data: [] }, timeoutMs || 120000).then(function (j) {
            promoDebugLog('promo-search RESPONSE timing', { countryId: countryId, departureId: departureId, ms: Date.now() - t0 });
            promoApplyFlightsFromSearchResponse(j);
            return j;
        });
    }

    function fetchPromoSearchBundled(countryId, timeoutMs, searchOpts) {
        var primaryDep = promoCurrentDepartureKey();
        var fallbackDep = promoWarmDepartureFallbackKey();
        return fetchPromoSearchForDeparture(countryId, primaryDep, timeoutMs, searchOpts).then(function (j) {
            if (j && j.success && Array.isArray(j.data) && j.data.length) return j;
            if (fallbackDep === primaryDep) return j;
            return fetchPromoSearchForDeparture(countryId, fallbackDep, timeoutMs, searchOpts).then(function (j2) {
                if (j2 && j2.success && Array.isArray(j2.data) && j2.data.length) return j2;
                return j;
            });
        });
    }

    /**
     * Мгновенная выдача: сначала только файл (cacheOnly), при промахе — обычный promo-search.
     * Не уходим сразу в live, если на сервере уже есть promo_cache_*.
     */
    function fetchPromoSearchBundledPreferCache(countryId, timeoutMs) {
        var cacheTimeout = Math.min(20000, Math.max(8000, (timeoutMs || 120000) / 6));
        return fetchPromoSearchBundled(countryId, cacheTimeout, { cacheOnly: true }).then(function (j) {
            var n = (j && Array.isArray(j.data)) ? j.data.length : 0;
            if (j && j.success && n >= PROMO_BLEND_MIN_HOTELS) {
                return j;
            }
            if (j && j.success && n > 0 && n < PROMO_BLEND_MIN_HOTELS) {
                return fetchPromoSearchBundled(countryId, timeoutMs || 120000).then(function (j2) {
                    var n2 = (j2 && Array.isArray(j2.data)) ? j2.data.length : 0;
                    if (j2 && j2.success && n2 > n) return j2;
                    return j;
                });
            }
            if (j && j.success && n > 0) {
                return j;
            }
            return fetchPromoSearchBundled(countryId, timeoutMs || 120000);
        });
    }

    function fetchPromoCacheIndex() {
        var base = TV_API_BASE.replace(/\/$/, '');
        var sep = base.indexOf('?') >= 0 ? '&' : '?';
        return safeFetchJsonWithTimeout(base + sep + 'type=promo-cache-index', { success: false, data: {} }, 15000);
    }
    function promoHotelDestinationLabel(h) {
        if (!h) return '';
        var parts = [];
        if (h.region) {
            if (typeof h.region === 'string') parts.push(h.region);
            else parts.push((h.region.name || h.region.russianName || '').toString());
        }
        if (h.regionName) parts.push(String(h.regionName));
        if (h.city) {
            parts.push(typeof h.city === 'string' ? h.city : (h.city.name || h.city.russianName || '').toString());
        }
        if (h.resort) {
            parts.push(typeof h.resort === 'string' ? h.resort : (h.resort.name || h.resort.russianName || '').toString());
        }
        return parts.filter(Boolean).join(' ').trim();
    }

    function promoCardGeoCountryName(h, cardCountryId) {
        if (isSochiPromoCountry(cardCountryId)) return 'Сочи';
        if (isPhuQuocPromoCountry(cardCountryId)) return 'Фукуок';
        return (h && h.country && h.country.name) ? h.country.name : '';
    }

    function promoRegionName(h) {
        return promoHotelDestinationLabel(h);
    }

    function promoRegionIsSochiDestination(region) {
        var r = (region || '').toString().trim();
        if (!r) return false;
        if (/\b(москва|московск|подмосков|санкт-петербург|петербург|спб|казань|новосибирск|екатеринбург|нижний\s+новгород|воронеж|ростов|красноярск|уфа|пермь|самара|волгоград|краснодар|калининград|мурманск|тюмень|омск|челябинск|иркутск|хабаровск|владивосток|тула|ярославль|смоленск|брянск|калуга|владимир|рязань|архангельск|псков|петрозаводск|великий\s+новгород)\b/i.test(r)) {
            return false;
        }
        return /(сочи|sochi|адлер|adler|хоста|лазаревск|лоо|дагомыс|мацеста|кудепста|красная\s*поляна|роза\s*хутор|эсто-?садок|имеретинск|имеретинский|сириус|olymp|олимпийск)/i.test(r);
    }

    function promoRegionIsTurkeyResortDestination(region) {
        var r = (region || '').toString().trim();
        if (!r) return true;
        /* Страховка на клиенте: Стамбул не показываем в акциях Турции. */
        if (/\b(istanbul|стамбул)\b/i.test(r)) return false;
        return true;
    }

    function promoFilterTurkeyDestinationHotels(data, countryId) {
        if (String(countryId) !== '4') {
            return Array.isArray(data) ? data.slice() : [];
        }
        var list = Array.isArray(data) ? data : [];
        if (!list.length) return [];
        var filtered = list.filter(function (h) {
            return h && promoRegionIsTurkeyResortDestination(promoRegionName(h));
        });
        return filtered.length ? filtered : list;
    }

    function promoFilterHotelsWithTours(data) {
        return (Array.isArray(data) ? data : []).filter(function (h) {
            return h && Array.isArray(h.tours) && h.tours.length > 0;
        });
    }

    /** Для countryId 47: только курорты Сочи (регион/город/курорт, без названия отеля). */
    function promoFilterSochiDestinationHotels(data, countryId) {
        if (String(countryId) !== PROMO_COUNTRY_ID_SOCHI) {
            return Array.isArray(data) ? data.slice() : [];
        }
        var list = Array.isArray(data) ? data : [];
        if (!list.length) return [];
        return list.filter(function (h) {
            return h && promoRegionIsSochiDestination(promoRegionName(h));
        });
    }

    function promoRegionIsPhuQuocDestination(region) {
        var r = (region || '').toString().trim();
        if (!r) return false;
        return /фу\s*к\s*уок|фукуок|phu\s*quoc|phuquoc|phú\s*quốc/i.test(r);
    }

    /** Для countryId 16104: только курорт Фукуок (остальной Вьетнам отсекается). */
    function promoFilterPhuQuocDestinationHotels(data, countryId) {
        if (String(countryId) !== PROMO_COUNTRY_ID_PHUQUOC) {
            return Array.isArray(data) ? data.slice() : [];
        }
        var list = Array.isArray(data) ? data : [];
        if (!list.length) return [];
        return list.filter(function (h) {
            return h && promoRegionIsPhuQuocDestination(promoRegionName(h));
        });
    }

    /** Плитка «Вьетнам» (16): без Фукуок — отдельная плитка 16104. */
    function promoFilterVietnamExcludePhuQuocHotels(data, countryId) {
        if (!isVietnamPromoCountry(countryId) || isPhuQuocPromoCountry(countryId)) {
            return Array.isArray(data) ? data.slice() : [];
        }
        var list = Array.isArray(data) ? data : [];
        if (!list.length) return [];
        return list.filter(function (h) {
            return h && !promoRegionIsPhuQuocDestination(promoRegionName(h));
        });
    }

    function promoFilterVirtualDestinationHotels(data, countryId) {
        var list = promoFilterSochiDestinationHotels(data, countryId);
        list = promoFilterPhuQuocDestinationHotels(list, countryId);
        return promoFilterVietnamExcludePhuQuocHotels(list, countryId);
    }

    function promoPostProcessHotelList(data, countryId) {
        var countryName = (typeof uCurrentCountryName !== 'undefined' && uCurrentCountryName) ? uCurrentCountryName : COUNTRY_NAME;
        var list = promoFilterVirtualDestinationHotels(Array.isArray(data) ? data : [], countryId);
        list = promoFilterTurkeyDestinationHotels(list, countryId);
        /* Для Сочи departureId уже в запросе API; фильтр по кодам в tour.name отрезал лишнее */
        if (String(countryId) !== PROMO_COUNTRY_ID_SOCHI) {
            var depForFilter = DEPARTURE_ID;
            try {
                if (window.__promoResultDepartureId) depForFilter = window.__promoResultDepartureId;
            } catch (eDepF) {}
            list = promoFilterHotelsForDeparture(list, depForFilter);
        }
        list = promoFilterHotelsMinNights(list, countryId);
        /* Турция: 4–5★; Египет: 3–5★; ночи 6–13; оператор-фильтр — сокращённый список. */
        if (isPromoTrOrEg(countryId)) {
            list = promoFilterHotelsTrEgStars(list, countryId);
        }
        list = promoFilterHotelsByAllowedOperators(list, countryId, countryName);
        list = promoFilterHotelsMinListPrice(list, countryId);
        return promoFilterHotelsWithTours(list);
    }

    /** После finalize не терять уже загруженную выдачу (blend иногда сильно урезает список). Для Сочи — всегда строгий фильтр курортов. */
    function promoPickDisplayHotels(finalized, rawFallback, countryId) {
        var cid = countryId != null ? String(countryId) : promoActiveCountryId();
        var fin = Array.isArray(finalized) ? finalized : [];
        var raw = Array.isArray(rawFallback) ? rawFallback : [];
        var pick;
        if (
            !isPromoTrOrEg(cid)
            && cid !== PROMO_COUNTRY_ID_SOCHI
            && cid !== PROMO_COUNTRY_ID_PHUQUOC
            && !isVietnamPromoCountry(cid)
            && raw.length > fin.length
            && raw.length >= 3
        ) {
            pick = raw;
        } else if (fin.length) {
            pick = fin;
        } else if (raw.length) {
            pick = raw;
        } else {
            return [];
        }
        if (cid === PROMO_COUNTRY_ID_SOCHI) {
            return promoFilterSochiDestinationHotels(pick, cid);
        }
        if (cid === PROMO_COUNTRY_ID_PHUQUOC) {
            return promoFilterPhuQuocDestinationHotels(pick, cid);
        }
        if (isVietnamPromoCountry(cid)) {
            return promoFilterVietnamExcludePhuQuocHotels(pick, cid);
        }
        return pick;
    }

    /**
     * SWR для акционных туров: мгновенно показать последний успешный снимок (sessionStorage),
     * если ему не больше PROMO_TOURS_SWR_MAX_MS и совпали город/страна/взрослые/окно дат.
     * Сетевой запрос всё равно выполняется — UI и кэш обновляются ответом API (данные актуальны).
     */
    var PROMO_TOURS_SWR_MAX_MS = 90 * 1000;
    var PROMO_TOURS_SWR_PREFIX = 'th_promo_tours_swr:';

    function promoToursSwrCacheKey(departureId, countryId, adults, dateFrom, dateTo) {
        return PROMO_TOURS_SWR_PREFIX + String(departureId) + ':' + String(countryId) + ':' + String(adults) + ':' + String(dateFrom) + ':' + String(dateTo);
    }
    function promoToursSwrRead(key) {
        if (PROMO_LIVE_ONLY) return null;
        try {
            var raw = sessionStorage.getItem(key);
            if (!raw) return null;
            var o = JSON.parse(raw);
            if (!o || typeof o.t !== 'number' || !Array.isArray(o.data)) return null;
            return o;
        } catch (e) { return null; }
    }
    function promoToursSwrReadForCountry(key, countryId) {
        var ent = promoToursSwrRead(key);
        if (!ent) return null;
        if ((Date.now() - ent.t) > promoToursSwrMaxAgeMs(countryId)) return null;
        return ent;
    }
    function promoToursSwrWrite(key, dataArr) {
        if (PROMO_LIVE_ONLY) return;
        try {
            var s = JSON.stringify({ t: Date.now(), data: dataArr });
            if (s.length > 4500000) return;
            sessionStorage.setItem(key, s);
        } catch (e) {
            try { sessionStorage.removeItem(key); } catch (e2) {}
        }
    }
    function promoToursTrySwrPaint(opts) {
        if (PROMO_LIVE_ONLY) return false;
        var swrKey = opts.swrKey;
        var countryId = opts.countryId;
        var seqOk = opts.seqOk;
        var onPaint = opts.onPaint;
        if (!swrKey || typeof seqOk !== 'function' || typeof onPaint !== 'function') return false;
        if (!seqOk()) return false;
        var ent = promoToursSwrReadForCountry(swrKey, countryId);
        if (!ent) return false;
        var raw = promoCoerceHotelsAfterPostProcess(ent.data, countryId);
        if (raw.length === 0) return false;
        if (!seqOk()) return false;
        raw.sort(function (a, b) { return promoHotelListPrice(a) - promoHotelListPrice(b); });
        onPaint(raw);
        promoSyncCptileMinPrice(countryId, raw);
        promoFinalizeTourResults(raw, countryId).then(function (data) {
            if (!seqOk()) return;
            var display = promoPickDisplayHotels(data, raw, countryId);
            if (!display.length) return;
            display.sort(function (a, b) { return promoHotelListPrice(a) - promoHotelListPrice(b); });
            onPaint(display);
            promoSyncCptileMinPrice(countryId, display);
        });
        return true;
    }

    function jsonCountryHasPromoTours(j, countryIdForRule) {
        if (!j || !j.success || !Array.isArray(j.data)) return false;
        return j.data.some(function (h) {
            return h && Array.isArray(h.tours) && h.tours.length > 0;
        });
    }
    /**
     * Быстрая проверка «есть ли акции» по манифесту data/promo_cache_index.json (крон).
     * При ошибке — fallback на runPromoChecksForCountriesInBackground.
     */
    function runPromoChecksFromManifest(countries, onResult, onComplete, manifestOpts) {
        if (PROMO_LIVE_ONLY) {
            promoDebugLog('плитки стран: без манифеста кэша, только live API');
            runPromoChecksForCountriesInBackground(countries, onResult, onComplete);
            return;
        }
        function applyBlock(block, source) {
            if (!block) return false;
            promoApplyManifestToCountries(countries);
            countries.forEach(function (c) {
                var ent = promoGetManifestEntry(c.id);
                onResult(c, !!(ent.has || (ent.minPrice && ent.minPrice > 0)), ent.minPrice || 0, { fromManifest: true });
            });
            if (typeof onComplete === 'function') onComplete();
            if (source === 'inline') {
                console.log('%c[Акции] Манифест из страницы (кэш)', 'color:#22c55e;font-weight:bold', { departureId: promoCurrentDepartureKey() });
            } else {
                console.log('%c[Акции] Манифест кэша загружен', 'color:#22c55e;font-weight:bold', { departureId: promoCurrentDepartureKey() });
            }
            return true;
        }

        var inlineBlock = promoGetManifestBlock();
        if (applyBlock(inlineBlock, 'inline')) {
            fetchPromoCacheIndex().then(function (res) {
                var depKey = promoCurrentDepartureKey();
                var remote = (res && res.success && res.data && typeof res.data === 'object') ? res.data[depKey] : null;
                if (remote) {
                    PROMO_CACHE_INDEX_BY_DEP[depKey] = remote;
                    countries.forEach(function (c) {
                        var ent = promoGetManifestEntry(c.id);
                        onResult(c, !!ent.has, ent.minPrice || 0, { fromManifest: true });
                    });
                }
            }).catch(function () {});
            promoMaybePrefetchAfterManifest(countries, manifestOpts);
            return;
        }

        fetchPromoCacheIndex().then(function (res) {
            var depKey = promoCurrentDepartureKey();
            var block = (res && res.success && res.data && typeof res.data === 'object') ? res.data[depKey] : null;
            if (block) {
                PROMO_CACHE_INDEX_BY_DEP[depKey] = block;
            }
            if (!applyBlock(block, 'api')) {
                console.warn('[Акции] Манифест кэша пуст — fallback на проверку через API');
                runPromoChecksForCountriesInBackground(countries, onResult, onComplete);
                return;
            }
            promoMaybePrefetchAfterManifest(countries, manifestOpts);
        }).catch(function () {
            runPromoChecksForCountriesInBackground(countries, onResult, onComplete);
        });
    }

    /** Prefetch цен на плитках: только популярные, с задержкой; «все страны» — без API. */
    function promoMaybePrefetchAfterManifest(countries, manifestOpts) {
        if (PROMO_LIVE_ONLY) return;
        manifestOpts = manifestOpts || {};
        if (manifestOpts.prefetch === false) return;
        var ids = countries.map(function (c) { return c.id; }).filter(function (id) {
            return id && !promoIsExcludedCountryId(id);
        });
        if (!ids.length) return;
        var delayMs = typeof manifestOpts.prefetchDelayMs === 'number' ? manifestOpts.prefetchDelayMs : 0;
        var prefetchOpts = manifestOpts.prefetchCacheOnly ? { cacheOnly: true } : null;
        var run = function () {
            if (window.__promoPrefetchPaused) return;
            promoPrefetchToursForCountries(ids, prefetchOpts);
        };
        if (delayMs > 0) {
            setTimeout(run, delayMs);
        } else {
            run();
        }
    }

    function promoPrefetchInstantTours() {
        promoPrefetchToursForCountries(null);
    }

    /** Предзагрузка promo-search для цен на плитках. opts.cacheOnly — только файл, без live (лимит Tourvisor). */
    function promoPrefetchToursForCountries(countryIds, prefetchOpts) {
        if (PROMO_LIVE_ONLY || window.__promoPrefetchPaused) return;
        prefetchOpts = prefetchOpts || {};
        var depId = promoCurrentDepartureKey();
        var ids = (countryIds && countryIds.length)
            ? countryIds.map(function (id) { return String(id); }).filter(function (id) { return id && !promoIsExcludedCountryId(id); })
            : promoInstantCountryIds();
        if (!ids.length) return;
        var dFrom = new Date();
        dFrom.setDate(dFrom.getDate() + PROMO_DATE_PLUS_FROM);
        var searchOpts = prefetchOpts.cacheOnly ? { cacheOnly: true } : null;
        var timeoutMs = prefetchOpts.cacheOnly ? 15000 : 120000;

        function prefetchOne(cid) {
            var countryId = String(cid);
            var storeKey = promoPrefetchStoreKey(depId, countryId);
            var existing = window.__promoPrefetchStore[storeKey];
            if (existing && (Date.now() - existing.t) < PROMO_TOURS_PREFETCH_MAX_MS) {
                if (existing.data && existing.data.length) {
                    promoSyncCptileForTile(countryId, promoPostProcessHotelList(existing.data, countryId));
                }
                return Promise.resolve();
            }
            var dTo = new Date();
            dTo.setDate(dTo.getDate() + promoDatePlusTo(countryId));
            var swrKey = promoToursSwrCacheKey(depId, countryId, thPromoAdultsCount(), formatLocalYMD(dFrom), formatLocalYMD(dTo));
            var swrEnt = promoToursSwrReadForCountry(swrKey, countryId);
            if (swrEnt && swrEnt.data && swrEnt.data.length) {
                var swrProcessed = promoPostProcessHotelList(swrEnt.data, countryId);
                window.__promoPrefetchStore[storeKey] = { t: swrEnt.t, data: swrProcessed };
                promoSyncCptileForTile(countryId, swrProcessed);
                return Promise.resolve();
            }
            return fetchPromoSearchBundled(countryId, timeoutMs, searchOpts).then(function (j) {
                promoApplyFlightsFromSearchResponse(j);
                var raw = (j && Array.isArray(j.data)) ? j.data : [];
                var data = promoPostProcessHotelList(raw, countryId);
                if (!data.length) {
                    if (prefetchOpts.cacheOnly) return;
                    if (usesNearestPromoFallback(countryId)) {
                        return applyNearestFallbackIfNeeded([], countryId).then(function (fb) {
                            if (fb && fb.length) {
                                window.__promoPrefetchStore[storeKey] = { t: Date.now(), data: fb };
                                promoToursSwrWrite(swrKey, fb);
                                promoSyncCptileForTile(countryId, fb);
                                return;
                            }
                            /* Не блокируем популярные плитки при пустом prefetch */
                            if (!promoCptileIsPopularCountry(countryId)) {
                                promoApplyCptilePromoState(countryId, false, 0, false, { fromTours: true, force: true });
                            }
                        });
                    } else if (!promoCptileIsPopularCountry(countryId)) {
                        promoApplyCptilePromoState(countryId, false, 0, false, { fromTours: true, force: true });
                    }
                    return;
                }
                window.__promoPrefetchStore[storeKey] = {
                    t: Date.now(),
                    data: data,
                    flightsByTourId: (j && j.flightsByTourId) ? j.flightsByTourId : null
                };
                promoToursSwrWrite(swrKey, data);
                promoSyncCptileForTile(countryId, data);
            }).catch(function () {});
        }

        /* Параллельно (до 3), а не цепочкой — быстрее бейджи цен на плитках */
        runLimitedConcurrency(ids, 3, function (cid) { return prefetchOne(cid); });
    }

    function promoTryInstantPrefetchPaint(opts) {
        if (PROMO_LIVE_ONLY) return false;
        var countryId = opts.countryId;
        var depId = promoCurrentDepartureKey();
        var storeKey = promoPrefetchStoreKey(depId, String(countryId));
        var pref = window.__promoPrefetchStore[storeKey];
        if (!pref || !Array.isArray(pref.data) || !pref.data.length) return false;
        if ((Date.now() - pref.t) > PROMO_TOURS_PREFETCH_MAX_MS) return false;
        promoApplyFlightsFromSearchResponse(pref.flightsByTourId ? { flightsByTourId: pref.flightsByTourId } : null);
        var seqOk = opts.seqOk;
        var onPaint = opts.onPaint;
        if (typeof seqOk !== 'function' || typeof onPaint !== 'function' || !seqOk()) return false;
        var raw = promoPostProcessHotelList(pref.data, countryId);
        if (!raw.length && pref.data.length) {
            raw = promoFilterHotelsWithTours(pref.data);
        }
        if (!raw.length) return false;
        raw.sort(function (a, b) { return promoHotelListPrice(a) - promoHotelListPrice(b); });
        onPaint(raw);
        promoSyncCptileMinPrice(countryId, raw);
        promoFinalizeTourResults(raw, countryId).then(function (data) {
            if (!seqOk()) return;
            var display = promoPickDisplayHotels(data, raw, countryId);
            if (!display.length) return;
            display.sort(function (a, b) { return promoHotelListPrice(a) - promoHotelListPrice(b); });
            onPaint(display);
            promoSyncCptileMinPrice(countryId, display);
        });
        return true;
    }

    /**
     * Фоновая проверка акций по странам (батчи). Для каждой страны вызывается onResult(c, hasPromo).
     * По завершении всех проверок — onComplete().
     */
    function promoResultFromPromoSearchResponse(j, countryId) {
        var data = (j && j.success && Array.isArray(j.data)) ? j.data : [];
        data = promoPostProcessHotelList(data, countryId);
        return {
            has: data.length > 0,
            minPrice: promoMinPriceFromHotels(data),
            data: data
        };
    }

    function runPromoChecksForCountriesInBackground(countries, onResult, onComplete) {
        var idx = 0;
        var batchSize = 2;
        var timeoutMs = 90000;
        var depId = promoCurrentDepartureKey();
        function runBatch() {
            if (idx >= countries.length) {
                if (typeof onComplete === 'function') onComplete();
                return;
            }
            var batch = countries.slice(idx, idx + batchSize);
            idx += batch.length;
            Promise.all(batch.map(function (c) {
                return fetchPromoSearchForDeparture(c.id, depId, timeoutMs).then(function (j) {
                    var res = promoResultFromPromoSearchResponse(j, c.id);
                    if (res.has || !usesNearestPromoFallback(c.id)) {
                        onResult(c, res.has, res.minPrice, { fromTours: true });
                        return;
                    }
                    return applyNearestFallbackIfNeeded(res.data, c.id).then(function (fb) {
                        var fbList = promoPostProcessHotelList(Array.isArray(fb) ? fb : [], c.id);
                        if (!fbList.length) {
                            onResult(c, res.has, res.minPrice, { fromTours: true });
                            return;
                        }
                        onResult(c, true, promoMinPriceFromHotels(fbList), { fromTours: true });
                    });
                }).catch(function () {
                    onResult(c, false, 0);
                });
            })).then(function () {
                runBatch();
            }).catch(function () {
                runBatch();
            });
        }
        runBatch();
    }

    function safeFetchJson(url, fallback) {
        fallback = fallback || { success: false, data: null };
        return fetch(url, { method: 'GET', cache: 'no-store' })
            .then(function (r) { return r.text().then(function (t) { return { ok: r.ok, text: t }; }); })
            .then(function (o) {
                var t = (o.text || '').trim();
                if (!t) return fallback;
                try { return JSON.parse(t); } catch (e) { return fallback; }
            })
            .catch(function () { return fallback; });
    }

    function safeFetchJsonWithTimeout(url, fallback, timeoutMs) {
        fallback = fallback || { success: false, data: null };
        timeoutMs = timeoutMs || 25000;
        var ctrl = typeof AbortController !== 'undefined' ? new AbortController() : null;
        var tid = null;
        if (ctrl) {
            tid = setTimeout(function () {
                try { ctrl.abort(); } catch (e) {}
            }, timeoutMs);
        }
        var fetchOpts = { method: 'GET', cache: 'no-store' };
        if (ctrl) fetchOpts.signal = ctrl.signal;
        var p = fetch(url, fetchOpts)
            .then(function (r) { return r.text().then(function (t) { return { ok: r.ok, text: t }; }); })
            .then(function (o) {
                if (tid) clearTimeout(tid);
                var t = (o.text || '').trim();
                if (!t) return fallback;
                try { return JSON.parse(t); } catch (e) { return fallback; }
            })
            .catch(function () {
                if (tid) clearTimeout(tid);
                return fallback;
            });
        return p;
    }

    function initHorizontalScrollHints() {
        var key = 'th_promo_scroll_hint_v1';
        var scrollers = Array.prototype.slice.call(document.querySelectorAll('.promo-tape-scroll'));
        if (!scrollers.length) return;

        function hasOverflow(el) { return el.scrollWidth > el.clientWidth + 4; }
        function updateFades(container) {
            var scroller = container.querySelector('.promo-tape-scroll');
            if (!scroller) return;
            var leftFade = container.querySelector('.promo-scroll-fade-left');
            var rightFade = container.querySelector('.promo-scroll-fade-right');
            var hint = container.querySelector('.promo-scroll-hint');
            if (!hasOverflow(scroller)) {
                if (leftFade) leftFade.classList.add('hidden');
                if (rightFade) rightFade.classList.add('hidden');
                if (hint) hint.classList.add('hidden');
                return;
            }
            var atStart = scroller.scrollLeft <= 2;
            var atEnd = scroller.scrollLeft + scroller.clientWidth >= scroller.scrollWidth - 2;
            if (leftFade) leftFade.classList.toggle('hidden', atStart);
            if (rightFade) rightFade.classList.toggle('hidden', atEnd);
            if (hint) hint.classList.toggle('hidden', !atStart);
        }

        scrollers.forEach(function (scroller) {
            var container = scroller.parentElement;
            if (!container) return;
            updateFades(container);
            scroller.addEventListener('scroll', function () { updateFades(container); }, { passive: true });
            var rafId = null;
            window.addEventListener('resize', function () {
                if (rafId != null) cancelAnimationFrame(rafId);
                rafId = requestAnimationFrame(function () {
                    rafId = null;
                    updateFades(container);
                });
            }, { passive: true });
            var hint = container.querySelector('.promo-scroll-hint');
            if (hint) {
                hint.addEventListener('click', function () {
                    try { scroller.scrollBy({ left: Math.min(scroller.clientWidth * 0.85, 420), behavior: 'smooth' }); }
                    catch (e) { scroller.scrollLeft += 320; }
                    try { localStorage.setItem(key, '1'); } catch (e) {}
                });
            }
        });

        var already = false;
        try { already = localStorage.getItem(key) === '1'; } catch (e) { already = false; }
        if (already) return;
        var first = scrollers[0];
        if (!first || !hasOverflow(first)) return;
        setTimeout(function () {
            try {
                first.scrollBy({ left: 120, behavior: 'smooth' });
                setTimeout(function () { first.scrollBy({ left: -80, behavior: 'smooth' }); }, 650);
            } catch (e) {
                first.scrollLeft = Math.min(first.scrollLeft + 120, first.scrollWidth);
                setTimeout(function () { first.scrollLeft = Math.max(first.scrollLeft - 80, 0); }, 650);
            }
            try { localStorage.setItem(key, '1'); } catch (e) {}
        }, 800);
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initHorizontalScrollHints);
    else initHorizontalScrollHints();

    /* ── Shared helpers (используются и в tours, и в unified) ── */
    var FLIGHT_PLACEHOLDER = '';
    var ROOM_CATEGORY_DEFAULT = 'Стандарт';
    window.__promoFlightsByTourId = window.__promoFlightsByTourId || {};
    window.__thFlightsLoadGen = window.__thFlightsLoadGen || 0;
    var promoFlightsPatchTimer = null;

    function getTourId(h) {
        var tour = promoPickBestTour(h);
        return (tour.id != null && tour.id !== '') ? String(tour.id) : '';
    }

    function patchPromoCardFlights(hotels) {
        var resultsEl = document.getElementById('promo-tours-results');
        if (!resultsEl || !hotels || !hotels.length) return;
        if (promoFlightsPatchTimer) clearTimeout(promoFlightsPatchTimer);
        promoFlightsPatchTimer = setTimeout(function () {
            promoFlightsPatchTimer = null;
            window.__thFlightsLoadGen = (window.__thFlightsLoadGen || 0) + 1;
            var loadGen = window.__thFlightsLoadGen;
            var depCity = promoDepartureNameForFlights();
            var depIdFl = promoDepartureIdForFlights();
            var base = (TV_API_BASE || '').replace(/\/$/, '');
            if (typeof thLoadTourFlightsForHotels === 'function' && base) {
                thLoadTourFlightsForHotels(hotels, {
                    apiBase: base,
                    departureCity: depCity,
                    departureId: depIdFl,
                    maxTours: hotels.length,
                    maxConcurrent: 4,
                    waveSize: 12,
                    patchEvery: 1,
                    loadGen: loadGen,
                    getTourId: getTourId,
                    patchContainer: resultsEl,
                    onDone: function () {
                        if (typeof thLoadFlightsForVisibleCards === 'function') {
                            thLoadFlightsForVisibleCards(resultsEl, {
                                apiBase: base,
                                departureCity: depCity,
                                departureId: depIdFl,
                                maxConcurrent: 4,
                                waveSize: 12,
                                patchContainer: resultsEl
                            });
                        }
                    }
                });
                return;
            }
            if (window.THTourCard && typeof window.THTourCard.mountInContainer === 'function') {
                window.THTourCard.mountInContainer(resultsEl);
            } else if (window.THTourCard && typeof window.THTourCard.patchFlightsInContainer === 'function') {
                window.THTourCard.patchFlightsInContainer(resultsEl);
            }
        }, 300);
    }
    function getHotelStarCategory(h) {
        if (!h) return null;
        var raw = h.category;
        if (raw == null || raw === '') raw = h.hotelCategory != null ? h.hotelCategory : h.stars;
        if (raw == null || raw === '') raw = h.hotelstars != null ? h.hotelstars : h.hotelStars;
        if ((raw == null || raw === '') && h.hotel && typeof h.hotel === 'object') {
            raw = h.hotel.category != null ? h.hotel.category : h.hotel.stars;
        }
        if (raw == null || raw === '') return null;
        var s = String(raw).trim();
        var n = parseInt(s, 10);
        if (!isNaN(n) && n >= 1 && n <= 5) return n;
        var m = s.match(/([1-5])\s*(?:\*|зв|\u2605|★|stars?)?/i);
        if (m) { n = parseInt(m[1], 10); if (!isNaN(n) && n >= 1 && n <= 5) return n; }
        return null;
    }

    function promoToursHaveAnyStarRating(list) {
        if (!list || !list.length) return false;
        for (var pi = 0; pi < list.length; pi++) {
            if (getHotelStarCategory(list[pi]) != null) return true;
        }
        return false;
    }

    function promoFilterHotelsByStarSelection(list, noStarFilters) {
        list = Array.isArray(list) ? list.slice() : [];
        if (noStarFilters) return list;
        var starSet = window.__promoStarSet;
        if (starSet && starSet.size > 0) {
            if (!promoToursHaveAnyStarRating(list)) return list;
            return list.filter(function (h) {
                var c = getHotelStarCategory(h);
                return c !== null && starSet.has(c);
            });
        }
        var selectedStars = (typeof window.__promoStarFilter === 'string') ? window.__promoStarFilter.trim() : '';
        if (selectedStars === '') return list;
        if (!promoToursHaveAnyStarRating(list)) return list;
        if (selectedStars === '4-5' || selectedStars === '4–5') {
            return list.filter(function (h) {
                var c = getHotelStarCategory(h);
                return c !== null && c >= 4 && c <= 5;
            });
        }
        var want = parseInt(selectedStars, 10);
        if (!isNaN(want)) {
            return list.filter(function (h) { return getHotelStarCategory(h) === want; });
        }
        return list;
    }

    var __promoStarPopupInited = false;
    function initPromoStarFilterPopup(onApply) {
        if (__promoStarPopupInited) return;
        var trigger = document.getElementById('promo-stars-trigger');
        var popup = document.getElementById('promo-stars-popup');
        var backdrop = document.getElementById('promo-stars-backdrop');
        var closeBtn = document.getElementById('promo-stars-close');
        var applyBtn = document.getElementById('promo-stars-apply');
        var label = document.getElementById('promo-stars-label');
        if (!trigger || !popup) return;
        __promoStarPopupInited = true;

        function getStarLabel(set) {
            if (!set || set.size === 0) return '';
            var vals = Array.from(set).sort(function (a, b) { return a - b; });
            if (vals.length === 1) return vals[0] + '★';
            if (vals.length === 2 && vals[0] + 1 === vals[1]) return vals[0] + '–' + vals[1] + '★';
            return vals.join(', ') + '★';
        }
        function syncTrigger() {
            if (!label) return;
            var sel = getStarLabel(window.__promoStarSet);
            var defaultHint = 'Необязательно — можно смотреть все туры';
            label.textContent = sel ? ('Сейчас: ' + sel) : defaultHint;
            trigger.classList.toggle('has-selection', !!sel);
        }
        function openPopup() {
            var cur = window.__promoStarSet;
            popup.querySelectorAll('.promo-star-cb').forEach(function (cb) {
                if (cb.value === 'all') cb.checked = (!cur || cur.size === 0);
                else cb.checked = !!(cur && cur.has(parseInt(cb.value, 10)));
            });
            popup.style.display = '';
            if (backdrop) backdrop.style.display = '';
            trigger.setAttribute('aria-expanded', 'true');
        }
        function closePopup() {
            popup.style.display = 'none';
            if (backdrop) backdrop.style.display = 'none';
            trigger.setAttribute('aria-expanded', 'false');
        }

        trigger.addEventListener('click', openPopup);
        if (closeBtn) closeBtn.addEventListener('click', closePopup);
        if (backdrop) backdrop.addEventListener('click', closePopup);
        popup.querySelectorAll('.promo-star-cb').forEach(function (cb) {
            cb.addEventListener('change', function () {
                if (cb.value === 'all' && cb.checked) {
                    popup.querySelectorAll('.promo-star-cb:not([value="all"])').forEach(function (c) { c.checked = false; });
                } else if (cb.value !== 'all' && cb.checked) {
                    var allCb = popup.querySelector('.promo-star-cb[value="all"]');
                    if (allCb) allCb.checked = false;
                }
            });
        });
        if (applyBtn) applyBtn.addEventListener('click', function () {
            var newSet = new Set();
            var allChecked = false;
            popup.querySelectorAll('.promo-star-cb').forEach(function (cb) {
                if (!cb.checked) return;
                if (cb.value === 'all') allChecked = true;
                else newSet.add(parseInt(cb.value, 10));
            });
            if (allChecked || newSet.size === 0) {
                window.__promoStarSet = null;
                window.__promoStarFilter = '';
            } else {
                window.__promoStarSet = newSet;
                window.__promoStarFilter = Array.from(newSet).sort(function (a, b) { return a - b; }).join(',');
            }
            syncTrigger();
            closePopup();
            if (typeof onApply === 'function') onApply();
        });
        syncTrigger();
    }
    /** Расшифровка аббревиатур питания Tourvisor */
    function expandMealName(raw) {
        if (!raw) return '';
        var s = raw.toString().trim();
        var map = {
            'AI':  'Всё включено',
            'UAI': 'Ультра всё включено',
            'BB':  'Завтрак',
            'HB':  'Завтрак + ужин',
            'HB+': 'Завтрак + ужин (улучш.)',
            'FB':  'Завтрак + обед + ужин',
            'RO':  'Без питания',
            'SC':  'Самообслуживание',
            'AL':  'Всё включено'
        };
        var key = s.toUpperCase();
        return map[key] || s;
    }

    function renderPromoTourCards(data) {
        if (!data || data.length === 0) return '';
        var tourDetailBase = '/frontend/window';
        var lp = window.__promoLastParams || { departure_city: DEPARTURE_NAME || DEFAULT_DEPARTURE_NAME, dateFrom: '', dateTo: '' };
        var adultsLabel = thPromoAdultsCount();
        var adultsNum = parseInt(String(adultsLabel), 10) || 2;
        var depCityDisplay = DEPARTURE_NAME || DEFAULT_DEPARTURE_NAME || '';
        var depIdPromo = DEPARTURE_ID || DEFAULT_DEPARTURE_ID;

        function promoTourDetailUrl(h, tour, startYmd, retYmd, priceNum, meal, region, country, tourId, link, desc, roomCategory, flightInfo) {
            var cardImg = promoCardPrimaryImage(h);
            var tourDetailParams = {
                tour_link: link, country: country, hotel_name: (h.name || ''),
                price: priceNum > 0 ? String(priceNum) : '',
                nights: String(tour.nights || ''), meal: meal, region: region,
                departure_city: lp.departure_city || DEFAULT_DEPARTURE_NAME,
                date_from: startYmd || '', date_to: retYmd || '',
                image: cardImg || '', description: desc ? desc.substring(0, 4000) : '',
                rating: String(h.rating || ''), category: String(h.category || ''), from_promo: '1',
                room_category: roomCategory, flight_info: flightInfo, tour_id: tourId
            };
            if (depIdPromo) tourDetailParams.departure_id = String(depIdPromo);
            if (h.id) tourDetailParams.hotel_id = String(h.id);
            tourDetailParams.adults = String(adultsLabel);
            try {
                tourDetailParams.return_url = (window.TourSessionManager && window.TourSessionManager.buildReturnUrl)
                    ? window.TourSessionManager.buildReturnUrl()
                    : (window.location.pathname + window.location.search);
            } catch (e) {}
            return country ? (tourDetailBase + '/tour-detail.php?' + new URLSearchParams(tourDetailParams).toString()) : (link || '#');
        }

        var cardCountryId = promoActiveCountryId();
        var showDirectBadgeForCountry = isPromoDirectFlightFilterCountry(cardCountryId);

        if (window.THTourCard && typeof window.THTourCard.render === 'function') {
            return data.map(function (h) {
                var tour = promoPickBestTour(h);
                var showPromoBadge = h.__promoShowBadge !== false;
                var region = (h.region && h.region.name) ? h.region.name : '';
                var country = promoCardGeoCountryName(h, cardCountryId);
                var mealRaw = (tour.meal && (tour.meal.russianName || tour.meal.name)) ? (tour.meal.russianName || tour.meal.name) : '';
                var meal = expandMealName(mealRaw);
                var nightsNum = tour.nights ? parseInt(String(tour.nights), 10) : 0;
                var priceNum = promoHotelListPrice(h);
                var link = h.hotelDescriptionLink || h.hoteldescriptionlink || h.link || '';
                var desc = (h.description || h.hotelDescription || h.descr || '').toString().trim();
                var roomCategory = (tour.roomType || h.roomCategory || ROOM_CATEGORY_DEFAULT).toString().trim() || ROOM_CATEGORY_DEFAULT;
                var tourId = getTourId(h);
                var flightData = promoFlightsCacheGet(tourId);
                var flightInfo = (flightData && flightData.summary) ? flightData.summary : (FLIGHT_PLACEHOLDER || '');
                var showDirectBadge = showDirectBadgeForCountry && !!(flightData && flightData.direct);
                var startYmd = promoTourStartYmd(tour);
                var retYmd = (startYmd && nightsNum) ? promoReturnYmd(startYmd, nightsNum) : '';
                var cardHref = promoTourDetailUrl(h, tour, startYmd, retYmd, priceNum, meal, region, country, tourId, link, desc, roomCategory, flightInfo);
                return window.THTourCard.render(h, {
                    tour: tour,
                    promo: showPromoBadge,
                    getImageUrl: getImageUrl,
                    imageProxy: TV_IMAGE_PROXY,
                    image: promoCardPrimaryImage(h),
                    detailUrl: cardHref,
                    adults: adultsNum,
                    dateFrom: startYmd,
                    dateTo: retYmd,
                    price: priceNum,
                    meal: meal,
                    departureCity: depCityDisplay,
                    departureId: depIdPromo,
                    carousel: true,
                    country: country,
                    countryId: cardCountryId,
                    directBadge: showDirectBadge,
                    flightMeta: promoFlightsCacheGet(tourId) || null
                });
            }).join('');
        }

        return data.map(function (h) {
            var photoUrls = thPromoHotelPhotoUrls(h);
            var slidesForCard = [];
            var slideDedup = {};
            for (var si = 0; si < photoUrls.length; si++) {
                var mapped = getImageUrl(photoUrls[si]);
                if (!mapped || !String(mapped).trim()) continue;
                if (slideDedup[mapped]) continue;
                slideDedup[mapped] = true;
                slidesForCard.push(mapped);
            }
            if (slidesForCard.length === 0) slidesForCard.push(fallbackImg);
            var slideMaxLegacy = (window.THTourCard && window.THTourCard.PHOTO_SLIDE_MAX) ? window.THTourCard.PHOTO_SLIDE_MAX : 6;
            slidesForCard = slidesForCard.slice(0, slideMaxLegacy);
            var img = slidesForCard[0];
            var region = (h.region && h.region.name) ? h.region.name : '';
            var country = promoCardGeoCountryName(h, cardCountryId);
            var tour = promoPickBestTour(h);
            var showPromoBadge = h.__promoShowBadge !== false;
            var mealRaw = (tour.meal && (tour.meal.russianName || tour.meal.name)) ? (tour.meal.russianName || tour.meal.name) : '';
            var meal = expandMealName(mealRaw);
            var nights = tour.nights || '';
            var nightsNum = nights ? parseInt(String(nights), 10) : 0;
            var priceNum = promoHotelListPrice(h);
            var link = h.hotelDescriptionLink || h.hoteldescriptionlink || h.link || '';
            var desc = (h.description || h.hotelDescription || h.descr || '').toString().trim();
            var roomCategory = (tour.roomType || h.roomCategory || ROOM_CATEGORY_DEFAULT).toString().trim() || ROOM_CATEGORY_DEFAULT;
            var tourId = getTourId(h);
            var flightData = promoFlightsCacheGet(tourId);
            var flightInfo = (flightData && flightData.summary) ? flightData.summary : (FLIGHT_PLACEHOLDER || '');
            var startYmd = promoTourStartYmd(tour);
            var retYmd = (startYmd && nightsNum) ? promoReturnYmd(startYmd, nightsNum) : '';

            // Hard funnel: без фейковой «было» (+15%)
            var oldPriceNum = 0;

            // Строка дат
            var datesMeta = '';
            if (startYmd && retYmd) {
                datesMeta = fmtDateShort(startYmd) + ' \u2013 ' + fmtDateShort(retYmd) + ', ' + nightsLabel(nightsNum) + ', ' + adultsNum + ' \u0432\u0437\u0440.';
            } else if (nightsNum) {
                datesMeta = nightsLabel(nightsNum) + ', ' + adultsNum + ' \u0432\u0437\u0440.';
            }

            var catNum = parseInt(String(h.category || ''), 10) || 0;
            var starsHtml = catNum > 0 ? '\u2605'.repeat(Math.min(catNum, 5)) : '';
            var nameEsc = (h.name || '').replace(/</g, '&lt;').replace(/"/g, '&quot;');
            var locEsc = (country + (region ? ', ' + region : '')).replace(/</g, '&lt;');
            var mediaBlock = (window.THTourCard && typeof window.THTourCard.buildCarouselMediaHtml === 'function')
                ? window.THTourCard.buildCarouselMediaHtml(slidesForCard, { fallbackImg: fallbackImg, hotelName: h.name, isPromo: showPromoBadge, detailUrl: cardHref })
                : ('<div class="th-tour-card__media th-tour-card__media--carousel">' +
                    '<div class="th-tour-card__strip-scroll" tabindex="-1">' +
                    slidesForCard.map(function (src, ji) {
                        var sEsc = String(src).replace(/"/g, '&quot;');
                        return '<img src="' + sEsc + '" alt="" class="th-tour-card__strip-img" loading="eager" decoding="async" onerror="this.onerror=null;this.src=\'' + fallbackImg + '\'">';
                    }).join('') +
                    '</div>' + (showPromoBadge ? '<span class="th-tour-card__badge th-tour-card__badge--promo">\u0410\u043a\u0446\u0438\u044f</span>' : '') + '</div>');

            var tourDetailParams = {
                tour_link: link, country: country, hotel_name: (h.name || ''),
                price: priceNum > 0 ? String(priceNum) : '',
                nights: String(nights), meal: meal, region: region,
                departure_city: lp.departure_city || DEFAULT_DEPARTURE_NAME,
                date_from: startYmd || '', date_to: retYmd || '',
                image: img, description: desc ? desc.substring(0, 4000) : '',
                rating: String(h.rating || ''), category: String(h.category || ''), from_promo: '1',
                room_category: roomCategory, flight_info: flightInfo, tour_id: tourId
            };
            var depIdPromo = DEPARTURE_ID || DEFAULT_DEPARTURE_ID;
            if (depIdPromo) tourDetailParams.departure_id = String(depIdPromo);
            if (h.id) tourDetailParams.hotel_id = String(h.id);
            tourDetailParams.adults = String(adultsLabel);
            try {
                tourDetailParams.return_url = (window.TourSessionManager && window.TourSessionManager.buildReturnUrl)
                    ? window.TourSessionManager.buildReturnUrl()
                    : (window.location.pathname + window.location.search);
            } catch (e) {}
            var tourDetailUrl = country ? (tourDetailBase + '/tour-detail.php?' + new URLSearchParams(tourDetailParams).toString()) : (link || '#');
            var cardHref = tourDetailUrl !== '#' ? tourDetailUrl : (link || '#');
            if (window.THTourCard && typeof window.THTourCard.appendGalleryToDetailUrl === 'function') {
                cardHref = window.THTourCard.appendGalleryToDetailUrl(cardHref, slidesForCard);
            }
            var flightHtml = (window.THTourCard && typeof window.THTourCard.buildFlightBlockHtml === 'function')
                ? window.THTourCard.buildFlightBlockHtml(depCityDisplay, tourId, { flightMeta: flightData })
                : '';
            var tourIdAttr = tourId ? ' data-th-tour-id="' + String(tourId).replace(/"/g, '&quot;') + '"' : '';
            var depAttr = depCityDisplay ? ' data-th-departure-city="' + depCityDisplay.replace(/"/g, '&quot;') + '"' : '';
            var hotelIdAttr = h.id ? ' data-th-hotel-id="' + String(h.id).replace(/"/g, '&quot;') + '"' : '';
            return '<article class="th-tour-card' + (showPromoBadge ? ' th-tour-card--promo' : '') + '"' + tourIdAttr + depAttr + hotelIdAttr + '>' +
                mediaBlock +
                '<a href="' + cardHref + '" class="th-tour-card__link th-tour-card__link--main">' +
                '<div class="th-tour-card__body">' +
                '<p class="th-tour-card__geo">' + locEsc + '</p>' +
                '<div class="th-tour-card__name-row">' +
                '<h3 class="th-tour-card__name">' + nameEsc + '</h3>' +
                (starsHtml ? '<span class="th-tour-card__stars">' + starsHtml + '</span>' : '') +
                '</div>' +
                (meal ? '<span class="th-tour-card__meal-badge">' + meal.replace(/</g,'&lt;') + '</span>' : '') +
                flightHtml +
                '<div class="th-tour-card__price-block">' +
                (oldPriceNum ? '<span class="th-tour-card__old-price">' + formatPrice(oldPriceNum) + '</span>' : '') +
                '<span class="th-tour-card__price-label">\u0437\u0430 ' + adultsNum + ' \u0432\u0437\u0440.</span>' +
                '<span class="th-tour-card__price">' + formatPrice(priceNum) + '</span>' +
                (showPromoBadge ? '<span class="th-tour-card__promo-label">\u0410\u043a\u0446\u0438\u043e\u043d\u043d\u0430\u044f \u0446\u0435\u043d\u0430</span>' : '') +
                (datesMeta ? '<span class="th-tour-card__dates">' + datesMeta + '</span>' : '') +
                '</div>' +
                '</div></a>' +
                '<div class="th-tour-card__actions">' +
                '<a href="' + cardHref + '" class="th-tour-card__btn">' + (window.THTourCard && window.THTourCard.DETAIL_BTN_LABEL ? window.THTourCard.DETAIL_BTN_LABEL : '\u0417\u0430\u0431\u0440\u043e\u043d\u0438\u0440\u043e\u0432\u0430\u0442\u044c') + '</a>' +
                (window.THTourCard && typeof window.THTourCard.buildPromoLeadButtonHtml === 'function'
                    ? window.THTourCard.buildPromoLeadButtonHtml({
                        hotelName: h.name,
                        hotelPrice: priceNum,
                        hotelCountry: country,
                        tourId: tourId
                    })
                    : '') +
                '</div></article>';
        }).join('');
    }

    if (cfg.step === 'departures') {
        var baseDep = TV_API_BASE.replace(/\/$/, '');
        var sepDep = baseDep.indexOf('?') >= 0 ? '&' : '?';
        if (!baseDep) {
            var ld0 = document.getElementById('promo-departures-loading');
            var em0 = document.getElementById('promo-departures-empty');
            if (ld0) ld0.classList.add('hidden');
            if (em0) {
                var p0 = em0.querySelector('p');
                if (p0) p0.textContent = 'Не настроен адрес API туров. Обратитесь к администратору сайта.';
                em0.classList.remove('hidden');
            }
        } else {
        safeFetchJsonWithTimeout(baseDep + sepDep + 'type=departures', { success: false, data: [] }, 14000).then(function (res) {
            var loadingEl = document.getElementById('promo-departures-loading');
            var gridEl = document.getElementById('promo-departures-grid');
            var emptyEl = document.getElementById('promo-departures-empty');
            if (!loadingEl || !gridEl) return;
            loadingEl.classList.add('hidden');
            var list = (res && Array.isArray(res.data)) ? res.data : [];
            if (window.THDeparturePreference && typeof THDeparturePreference.filterDepartures === 'function') {
                list = THDeparturePreference.filterDepartures(list);
            } else {
                list = list.filter(function (d) {
                    var n = String((d && (d.name || d.russianName)) || '').toLowerCase().trim();
                    return n !== 'красноярск' && n !== 'krasnoyarsk';
                });
            }
            if (list.length === 0) {
                if (emptyEl) emptyEl.classList.remove('hidden');
                thYmReachGoal('promo_departures_empty');
                return;
            }
            try {
                var skipAuto = new URLSearchParams(window.location.search || '').get('choose_departure') === '1';
                if (!skipAuto && window.THDeparturePreference && typeof THDeparturePreference.getSaved === 'function') {
                    var saved = THDeparturePreference.getSaved();
                    if (saved && saved.id && !(typeof THDeparturePreference.isBlockedDepartureName === 'function' && THDeparturePreference.isBlockedDepartureName(saved.name))) {
                        var sid = String(saved.id);
                        var found = list.find(function (d) { return String(d.id) === sid; });
                        if (found) {
                            var n = (found.name || found.russianName || saved.name || '').toString();
                            var q = new URLSearchParams(window.location.search || '');
                            var qs = 'departureId=' + encodeURIComponent(found.id) + '&departureName=' + encodeURIComponent(n);
                            var cId = q.get('countryId');
                            var cNm = q.get('countryName');
                            if (cId) qs += '&countryId=' + encodeURIComponent(cId);
                            if (cNm) qs += '&countryName=' + encodeURIComponent(cNm);
                            location.replace('/frontend/window/promotions.php?' + qs);
                            return;
                        }
                    }
                    if (typeof THDeparturePreference.onDeparturesReady === 'function') {
                        THDeparturePreference.onDeparturesReady(list);
                    }
                }
            } catch (e) {}
            var htmlParts = list.map(function (d) {
                var id = d.id;
                var name = (d.name || d.russianName || '').toString();
                var nameEsc = name.replace(/"/g, '&quot;').replace(/</g, '&lt;');
                var href = '/frontend/window/promotions.php?departureId=' + encodeURIComponent(id) + '&departureName=' + encodeURIComponent(name);
                var img = DEPARTURE_CITY_IMAGES[name] || DEPARTURE_CITY_IMAGES[name.toLowerCase()] || DEPARTURE_CITY_IMAGES['_default'] || '';
                var imgStyle = img ? 'background-image:url(\'' + img.replace(/'/g, "\\'") + '\');' : 'background:linear-gradient(135deg,#0c4a6e 0%,#0369a1 50%,#0ea5e9 100%);';
                return '<a href="' + href + '" class="country-card surface-card block overflow-hidden rounded-2xl" title="' + nameEsc + '">' +
                    '<div class="promo-country-img aspect-[4/3] w-full bg-no-repeat bg-center relative" style="' + imgStyle + '">' +
                    (!img ? '<div class="absolute inset-0 flex items-center justify-center"><i class="fas fa-plane-departure text-7xl text-white/60"></i></div>' : '') +
                    '<span class="promo-banner-chip"><i class="fas fa-tag text-[10px]"></i><strong>' + nameEsc + '</strong><span>Спецпредложение</span></span>' +
                    '<div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/30 to-transparent"></div>' +
                    '<span class="absolute bottom-5 left-5 right-5 text-white font-bold text-xl sm:text-2xl drop-shadow-xl">' + nameEsc + '</span>' +
                    /* '<span class="absolute top-4 right-4 badge-promo">Вылет</span>' + */
                    '</div></a>';
            });
            gridEl.innerHTML = htmlParts.join('');
            gridEl.classList.remove('hidden');
            /* ── promo_city_click: клик на карточку города вылета ── */
            gridEl.addEventListener('click', function (e) {
                var card = e.target.closest('a[href]');
                if (card) promoYm('promo_city_click');
            });
        }).catch(function () {
            var el = document.getElementById('promo-departures-loading');
            if (el) el.classList.add('hidden');
            var emptyEl = document.getElementById('promo-departures-empty');
            if (emptyEl) emptyEl.classList.remove('hidden');
        });
        }
    } else if (cfg.step === 'countries') {
        var countriesApiBase = TV_API_BASE.replace(/\/$/, '');
        var loadingEl = document.getElementById('promo-countries-loading');
        var gridEl = document.getElementById('promo-countries-grid');
        var popularWrap = document.getElementById('promo-popular-wrap');
        var popularGrid = document.getElementById('promo-popular-grid');
        var otherWrap = document.getElementById('promo-other-wrap');
        var otherLoading = document.getElementById('promo-other-loading');
        var emptyEl = document.getElementById('promo-countries-empty');
        var emptyMsgEl = document.getElementById('promo-countries-empty-msg');
        var imgMap = {};
        var popular = promoBuildPopularList(cfg, {});

        function escAttr(s) { return String(s || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;'); }

        function countryImageStyle(c) {
            var id = c.id;
            var name = (c.name || c.russianName || '').toString();
            var nameNorm = name.toLowerCase().trim();
            var img = LOCAL_COUNTRY_IMAGES[name] || imgMap[id] || imgMap[name] || imgMap[nameNorm] || (PROMO_COUNTRY_FALLBACK_IMAGES && (PROMO_COUNTRY_FALLBACK_IMAGES[name] || PROMO_COUNTRY_FALLBACK_IMAGES[nameNorm] || PROMO_COUNTRY_FALLBACK_IMAGES['_default'])) || '';
            return img
                ? 'background-image:url(\'' + String(img).replace(/'/g, "\\'") + '\');'
                : 'background-image:url(\'https://images.unsplash.com/photo-1488646953014-85cb4e065289?w=600\');';
        }

        function countryHref(c, displayName) {
            var id = c.id;
            var name = (displayName || c.name || c.russianName || '').toString();
            return '/frontend/window/promotions.php?departureId=' + encodeURIComponent(DEPARTURE_ID)
                + '&departureName=' + encodeURIComponent(DEPARTURE_NAME)
                + '&countryId=' + encodeURIComponent(id)
                + '&countryName=' + encodeURIComponent(name);
        }

        function renderCountryCard(c, opts) {
            opts = opts || {};
            var id = c.id;
            var rawName = (c.name || c.russianName || '').toString();
            var displayName = (opts.displayName || rawName).toString();
            var nameEsc = escAttr(displayName);
            var href = countryHref(c, rawName);
            var cid = String(id);
            var style = countryImageStyle(c);
            var isPopular = !!opts.isPopular;
            var classes = 'country-card promo-country-tile surface-card block overflow-hidden rounded-2xl relative';
            if (isPopular) classes += ' promo-country-popular';
            return '<div class="' + classes + '" data-promo-cid="' + escAttr(cid) + '" data-promo-popular="' + (isPopular ? '1' : '0') + '">' +
                '<a href="' + escAttr(href) + '" class="block relative z-10" title="' + nameEsc + '" aria-label="' + nameEsc + '">' +
                '<div class="promo-country-img aspect-[4/3] w-full bg-no-repeat relative" style="' + style + '">' +
                '<span class="promo-banner-chip"><i class="fas fa-bolt text-[10px]"></i><strong>' + nameEsc + '</strong><span>Акция</span></span>' +
                '<div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/20 to-transparent"></div>' +
                '<span class="absolute bottom-4 left-4 right-16 text-white font-bold text-xl sm:text-2xl drop-shadow-lg">' + nameEsc + '</span>' +
                '</div></a>' +
                '<button type="button" class="promo-country-callback" data-promo-country-callback data-country-name="' + nameEsc + '" data-country-id="' + escAttr(cid) + '">Перезвонить</button>' +
                '</div>';
        }

        function renderPopularCards() {
            if (!popularGrid || !popular.length) return;
            popularGrid.innerHTML = popular.map(function (p) {
                return renderCountryCard(p.c, { isPopular: true, displayName: p.displayName });
            }).join('');
            if (popularWrap) popularWrap.classList.remove('hidden');
        }

        function reorderOtherGridByPromo() {
            if (!gridEl) return;
            var cards = Array.prototype.slice.call(gridEl.querySelectorAll('[data-promo-popular="0"]'));
            cards.sort(function (a, b) {
                var aPromo = a.getAttribute('data-promo-has') === '1' ? 1 : 0;
                var bPromo = b.getAttribute('data-promo-has') === '1' ? 1 : 0;
                if (aPromo !== bPromo) return bPromo - aPromo;
                var aNameEl = a.querySelector('.absolute.bottom-4');
                var bNameEl = b.querySelector('.absolute.bottom-4');
                var aName = (aNameEl ? aNameEl.textContent : '').trim();
                var bName = (bNameEl ? bNameEl.textContent : '').trim();
                return aName.localeCompare(bName, 'ru');
            });
            cards.forEach(function (card) { gridEl.appendChild(card); });
        }

        function applyCountryPromoCheck(c, hasPromo) {
            var id = String(c.id);
            var popularEl = popularGrid ? popularGrid.querySelector('[data-promo-cid="' + id + '"]') : null;
            var otherEl = gridEl ? gridEl.querySelector('[data-promo-cid="' + id + '"]') : null;
            var el = popularEl || otherEl;
            if (!el) return;
            var isPopular = el.getAttribute('data-promo-popular') === '1';
            if (hasPromo) {
                var badge = el.querySelector('.promo-country-check-badge');
                if (badge) badge.remove();
                el.classList.remove('opacity-60');
                el.setAttribute('aria-disabled', 'false');
                if (!isPopular) {
                    el.setAttribute('data-promo-has', '1');
                    reorderOtherGridByPromo();
                }
                return;
            }
            if (isPopular) {
                var badge2 = el.querySelector('.promo-country-check-badge');
                if (badge2) {
                    badge2.setAttribute('data-promo-checking', '0');
                    badge2.innerHTML = '<i class="fas fa-info-circle" style="font-size:10px"></i><span>нет туров</span>';
                    badge2.style.background = 'rgba(15,23,42,0.78)';
                }
                el.classList.add('opacity-60');
                return;
            }
            if (otherEl) otherEl.remove();
        }

        if (!loadingEl || !gridEl) {
            var depSpin2 = document.getElementById('promo-departures-loading');
            if (depSpin2) depSpin2.classList.add('hidden');
        } else if (!popular.length) {
            loadingEl.classList.add('hidden');
            if (emptyMsgEl) emptyMsgEl.textContent = 'Список популярных направлений не настроен.';
            if (emptyEl) emptyEl.classList.remove('hidden');
        } else {
            renderPopularCards();
            loadingEl.classList.add('hidden');
            if (emptyEl) emptyEl.classList.add('hidden');
            if (otherLoading) otherLoading.classList.remove('hidden');

            (function applyPopularManifestNowLegacy() {
                if (PROMO_LIVE_ONLY) return;
                var blockNow = promoGetManifestBlock();
                if (!blockNow) return;
                popular.forEach(function (p) {
                    var ent = blockNow[String(p.c.id)] || { has: false };
                    applyCountryPromoCheck(p.c, !!ent.has);
                });
            })();
            runPromoChecksFromManifest(popular.map(function (p) { return p.c; }), function (c, hasPromo) {
                applyCountryPromoCheck(c, hasPromo);
            }, function () {}, { prefetchDelayMs: 400, prefetchCacheOnly: true });

            safeFetchJson(COUNTRIES_WITH_IMAGES_URL, { countries: [] }).catch(function (e) {
                console.warn('[Акции: страны] Ошибка countries-with-images:', e);
                return { countries: [] };
            }).then(function (imagesRes) {
                imgMap = promoBuildImgMap(imagesRes || {});
                renderPopularCards();
            });

            /* Блок «Все направления» отключён — только популярные */
            if (otherLoading) otherLoading.classList.add('hidden');
            if (otherWrap) {
                otherWrap.classList.add('hidden');
                otherWrap.setAttribute('hidden', '');
            }
            if (gridEl) gridEl.innerHTML = '';
        }
    } else {
        if (promoIsExcludedCountryId(COUNTRY_ID)) {
            var backPromo = '/frontend/window/promotions.php?departureId=' + encodeURIComponent(DEPARTURE_ID || DEFAULT_DEPARTURE_ID)
                + '&departureName=' + encodeURIComponent(DEPARTURE_NAME || DEFAULT_DEPARTURE_NAME);
            location.replace(backPromo);
            return;
        }

        /** Выбор звёздности: вызывается из onclick в разметке (надёжнее делегирования при любом CSS/порядке скриптов) */
        window.thPromoPickStar = function (btn) {
            if (typeof window.__TH_PROMO_STAR_APPLY === 'function') {
                return window.__TH_PROMO_STAR_APPLY(btn);
            }
            return false;
        };

        var base = TV_API_BASE.replace(/\/$/, '');
        var sep = base.indexOf('?') >= 0 ? '&' : '?';
        /** Защита от гонки: быстрый клик по разным странам — не применять устаревший merge. */
        var promoToursFetchSeq = 0;

        /** По умолчанию показываем все отели без фильтра по звёздности. */
        window.__promoStarFilter = '';
        window.__promoStarSet = null;

        initPromoStarFilterPopup(applyPromoFiltersAndRender);

        function syncPromoStarButtons() { /* legacy: заменено попапом */ }

        function getFilteredPromoTours() {
            var list = promoFilterHotelsMinNights(window.__promoAllTours || [], COUNTRY_ID);
            return promoFilterHotelsByStarSelection(list, PROMO_NO_STAR_FILTERS);
        }

        function applyPromoFiltersAndRender() {
            var resultsEl = document.getElementById('promo-tours-results');
            var emptyEl = document.getElementById('promo-tours-empty');
            var emptyMsg = document.getElementById('promo-tours-empty-msg');
            if (!resultsEl) return;
            var filtered = getFilteredPromoTours();
            var prepared = promoPrepareHotelListForDisplay(filtered);
            if (emptyEl) emptyEl.classList.add('hidden');
            resultsEl.classList.remove('hidden');
            resultsEl.innerHTML = renderPromoTourCards(prepared);
            patchPromoCardFlights(prepared);
            updatePromoHotelCardPrices(prepared);
            if (window.THTourCard && typeof window.THTourCard.mountInContainer === 'function') {
                window.THTourCard.mountInContainer(resultsEl);
            } else if (window.THTourCard && typeof window.THTourCard.ensureCarouselsInContainer === 'function') {
                window.THTourCard.ensureCarouselsInContainer(resultsEl);
            } else if (window.THTourCard && typeof window.THTourCard.kickImagesInContainer === 'function') {
                window.THTourCard.kickImagesInContainer(resultsEl);
            }
            if (COUNTRY_ID && prepared.length) promoSyncCptileMinPrice(COUNTRY_ID, prepared);
            if (emptyEl) {
                if (filtered.length === 0) {
                    if (emptyMsg) emptyMsg.textContent = 'По заданным фильтрам нет туров. Измените категорию отеля.';
                    emptyEl.classList.remove('hidden');
                } else {
                    emptyEl.classList.add('hidden');
                }
            }
        }

        window.__TH_PROMO_STAR_APPLY = function (btn) {
            if (!btn || typeof btn.getAttribute !== 'function') return false;
            var raw = btn.getAttribute('data-promo-star');
            window.__promoStarFilter = (raw === null || raw === undefined) ? '' : String(raw);
            syncPromoStarButtons();
            applyPromoFiltersAndRender();
            return false;
        };

        function loadPromoTours() {
            if (window.__isUnifiedMode) return;
            window.__promoActiveCountryId = String(COUNTRY_ID != null ? COUNTRY_ID : '');
            var mySeq = ++promoToursFetchSeq;
            window.__promoStarFilter = '';
            window.__promoStarSet = null;
            syncPromoStarButtons();
            var titleReset = document.getElementById('promo-tours-title');
            var subtitleReset = document.getElementById('promo-tours-subtitle');
            if (titleReset) titleReset.textContent = 'Акционные туры в ' + (COUNTRY_NAME_ACC || COUNTRY_NAME);
            if (subtitleReset) {
                subtitleReset.textContent = 'Цены за ' + thPromoAdultsCount() + ' взрослых. Ближайшие вылеты.';
            }

            var dFrom = new Date(); dFrom.setDate(dFrom.getDate() + PROMO_DATE_PLUS_FROM);
            var dTo = new Date(); dTo.setDate(dTo.getDate() + promoDatePlusTo(COUNTRY_ID));
            var dateFrom = formatLocalYMD(dFrom);
            var dateTo = formatLocalYMD(dTo);
            window.__promoLastParams = { departureId: String(DEPARTURE_ID || DEFAULT_DEPARTURE_ID), departure_city: DEPARTURE_NAME || DEFAULT_DEPARTURE_NAME, dateFrom: dateFrom, dateTo: dateTo };
            var swrKey = promoToursSwrCacheKey(String(DEPARTURE_ID || DEFAULT_DEPARTURE_ID || '0'), String(COUNTRY_ID), thPromoAdultsCount(), dateFrom, dateTo);
            var paintOptsLegacy = {
                swrKey: swrKey,
                countryId: COUNTRY_ID,
                seqOk: function () { return mySeq === promoToursFetchSeq; },
                onPaint: function (data) {
                    window.__promoAllTours = data;
                    var le = document.getElementById('promo-tours-loading');
                    var re = document.getElementById('promo-tours-results');
                    var ee = document.getElementById('promo-tours-empty');
                    if (le) le.classList.add('hidden');
                    if (ee) ee.classList.add('hidden');
                    if (re) re.classList.remove('hidden');
                    syncPromoStarButtons();
                    applyPromoFiltersAndRender();
                }
            };
            var painted = false;
            if (!PROMO_LIVE_ONLY) {
                painted = promoTryInstantPrefetchPaint(paintOptsLegacy) || promoToursTrySwrPaint(paintOptsLegacy);
            }
            var ldEl = document.getElementById('promo-tours-loading');
            var rsEl = document.getElementById('promo-tours-results');
            var emEl = document.getElementById('promo-tours-empty');
            if (emEl) emEl.classList.add('hidden');
            if (!painted) {
                if (ldEl) ldEl.classList.remove('hidden');
                if (rsEl) { rsEl.innerHTML = ''; rsEl.classList.add('hidden'); }
            } else if (ldEl) {
                ldEl.classList.add('hidden');
            }
            fetchPromoSearchBundledPreferCache(COUNTRY_ID, 120000)
                .then(function (j) {
                    if (mySeq !== promoToursFetchSeq) return;
                    promoClearFallbackDepartureNotice();
                    console.groupCollapsed('%c[Акции] Запрос туров (' + promoSearchRequestLogTitle(j) + ')', 'color: #f59e0b; font-weight: bold');
                    promoLogSearchResponse('promo-search', j || {}, { countryId: COUNTRY_ID });
                    var mergedData = (j && j.success && Array.isArray(j.data)) ? j.data : [];
                    j = {
                        success: !!(j && j.success),
                        data: mergedData,
                        error: (j && j.error) ? j.error : undefined,
                        fromCache: !!(j && j.fromCache),
                        promoSearchSource: (j && j.promoSearchSource) ? j.promoSearchSource : undefined
                    };
                    var loadingEl = document.getElementById('promo-tours-loading');
                    var resultsEl = document.getElementById('promo-tours-results');
                    var emptyEl = document.getElementById('promo-tours-empty');
                    if (loadingEl) loadingEl.classList.add('hidden');

                    console.group('%c[Акции] Итог promo-search', 'color: #22c55e; font-weight: bold');
                    console.log('success:', j.success);
                    console.log('error:', j.error);
                    console.log('fromCache:', j.fromCache);
                    console.log('promoSearchSource:', j.promoSearchSource);
                    console.log('data — массив?', Array.isArray(j.data), 'длина:', (j.data && j.data.length) || 0);
                    if (j.data && j.data.length > 0) {
                        console.log('первые 2 отеля:', j.data.slice(0, 2).map(function (h) { return { name: h.name, tours: (h.tours || []).length }; }));
                    }
                    console.log('полный ответ:', j);
                    console.groupEnd();

                    if (!j.success) {
                        if (window.__promoAllTours && window.__promoAllTours.length > 0) {
                            if (loadingEl) loadingEl.classList.add('hidden');
                            return;
                        }
                        var nearestErrFallback = false;
                        try {
                            nearestErrFallback = usesNearestPromoFallback(COUNTRY_ID);
                        } catch (eVn) { nearestErrFallback = false; }
                        if (nearestErrFallback) {
                            return promoFinalizeTourResults([], COUNTRY_ID)
                                .then(function (dataE) {
                                    if (mySeq !== promoToursFetchSeq) return;
                                    if (dataE && dataE.length > 0) {
                                        dataE.sort(function (a, b) { return promoHotelListPrice(a) - promoHotelListPrice(b); });
                                        promoToursSwrWrite(swrKey, dataE);
                                        window.__promoAllTours = dataE;
                                        syncPromoStarButtons();
                                        applyPromoFiltersAndRender();
                                        if (emptyEl) emptyEl.classList.add('hidden');
                                        if (resultsEl) resultsEl.classList.remove('hidden');
                                        return;
                                    }
                                    console.warn('[Акции] Ошибка onlyPromo и пустой fallback ближайших дат:', j.error);
                                    var msgElFb = document.getElementById('promo-tours-empty-msg');
                                    var localhostHintFb = (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1')
                                        ? ' На localhost возможны таймауты или ошибки SSL при обращении к внешнему API — на продакшене обычно работает стабильнее.'
                                        : '';
                                    if (msgElFb) msgElFb.textContent = (j.error || 'Ошибка загрузки') + '. Попробуйте позже или уточните в офисах.' + localhostHintFb;
                                    if (emptyEl) emptyEl.classList.remove('hidden');
                                });
                        }
                        console.warn('[Акции] Ошибка API:', j.error);
                        var msgEl = document.getElementById('promo-tours-empty-msg');
                        var localhostHint = (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1')
                            ? ' На localhost возможны таймауты или ошибки SSL при обращении к внешнему API — на продакшене обычно работает стабильнее.'
                            : '';
                        if (msgEl) msgEl.textContent = (j.error || 'Ошибка загрузки') + '. Попробуйте позже или уточните в офисах.' + localhostHint;
                        if (emptyEl) emptyEl.classList.remove('hidden');
                        return;
                    }

                    var data0 = promoCoerceHotelsAfterPostProcess(j.data, COUNTRY_ID);
                    promoDebugSummarizeHotels(data0, 'после postProcess (legacy)');
                    if (data0.length > 0) {
                        data0.sort(function (a, b) { return promoHotelListPrice(a) - promoHotelListPrice(b); });
                        promoToursSwrWrite(swrKey, data0);
                        window.__promoAllTours = data0;
                        syncPromoStarButtons();
                        applyPromoFiltersAndRender();
                    }
                    return promoFinalizeTourResults(data0, COUNTRY_ID)
                        .then(function (data) {
                            if (mySeq !== promoToursFetchSeq) return;
                            var display = promoPickDisplayHotels(data, data0, COUNTRY_ID);
                            if (!display.length) {
                                window.__promoAllTours = [];
                                if (resultsEl) { resultsEl.innerHTML = ''; resultsEl.classList.add('hidden'); }
                                console.log('[Акции] Пустой результат — показываем сообщение «нет туров со скидкой»');
                                var msgEl0 = document.getElementById('promo-tours-empty-msg');
                                var localhostHint0 = (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1')
                                    ? ' На localhost API может не вернуть туры из-за сетевых ограничений или SSL — на продакшен-сервере обычно работает стабильнее.'
                                    : '';
                                if (msgEl0) msgEl0.textContent = 'По заданным фильтрам нет туров. Измените параметры поиска или категорию отеля.' + localhostHint0;
                                if (emptyEl) emptyEl.classList.remove('hidden');
                                return;
                            }
                            display.sort(function (a, b) {
                                return promoHotelListPrice(a) - promoHotelListPrice(b);
                            });
                            promoToursSwrWrite(swrKey, display);
                            window.__promoAllTours = display;
                            syncPromoStarButtons();
                            applyPromoFiltersAndRender();
                            promoShowFallbackDepartureNoticeIfAny();
                            if (COUNTRY_ID) promoSyncCptileMinPrice(COUNTRY_ID, display);
                        });
                })
                .catch(function (err) {
                    if (mySeq !== promoToursFetchSeq) return;
                    if (window.__promoAllTours && window.__promoAllTours.length > 0) {
                        var loadingElKeep = document.getElementById('promo-tours-loading');
                        if (loadingElKeep) loadingElKeep.classList.add('hidden');
                        return;
                    }
                    console.error('[Акции] Ошибка fetch:', err.message, err);
                    var loadingEl = document.getElementById('promo-tours-loading');
                    var emptyEl = document.getElementById('promo-tours-empty');
                    var msgEl = document.getElementById('promo-tours-empty-msg');
                    var localhostHint = (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1')
                        ? ' На localhost возможны таймауты или ошибки при обращении к TourVisor API — на продакшене обычно работает стабильнее.'
                        : '';
                    if (loadingEl) loadingEl.classList.add('hidden');
                    if (msgEl) msgEl.textContent = 'Не удалось загрузить туры. Проверьте подключение и попробуйте позже или уточните в офисах.' + localhostHint;
                    if (emptyEl) emptyEl.classList.remove('hidden');
                });
        }

        document.addEventListener('DOMContentLoaded', function () {
            console.log('[Акции] DOMContentLoaded — запуск loadPromoTours, countryId:', COUNTRY_ID);
            if (PROMO_LIVE_ONLY) {
                console.warn('[Акции] PROMO_LIVE_ONLY: принудительно live, без кэша');
            } else {
                console.log('[Акции] Режим: кэш promo_speed_file, при промахе — live (см. promoSearchSource в логах)');
            }
            loadPromoTours();
        });

        window.addEventListener('pageshow', function (ev) {
            if (!ev.persisted) return;
            console.log('[Акции] pageshow (bfcache) — повторная загрузка туров');
            loadPromoTours();
        });
    }

    /* ═══════════════════════════════════════════════════════════════
       UNIFIED STEP: Плитка стран + AJAX-загрузка туров без перезагрузки
       ═══════════════════════════════════════════════════════════════ */
    if (cfg.step === 'unified') {
        window.__isUnifiedMode = true;
        var uBase = TV_API_BASE.replace(/\/$/, '');
        var uSep  = uBase.indexOf('?') >= 0 ? '&' : '?';
        var uTourFetchSeq = 0;

        /* ── Общий стейт unified-вида ── */
        var uCurrentCountryId   = null;
        var uCurrentCountryName = '';
        var uCurrentCountryAcc  = '';
        var uPromoNoStar = PROMO_NO_STAR_FILTERS;

        /* ── Утилиты ── */
        function uEsc(s) { return String(s || '').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;'); }

        function uCountryImgStyle(c, imgMap) {
            var id = c.id;
            var name = (c.name || c.russianName || '').toString();
            var nameNorm = name.toLowerCase().trim();
            var img = LOCAL_COUNTRY_IMAGES[name] || imgMap[id] || imgMap[name] || imgMap[nameNorm]
                || (PROMO_COUNTRY_FALLBACK_IMAGES && (PROMO_COUNTRY_FALLBACK_IMAGES[name] || PROMO_COUNTRY_FALLBACK_IMAGES[nameNorm] || PROMO_COUNTRY_FALLBACK_IMAGES['_default']))
                || '';
            return img ? 'background-image:url(\'' + String(img).replace(/'/g,"\\'") + '\');' : 'background:#c7d2e2;';
        }

        /* ── Рендер компактной плитки страны ── */
        function uRenderTile(c, displayName, imgMap) {
            var id = String(c.id);
            var name = uEsc(displayName || c.name || c.russianName || '');
            var style = uCountryImgStyle(c, imgMap);
            var mEnt = promoGetManifestEntry(id);
            var mPrice = (mEnt.minPrice && mEnt.minPrice > 0) ? mEnt.minPrice : 0;
            var mHas = !!(mEnt.has || mPrice > 0);
            var priceHtml = (mHas && mPrice > 0)
                ? '<div class="promo-cptile-price">от ' + formatPrice(mPrice) + '</div>'
                : '';
            /* Без спиннера «акции…» — плитки сразу кликабельны; цена дорисуется из prefetch/манифеста */
            return '<button type="button" class="promo-cptile" data-uid="' + uEsc(id) + '"' +
                ' data-uname="' + uEsc(displayName || (c.name || c.russianName || '').toString()) + '"' +
                ' data-unostar="' + (id === '46' ? '1' : '0') + '"' +
                ' aria-label="' + name + '">' +
                '<div class="promo-cptile-img" style="' + style + '"></div>' +
                '<div class="promo-cptile-overlay"></div>' +
                '<div class="promo-cptile-name">' + name + '</div>' +
                priceHtml +
                '</button>';
        }

        /* ── Показать / скрыть секции ── */
        function uShowCountries() {
            var ts = document.getElementById('promo-tours-section');
            var cs = document.getElementById('promo-countries-section');
            if (ts) ts.classList.add('hidden');
            if (cs) cs.classList.remove('hidden');
            /* Сбрасываем активный тайл */
            document.querySelectorAll('.promo-cptile.is-active').forEach(function(t) { t.classList.remove('is-active'); });
            uCurrentCountryId = null;
            window.__promoActiveCountryId = '';
            window.__promoActiveCountryName = '';
            promoUpdateStickyLead(false);
            /* URL — убираем countryId */
            try {
                var u = new URL(window.location.href);
                u.searchParams.delete('countryId');
                u.searchParams.delete('countryName');
                history.replaceState(null, '', u.pathname + (u.search || '') + (u.hash || ''));
            } catch(_) {}
        }

        document.addEventListener('click', function (ev) {
            var t = ev.target;
            if (!t || typeof t.closest !== 'function') return;
            if (t.closest('#promo-back-to-countries') || t.closest('#promo-back-to-countries-2')) {
                ev.preventDefault();
                uShowCountries();
            }
        });

        function uShowTours(countryId, countryName) {
            var cs = document.getElementById('promo-countries-section');
            var ts = document.getElementById('promo-tours-section');
            if (cs) cs.classList.add('hidden');
            if (ts) ts.classList.remove('hidden');
            ts && ts.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        /* ── Загрузить туры для выбранной страны ── */
        function uLoadTours(countryId, countryName, noStar) {
            if (promoIsExcludedCountryId(countryId)) {
                uShowCountries();
                return;
            }
            promoSyncDepartureFromSitePreference();
            window.__isUnifiedMode = true;
            promoSetPrefetchPaused(true);
            var myTourSeq = ++uTourFetchSeq;
            uCurrentCountryId   = countryId;
            window.__promoActiveCountryId = String(countryId);
            uCurrentCountryName = countryName;
            window.__promoActiveCountryName = countryName;
            promoUpdateStickyLead(true);
            uPromoNoStar = promoSkipsStarFilterForCountry(countryId);

            /* Обновляем заголовок */
            var title = document.getElementById('promo-tours-title');
            var sub   = document.getElementById('promo-tours-subtitle');
            if (title) title.textContent = 'Акционные туры в ' + promoTitleCountryName(countryId, countryName);
            if (sub) {
                sub.textContent = 'Цены за ' + thPromoAdultsCount() + ' взрослых. Ближайшие вылеты.';
            }

            /* Управление фильтром звёзд */
            var filtersRow = document.getElementById('promo-filters-row');
            if (filtersRow) filtersRow.style.display = uPromoNoStar ? 'none' : '';

            /* Сброс и показ секции туров: по умолчанию «Все★» как в разметке; фильтр в попапе по-прежнему можно сузить. */
            window.__promoStarFilter = '';
            try { window.__promoStarSet = null; } catch (eStar) {}
            var lblStars = document.getElementById('promo-stars-label');
            var trigStars = document.getElementById('promo-stars-trigger');
            if (lblStars) lblStars.textContent = 'Необязательно — можно смотреть все туры';
            if (trigStars) trigStars.classList.remove('has-selection');

            var loadEl    = document.getElementById('promo-tours-loading');
            var resultsEl = document.getElementById('promo-tours-results');
            var emptyEl   = document.getElementById('promo-tours-empty');

            /* Синхронизируем кнопки звёзд (только с data-promo-star; триггер попапа без атрибута не трогаем как «кнопку звёзд»). */
            document.querySelectorAll('#promo-filters-row .promo-star-btn[data-promo-star]').forEach(function(btn) {
                var v = btn.getAttribute('data-promo-star') || '';
                var on = (v === window.__promoStarFilter);
                btn.setAttribute('aria-pressed', on ? 'true' : 'false');
                btn.classList.toggle('is-active', on);
            });

            /* URL update */
            try {
                var u = new URL(window.location.href);
                u.searchParams.set('countryId',   String(countryId));
                u.searchParams.set('countryName', countryName);
                history.pushState({ countryId: countryId, countryName: countryName }, '', u.pathname + (u.search || '') + (u.hash || ''));
            } catch(_) {}

            uShowTours(countryId, countryName);

            /* Запрос туров: параллельно несколько окон ночей (ограничение API ≤10 ночей за вызов). */
            var dFrom = new Date(); dFrom.setDate(dFrom.getDate() + PROMO_DATE_PLUS_FROM);
            var dTo   = new Date(); dTo.setDate(dTo.getDate() + promoDatePlusTo(countryId));
            var dFromStr = formatLocalYMD(dFrom);
            var dToStr = formatLocalYMD(dTo);
            window.__promoLastParams = {
                departureId:   String(DEPARTURE_ID || DEFAULT_DEPARTURE_ID),
                departure_city: DEPARTURE_NAME || DEFAULT_DEPARTURE_NAME,
                dateFrom:      dFromStr,
                dateTo:        dToStr
            };

            var swrKeyU = promoToursSwrCacheKey(String(DEPARTURE_ID || DEFAULT_DEPARTURE_ID || '0'), String(countryId), thPromoAdultsCount(), dFromStr, dToStr);
            var paintOptsU = {
                swrKey: swrKeyU,
                countryId: countryId,
                seqOk: function () { return myTourSeq === uTourFetchSeq; },
                onPaint: function (data) {
                    window.__promoAllTours = data;
                    if (loadEl) loadEl.classList.add('hidden');
                    if (emptyEl) emptyEl.classList.add('hidden');
                    if (resultsEl) resultsEl.classList.remove('hidden');
                    uRenderTourCards(data);
                }
            };
            var paintedU = false;
            if (!PROMO_LIVE_ONLY) {
                paintedU = promoTryInstantPrefetchPaint(paintOptsU) || promoToursTrySwrPaint(paintOptsU);
            }
            if (!paintedU) {
                window.__promoAllTours = [];
                if (loadEl) loadEl.classList.remove('hidden');
                if (resultsEl) { resultsEl.innerHTML = ''; resultsEl.classList.add('hidden'); }
                if (emptyEl) emptyEl.classList.add('hidden');
            }

            function uReleasePrefetchPause() {
                if (myTourSeq !== uTourFetchSeq) return;
                setTimeout(function () {
                    if (myTourSeq === uTourFetchSeq) promoSetPrefetchPaused(false);
                }, 2500);
            }

            fetchPromoSearchBundledPreferCache(countryId, 120000)
                .then(function (j) {
                    if (myTourSeq !== uTourFetchSeq) return;
                    if (loadEl) loadEl.classList.add('hidden');
                    console.groupCollapsed('%c[Акции] Запрос туров (unified, ' + promoSearchRequestLogTitle(j) + ')', 'color:#f59e0b;font-weight:bold');
                    promoLogSearchResponse('promo-search (unified)', j || {}, { countryId: countryId });
                    var merged = (j && j.success && Array.isArray(j.data)) ? j.data : [];
                    j = {
                        success: !!(j && j.success),
                        data: merged,
                        error: (j && j.error) ? j.error : undefined,
                        fromCache: !!(j && j.fromCache),
                        promoSearchSource: (j && j.promoSearchSource) ? j.promoSearchSource : undefined
                    };
                    console.group('%c[Акции] Итог promo-search (unified)', 'color:#22c55e;font-weight:bold');
                    console.log('countryId:', countryId, 'success:', j.success, 'source:', j.promoSearchSource, 'fromCache:', j.fromCache);
                    console.log('hotels:', (j.data && j.data.length) || 0, 'полный ответ:', j);
                    console.groupEnd();
                    console.groupEnd();
                    if (!j.success) {
                        if (window.__promoAllTours && window.__promoAllTours.length > 0) {
                            if (loadEl) loadEl.classList.add('hidden');
                            return;
                        }
                        var nearestErrFbU = false;
                        try {
                            nearestErrFbU = usesNearestPromoFallback(countryId);
                        } catch (eVnU) { nearestErrFbU = false; }
                        if (nearestErrFbU) {
                            return promoFinalizeTourResults([], countryId)
                                .then(function (dataEu) {
                                    if (myTourSeq !== uTourFetchSeq) return;
                                    if (dataEu && dataEu.length > 0) {
                                        dataEu.sort(function (a, b) { return promoHotelListPrice(a) - promoHotelListPrice(b); });
                                        promoToursSwrWrite(swrKeyU, dataEu);
                                        window.__promoAllTours = dataEu;
                                        uRenderTourCards(dataEu);
                                        if (emptyEl) emptyEl.classList.add('hidden');
                                        if (resultsEl) resultsEl.classList.remove('hidden');
                                        return;
                                    }
                                    var msg = document.getElementById('promo-tours-empty-msg');
                                    if (msg) msg.textContent = 'Не удалось загрузить туры. Попробуйте позже или выберите другое направление.';
                                    var hint = document.getElementById('promo-tours-empty-hint');
                                    if (hint) hint.textContent = 'Попробуйте обновить страницу или обратитесь к менеджеру — мы подберём тур вручную.';
                                    if (emptyEl) emptyEl.classList.remove('hidden');
                                });
                        }
                        var msg = document.getElementById('promo-tours-empty-msg');
                        if (msg) msg.textContent = 'Не удалось загрузить туры. Попробуйте позже или выберите другое направление.';
                        var hint = document.getElementById('promo-tours-empty-hint');
                        if (hint) hint.textContent = 'Попробуйте обновить страницу или обратитесь к менеджеру — мы подберём тур вручную.';
                        if (emptyEl) emptyEl.classList.remove('hidden');
                        return;
                    }
                    var data0u = promoCoerceHotelsAfterPostProcess(j.data, countryId);
                    promoDebugSummarizeHotels(data0u, 'после postProcess (unified)');
                    if (data0u.length > 0) {
                        data0u.sort(function (a, b) { return promoHotelListPrice(a) - promoHotelListPrice(b); });
                        promoToursSwrWrite(swrKeyU, data0u);
                        window.__promoAllTours = data0u;
                        if (loadEl) loadEl.classList.add('hidden');
                        uRenderTourCards(data0u);
                        promoSyncCptileMinPrice(countryId, data0u);
                    }
                    return promoFinalizeTourResults(data0u, countryId)
                        .then(function (data) {
                            if (myTourSeq !== uTourFetchSeq) return;
                            promoDebugSummarizeHotels(data, 'после finalize (unified)');
                            var display = promoPickDisplayHotels(data, data0u, countryId);
                            if (!display.length) {
                                window.__promoAllTours = [];
                                if (resultsEl) { resultsEl.innerHTML = ''; resultsEl.classList.add('hidden'); }
                                var msg2 = document.getElementById('promo-tours-empty-msg');
                                if (msg2) {
                                    msg2.textContent = 'По этому направлению горящих туров пока нет';
                                }
                                if (emptyEl) emptyEl.classList.remove('hidden');
                                return;
                            }
                            display.sort(function(a, b) { return promoHotelListPrice(a) - promoHotelListPrice(b); });
                            promoToursSwrWrite(swrKeyU, display);
                            window.__promoAllTours = display;
                            uRenderTourCards(display);
                            promoSyncCptileMinPrice(countryId, display);
                        });
                })
                .catch(function() {
                    if (myTourSeq !== uTourFetchSeq) return;
                    if (window.__promoAllTours && window.__promoAllTours.length > 0) {
                        if (loadEl) loadEl.classList.add('hidden');
                        return;
                    }
                    if (loadEl) loadEl.classList.add('hidden');
                    var msg3 = document.getElementById('promo-tours-empty-msg');
                    if (msg3) msg3.textContent = 'Ошибка загрузки. Проверьте соединение и попробуйте снова.';
                    if (emptyEl) emptyEl.classList.remove('hidden');
                })
                .finally(uReleasePrefetchPause);
        }

        /* ── Рендер карточек туров (переиспользует renderPromoTourCards) ── */
        function uRenderTourCards(data) {
            /* renderPromoTourCards — функция уже объявлена выше в блоке tours */
            var resultsEl = document.getElementById('promo-tours-results');
            var emptyEl   = document.getElementById('promo-tours-empty');
            if (!resultsEl) return;

            var filtered = uGetFilteredTours(data);
            var prepared = promoPrepareHotelListForDisplay(filtered);
            if (uCurrentCountryId && prepared.length) {
                promoSyncCptileMinPrice(uCurrentCountryId, prepared);
            }
            if (emptyEl) emptyEl.classList.add('hidden');
            resultsEl.classList.remove('hidden');
            resultsEl.innerHTML = renderPromoTourCards(prepared);
            patchPromoCardFlights(prepared);
            updatePromoHotelCardPrices(prepared);
            if (window.THTourCard && typeof window.THTourCard.mountInContainer === 'function') {
                window.THTourCard.mountInContainer(resultsEl);
            } else if (window.THTourCard && typeof window.THTourCard.ensureCarouselsInContainer === 'function') {
                window.THTourCard.ensureCarouselsInContainer(resultsEl);
            } else if (window.THTourCard && typeof window.THTourCard.kickImagesInContainer === 'function') {
                window.THTourCard.kickImagesInContainer(resultsEl);
            }
            if (emptyEl) {
                if (filtered.length === 0) {
                    var msg = document.getElementById('promo-tours-empty-msg');
                    if (msg) msg.textContent = 'По выбранной категории отеля туров нет. Попробуйте другой фильтр.';
                    emptyEl.classList.remove('hidden');
                } else {
                    emptyEl.classList.add('hidden');
                }
            }
        }

        function uGetFilteredTours(data) {
            var list = promoFilterHotelsMinNights(data || window.__promoAllTours || [], uCurrentCountryId);
            return promoFilterHotelsByStarSelection(list, uPromoNoStar);
        }

        /* ── Star filter: попап + legacy-кнопки ── */
        window.thPromoPickStar = function(btn) {
            if (!btn || typeof btn.getAttribute !== 'function') return false;
            var raw = btn.getAttribute('data-promo-star');
            window.__promoStarFilter = (raw === null || raw === undefined) ? '' : String(raw);
            window.__promoStarSet = null;
            document.querySelectorAll('#promo-filters-row .promo-star-btn[data-promo-star]').forEach(function(b) {
                var v = b.getAttribute('data-promo-star') || '';
                var on = (v === window.__promoStarFilter);
                b.setAttribute('aria-pressed', on ? 'true' : 'false');
                b.classList.toggle('is-active', on);
            });
            uRenderTourCards(window.__promoAllTours || []);
            return false;
        };
        window.__TH_PROMO_STAR_APPLY = window.thPromoPickStar;

        /* ── Загрузка стран: этап 1 — популярные сразу; этап 2 — остальные в фоне ── */
        function uRefreshCountryTilesPromoState() {
            var ids = [];
            document.querySelectorAll('.promo-cptile[data-uid]').forEach(function (tile) {
                ids.push(tile.getAttribute('data-uid'));
                tile.classList.remove('is-active', 'promo-cptile-nopromo');
            });
            if (!ids.length) return;
            promoRepaintCptilesForIds(ids);
            var popularIds = [];
            var otherIds = [];
            ids.forEach(function (id) {
                var tile = document.querySelector('.promo-cptile[data-uid="' + id + '"]');
                if (tile && tile.closest('#promo-popular-grid')) popularIds.push(id);
                else otherIds.push(id);
            });
            if (popularIds.length) {
                promoApplyManifestToCountries(popularIds.map(function (id) { return { id: id }; }), true);
                runPromoChecksFromManifest(popularIds.map(function (id) { return { id: id }; }), function (c, hasPromo, minPrice, opts) {
                    promoApplyCptilePromoState(c.id, hasPromo, minPrice, true, opts);
                }, null, { prefetchDelayMs: 400, prefetchCacheOnly: true });
            }
            if (otherIds.length) {
                runPromoChecksFromManifest(otherIds.map(function (id) { return { id: id }; }), function (c, hasPromo, minPrice, opts) {
                    promoApplyCptilePromoState(c.id, hasPromo, minPrice, false, opts);
                }, null, { prefetch: false });
            }
        }

        function uSwitchDeparture(newId, newName) {
            var picked = promoAllowedDeparture(newId, newName);
            if (String(DEPARTURE_ID) === String(picked.id)) return;
            DEPARTURE_ID = picked.id;
            DEPARTURE_NAME = picked.name;
            try {
                localStorage.setItem('th_departure_id', String(DEPARTURE_ID));
                localStorage.setItem('th_departure_name', DEPARTURE_NAME);
            } catch (eLs) {}
            if (window.THDeparturePreference && typeof window.THDeparturePreference.save === 'function') {
                window.THDeparturePreference.save(DEPARTURE_ID, DEPARTURE_NAME);
            }
            try {
                window.TH_DEPARTURE = { id: DEPARTURE_ID, name: DEPARTURE_NAME };
            } catch (eThDep) {}
            promoClearFlightsCache();
            promoUpdateDepartureUi(picked);
            try {
                var u = new URL(window.location.href);
                u.searchParams.set('departureId', String(DEPARTURE_ID));
                u.searchParams.set('departureName', DEPARTURE_NAME);
                history.replaceState(null, '', u.pathname + (u.search || '') + (u.hash || ''));
            } catch (eUrl) {}
            if (uCurrentCountryId) {
                uLoadTours(uCurrentCountryId, uCurrentCountryName, uPromoNoStar);
            } else {
                uRefreshCountryTilesPromoState();
            }
        }

        initPromoDeparturePicker(uSwitchDeparture);

        document.addEventListener('DOMContentLoaded', function() {
            initPromoStarFilterPopup(function () {
                uRenderTourCards(window.__promoAllTours || []);
            });
            promoSyncDepartureFromSitePreference();
            var loadingEl = document.getElementById('promo-countries-loading');
            var popularWrap = document.getElementById('promo-popular-wrap');
            var popularGrid = document.getElementById('promo-popular-grid');
            var otherWrap = document.getElementById('promo-other-wrap');
            var otherLoading = document.getElementById('promo-other-loading');
            var gridEl = document.getElementById('promo-countries-grid');
            var emptyEl = document.getElementById('promo-countries-empty');
            var emptyMsgEl = document.getElementById('promo-countries-empty-msg');

            var imgMap = {};
            var popular = promoBuildPopularList(cfg, {});
            var uPreselectDone = false;

            function renderPopularTiles() {
                if (!popularGrid || !popular.length) return;
                popularGrid.innerHTML = popular.map(function (p) {
                    return uRenderTile(p.c, p.displayName, imgMap);
                }).join('');
                if (popularWrap) popularWrap.classList.remove('hidden');
                promoApplyManifestToCountries(popular.map(function (p) { return p.c; }), true);
                promoRepaintCptilesForIds(popular.map(function (p) { return p.c.id; }));
            }

            function hideMainLoading() {
                if (loadingEl) loadingEl.classList.add('hidden');
            }

            function tryPreselectCountry() {
                if (uPreselectDone) return;
                var preselectedId = COUNTRY_ID || cfg.countryId;
                if (!preselectedId) return;
                uPreselectDone = true;
                var preName = cfg.countryName || '';
                var preNoStar = promoSkipsStarFilterForCountry(preselectedId);
                setTimeout(function () {
                    document.querySelectorAll('.promo-cptile[data-uid="' + String(preselectedId) + '"]').forEach(function (t) {
                        t.classList.add('is-active');
                    });
                }, 50);
                uLoadTours(String(preselectedId), preName, preNoStar);
            }

            function bindUnifiedTileDelegation() {
                var section = document.getElementById('promo-countries-section');
                if (!section || section.__thUnifiedTilesBound) return;
                section.__thUnifiedTilesBound = true;
                section.addEventListener('click', function (e) {
                    var tile = e.target.closest('.promo-cptile');
                    if (!tile) return;
                    /* nopromo раньше блокировал клик по популярным — убираем жёсткий стоп */
                    if (tile.classList.contains('promo-cptile-nopromo') && !tile.closest('#promo-popular-grid')) return;
                    var cId = tile.dataset.uid;
                    var cName = tile.dataset.uname;
                    var noS = tile.dataset.unostar === '1';
                    if (!cId) return;
                    document.querySelectorAll('.promo-cptile.is-active').forEach(function (t) { t.classList.remove('is-active'); });
                    tile.classList.add('is-active');
                    promoYm('promo_country_click');
                    uLoadTours(cId, cName, noS);
                });
            }

            bindUnifiedTileDelegation();

            var toursResultsEl = document.getElementById('promo-tours-results');
            if (toursResultsEl && !toursResultsEl.__thTourOpenBound) {
                toursResultsEl.__thTourOpenBound = true;
                toursResultsEl.addEventListener('click', function (e) {
                    if (e.target.closest('a[href]')) promoYm('promo_tour_open');
                });
            }

            if (!window.__thPromoPopstateBound) {
                window.__thPromoPopstateBound = true;
                window.addEventListener('popstate', function (e) {
                    if (e.state && e.state.countryId) {
                        uLoadTours(e.state.countryId, e.state.countryName || '', false);
                    } else {
                        uShowCountries();
                    }
                });
            }

            /* Этап 1: популярные из конфига — без ожидания полного справочника */
            if (!popular.length) {
                hideMainLoading();
                if (emptyMsgEl) emptyMsgEl.textContent = 'Список популярных направлений не настроен.';
                if (emptyEl) emptyEl.classList.remove('hidden');
                return;
            }

            renderPopularTiles();
            hideMainLoading();

            var popularOnly = popular.map(function (p) { return p.c; });
            promoApplyManifestToCountries(popularOnly, true);
            runPromoChecksFromManifest(popularOnly, function (c, hasPromo, minPrice, opts) {
                promoApplyCptilePromoState(c.id, hasPromo, minPrice, true, opts);
            }, function () {
                tryPreselectCountry();
            }, { prefetchDelayMs: 400, prefetchCacheOnly: true });

            safeFetchJson(COUNTRIES_WITH_IMAGES_URL, { countries: [] }).catch(function () {
                return { countries: [] };
            }).then(function (imagesRes) {
                imgMap = promoBuildImgMap(imagesRes || {});
                renderPopularTiles();
            });

            /* Этап 2 отключён: только популярные направления */
            if (otherLoading) otherLoading.classList.add('hidden');
            if (otherWrap) {
                otherWrap.classList.add('hidden');
                otherWrap.setAttribute('hidden', '');
            }
            if (gridEl) gridEl.innerHTML = '';
            /* Deep-link: стартуем cacheOnly prefetch до загрузки туров. */
            if (COUNTRY_ID || cfg.countryId) {
                promoPrefetchToursForCountries(
                    [String(COUNTRY_ID || cfg.countryId)],
                    { cacheOnly: true }
                );
            }
            tryPreselectCountry();
        });
    }

    function promoUpdateStickyLead(toursVisible) {
        var generic = document.getElementById('promo-sticky-cta');
        var results = document.getElementById('promo-results-sticky-lead');
        var resultsBtn = document.getElementById('promo-results-sticky-lead-btn');
        if (generic) {
            if (toursVisible) generic.setAttribute('hidden', '');
            else generic.removeAttribute('hidden');
        }
        if (results) {
            if (toursVisible) {
                results.classList.add('is-visible');
                var cn = (typeof window.__promoActiveCountryName === 'string') ? window.__promoActiveCountryName : '';
                if (resultsBtn) {
                    var label = cn
                        ? ('Подобрать тур: ' + cn.replace(/</g, ''))
                        : 'Подобрать горящий тур';
                    resultsBtn.innerHTML = '<i class="fas fa-phone" aria-hidden="true"></i> ' + label;
                }
            } else {
                results.classList.remove('is-visible');
            }
        }
        if (window.THMobile && typeof window.THMobile.sync === 'function') window.THMobile.sync();
    }

    document.addEventListener('DOMContentLoaded', function () {
        function ymGoal(goal) {
            try {
                var c = window.__TH_PROMO_PAGE__;
                var id = (c && c.ymId && String(c.ymId).replace(/\D/g, '')) ? parseInt(String(c.ymId).replace(/\D/g, ''), 10) : 0;
                if (id && typeof ym === 'function') ym(id, 'reachGoal', goal);
            } catch (eY) {}
        }
        function sendUonLead(form, note, msgBox) {
            var fd = new FormData(form);
            var leadSource = form.getAttribute('data-th-lead-source') || 'promo_bottom';
            var leadMessage = note;
            if (window.THPromoLead && typeof window.THPromoLead.buildMessage === 'function') {
                leadMessage = window.THPromoLead.buildMessage(note || '');
            }
            var payload = {
                name: String(fd.get('name') || '').trim(),
                phone: String(fd.get('phone') || '').trim(),
                message: leadMessage,
                agree: !!fd.get('agree'),
                agree_ads: !!fd.get('agree_ads'),
                website: String(fd.get('website') || ''),
                source: leadSource
            };
            function showLeadErr(text) {
                if (msgBox) {
                    msgBox.textContent = text;
                    msgBox.className = 'text-sm rounded-lg p-2 bg-red-100 text-red-900 block';
                    msgBox.classList.remove('hidden');
                } else {
                    alert(text);
                }
            }
            if (window.THLeadCapture && window.THLeadCapture.submit) {
                /* client+server через THLeadCapture */
            } else {
                /* Fallback без THLeadCapture — минимальная клиентская проверка до fetch */
                if (!payload.name || payload.name.length < 2) {
                    showLeadErr('Укажите корректные ФИО');
                    return Promise.resolve();
                }
                var cyrFb = payload.name.match(/\p{Script=Cyrillic}/gu);
                if (!cyrFb || cyrFb.length < 2) {
                    showLeadErr('Укажите ФИО русскими буквами');
                    return Promise.resolve();
                }
                var pd = payload.phone.replace(/\D/g, '');
                if (pd.length === 11 && pd[0] === '8') pd = '7' + pd.slice(1);
                var restFb = pd.slice(1);
                if (pd.length !== 11 || pd[0] !== '7' || !restFb || restFb[0] !== '9' || /^(\d)\1{9}$/.test(restFb)) {
                    showLeadErr('Укажите корректный мобильный телефон РФ (+7 9XX…)');
                    return Promise.resolve();
                }
                if (!payload.agree) {
                    showLeadErr('Нужно согласие на обработку данных');
                    return Promise.resolve();
                }
            }
            var send = (window.THLeadCapture && window.THLeadCapture.submit)
                ? window.THLeadCapture.submit(payload)
                : fetch('/backend/api/uon-lead.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        name: payload.name,
                        phone: payload.phone,
                        message: payload.message,
                        agree: payload.agree,
                        agree_ads: payload.agree_ads,
                        website: payload.website,
                        funnel_source: leadSource,
                        source: leadSource
                    })
                }).then(function (r) { return r.json().catch(function () { return { success: false, error: 'Ошибка ответа' }; }); });

            var usedLeadCapture = !!(window.THLeadCapture && window.THLeadCapture.submit);
            send.then(function (data) {
                if (data && data.success) {
                    ymGoal('promo_lead_success');
                    /* lead_ok уже стреляет THLeadCapture.submit */
                    if (!usedLeadCapture) ymGoal('lead_ok');
                    promoYm('promo_lead_submit');
                    form.reset();
                    if (msgBox) {
                        msgBox.textContent = data.message || 'Заявка отправлена. Перезвоним за 15 минут.';
                        msgBox.className = 'text-sm rounded-lg p-2 bg-emerald-100 text-emerald-900 block';
                        msgBox.classList.remove('hidden');
                    } else {
                        alert('Заявка отправлена. Перезвоним за 15 минут.');
                    }
                } else {
                    var err = (data && data.error) ? data.error : 'Не удалось отправить.';
                    if (msgBox) {
                        msgBox.textContent = err;
                        msgBox.className = 'text-sm rounded-lg p-2 bg-red-100 text-red-900 block';
                        msgBox.classList.remove('hidden');
                    } else {
                        alert(err);
                    }
                    ymGoal('promo_lead_error');
                    if (!usedLeadCapture) ymGoal('lead_err');
                }
            }).catch(function () {
                ymGoal('promo_lead_error');
                if (!usedLeadCapture) ymGoal('lead_err');
                alert('Нет связи с сервером. Попробуйте позже.');
            });
        }
        function promoOpenLead(source, note, title, sub) {
            if (window.THPromoLead && typeof window.THPromoLead.open === 'function') {
                window.THPromoLead.open({
                    source: source,
                    note: note || '',
                    title: title,
                    sub: sub
                });
            } else if (typeof window.openSiteFeedbackModal === 'function') {
                window.openSiteFeedbackModal({ source: source || 'promo_lead' });
            }
        }
        var sticky = document.getElementById('promo-sticky-cta');
        var stickyBtn = document.getElementById('promo-sticky-cta-btn');
        if (stickyBtn) {
            stickyBtn.addEventListener('click', function () {
                ymGoal('promo_sticky_cta_click');
                promoOpenLead('promo_sticky');
            });
        } else if (sticky) {
            sticky.querySelectorAll('a[href^="#"]').forEach(function (a) {
                a.addEventListener('click', function () { ymGoal('promo_sticky_cta_click'); });
            });
        }
        var resultsStickyBtn = document.getElementById('promo-results-sticky-lead-btn');
        if (resultsStickyBtn) {
            resultsStickyBtn.addEventListener('click', function () {
                ymGoal('promo_results_sticky_click');
                promoOpenLead('promo_results_sticky');
            });
        }
        var emptyPickBtn = document.getElementById('promo-empty-pick-btn');
        if (emptyPickBtn) {
            emptyPickBtn.addEventListener('click', function () {
                ymGoal('promo_empty_pick_click');
                promoOpenLead(
                    'promo_empty',
                    'Нет горящих туров по выбранному направлению — нужен индивидуальный подбор.',
                    'Подберём тур в это направление',
                    'Оставьте телефон — найдём альтернативу или сообщим, когда появятся акции.'
                );
            });
        }
        var heroLeadBtn = document.getElementById('promo-hero-lead-btn');
        if (heroLeadBtn) {
            heroLeadBtn.addEventListener('click', function () {
                ymGoal('promo_hero_lead_click');
                promoOpenLead(
                    'promo_hero',
                    'Запрос с hero-блока страницы акций.',
                    'Лучшие горящие предложения',
                    'Оставьте телефон — подберём акции из вашего города вылета.'
                );
            });
        }
        document.addEventListener('click', function (ev) {
            var leadBtn = ev.target && ev.target.closest ? ev.target.closest('[data-th-promo-card-lead]') : null;
            if (!leadBtn) return;
            if (!leadBtn.closest('#promo-tours-results')) return;
            ev.preventDefault();
            ev.stopPropagation();
            ymGoal('promo_card_lead_click');
            var hotelName = leadBtn.getAttribute('data-hotel-name') || '';
            var hotelPrice = leadBtn.getAttribute('data-hotel-price') || '';
            var hotelCountry = leadBtn.getAttribute('data-hotel-country') || '';
            if (window.THPromoLead && typeof window.THPromoLead.open === 'function') {
                window.THPromoLead.open({
                    source: 'promo_card',
                    hotelName: hotelName,
                    hotelPrice: hotelPrice,
                    country: hotelCountry,
                    note: 'Запрос уточнения цены с карточки акции.',
                    title: 'Уточнить цену',
                    sub: 'Менеджер проверит актуальность и перезвонит за 15 минут.'
                });
            }
        });
        var df = document.getElementById('promo-departures-fallback-lead');
        if (df) {
            df.addEventListener('submit', function (ev) {
                ev.preventDefault();
                sendUonLead(df, 'Акции: не загрузился список городов вылета.', null);
            });
        }
        var cf = document.getElementById('promo-countries-fallback-lead');
        if (cf) {
            cf.addEventListener('submit', function (ev) {
                ev.preventDefault();
                sendUonLead(cf, 'Акции: не загрузился список стран (вылет из ' + (window.__TH_PROMO_PAGE__ && window.__TH_PROMO_PAGE__.departureName ? window.__TH_PROMO_PAGE__.departureName : '') + ').', null);
            });
        }
        var dr = document.getElementById('promo-departures-retry');
        if (dr) dr.addEventListener('click', function () { location.reload(); });
        var cr = document.getElementById('promo-countries-retry');
        if (cr) cr.addEventListener('click', function () { location.reload(); });
    });
})();
