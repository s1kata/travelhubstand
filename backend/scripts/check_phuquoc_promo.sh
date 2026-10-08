#!/usr/bin/env bash
# Проверка туров на Фукуок (виртуальный countryId=16104) для страницы акций.
# Usage:
#   PROXY=https://travelhub63.ru/frontend/api/tourvisor-proxy.php bash backend/scripts/check_phuquoc_promo.sh
#   DEP=1 bash backend/scripts/check_phuquoc_promo.sh

set -euo pipefail
PROXY="${PROXY:-https://travelhub63.ru/frontend/api/tourvisor-proxy.php}"
DEP="${DEP:-1}"
DF="${DF:-$(date -u +%Y-%m-%d)}"
DT="${DT:-$(date -u -v+21d +%Y-%m-%d 2>/dev/null || date -u -d '+21 days' +%Y-%m-%d)}"

echo "proxy=$PROXY dep=$DEP dates=$DF..$DT"
echo "---- search-cached VN region 104 (onlyPromo) ----"
curl -sS -m 120 -D - -o /tmp/pq_sc.json \
  "${PROXY}?type=search-cached&source=promo&countryId=16&regionIds=104&departureId=${DEP}&adults=2&nightsFrom=7&nightsTo=14&dateFrom=${DF}&dateTo=${DT}&onlyPromo=1&live=1" \
  | grep -i 'x-tourvisor-items\|x-tourvisor-success\|HTTP/' || true
python3 - <<'PY'
import json
j=json.load(open('/tmp/pq_sc.json'))
d=j.get('data') or []
print('hotels', len(d) if isinstance(d,list) else d, 'success', j.get('success'))
PY

echo "---- promo-search tile 16104 ----"
curl -sS -m 150 -D - -o /tmp/pq_ps.json \
  "${PROXY}?type=promo-search&source=promo&countryId=16104&departureId=${DEP}&adults=2&dateFrom=${DF}&dateTo=${DT}&live=1" \
  | grep -i 'x-tourvisor-items\|x-tourvisor-success\|x-tourvisor-promo\|HTTP/' || true
python3 - <<'PY'
import json
j=json.load(open('/tmp/pq_ps.json'))
d=j.get('data') or []
print('hotels', len(d) if isinstance(d,list) else d, 'success', j.get('success'), 'src', j.get('promoSearchSource'))
if isinstance(d,list) and d:
    print('sample', d[0].get('name'), (d[0].get('region') or {}).get('name'))
PY
