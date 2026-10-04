#!/usr/bin/env python3
"""Smoke test SL/VN search on travel63test.ru"""
import json
import urllib.parse
import urllib.request
from concurrent.futures import ThreadPoolExecutor, as_completed

BASE = "https://travel63test.ru/backend/components/api/tourvisor-proxy.php"
TIMEOUT_CACHE = 30
TIMEOUT_LIVE = 130

results = []


def fetch(label, params, timeout):
    qs = urllib.parse.urlencode(params)
    url = f"{BASE}?{qs}"
    req = urllib.request.Request(url, headers={"User-Agent": "TravelHub-SmokeTest/1.0"})
    try:
        with urllib.request.urlopen(req, timeout=timeout) as resp:
            hdrs = {k.lower(): v for k, v in resp.headers.items()}
            body = resp.read().decode("utf-8", errors="replace")
        data = json.loads(body)
        items_hdr = hdrs.get("x-tourvisor-items")
        items = int(items_hdr) if items_hdr else len(data.get("data") or [])
        return {
            "label": label,
            "items": items,
            "raw": hdrs.get("x-tourvisor-items-raw", ""),
            "after_op": hdrs.get("x-tourvisor-items-after-operator", ""),
            "after_price": hdrs.get("x-tourvisor-items-after-price", ""),
            "cache": hdrs.get("x-tourvisor-cache", ""),
            "success": bool(data.get("success")),
            "from_cache": data.get("fromCache"),
            "error": data.get("error") or "",
        }
    except Exception as e:
        return {
            "label": label,
            "items": -1,
            "raw": "",
            "after_op": "",
            "after_price": "",
            "cache": "",
            "success": False,
            "from_cache": None,
            "error": str(e),
        }


jobs = []
countries = [(12, "SL"), (16, "VN")]
deps = [7, 1]
windows = [
    ("2026-09-01", "2026-09-30", "Sep26"),
    ("2026-10-01", "2026-10-31", "Oct26"),
    ("2026-11-01", "2026-11-30", "Nov26"),
    ("2026-12-01", "2026-12-31", "Dec26"),
    ("2027-01-01", "2027-01-31", "Jan27"),
    ("2027-02-01", "2027-02-28", "Feb27"),
]
night_sets = [(6, 9, "n6-9"), (5, 10, "n5-10"), (7, 14, "n7-14"), (10, 14, "n10-14")]

# Phase 1: cache-only
for cid, cname in countries:
    for dep in deps:
        for df, dt, wtag in windows:
            for nf, nt, ntag in night_sets:
                label = f"{cname} dep{dep} {wtag} {ntag} cache"
                params = {
                    "type": "search-cached",
                    "departureId": dep,
                    "countryId": cid,
                    "dateFrom": df,
                    "dateTo": dt,
                    "nightsFrom": nf,
                    "nightsTo": nt,
                    "adults": 2,
                    "cacheOnly": "1",
                    "slim": "1",
                }
                jobs.append((label, params, TIMEOUT_CACHE))

# Phase 2: live default UI
for cid, cname in countries:
    for df, dt, wtag in [windows[0], windows[2], windows[4]]:
        label = f"{cname} dep7 {wtag} n6-9 LIVE"
        params = {
            "type": "search-cached",
            "departureId": 7,
            "countryId": cid,
            "dateFrom": df,
            "dateTo": dt,
            "nightsFrom": 6,
            "nightsTo": 9,
            "adults": 2,
            "live": "1",
        }
        jobs.append((label, params, TIMEOUT_LIVE))

# Phase 3: filters
for cid, cname in countries:
    base = {
        "type": "search-cached",
        "departureId": 7,
        "countryId": cid,
        "dateFrom": "2026-09-15",
        "dateTo": "2026-09-22",
        "nightsFrom": 7,
        "nightsTo": 14,
        "adults": 2,
        "live": "1",
    }
    jobs.append((f"{cname} charter LIVE", {**base, "onlyCharter": "1"}, TIMEOUT_LIVE))
    jobs.append((f"{cname} direct LIVE", {**base, "onlyDirect": "1"}, TIMEOUT_LIVE))
    jobs.append((f"{cname} mealAI LIVE", {**base, "meal": 7}, TIMEOUT_LIVE))

# Phase 4: promo
for cid, cname in countries:
    jobs.append(
        (
            f"{cname} promo-search",
            {"type": "promo-search", "departureId": 7, "countryId": cid, "adults": 2},
            60,
        )
    )

print(f"Running {len(jobs)} probes...")
with ThreadPoolExecutor(max_workers=8) as ex:
    futs = {ex.submit(fetch, *j): j[0] for j in jobs}
    for fut in as_completed(futs):
        results.append(fut.result())

results.sort(key=lambda r: r["label"])

# Print table
print(f"{'Label':<35} {'Items':>5} {'Raw':>5} {'Op':>5} {'Price':>5} {'Cache':>5} {'OK':>4}")
print("-" * 80)
for r in results:
    print(
        f"{r['label']:<35} {r['items']:>5} {r['raw']:>5} {r['after_op']:>5} {r['after_price']:>5} "
        f"{str(r['cache']):>5} {'Y' if r['success'] else 'N':>4}"
        + (f"  ERR:{r['error'][:40]}" if r["error"] else "")
    )

live_zero = [
    r
    for r in results
    if r["items"] == 0
    and "cache" not in r["label"]
    and "direct" not in r["label"]
    and "charter" not in r["label"]
]
cache_zero = [r for r in results if r["items"] == 0 and "cache" in r["label"]]
cache_hit_nonempty = [r for r in results if "cache" in r["label"] and r["items"] > 0]

print("\n=== SUMMARY ===")
print(f"Total: {len(results)}")
print(f"Cache hits with items: {len(cache_hit_nonempty)} / {len([r for r in results if 'cache' in r['label']])}")
print(f"Cache empty/miss: {len(cache_zero)}")
print(f"Live/promo zero (excl direct/charter): {len(live_zero)}")
if live_zero:
    print("Live/promo failures:")
    for r in live_zero:
        print(f"  - {r['label']}: {r['error'] or 'zero items'}")

# Group cache by country/month
print("\n=== Cache matrix (dep7, n6-9) items ===")
for cname in ("SL", "VN"):
    row = []
    for wtag in ["Sep26", "Oct26", "Nov26", "Dec26", "Jan27", "Feb27"]:
        m = next(
            (r for r in results if r["label"] == f"{cname} dep7 {wtag} n6-9 cache"),
            None,
        )
        row.append(f"{wtag}={m['items'] if m else '?'}")
    print(f"{cname}: " + ", ".join(row))
