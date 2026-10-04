/**
 * Отсев битых цен Tourvisor (зеркало backend/components/th_tour_price.php).
 * Без ручных минимумов по странам — только парсинг полей и выбросы в выдаче.
 */
(function (global) {
  'use strict';

  function normalizeField(v) {
    if (v == null || v === '') return 0;
    if (typeof v === 'object') {
      var keys = ['value', 'rub', 'amount', 'total'];
      for (var i = 0; i < keys.length; i++) {
        if (v[keys[i]] != null) {
          var nested = normalizeField(v[keys[i]]);
          if (nested > 0) return nested;
        }
      }
      return 0;
    }
    if (typeof v === 'string') {
      var s = v.replace(/\u00a0/g, '').replace(/\s/g, '').replace(',', '.');
      if (!/^-?\d+(?:\.\d+)?$/.test(s)) return 0;
      v = s;
    }
    var n = Math.round(Number(v));
    if (!Number.isFinite(n) || n <= 0 || n > 50000000) return 0;
    return n;
  }

  function pickMeta(tour) {
    if (!tour || typeof tour !== 'object') {
      return { price: 0, source: '', weak: true, conflict: false };
    }
    var total = normalizeField(tour.totalPrice);
    var rub = normalizeField(tour.priceRub);
    var price = normalizeField(tour.price);
    var cost = normalizeField(tour.cost);
    var fuel = normalizeField(tour.fuelCharge);

    function addFuel(base) {
      if (base <= 0) return 0;
      if (fuel <= 0) return base;
      if (fuel < Math.round(base * 0.35)) return base + fuel;
      return base;
    }

    if (total > 0) {
      var pkg = addFuel(total);
      var conflict = price > 0 && (price < Math.round(pkg * 0.45) || price > Math.round(pkg * 2.2));
      return { price: pkg, source: 'totalPrice', weak: false, conflict: conflict };
    }
    if (rub > 0) {
      pkg = addFuel(rub);
      conflict = price > 0 && (price < Math.round(pkg * 0.45) || price > Math.round(pkg * 2.2));
      return { price: pkg, source: 'priceRub', weak: false, conflict: conflict };
    }
    var cands = [];
    if (price > 0) cands.push({ src: 'price', val: addFuel(price) });
    if (cost > 0) cands.push({ src: 'cost', val: addFuel(cost) });
    if (!cands.length) return { price: 0, source: '', weak: true, conflict: false };
    cands.sort(function (a, b) { return a.val - b.val; });
    if (cands[cands.length - 1].val > cands[0].val * 2.5) {
      return { price: cands[cands.length - 1].val, source: 'max_weak', weak: true, conflict: true };
    }
    return { price: cands[0].val, source: cands[0].src, weak: true, conflict: false };
  }

  function pickPriceNum(tour) {
    return pickMeta(tour).price;
  }

  function ppnaAbsurdFloor(nights) {
    var n = Math.max(0, parseInt(String(nights || ''), 10) || 0);
    if (n >= 10) return 4500;
    if (n >= 7) return 3200;
    if (n >= 4) return 2400;
    return 1500;
  }

  function percentile(sorted, p) {
    if (!sorted.length) return 0;
    if (sorted.length === 1) return sorted[0];
    var idx = Math.floor((sorted.length - 1) * p);
    idx = Math.max(0, Math.min(sorted.length - 1, idx));
    return sorted[idx];
  }

  function resolveTourAdults(tour, ctx) {
    if (tour && tour.adults != null && parseInt(String(tour.adults), 10) > 0) {
      return Math.max(1, Math.min(9, parseInt(String(tour.adults), 10) || 1));
    }
    return Math.max(1, Math.min(9, parseInt(String((ctx && ctx.adults) || 2), 10) || 2));
  }

  function ppna(price, nights, adults) {
    return price / (Math.max(1, nights) * Math.max(1, adults));
  }

  function buildBatchContext(hotels, adults, packageMode) {
    adults = Math.max(1, Math.min(9, parseInt(String(adults != null ? adults : 2), 10) || 2));
    packageMode = packageMode !== false;
    var prices = [];
    var perNight = [];
    var ppnaList = [];
    var weakCount = 0;
    var pricedCount = 0;
    (hotels || []).forEach(function (hotel) {
      if (!hotel) return;
      (hotel.tours || []).forEach(function (tour) {
        var meta = pickMeta(tour);
        if (meta.price <= 0) return;
        pricedCount++;
        if (meta.weak) weakCount++;
        prices.push(meta.price);
        var nights = Math.max(1, parseInt(String(tour.nights || ''), 10) || 0);
        perNight.push(meta.price / nights);
        ppnaList.push(ppna(meta.price, nights, resolveTourAdults(tour, { adults: adults })));
      });
    });
    prices.sort(function (a, b) { return a - b; });
    var median = percentile(prices, 0.5);
    var p25 = percentile(prices, 0.25);
    var p75 = percentile(prices, 0.75);
    var iqr = Math.max(0, p75 - p25);
    if (iqr < 1 && median > 0) iqr = Math.round(median * 0.12);
    var tukey = p25 - Math.round(iqr * 1.5);
    var ratio = median > 25000 ? Math.round(median * 0.38) : (median > 8000 ? Math.round(median * 0.32) : 0);
    var lowFence = Math.max(0, Math.max(tukey, ratio));
    var priceSpread = (median > 0 && prices.length >= 2) ? ((prices[prices.length - 1] - prices[0]) / median) : 0;
    var weakRatio = pricedCount > 0 ? (weakCount / pricedCount) : 0;

    perNight.sort(function (a, b) { return a - b; });
    var pnMed = perNight.length ? percentile(perNight, 0.5) : 0;
    var pnP25 = perNight.length ? percentile(perNight, 0.25) : 0;
    var pnP75 = perNight.length ? percentile(perNight, 0.75) : 0;
    var pnIqr = Math.max(0, pnP75 - pnP25);
    if (pnIqr < 1 && pnMed > 0) pnIqr = pnMed * 0.12;
    var pnFence = Math.max(0, Math.max(pnP25 - pnIqr * 1.5, pnMed > 0 ? pnMed * 0.38 : 0));

    ppnaList.sort(function (a, b) { return a - b; });
    var ppnaMed = ppnaList.length ? percentile(ppnaList, 0.5) : 0;
    var ppnaP25 = ppnaList.length ? percentile(ppnaList, 0.25) : 0;
    var ppnaP75 = ppnaList.length ? percentile(ppnaList, 0.75) : 0;
    var ppnaIqr = Math.max(0, ppnaP75 - ppnaP25);
    if (ppnaIqr < 1 && ppnaMed > 0) ppnaIqr = ppnaMed * 0.12;
    var ppnaFence = Math.max(0, Math.max(ppnaP25 - ppnaIqr * 1.5, ppnaMed > 0 ? ppnaMed * 0.38 : 0));

    return {
      prices: prices,
      median: median,
      p25: p25,
      p75: p75,
      low_fence: lowFence,
      per_night_median: pnMed,
      per_night_low_fence: pnFence,
      ppna_median: ppnaMed,
      ppna_low_fence: ppnaFence,
      weak_ratio: weakRatio,
      price_spread: priceSpread,
      adults: adults,
      package_mode: packageMode
    };
  }

  function hotelPeerPrices(hotel) {
    var out = [];
    (hotel && hotel.tours || []).forEach(function (t) {
      var p = pickMeta(t).price;
      if (p > 0) out.push(p);
    });
    if (!out.length && hotel) {
      var fb = pickPriceNum(hotel);
      if (fb > 0) out.push(fb);
    }
    out.sort(function (a, b) { return a - b; });
    return out;
  }

  function garbageReasons(tour, hotel, ctx, hotelPeerMedian) {
    var reasons = [];
    var meta = pickMeta(tour);
    var price = meta.price;
    if (price <= 0) return ['no_price'];
    if (price < 500) reasons.push('absurd_low');

    var nights = Math.max(0, parseInt(String(tour.nights || ''), 10) || 0);
    var adultsNum = resolveTourAdults(tour, ctx || {});
    var ppnaVal = nights >= 1 ? ppna(price, nights, adultsNum) : 0;
    hotel = hotel || {};

    if (ctx && ctx.package_mode) {
      if (meta.conflict) reasons.push('field_conflict');
      if (nights >= 3 && ppnaVal > 0) {
        var absurdFloor = ppnaAbsurdFloor(nights);
        if (ppnaVal < absurdFloor) reasons.push('ppna_absurd');
        else if (meta.weak && ppnaVal < absurdFloor * 1.15) reasons.push('weak_ppna');
      }
      var sampleWeak = (ctx.prices && ctx.prices.length) || 0;
      var batchMedian = (ctx.median || 0);
      if (meta.weak && sampleWeak >= 3 && (ctx.weak_ratio || 0) >= 0.55 && (ctx.price_spread || 0) < 0.22
        && batchMedian > 0 && batchMedian < 75000) {
        reasons.push('weak_uniform_cluster');
      }
    }

    var hotelAnchor = Math.max(
      normalizeField(hotel.minPrice),
      normalizeField(hotel.price),
      normalizeField(hotel.minprice)
    );
    if (hotelAnchor > 0 && price < Math.round(hotelAnchor * 0.48)) {
      reasons.push('hotel_anchor_low');
    }

    if (hotelPeerMedian != null && hotelPeerMedian > 0 && price < Math.round(hotelPeerMedian * 0.42)) {
      reasons.push('hotel_peer_outlier');
    }

    var sample = (ctx && ctx.prices) ? ctx.prices.length : 0;
    if (sample >= 3 && ctx && ctx.package_mode) {
      if (ctx.low_fence > 0 && price < ctx.low_fence) reasons.push('batch_outlier_low');
      if (nights >= 3 && ctx.per_night_low_fence > 0 && (price / Math.max(1, nights)) < ctx.per_night_low_fence) {
        reasons.push('batch_ppn_outlier');
      }
      if (nights >= 1 && ctx.ppna_low_fence > 0 && ppnaVal > 0 && ppnaVal < ctx.ppna_low_fence) {
        reasons.push('ppna_batch_outlier');
      }
    }

    return reasons;
  }

  function isGarbageTour(tour, hotel, ctx, hotelPeerMedian) {
    return garbageReasons(tour, hotel, ctx, hotelPeerMedian).length > 0;
  }

  function hotelMinPlausiblePrice(hotel, opts) {
    opts = opts || {};
    if (opts.hotelOnly) return 0;
    var batch = (opts.batchHotels && opts.batchHotels.length) ? opts.batchHotels : [hotel];
    var ctx = buildBatchContext(batch, opts.adults, true);
    var peers = hotelPeerPrices(hotel);
    var peerMed = peers.length ? percentile(peers, 0.5) : null;
    var min = 0;
    (hotel && hotel.tours || []).forEach(function (t) {
      if (isGarbageTour(t, hotel, ctx, peerMed)) return;
      var p = pickMeta(t).price;
      if (min === 0 || p < min) min = p;
    });
    if (min > 0) return min;
    var fallback = pickPriceNum(hotel);
    if (fallback > 0 && !isGarbageTour({ price: fallback }, hotel, ctx)) return fallback;
    return 0;
  }

  function pickCheapestPlausibleTour(hotel, opts) {
    opts = opts || {};
    if (opts.hotelOnly) return null;
    var batch = (opts.batchHotels && opts.batchHotels.length) ? opts.batchHotels : [hotel];
    var ctx = buildBatchContext(batch, opts.adults, true);
    var peers = hotelPeerPrices(hotel);
    var peerMed = peers.length ? percentile(peers, 0.5) : null;
    var best = null;
    var bestPrice = 0;
    (hotel && hotel.tours || []).forEach(function (t) {
      if (isGarbageTour(t, hotel, ctx, peerMed)) return;
      var p = pickMeta(t).price;
      if (best === null || p < bestPrice) {
        best = t;
        bestPrice = p;
      }
    });
    return best;
  }

  /** Фильтр всей выдачи (как на бэкенде). */
  function filterHotels(hotels, adults) {
    var ctx = buildBatchContext(hotels, adults, true);
    var out = [];
    (hotels || []).forEach(function (hotel) {
      if (!hotel) return;
      var peers = hotelPeerPrices(hotel);
      var peerMed = peers.length ? percentile(peers, 0.5) : null;
      var kept = [];
      var min = 0;
      (hotel.tours || []).forEach(function (t) {
        var peerForTour = peerMed;
        if (peers.length >= 2) {
          var p = pickMeta(t).price;
          var others = peers.filter(function (x) { return x !== p; });
          if (others.length) peerForTour = percentile(others.slice().sort(function (a, b) { return a - b; }), 0.5);
        }
        if (isGarbageTour(t, hotel, ctx, peerForTour)) return;
        kept.push(t);
        var pr = pickMeta(t).price;
        if (min === 0 || pr < min) min = pr;
      });
      if (!kept.length) return;
      var copy = Object.assign({}, hotel);
      copy.tours = kept;
      if (min > 0) {
        copy.price = min;
        copy.minPrice = min;
      }
      out.push(copy);
    });
    return out;
  }

  global.THTourPriceSanity = {
    normalizeField: normalizeField,
    pickMeta: pickMeta,
    pickPriceNum: pickPriceNum,
    buildBatchContext: buildBatchContext,
    garbageReasons: garbageReasons,
    isGarbageTour: isGarbageTour,
    hotelMinPlausiblePrice: hotelMinPlausiblePrice,
    pickCheapestPlausibleTour: pickCheapestPlausibleTour,
    filterHotels: filterHotels
  };
})(typeof window !== 'undefined' ? window : this);
