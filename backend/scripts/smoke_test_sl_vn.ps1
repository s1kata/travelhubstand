$Base = "https://travel63test.ru/backend/components/api/tourvisor-proxy.php"
$Results = @()

function Test-Search {
    param(
        [string]$Label,
        [hashtable]$Params,
        [int]$TimeoutSec = 90
    )
    $qs = ($Params.GetEnumerator() | ForEach-Object {
        "$($_.Key)=$([uri]::EscapeDataString([string]$_.Value))"
    }) -join "&"
    $url = "$Base?$qs"
    try {
        $resp = Invoke-WebRequest -Uri $url -TimeoutSec $TimeoutSec -UseBasicParsing
        $h = @{}
        foreach ($k in $resp.Headers.Keys) {
            $lk = $k.ToLower()
            if ($lk -match 'tourvisor|cache-namespace|filter-profile') {
                $h[$lk] = $resp.Headers[$k] -join ','
            }
        }
        $json = $resp.Content | ConvertFrom-Json
        $items = if ($json.data) { @($json.data).Count } else { 0 }
        $minPrice = $null
        if ($items -gt 0 -and $json.data[0].price) { $minPrice = $json.data[0].price }
        [PSCustomObject]@{
            Label = $Label
            Items = $items
            Success = [bool]$json.success
            FromCache = $json.fromCache
            Raw = $h['x-tourvisor-items-raw']
            AfterOp = $h['x-tourvisor-items-after-operator']
            AfterPrice = $h['x-tourvisor-items-after-price']
            Namespace = $h['x-tourvisor-cache-namespace']
            Profile = $h['x-tourvisor-filter-profile']
            Cache = $h['x-tourvisor-cache']
            MinPrice = $minPrice
            Error = $json.error
        }
    } catch {
        [PSCustomObject]@{
            Label = $Label
            Items = -1
            Success = $false
            FromCache = $null
            Raw = $null; AfterOp = $null; AfterPrice = $null
            Namespace = $null; Profile = $null; Cache = $null
            MinPrice = $null
            Error = $_.Exception.Message
        }
    }
}

$countries = @(
    @{ id = 12; name = 'SL' },
    @{ id = 16; name = 'VN' }
)
$deps = @(7, 1)
$windows = @(
    @{ from = '2026-09-01'; to = '2026-09-30'; tag = 'Sep26' },
    @{ from = '2026-10-01'; to = '2026-10-31'; tag = 'Oct26' },
    @{ from = '2026-11-01'; to = '2026-11-30'; tag = 'Nov26' },
    @{ from = '2026-12-01'; to = '2026-12-31'; tag = 'Dec26' },
    @{ from = '2027-01-01'; to = '2027-01-31'; tag = 'Jan27' },
    @{ from = '2027-02-01'; to = '2027-02-28'; tag = 'Feb27' }
)
$nightSets = @(
    @{ from = 6; to = 9; tag = 'n6-9' },
    @{ from = 5; to = 10; tag = 'n5-10' },
    @{ from = 7; to = 14; tag = 'n7-14' },
    @{ from = 10; to = 14; tag = 'n10-14' }
)

Write-Host "=== Phase 1: cache-only (site first paint) ===" -ForegroundColor Cyan
foreach ($c in $countries) {
    foreach ($dep in $deps) {
        foreach ($w in $windows) {
            foreach ($n in $nightSets) {
                $label = "$($c.name) dep$dep $($w.tag) $($n.tag) cache"
                $p = @{
                    type = 'search-cached'
                    departureId = $dep
                    countryId = $c.id
                    dateFrom = $w.from
                    dateTo = $w.to
                    nightsFrom = $n.from
                    nightsTo = $n.to
                    adults = 2
                    cacheOnly = 1
                    slim = 1
                }
                $Results += Test-Search -Label $label -Params $p -TimeoutSec 30
            }
        }
    }
}

Write-Host "=== Phase 2: live main search (default UI nights 6-9, key months) ===" -ForegroundColor Cyan
foreach ($c in $countries) {
    foreach ($w in @($windows[0], $windows[2], $windows[4])) {
        $label = "$($c.name) dep7 $($w.tag) n6-9 LIVE"
        $p = @{
            type = 'search-cached'
            departureId = 7
            countryId = $c.id
            dateFrom = $w.from
            dateTo = $w.to
            nightsFrom = 6
            nightsTo = 9
            adults = 2
            live = 1
        }
        $Results += Test-Search -Label $label -Params $p -TimeoutSec 120
    }
}

Write-Host "=== Phase 3: filters (charter / direct) ===" -ForegroundColor Cyan
$filterBase = @{
    type = 'search-cached'
    departureId = 7
    dateFrom = '2026-09-15'
    dateTo = '2026-09-22'
    nightsFrom = 7
    nightsTo = 14
    adults = 2
    live = 1
}
foreach ($c in $countries) {
    $cid = $c.id; $cn = $c.name
    $Results += Test-Search -Label "$cn charter LIVE" -Params ($filterBase + @{ countryId = $cid; onlyCharter = 1 }) -TimeoutSec 120
    $Results += Test-Search -Label "$cn direct LIVE" -Params ($filterBase + @{ countryId = $cid; onlyDirect = 1 }) -TimeoutSec 120
    $Results += Test-Search -Label "$cn meal AI LIVE" -Params ($filterBase + @{ countryId = $cid; meal = 7 }) -TimeoutSec 120
}

Write-Host "=== Phase 4: promo-search ===" -ForegroundColor Cyan
foreach ($c in $countries) {
    $p = @{
        type = 'promo-search'
        departureId = 7
        countryId = $c.id
        adults = 2
    }
    $Results += Test-Search -Label "$($c.name) promo-search" -Params $p -TimeoutSec 60
}

$Results | Format-Table Label, Items, Success, FromCache, Raw, AfterOp, AfterPrice, Cache, MinPrice, Error -AutoSize

$fail = $Results | Where-Object { $_.Items -le 0 -and $_.Label -notmatch 'direct|charter|cache' }
$cacheMiss = $Results | Where-Object { $_.Label -match 'cache' -and $_.Items -le 0 }
Write-Host "`n=== SUMMARY ===" -ForegroundColor Yellow
Write-Host "Total tests: $($Results.Count)"
Write-Host "Live/promo zero results: $(@($fail).Count)"
Write-Host "Cache-only empty/miss: $(@($cacheMiss).Count)"
if ($fail) {
    Write-Host "Zero-result live/promo:" -ForegroundColor Red
    $fail | ForEach-Object { Write-Host "  $($_.Label): err=$($_.Error)" }
}
