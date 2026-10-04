/**
 * Tourvisor: в ответе tour-flights может быть несколько пакетов перелёта.
 * Выбираем вариант по городу вылета (подсказки + аэропорты), иначе isDefault, иначе первый.
 * Мета перелёта для API/фильтров — thFlightMetaFromPackage (карточки: только «Вылет из …»).
 */
(function (global) {
    'use strict';

    function norm(s) {
        return String(s || '').trim().toLowerCase().replace(/ё/g, 'е');
    }

    function portDepText(leg) {
        if (!leg || !leg.departure) return '';
        var p = leg.departure.port || {};
        var bits = [p.shortName, p.name, p.id, p.code, p.iata, p.enName, p.enname];
        var out = [];
        bits.forEach(function (b) {
            var x = norm(b);
            if (x && out.indexOf(x) < 0) out.push(x);
        });
        return out.join(' ');
    }

    function hintsForCity(cityRaw) {
        var s = norm(cityRaw);
        if (!s) return [];
        var out = [];
        function add(x) {
            x = norm(x);
            if (x && out.indexOf(x) < 0) out.push(x);
        }
        add(s);
        if (s.indexOf('самар') !== -1) { add('самара'); add('курумоч'); add('kuf'); }
        if (s.indexOf('москв') !== -1) {
            add('москва'); add('домодедово'); add('внуково'); add('шереметьево'); add('жуковский');
            add('dme'); add('svo'); add('vko'); add('zia'); add('mow');
        }
        if (s.indexOf('петербург') !== -1 || s === 'спб' || s.indexOf('с-петербург') !== -1) { add('пулков'); add('петербург'); add('санкт'); }
        if (s.indexOf('казан') !== -1) add('казань');
        if (s.indexOf('екатеринбург') !== -1) { add('екатеринбург'); add('кольцово'); }
        if (s.indexOf('новосибирск') !== -1) { add('новосибирск'); add('толмачево'); }
        if (s.indexOf('красноярск') !== -1) { add('красноярск'); add('емельяново'); }
        if (s.indexOf('ростов') !== -1) { add('ростов'); add('платов'); }
        if (s.indexOf('краснодар') !== -1) { add('краснодар'); add('пашковский'); }
        if (s.indexOf('уфа') !== -1) add('уфа');
        if (s.indexOf('перм') !== -1) add('пермь');
        if (s.indexOf('воронеж') !== -1) add('воронеж');
        if (s.indexOf('сочи') !== -1) add('сочи');
        if (s.indexOf('нижн') !== -1 && s.indexOf('новгород') !== -1) { add('нижний'); add('стригино'); }
        if (s.indexOf('саратов') !== -1) add('саратов');
        if (s.indexOf('омск') !== -1) add('омск');
        if (s.indexOf('тюмен') !== -1) add('тюмень');
        if (s.indexOf('иркутск') !== -1) add('иркутск');
        if (s.indexOf('челябинск') !== -1) add('челябинск');
        if (s.indexOf('калининград') !== -1) { add('калининград'); add('храброво'); }
        if (s.indexOf('минеральн') !== -1) add('минеральн');
        if (s.indexOf('мурманск') !== -1) add('мурманск');
        if (s.indexOf('владивосток') !== -1) add('владивосток');
        if (s.indexOf('хабаровск') !== -1) add('хабаровск');
        if (s.indexOf('южно-сахалинск') !== -1 || s.indexOf('сахалинск') !== -1) add('сахалинск');
        return out;
    }

    var DEPARTURE_IATA_HINTS = {
        '1': ['dme', 'svo', 'vko', 'zia', 'mow', 'москва', 'домодедово', 'шереметьево', 'внуково', 'жуковский'],
        '7': ['kuf', 'самара', 'курумоч']
    };

    function mergeDepartureHints(cityHint, departureIdHint) {
        var hints = hintsForCity(cityHint);
        var depKey = departureIdHint != null ? String(departureIdHint) : '';
        var iataList = DEPARTURE_IATA_HINTS[depKey];
        if (iataList) {
            iataList.forEach(function (code) {
                code = norm(code);
                if (code && hints.indexOf(code) < 0) hints.push(code);
            });
        }
        return hints;
    }

    function depTextMatchesHints(depText, hints) {
        if (!depText) return false;
        for (var i = 0; i < hints.length; i++) {
            var h = hints[i];
            if (!h) continue;
            if (h.length === 3 && depText.indexOf(h) !== -1) return true;
            if (h.length >= 2 && depText.indexOf(h) !== -1) return true;
        }
        return false;
    }

    function portTextIsBlocked(depText) {
        var t = norm(depText);
        if (!t) return false;
        var blocked = ['красноярск', 'krasnoyarsk', 'емельяново', 'kja'];
        for (var i = 0; i < blocked.length; i++) {
            if (t.indexOf(blocked[i]) !== -1) return true;
        }
        return false;
    }

    function pickTourvisorFlightPackage(flights, cityHint, departureIdHint) {
        if (!flights || !flights.length) return null;
        var hints = mergeDepartureHints(cityHint, departureIdHint);
        if (hints.length) {
            for (var i = 0; i < flights.length; i++) {
                var f = flights[i];
                var fw = f.forward && f.forward[0];
                var depTxt = portDepText(fw);
                if (portTextIsBlocked(depTxt)) continue;
                if (depTextMatchesHints(depTxt, hints)) return f;
            }
        }
        for (var j = 0; j < flights.length; j++) {
            if (!flights[j]) continue;
            var fw3 = flights[j].forward && flights[j].forward[0];
            if (portTextIsBlocked(portDepText(fw3))) continue;
            if (flights[j].isDefault) return flights[j];
        }
        for (var m = 0; m < flights.length; m++) {
            var fw4 = flights[m].forward && flights[m].forward[0];
            if (!portTextIsBlocked(portDepText(fw4))) return flights[m];
        }
        return null;
    }

    function legCompanyName(leg) {
        if (!leg) return '';
        return (leg.company && leg.company.name) || (leg.airline && leg.airline.name) || leg.companyName || '';
    }

    function legPortLabel(leg, end) {
        end = end || 'departure';
        if (!leg || !leg[end]) return '';
        var port = leg[end].port ? (leg[end].port.shortName || leg[end].port.name || '') : '';
        var time = String(leg[end].time || '').trim();
        if (port && time) return port + ' ' + time;
        return port || time;
    }

    /** Город вылета из порта первого сегмента (не подсказка поиска). */
    function departureCityFromPackageLeg(fw) {
        if (!fw || !fw.departure || !fw.departure.port) return '';
        var raw = String(fw.departure.port.shortName || fw.departure.port.name || '').trim();
        if (!raw) return '';
        var n = norm(raw);
        if (n.indexOf('домодедово') !== -1 || n.indexOf('шереметьево') !== -1 || n.indexOf('внуково') !== -1 || n.indexOf('жуковск') !== -1) return 'Москва';
        if (n.indexOf('курумоч') !== -1 || n === 'kuf') return 'Самара';
        if (n.indexOf('пулков') !== -1) return 'Санкт-Петербург';
        if (n.indexOf('самар') !== -1) return 'Самара';
        if (n.indexOf('москв') !== -1) return 'Москва';
        return raw;
    }

    /** Маршрут сегмента(ов): авиакомпания · вылет → прилёт */
    function directionLineFromPackageLegs(legs) {
        if (!legs || !legs.length) return '';
        var first = legs[0];
        var last = legs[legs.length - 1];
        var dep = legPortLabel(first, 'departure');
        var arr = legPortLabel(last, 'arrival');
        var route = (dep && arr) ? dep + ' \u2192 ' + arr : (dep || arr);
        var companies = [];
        legs.forEach(function (leg) {
            var n = legCompanyName(leg);
            if (n && companies.indexOf(n) < 0) companies.push(n);
        });
        var airline = companies.join(' / ');
        if (airline && route) return airline + ' \u00b7 ' + route;
        if (airline) return airline;
        return route;
    }

    function collectCompaniesFromPackage(pkg) {
        var companies = [];
        function add(n) {
            n = String(n || '').trim();
            if (n && companies.indexOf(n) < 0) companies.push(n);
        }
        var legs = []
            .concat(Array.isArray(pkg.forward) ? pkg.forward : [])
            .concat(Array.isArray(pkg.backward) ? pkg.backward : [])
            .concat(Array.isArray(pkg.back) ? pkg.back : []);
        legs.forEach(function (leg) {
            add(legCompanyName(leg));
            var segs = Array.isArray(leg.segments) ? leg.segments : [];
            segs.forEach(function (seg) { add(legCompanyName(seg)); });
        });
        return companies;
    }

    function promoTourvisorPackageIsDirect(pkg) {
        if (!pkg) return false;
        var fw = pkg.forward;
        var bw = pkg.backward || pkg.back;
        if (!Array.isArray(fw) || fw.length !== 1) return false;
        if (Array.isArray(bw) && bw.length > 1) return false;
        return true;
    }

    function thFlightMetaFromPackage(pkg, cityHint) {
        if (!pkg) return null;
        var city = String(cityHint || '').trim();
        var airline = '';
        var time = '';
        var route = '';
        var companies = collectCompaniesFromPackage(pkg);
        airline = companies[0] || '';
        var fw = pkg.forward && pkg.forward[0];
        if (fw && fw.departure) {
            time = String(fw.departure.time || '').trim();
        }
        if (fw) {
            var depP = (fw.departure && fw.departure.port)
                ? (fw.departure.port.shortName || fw.departure.port.name || '')
                : '';
            var arrP = (fw.arrival && fw.arrival.port)
                ? (fw.arrival.port.shortName || fw.arrival.port.name || '')
                : '';
            if (depP && arrP) route = depP + ' \u2192 ' + arrP;
            var actualCity = departureCityFromPackageLeg(fw);
            if (actualCity) city = actualCity;
        }
        if (!city) city = 'Самара';
        var forwardLine = directionLineFromPackageLegs(pkg.forward);
        var backwardLegs = pkg.backward || pkg.back;
        var backwardLine = directionLineFromPackageLegs(backwardLegs);
        var subline = forwardLine;
        if (!subline) {
            if (airline && time) subline = airline + ' \u00b7 ' + time;
            else if (airline) subline = airline;
            else if (time) subline = time;
            else if (route) subline = route;
        }
        return {
            city: city,
            airline: airline,
            airlines: companies,
            companies: companies,
            time: time,
            route: route,
            forwardLine: forwardLine,
            backwardLine: backwardLine,
            subline: subline,
            summary: forwardLine || route || subline,
            direct: promoTourvisorPackageIsDirect(pkg)
        };
    }

    /** Город из строки «Аэрофлот · Самара 12:10 → …». */
    function cityFromForwardLine(line) {
        var s = String(line || '').trim();
        if (!s) return '';
        var idx = s.indexOf('\u00b7');
        if (idx < 0) idx = s.indexOf('·');
        if (idx < 0) return '';
        var rest = s.slice(idx + 1).trim();
        var m = rest.match(/^(.+?)\s+\d{1,2}:\d{2}/);
        return (m && m[1]) ? m[1].trim() : '';
    }

    function thFlightMetaNormalize(raw, fallbackCity) {
        if (!raw) {
            var fb = String(fallbackCity || '').trim();
            return fb ? { city: fb, subline: '' } : null;
        }
        if (raw.city) {
            var m = {
                city: String(raw.city || fallbackCity || '').trim() || String(fallbackCity || 'Самара'),
                airline: raw.airline || (raw.companies && raw.companies[0]) || '',
                time: raw.time || '',
                route: raw.route || '',
                subline: raw.subline || '',
                summary: raw.summary || '',
                companies: raw.companies || raw.airlines || [],
                direct: !!raw.direct
            };
            m.forwardLine = raw.forwardLine || m.forwardLine || '';
            m.backwardLine = raw.backwardLine || m.backwardLine || '';
            if (!m.subline) {
                if (m.forwardLine) m.subline = m.forwardLine;
                else if (m.airline && m.time) m.subline = m.airline + ' \u00b7 ' + m.time;
                else if (m.airline) m.subline = m.airline;
                else if (m.time) m.subline = m.time;
                else if (m.route) m.subline = m.route;
                else if (m.summary) m.subline = m.summary;
            }
            if (!m.forwardLine && m.subline) m.forwardLine = m.subline;
            var cityFromLine = cityFromForwardLine(m.forwardLine);
            if (cityFromLine) m.city = cityFromLine;
            return m;
        }
        var companies = raw.companies || [];
        var airline0 = companies[0] || raw.airline || '';
        var city0 = String(fallbackCity || 'Самара').trim();
        var sub = airline0 || raw.summary || '';
        return {
            city: city0,
            airline: airline0,
            companies: companies,
            subline: sub,
            summary: raw.summary || sub,
            direct: !!raw.direct
        };
    }

    function thFlightsCacheKey(tourId, depCity) {
        return String(tourId) + '@' + norm(depCity || '');
    }

    function thFlightsCacheGet(tourId, depCity) {
        if (!tourId) return null;
        var key = thFlightsCacheKey(tourId, depCity);
        var stores = [
            global.__thFlightsByTourId,
            global.__promoFlightsByTourId,
            global.__countryFlightsByTourId,
            global.__mainFlightsByTourId
        ];
        for (var i = 0; i < stores.length; i++) {
            if (stores[i] && stores[i][key]) return stores[i][key];
        }
        return null;
    }

    function thFlightsCacheSet(tourId, meta, depCity) {
        if (!tourId || !meta) return;
        var cityKey = depCity || meta.city || '';
        var key = thFlightsCacheKey(tourId, cityKey);
        global.__thFlightsByTourId = global.__thFlightsByTourId || {};
        global.__thFlightsByTourId[key] = meta;
        global.__promoFlightsByTourId = global.__promoFlightsByTourId || {};
        global.__promoFlightsByTourId[key] = meta;
        global.__countryFlightsByTourId = global.__countryFlightsByTourId || {};
        global.__countryFlightsByTourId[key] = meta;
        global.__mainFlightsByTourId = global.__mainFlightsByTourId || {};
        global.__mainFlightsByTourId[key] = meta;
    }

    function thFlightPackagePriceNum(pkg, info) {
        if (!pkg || typeof pkg !== 'object') return 0;
        var total = 0;
        if (pkg.price != null) {
            var pv = (pkg.price && pkg.price.value != null) ? pkg.price.value : pkg.price;
            var pn = Math.round(Number(pv));
            if (pn > 0) total += pn;
        }
        if (pkg.fuelCharge != null) {
            var fv = (pkg.fuelCharge && pkg.fuelCharge.value != null) ? pkg.fuelCharge.value : pkg.fuelCharge;
            var fn = Math.round(Number(fv));
            if (fn > 0) total += fn;
        }
        if (info && info.surcharges && info.surcharges.length) {
            info.surcharges.forEach(function (s) {
                var sn = Math.round(Number(s && s.amount));
                if (!isNaN(sn) && sn > 0) total += sn;
            });
        }
        return total > 0 ? total : 0;
    }

    function thFlightDirectionLine(legs) {
        if (!legs || !legs.length) return '';
        var first = legs[0];
        var last = legs[legs.length - 1];
        function portLabel(leg, end) {
            if (!leg || !leg[end]) return '';
            var port = leg[end].port ? (leg[end].port.shortName || leg[end].port.name || leg[end].port.id || '') : '';
            var time = String(leg[end].time || '').trim();
            port = String(port || '').trim();
            if (port && time) return port + ' ' + time;
            return port || time || String(leg.number || '').trim();
        }
        var dep = portLabel(first, 'departure');
        var arr = portLabel(last, 'arrival');
        if (dep && arr) return dep + ' \u2192 ' + arr;
        return dep || arr;
    }

    function thFlightPackageLines(pkg) {
        var lines = [];
        var fwd = thFlightDirectionLine(pkg && pkg.forward);
        var bwd = thFlightDirectionLine(pkg && (pkg.backward || pkg.back));
        if (fwd) lines.push(fwd);
        if (bwd) lines.push(bwd);
        return lines;
    }

    function thFlightPackageSummary(pkg) {
        if (!pkg) return '';
        var airlines = collectCompaniesFromPackage(pkg);
        var airlineLabel = airlines.join(' \u00b7 ');
        var lines = thFlightPackageLines(pkg);
        return [airlineLabel].concat(lines).filter(Boolean).join(' | ');
    }

    function thFlightPkgIsDirect(pkg) {
        if (!pkg) return false;
        var fw = pkg.forward;
        var bw = pkg.backward || pkg.back;
        if (!Array.isArray(fw) || fw.length !== 1) return false;
        if (Array.isArray(bw) && bw.length > 1) return false;
        return true;
    }

    function thFlightPkgCardHtml(pkg, idx, selectedIdx, info) {
        var lines = thFlightPackageLines(pkg);
        var airlines = collectCompaniesFromPackage(pkg);
        var priceNum = thFlightPackagePriceNum(pkg, info);
        var priceTxt = priceNum > 0 ? priceNum.toLocaleString('ru-RU') + ' \u20bd' : '';
        var badges = [];
        if (thFlightPkgIsDirect(pkg)) badges.push('\u041f\u0440\u044f\u043c\u043e\u0439');
        else if ((pkg.forward && pkg.forward.length > 1) || ((pkg.backward || pkg.back) && (pkg.backward || pkg.back).length > 1)) {
            badges.push('\u0421 \u043f\u0435\u0440\u0435\u0441\u0430\u0434\u043a\u043e\u0439');
        }
        if (pkg.isDefault) badges.push('\u041f\u043e \u0443\u043c\u043e\u043b\u0447\u0430\u043d\u0438\u044e');
        var badgeHtml = badges.map(function (b) {
            return '<span class="th-flight-pkg__badge">' + escHtml(b) + '</span>';
        }).join('');
        var linesHtml = '';
        if (lines.length) {
            linesHtml = lines.map(function (ln, li) {
                var label = li === 0 ? '\u0422\u0443\u0434\u0430' : '\u041e\u0431\u0440\u0430\u0442\u043d\u043e';
                return '<div class="th-flight-pkg__leg"><span class="th-flight-pkg__leg-label">' + label + '</span><span>' + escHtml(ln) + '</span></div>';
            }).join('');
        } else {
            linesHtml = '<div class="th-flight-pkg__leg"><span>\u0414\u0435\u0442\u0430\u043b\u0438 \u0443\u0442\u043e\u0447\u043d\u044f\u044e\u0442\u0441\u044f \u0443 \u0442\u0443\u0440\u043e\u043f\u0435\u0440\u0430\u0442\u043e\u0440\u0430</span></div>';
        }
        return (
            '<button type="button" class="th-flight-pkg' + (idx === selectedIdx ? ' is-selected' : '') + '" data-th-flight-idx="' + idx + '">' +
            '<div class="th-flight-pkg__body">' +
            '<div class="th-flight-pkg__air">' + escHtml(airlines.join(' \u00b7 ') || '\u0410\u0432\u0438\u0430\u043a\u043e\u043c\u043f\u0430\u043d\u0438\u044f \u0443\u0442\u043e\u0447\u043d\u044f\u0435\u0442\u0441\u044f') + '</div>' +
            (badgeHtml ? '<div class="th-flight-pkg__badges">' + badgeHtml + '</div>' : '') +
            '<div class="th-flight-pkg__legs">' + linesHtml + '</div>' +
            '</div>' +
            (priceTxt ? '<div class="th-flight-pkg__price">' + escHtml(priceTxt) + '</div>' : '') +
            '</button>'
        );
    }

    var thFlightPickModalEl = null;
    var thFlightPickModalCard = null;
    var thFlightPickModalOnSelect = null;

    function thFlightPickModalEnsure() {
        if (thFlightPickModalEl) return thFlightPickModalEl;
        var root = document.createElement('div');
        root.id = 'th-flight-pick-modal';
        root.className = 'th-flight-pick-modal hidden';
        root.setAttribute('role', 'dialog');
        root.setAttribute('aria-modal', 'true');
        root.setAttribute('aria-labelledby', 'th-flight-pick-modal-title');
        root.innerHTML =
            '<div class="th-flight-pick-modal__backdrop" data-th-flight-modal-close="1"></div>' +
            '<div class="th-flight-pick-modal__panel">' +
            '<button type="button" class="th-flight-pick-modal__close" data-th-flight-modal-close="1" aria-label="\u0417\u0430\u043a\u0440\u044b\u0442\u044c">&times;</button>' +
            '<h2 id="th-flight-pick-modal-title" class="th-flight-pick-modal__title">\u0412\u044b\u0431\u0435\u0440\u0438\u0442\u0435 \u043f\u0435\u0440\u0435\u043b\u0451\u0442</h2>' +
            '<p class="th-flight-pick-modal__hint">\u0426\u0435\u043d\u0430 \u0442\u0443\u0440\u0430 \u0437\u0430\u0432\u0438\u0441\u0438\u0442 \u043e\u0442 \u0432\u044b\u0431\u0440\u0430\u043d\u043d\u043e\u0433\u043e \u0440\u0435\u0439\u0441\u0430</p>' +
            '<div class="th-flight-pick-modal__list th-flight-pkgs" id="th-flight-pick-modal-list"></div>' +
            '<p class="th-flight-pick-modal__loading hidden" id="th-flight-pick-modal-loading">\u0417\u0430\u0433\u0440\u0443\u0436\u0430\u0435\u043c \u0432\u0430\u0440\u0438\u0430\u043d\u0442\u044b\u2026</p>' +
            '</div>';
        document.body.appendChild(root);
        root.addEventListener('click', function (e) {
            if (e.target.closest('[data-th-flight-modal-close]')) thFlightPickModalClose();
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && thFlightPickModalEl && !thFlightPickModalEl.classList.contains('hidden')) {
                thFlightPickModalClose();
            }
        });
        thFlightPickModalEl = root;
        return root;
    }

    function thFlightPickModalClose() {
        if (!thFlightPickModalEl) return;
        thFlightPickModalEl.classList.add('hidden');
        thFlightPickModalCard = null;
        thFlightPickModalOnSelect = null;
        document.body.classList.remove('th-flight-pick-modal-open');
    }

    function thFlightPickModalRenderList(flights, selectedIdx, info) {
        var listEl = document.getElementById('th-flight-pick-modal-list');
        var loadingEl = document.getElementById('th-flight-pick-modal-loading');
        if (!listEl) return;
        var html = '';
        flights.forEach(function (pkg, idx) {
            html += thFlightPkgCardHtml(pkg, idx, selectedIdx, info);
        });
        listEl.innerHTML = html;
        if (loadingEl) loadingEl.classList.add('hidden');
        listEl.classList.toggle('hidden', !html);
        listEl.querySelectorAll('[data-th-flight-idx]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var i = parseInt(btn.getAttribute('data-th-flight-idx'), 10);
                if (isNaN(i)) return;
                thFlightPickModalSelect(i);
            });
        });
    }

    function thFlightPickModalSelect(idx) {
        var store = thFlightPickModalCard ? thFlightPackagesGet(thFlightPickModalCard.getAttribute('data-th-tour-id')) : null;
        if (!store || !store.flights || !store.flights[idx]) return;
        var tourId = String(thFlightPickModalCard.getAttribute('data-th-tour-id') || '');
        var depCity = store.depCity || thFlightPickModalCard.getAttribute('data-th-departure-city') || '';
        var pkg = store.flights[idx];
        store.selectedIdx = idx;
        thFlightPackagesSet(tourId, store);
        var meta = thFlightMetaFromPackage(pkg, depCity);
        if (meta) thFlightsCacheSet(tourId, meta, depCity || meta.city);
        thFlightPickModalRenderList(store.flights, idx, store.info);
        if (global.THTourCard && typeof global.THTourCard.applyFlightSelection === 'function') {
            global.THTourCard.applyFlightSelection(thFlightPickModalCard, tourId, pkg, store.info);
        }
        if (typeof thFlightPickModalOnSelect === 'function') {
            thFlightPickModalOnSelect({ tourId: tourId, pkg: pkg, idx: idx, meta: meta, info: store.info });
        }
        thFlightPickModalClose();
    }

    function thFlightPickModalOpen(opts) {
        opts = opts || {};
        var cardEl = opts.cardEl;
        var tourId = String(opts.tourId || (cardEl && cardEl.getAttribute('data-th-tour-id')) || '');
        if (!tourId) return Promise.resolve();
        var modal = thFlightPickModalEnsure();
        var listEl = document.getElementById('th-flight-pick-modal-list');
        var loadingEl = document.getElementById('th-flight-pick-modal-loading');
        thFlightPickModalCard = cardEl || null;
        thFlightPickModalOnSelect = opts.onSelect || null;
        modal.classList.remove('hidden');
        document.body.classList.add('th-flight-pick-modal-open');
        if (listEl) {
            listEl.innerHTML = '';
            listEl.classList.add('hidden');
        }
        if (loadingEl) loadingEl.classList.remove('hidden');

        var depCity = opts.departureCity || (cardEl && cardEl.getAttribute('data-th-departure-city')) || '';
        var departureId = opts.departureId != null ? opts.departureId : null;
        var cached = thFlightPackagesGet(tourId);
        if (cached && cached.flights && cached.flights.length) {
            var sel = cached.selectedIdx != null ? cached.selectedIdx : 0;
            thFlightPickModalRenderList(cached.flights, sel, cached.info);
            return Promise.resolve(cached);
        }

        return thFetchTourFlightsJson(tourId, { departureCity: depCity, departureId: departureId, force: true })
            .then(function (j) {
                if (!j || j.success === false) {
                    if (loadingEl) loadingEl.textContent = '\u041d\u0435 \u0443\u0434\u0430\u043b\u043e\u0441\u044c \u0437\u0430\u0433\u0440\u0443\u0437\u0438\u0442\u044c \u0432\u0430\u0440\u0438\u0430\u043d\u0442\u044b \u043f\u0435\u0440\u0435\u043b\u0451\u0442\u0430';
                    return null;
                }
                var flights = j.flights;
                if (!Array.isArray(flights) && j.data && Array.isArray(j.data.flights)) flights = j.data.flights;
                if (!Array.isArray(flights) || !flights.length) {
                    if (loadingEl) loadingEl.textContent = '\u0412\u0430\u0440\u0438\u0430\u043d\u0442\u044b \u043f\u0435\u0440\u0435\u043b\u0451\u0442\u0430 \u043d\u0435 \u043d\u0430\u0439\u0434\u0435\u043d\u044b';
                    return null;
                }
                var pick = pickTourvisorFlightPackage(flights, depCity, departureId);
                var selectedIdx = pick ? Math.max(0, flights.indexOf(pick)) : 0;
                var info = j.info || (j.data && j.data.info) || null;
                thFlightPackagesSet(tourId, {
                    flights: flights.slice(),
                    selectedIdx: selectedIdx,
                    depCity: depCity,
                    departureId: departureId,
                    info: info
                });
                var meta = thFlightMetaFromPackage(flights[selectedIdx], depCity);
                if (meta) thFlightsCacheSet(tourId, meta, depCity || meta.city);
                thFlightPickModalRenderList(flights, selectedIdx, info);
                return { flights: flights, selectedIdx: selectedIdx, info: info };
            });
    }

    function thFlightPackagesStore() {
        global.__thFlightPackagesByTourId = global.__thFlightPackagesByTourId || {};
        return global.__thFlightPackagesByTourId;
    }

    function thFlightPackagesGet(tourId) {
        if (!tourId) return null;
        return thFlightPackagesStore()[String(tourId)] || null;
    }

    function thFlightPackagesSet(tourId, payload) {
        if (!tourId || !payload) return;
        thFlightPackagesStore()[String(tourId)] = payload;
    }

    function thFlightPackageOptionLabel(pkg, depCity) {
        var meta = thFlightMetaFromPackage(pkg, depCity) || {};
        var airline = String(meta.airline || (meta.companies && meta.companies[0]) || '').trim();
        var time = String(meta.time || '').trim();
        var price = thFlightPackagePriceNum(pkg);
        var bits = [];
        if (airline && time) bits.push(airline + ' · ' + time);
        else if (airline) bits.push(airline);
        else if (time) bits.push(time);
        else if (meta.forwardLine) bits.push(String(meta.forwardLine).slice(0, 42));
        else bits.push('Вариант перелёта');
        if (price > 0) bits.push(price.toLocaleString('ru-RU') + ' ₽');
        return bits.join(' — ');
    }

    function thFlightsCacheFromJson(tourId, json, depCity, departureIdHint) {
        if (!tourId || !json || json.success === false) return null;
        var flights = json.flights;
        if (!Array.isArray(flights) && json.data && Array.isArray(json.data.flights)) {
            flights = json.data.flights;
        }
        if (!Array.isArray(flights) || !flights.length) return null;
        var pkg = pickTourvisorFlightPackage(flights, depCity, departureIdHint);
        if (!pkg) return null;
        var selectedIdx = Math.max(0, flights.indexOf(pkg));
        thFlightPackagesSet(tourId, {
            flights: flights.slice(),
            selectedIdx: selectedIdx,
            depCity: depCity || '',
            departureId: departureIdHint != null ? departureIdHint : null,
            info: json.info || (json.data && json.data.info) || null
        });
        var meta = thFlightMetaFromPackage(pkg, depCity);
        if (meta) thFlightsCacheSet(tourId, meta, depCity || meta.city);
        return meta;
    }

    function thFlightChipHtml(meta, depCity) {
        if (global.THTourCard && typeof global.THTourCard.buildFlightBlockHtml === 'function') {
            return global.THTourCard.buildFlightBlockHtml(depCity || '', '', { flightMeta: meta });
        }
        var city = String((meta && meta.city) || depCity || '').trim() || 'Самара';
        var subline = '';
        if (meta) {
            var airline = String(meta.airline || (meta.companies && meta.companies[0]) || '').trim();
            var time = String(meta.time || '').trim();
            subline = String(meta.subline || meta.summary || '').trim();
            if (!subline && airline && time) subline = airline + ' \u00b7 ' + time;
            else if (!subline && airline) subline = airline;
            else if (!subline && time) subline = time;
        }
        var hasFlightData = !!subline;
        if (!subline) subline = '\u0423\u0442\u043e\u0447\u043d\u0438\u0442\u0435 \u0443 \u043c\u0435\u043d\u0435\u0434\u0436\u0435\u0440\u0430';
        var subClass = hasFlightData
            ? 'th-tour-card__flight-sub'
            : 'th-tour-card__flight-sub th-tour-card__flight-sub--stub';
        return (
            '<div class="th-tour-card__flight-chip">' +
            '<span class="th-tour-card__flight-icon" aria-hidden="true"><i class="fas fa-plane"></i></span>' +
            '<div class="th-tour-card__flight-text">' +
            '<b>\u0412\u044b\u043b\u0435\u0442</b>' +
            '<span class="th-tour-card__flight-city">' + escHtml(city) + '</span>' +
            '<span class="' + subClass + '">' + escHtml(subline) + '</span>' +
            '</div></div>'
        );
    }

    function escHtml(s) {
        return String(s || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/"/g, '&quot;');
    }

    var thTourFlightsInflight = Object.create(null);
    var thTourFlightsFailMemo = Object.create(null);
    var TH_TOUR_FLIGHTS_FAIL_TTL_MS = 900000;
    global.__thFlightsPending = global.__thFlightsPending || Object.create(null);
    global.__thFlightsFailed = global.__thFlightsFailed || Object.create(null);

    function thFlightMetaReady(tourId, depCity) {
        var meta = thFlightsCacheGet(tourId, depCity);
        if (!meta) return false;
        return !!(String(meta.forwardLine || meta.subline || meta.summary || meta.airline || meta.time || '').trim());
    }

    function thFlightLoadMarkPending(tourId) {
        var tid = String(tourId || '');
        if (!tid) return;
        global.__thFlightsPending[tid] = true;
        delete global.__thFlightsFailed[tid];
    }

    function thFlightLoadClearPending(tourId) {
        var tid = String(tourId || '');
        if (!tid) return;
        delete global.__thFlightsPending[tid];
    }

    function thFlightLoadMarkFailed(tourId) {
        var tid = String(tourId || '');
        if (!tid) return;
        delete global.__thFlightsPending[tid];
        global.__thFlightsFailed[tid] = true;
    }

    function thFlightLoadIsPending(tourId) {
        return !!global.__thFlightsPending[String(tourId || '')];
    }

    function thFlightLoadIsFailed(tourId) {
        return !!global.__thFlightsFailed[String(tourId || '')];
    }

    function thFlightLoadResetAll() {
        global.__thFlightsPending = Object.create(null);
        global.__thFlightsFailed = Object.create(null);
    }

    function thTourFlightsFailKey(tourId) {
        return String(tourId || '');
    }

    function thTourFlightsRecentFail(tourId) {
        var key = thTourFlightsFailKey(tourId);
        var row = thTourFlightsFailMemo[key];
        if (!row) return null;
        if ((Date.now() - row.ts) > TH_TOUR_FLIGHTS_FAIL_TTL_MS) {
            delete thTourFlightsFailMemo[key];
            return null;
        }
        return row;
    }

    function thMarkTourFlightsFail(tourId, err) {
        thTourFlightsFailMemo[thTourFlightsFailKey(tourId)] = {
            ts: Date.now(),
            error: String(err || 'fail')
        };
    }

    function thFetchTourFlightsJson(tourId, opts) {
        opts = opts || {};
        var tid = String(tourId || '');
        if (!tid) return Promise.resolve({ success: false, error: 'tourId required' });
        var pkgStore = thFlightPackagesGet(tid);
        if (!opts.force && pkgStore && Array.isArray(pkgStore.flights) && pkgStore.flights.length) {
            return Promise.resolve({
                success: true,
                flights: pkgStore.flights.slice(),
                info: pkgStore.info || null,
                _fromPackagesCache: true
            });
        }
        var recentFail = thTourFlightsRecentFail(tid);
        if (!opts.force && recentFail) {
            return Promise.resolve({ success: false, error: recentFail.error, _memoFail: true });
        }
        if (thTourFlightsInflight[tid]) {
            return thTourFlightsInflight[tid];
        }
        var run;
        if (typeof global.tvFetch === 'function') {
            /* TV на пустых Paks/SL часто отвечает 15–35с; 45с держит «Загружаем…» слишком долго. */
            run = global.tvFetch('tour-flights', { tourId: tid, currency: 'RUB' }, { timeoutMs: 18000, quiet: true });
        } else {
            var base = opts.apiBase || global.TH_TV_API_BASE || global.TV_API_BASE || '';
            if (!base) {
                return Promise.resolve({ success: false, error: 'API base missing' });
            }
            var sep = base.indexOf('?') >= 0 ? '&' : '?';
            var url = base + sep + 'type=tour-flights&tourId=' + encodeURIComponent(tid) + '&currency=RUB';
            run = thTvFetch(url).then(function (r) {
                if (!r.ok) {
                    var err = new Error('HTTP ' + r.status);
                    err.status = r.status;
                    throw err;
                }
                return r.json();
            });
        }
        run = run.then(function (j) {
            if (!j || j.success === false) {
                var errMsg = (j && j.error) || 'flight error';
                if (j && j.tourGone) {
                    thMarkTourFlightsFail(tid, errMsg);
                    return j;
                }
                thMarkTourFlightsFail(tid, errMsg);
                return j || { success: false, error: 'empty response' };
            }
            /* TV часто: success=true, flights=[], error.code=3 «нет ответа от ТО» (Paks/SL).
               Без этого карточка вечно «Загружаем перелёт…», прямые не появляются. */
            var flights = j.flights;
            if (!Array.isArray(flights) && j.data && Array.isArray(j.data.flights)) {
                flights = j.data.flights;
            }
            var tvErr = j.error || (j.data && j.data.error) || null;
            var noFlights = !Array.isArray(flights) || !flights.length;
            if (noFlights) {
                var reason = 'no flights';
                if (tvErr) {
                    if (typeof tvErr === 'string') reason = tvErr;
                    else reason = String(tvErr.reason || tvErr.message || tvErr.code || reason);
                }
                thMarkTourFlightsFail(tid, reason);
                return {
                    success: false,
                    error: reason,
                    flights: [],
                    _operatorNoFlights: true,
                    _tvError: tvErr
                };
            }
            return j;
        }).catch(function (e) {
            thMarkTourFlightsFail(tid, e && e.message ? e.message : e);
            return { success: false, error: String(e && e.message ? e.message : e) };
        });
        thTourFlightsInflight[tid] = run;
        return run.finally(function () {
            delete thTourFlightsInflight[tid];
        });
    }

    function thTvFetch(url, attempt) {
        attempt = attempt || 0;
        var ctrl = typeof AbortController !== 'undefined' ? new AbortController() : null;
        var timer = null;
        if (ctrl) {
            timer = setTimeout(function () {
                try { ctrl.abort(); } catch (eA) {}
            }, 18000);
        }
        return fetch(url, { cache: 'no-store', signal: ctrl ? ctrl.signal : undefined })
            .then(function (r) {
                if (r.ok || attempt >= 1) return r;
                if (r.status === 503 || r.status === 429 || r.status === 502 || r.status === 504) {
                    var waitMs = 600 + attempt * 500;
                    return new Promise(function (resolve) {
                        setTimeout(resolve, waitMs);
                    }).then(function () {
                        return thTvFetch(url, attempt + 1);
                    });
                }
                return r;
            })
            .finally(function () {
                if (timer) clearTimeout(timer);
            });
    }

    function thFlightsNetworkBusyInc() {
        global.__thFlightsNetworkBusyDepth = (global.__thFlightsNetworkBusyDepth || 0) + 1;
        global.__thFlightsNetworkBusy = true;
    }

    function thFlightsNetworkBusyDec() {
        global.__thFlightsNetworkBusyDepth = Math.max(0, (global.__thFlightsNetworkBusyDepth || 1) - 1);
        if (global.__thFlightsNetworkBusyDepth > 0) return;
        global.__thFlightsNetworkBusy = false;
        if (global.THTourCard && typeof global.THTourCard.resumeCarouselHydrate === 'function') {
            try { global.THTourCard.resumeCarouselHydrate(); } catch (e) {}
        }
    }

    function thLoadTourFlightsForHotels(hotels, opts) {
        opts = opts || {};
        var base = opts.apiBase || global.TH_TV_API_BASE || global.TV_API_BASE || '';
        var depCity = opts.departureCity || (global.TH_DEPARTURE && global.TH_DEPARTURE.name) || 'Самара';
        var departureIdHint = opts.departureId != null ? opts.departureId : (global.TH_DEPARTURE && global.TH_DEPARTURE.id);
        var maxTours = opts.maxTours != null ? Math.max(0, parseInt(String(opts.maxTours), 10) || 0) : 0;
        var maxConcurrent = opts.maxConcurrent != null ? opts.maxConcurrent : 5;
        var patchEvery = opts.patchEvery != null ? opts.patchEvery : 1;
        /* Лестница: сначала waveSize туров (видимые), потом следующая пачка — не долбим TV всеми сразу (Шри-Ланка и т.п.). 0 = одна очередь. */
        var waveSize = opts.waveSize != null ? Math.max(0, parseInt(String(opts.waveSize), 10) || 0) : 0;
        var loadGen = opts.loadGen;
        var getTourId = opts.getTourId;
        var onDone = opts.onDone;
        if (!base) {
            if (onDone) onDone();
            return Promise.resolve();
        }
        var tourIds = [];
        (hotels || []).forEach(function (h) {
            var tid = '';
            if (typeof getTourId === 'function') tid = getTourId(h);
            else {
                var tour = (h && h.tours && h.tours[0]) ? h.tours[0] : (h && h._tour) ? h._tour : {};
                tid = (tour.id != null && tour.id !== '') ? String(tour.id) : '';
            }
            if (tid && tourIds.indexOf(tid) < 0) tourIds.push(tid);
        });
        if (opts.priorityTourIds) {
            tourIds.sort(function (a, b) {
                var pa = opts.priorityTourIds[a] ? 0 : 1;
                var pb = opts.priorityTourIds[b] ? 0 : 1;
                return pa - pb;
            });
        }
        tourIds = tourIds.filter(function (tid) {
            return !thFlightMetaReady(tid, depCity);
        });
        /* maxTours=0 → все туры в выдаче; иначе обрезка */
        if (maxTours > 0 && tourIds.length > maxTours) {
            tourIds = tourIds.slice(0, maxTours);
        }
        if (!tourIds.length) {
            if (onDone) onDone();
            return Promise.resolve();
        }
        /* Пока грузим tour-flights — не стартуем type=hotel / verybig (иначе flights «ожидает» в Network). */
        thFlightsNetworkBusyInc();
        var allIds = tourIds.slice();
        var waveIndex = 0;
        var queue = [];
        var retryQueue = [];
        var active = 0;
        var loaded = 0;
        var retryPass = 0;
        var inRetryPhase = false;
        var busyReleased = false;
        var releaseBusy = function () {
            if (busyReleased) return;
            busyReleased = true;
            thFlightsNetworkBusyDec();
        };
        var stale = function () {
            return loadGen != null && loadGen !== global.__thFlightsLoadGen;
        };

        function patchFlightsNow() {
            if (stale()) return;
            if (opts.patchContainer && global.THTourCard && typeof global.THTourCard.patchFlightsInContainer === 'function') {
                global.THTourCard.patchFlightsInContainer(opts.patchContainer);
            } else if (global.THTourCard && typeof global.THTourCard.patchFlightsInContainer === 'function') {
                global.THTourCard.patchFlightsInContainer(document);
            }
        }

        function markWavePending(ids) {
            (ids || []).forEach(function (tid) {
                if (!thFlightMetaReady(tid, depCity)) thFlightLoadMarkPending(tid);
            });
        }

        function startNextWave() {
            if (stale()) {
                if (onDone) onDone();
                return;
            }
            if (waveSize <= 0) {
                queue = allIds.slice();
                markWavePending(queue);
                drain();
                return;
            }
            var start = waveIndex * waveSize;
            if (start >= allIds.length) {
                finishAll();
                return;
            }
            var end = Math.min(allIds.length, start + waveSize);
            queue = allIds.slice(start, end);
            waveIndex += 1;
            markWavePending(queue);
            patchFlightsNow();
            drain();
        }

        function finishAll() {
            if (stale()) {
                if (onDone) onDone();
                return;
            }
            if (retryQueue.length && retryPass < 1) {
                retryPass++;
                inRetryPhase = true;
                queue = retryQueue.slice();
                retryQueue = [];
                queue.forEach(function (tid) {
                    delete thTourFlightsFailMemo[thTourFlightsFailKey(tid)];
                    thFlightLoadMarkPending(tid);
                });
                patchFlightsNow();
                drain();
                return;
            }
            retryQueue.forEach(function (tid) {
                if (!thFlightMetaReady(tid, depCity)) thFlightLoadMarkFailed(tid);
            });
            patchFlightsNow();
            if (onDone) onDone();
        }

        function noteTourFlightResult(tourId, j) {
            if (j && j.tourGone) {
                thFlightLoadMarkFailed(tourId);
                return;
            }
            if (thFlightMetaReady(tourId, depCity)) {
                thFlightLoadClearPending(tourId);
                return;
            }
            /* Пустой ответ ТО / memo — не крутим ретраи, сразу stub. */
            if (j && (j._operatorNoFlights || j.operatorNoFlights || j._memoFail)) {
                thFlightLoadMarkFailed(tourId);
                return;
            }
            if (j && j.success === false) {
                /* Один мягкий ретрай только на сетевые/временные ошибки. */
                retryQueue.push(tourId);
                return;
            }
            retryQueue.push(tourId);
        }

        function onWaveOrQueueIdle() {
            if (queue.length || active > 0) {
                drain();
                return;
            }
            patchFlightsNow();
            if (inRetryPhase) {
                finishAll();
                return;
            }
            if (waveSize > 0 && waveIndex * waveSize < allIds.length) {
                startNextWave();
                return;
            }
            finishAll();
        }

        function drain() {
            if (stale()) return;
            while (active < maxConcurrent && queue.length) {
                (function (tourId) {
                    active++;
                    var forceRetry = retryPass > 0;
                    thFetchTourFlightsJson(tourId, {
                        apiBase: base,
                        departureCity: depCity,
                        departureId: departureIdHint,
                        force: forceRetry
                    })
                        .then(function (j) {
                            if (j && j._fromPackagesCache) {
                                noteTourFlightResult(tourId, j);
                                return;
                            }
                            if (!j || j._memoFail || j.tourGone || j._operatorNoFlights || j.operatorNoFlights) {
                                noteTourFlightResult(tourId, j);
                                return;
                            }
                            if (!stale()) thFlightsCacheFromJson(tourId, j, depCity, departureIdHint);
                            noteTourFlightResult(tourId, j);
                        })
                        .catch(function () {
                            retryQueue.push(tourId);
                        })
                        .finally(function () {
                            active--;
                            loaded++;
                            if (!stale() && (loaded % patchEvery === 0 || !queue.length)) {
                                patchFlightsNow();
                            }
                            if (!queue.length && active === 0) {
                                onWaveOrQueueIdle();
                            } else {
                                drain();
                            }
                        });
                })(queue.shift());
            }
        }

        return new Promise(function (resolve) {
            var userDone = onDone;
            onDone = function () {
                releaseBusy();
                if (typeof userDone === 'function') userDone();
                resolve();
            };
            opts.onDone = onDone;
            startNextWave();
        });
    }

    /** Догрузка перелётов для карточек на экране без кэша (после «ещё» / частичного ответа). */
    function thLoadFlightsForVisibleCards(root, opts) {
        opts = opts || {};
        var scope = root && root.querySelectorAll ? root : document;
        var cards = scope.querySelectorAll('.th-tour-card[data-th-tour-id]');
        var hotels = [];
        var seen = {};
        var depCity = opts.departureCity || (global.TH_DEPARTURE && global.TH_DEPARTURE.name) || '';
        cards.forEach(function (card) {
            var tid = card.getAttribute('data-th-tour-id');
            if (!tid || seen[tid]) return;
            seen[tid] = true;
            if (thFlightMetaReady(tid, depCity || card.getAttribute('data-th-departure-city') || '')) return;
            hotels.push({ tours: [{ id: tid }], _tour: { id: tid } });
        });
        if (!hotels.length) {
            if (opts.onDone) opts.onDone();
            return Promise.resolve();
        }
        return thLoadTourFlightsForHotels(hotels, Object.assign({}, opts, {
            maxTours: hotels.length,
            maxConcurrent: opts.maxConcurrent != null ? opts.maxConcurrent : 4,
            waveSize: opts.waveSize != null ? opts.waveSize : 12,
            getTourId: function (h) {
                return (h && h._tour && h._tour.id != null) ? String(h._tour.id) : '';
            },
            patchContainer: opts.patchContainer || scope
        }));
    }

    global.thPickTourvisorFlightPackage = pickTourvisorFlightPackage;
    global.thFlightMetaFromPackage = thFlightMetaFromPackage;
    global.thFlightMetaNormalize = thFlightMetaNormalize;
    global.thFlightChipHtml = thFlightChipHtml;
    global.thFlightsCacheGet = thFlightsCacheGet;
    global.thFlightsCacheSet = thFlightsCacheSet;
    global.thFlightsCacheFromJson = thFlightsCacheFromJson;
    global.thFetchTourFlightsJson = thFetchTourFlightsJson;
    global.thLoadTourFlightsForHotels = thLoadTourFlightsForHotels;
    global.thLoadFlightsForVisibleCards = thLoadFlightsForVisibleCards;
    global.thFlightLoadIsPending = thFlightLoadIsPending;
    global.thFlightLoadIsFailed = thFlightLoadIsFailed;
    global.thFlightLoadResetAll = thFlightLoadResetAll;
    global.thFlightPackagesGet = thFlightPackagesGet;
    global.thFlightPackagesSet = thFlightPackagesSet;
    global.thFlightPackagePriceNum = thFlightPackagePriceNum;
    global.thFlightPackageOptionLabel = thFlightPackageOptionLabel;
    global.thFlightPackageSummary = thFlightPackageSummary;
    global.thFlightPkgCardHtml = thFlightPkgCardHtml;
    global.thFlightPackageLines = thFlightPackageLines;
    global.thFlightPickModalOpen = thFlightPickModalOpen;
    global.thFlightPickModalClose = thFlightPickModalClose;
})(typeof window !== 'undefined' ? window : this);
