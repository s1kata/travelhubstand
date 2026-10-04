#!/bin/bash
BASE="https://travel63test.ru/backend/components/api/tourvisor-proxy.php"
OUT="/tmp/sl_vn_smoke.tsv"
echo -e "label\titems\traw\tafter_op\tafter_price\tcache\tsuccess\terror" > "$OUT"

probe() {
  local label="$1"
  shift
  local hdr body items success err
  hdr=$(mktemp)
  body=$(mktemp)
  if ! curl -sS -m "$TIMEOUT" "$BASE?$*" -D "$hdr" -o "$body" 2>/dev/null; then
    echo -e "${label}\t-1\t\t\t\t\tfalse\tcurl_fail" >> "$OUT"
    rm -f "$hdr" "$body"
    return
  fi
  raw=$(grep -i '^x-tourvisor-items-raw:' "$hdr" | awk '{print $2}' | tr -d '\r')
  aop=$(grep -i '^x-tourvisor-items-after-operator:' "$hdr" | awk '{print $2}' | tr -d '\r')
  apr=$(grep -i '^x-tourvisor-items-after-price:' "$hdr" | awk '{print $2}' | tr -d '\r')
  cache=$(grep -i '^x-tourvisor-cache:' "$hdr" | awk '{print $2}' | tr -d '\r')
  items=$(grep -i '^x-tourvisor-items:' "$hdr" | awk '{print $2}' | tr -d '\r')
  success=$(python3 -c "import json; d=json.load(open('$body')); print('true' if d.get('success') else 'false')" 2>/dev/null || echo false)
  if [ -z "$items" ]; then
    items=$(python3 -c "import json; d=json.load(open('$body')); print(len(d.get('data') or []))" 2>/dev/null || echo 0)
  fi
  err=$(python3 -c "import json; d=json.load(open('$body')); print(d.get('error') or '')" 2>/dev/null || echo '')
  echo -e "${label}\t${items:-0}\t${raw}\t${aop}\t${apr}\t${cache}\t${success}\t${err}" >> "$OUT"
  rm -f "$hdr" "$body"
}

countries="12:SL 16:VN"
deps="7 1"
windows="2026-09-01:2026-09-30:Sep26 2026-10-01:2026-10-31:Oct26 2026-11-01:2026-11-30:Nov26 2026-12-01:2026-12-31:Dec26 2027-01-01:2027-01-31:Jan27 2027-02-01:2027-02-28:Feb27"
nights="6:9:n6-9 5:10:n5-10 7:14:n7-14 10:14:n10-14"

echo "=== Phase 1 cache-only ==="
TIMEOUT=25
for c in $countries; do
  cid=${c%%:*}; cname=${c##*:}
  for dep in $deps; do
    for w in $windows; do
      df=${w%%:*}; rest=${w#*:}; dt=${rest%%:*}; wtag=${rest##*:}
      for n in $nights; do
        nf=${n%%:*}; rest2=${n#*:}; nt=${rest2%%:*}; ntag=${rest2##*:}
        q="type=search-cached&departureId=${dep}&countryId=${cid}&dateFrom=${df}&dateTo=${dt}&nightsFrom=${nf}&nightsTo=${nt}&adults=2&cacheOnly=1&slim=1"
        probe "${cname} dep${dep} ${wtag} ${ntag} cache" "$q"
      done
    done
  done
done

echo "=== Phase 2 live (default UI) ==="
TIMEOUT=120
for c in $countries; do
  cid=${c%%:*}; cname=${c##*:}
  for w in "2026-09-01:2026-09-30:Sep26" "2026-11-01:2026-11-30:Nov26" "2027-01-01:2027-01-31:Jan27"; do
    df=${w%%:*}; rest=${w#*:}; dt=${rest%%:*}; wtag=${rest##*:}
    q="type=search-cached&departureId=7&countryId=${cid}&dateFrom=${df}&dateTo=${dt}&nightsFrom=6&nightsTo=9&adults=2&live=1"
    probe "${cname} dep7 ${wtag} n6-9 LIVE" "$q"
  done
done

echo "=== Phase 3 filters ==="
for c in $countries; do
  cid=${c%%:*}; cname=${c##*:}
  base="type=search-cached&departureId=7&countryId=${cid}&dateFrom=2026-09-15&dateTo=2026-09-22&nightsFrom=7&nightsTo=14&adults=2&live=1"
  probe "${cname} charter LIVE" "${base}&onlyCharter=1"
  probe "${cname} direct LIVE" "${base}&onlyDirect=1"
  probe "${cname} mealAI LIVE" "${base}&meal=7"
done

echo "=== Phase 4 promo ==="
for c in $countries; do
  cid=${c%%:*}; cname=${c##*:}
  probe "${cname} promo-search" "type=promo-search&departureId=7&countryId=${cid}&adults=2"
done

echo "=== RESULTS ==="
column -t -s $'\t' "$OUT" 2>/dev/null || cat "$OUT"
echo ""
echo "=== ZERO live/promo (excluding direct/charter) ==="
awk -F'\t' 'NR>1 && $2==0 && $0 !~ /direct|charter/ && $0 !~ /cache/' "$OUT"
echo ""
echo "=== ZERO cache-only ==="
awk -F'\t' 'NR>1 && $2==0 && $0 ~ /cache/' "$OUT" | head -30
echo "cache zero count: $(awk -F'\t' 'NR>1 && $2==0 && $0 ~ /cache/' "$OUT" | wc -l)"
