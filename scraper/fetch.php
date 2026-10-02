<?php

declare(strict_types=1);

/**
 * WSDC Data Fetcher
 *
 * Reads slo_wsdc_ids.csv, fetches each dancer's data from the WSDC points
 * lookup API, and writes:
 *   data/raw/{wscid}.json   – raw API response per dancer
 *   data/dancers.json       – normalised index used by the Laravel app
 *   data/last_updated.txt   – ISO-8601 timestamp of the last successful run
 *
 * Usage:
 *   php scraper/fetch.php
 *
 * Cron example (daily at 03:00):
 *   0 3 * * * php /path/to/slo-wsdc-db/scraper/fetch.php >> /var/log/wsdc_fetch.log 2>&1
 */

// ---------------------------------------------------------------------------
// Config
// ---------------------------------------------------------------------------

define('BASE_URL',    'https://points.worldsdc.com');
define('LOOKUP_URL',  BASE_URL . '/lookup2020');
define('FIND_URL',    BASE_URL . '/lookup2020/find');
define('USER_AGENT',  'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36');

define('ROOT_DIR',    dirname(__DIR__));
define('CSV_FILE',    ROOT_DIR . '/slo_wsdc_ids.csv');
define('DATA_DIR',    ROOT_DIR . '/data');
define('RAW_DIR',     DATA_DIR . '/raw');
define('COOKIE_FILE', sys_get_temp_dir() . '/wsdc_cookies.txt');

/** Milliseconds to sleep between requests to avoid rate-limiting. */
define('REQUEST_DELAY_US', 500_000);

/** Division order: highest to lowest (used for ranking logic). */
const DIVISIONS = ['CHA', 'ALS', 'ADV', 'INT', 'NOV', 'NEW'];

// ---------------------------------------------------------------------------
// Bootstrap
// ---------------------------------------------------------------------------

if (!is_dir(DATA_DIR)) {
    mkdir(DATA_DIR, 0755, true);
}
if (!is_dir(RAW_DIR)) {
    mkdir(RAW_DIR, 0755, true);
}

// ---------------------------------------------------------------------------
// Token / session handling
// ---------------------------------------------------------------------------

/** Fetch a fresh CSRF token and warm up the session cookie. */
function fetchToken(): string
{
    // Wipe any stale cookie file so we get a clean session
    if (file_exists(COOKIE_FILE)) {
        unlink(COOKIE_FILE);
    }

    $html = httpGet(LOOKUP_URL);

    // Extract _token from hidden input
    if (!preg_match('/<input[^>]+name=["\']_token["\'][^>]+value=["\']([^"\']+)["\']/', $html, $m) &&
        !preg_match('/<input[^>]+value=["\']([^"\']+)["\'][^>]+name=["\']_token["\']/', $html, $m)) {
        throw new RuntimeException('Could not extract CSRF token from WSDC lookup page.');
    }

    $token = $m[1];
    log_msg("Token acquired: {$token}");
    return $token;
}

// ---------------------------------------------------------------------------
// HTTP helpers
// ---------------------------------------------------------------------------

function buildCurl(string $url): CurlHandle
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_USERAGENT      => USER_AGENT,
        CURLOPT_COOKIEJAR      => COOKIE_FILE,
        CURLOPT_COOKIEFILE     => COOKIE_FILE,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER     => [
            'Accept: text/html,application/xhtml+xml,application/json,*/*;q=0.9',
            'Accept-Language: en-US,en;q=0.9',
        ],
    ]);
    return $ch;
}

function httpGet(string $url): string
{
    $ch = buildCurl($url);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($body === false || $code >= 500) {
        throw new RuntimeException("GET {$url} failed (HTTP {$code}).");
    }

    return (string) $body;
}

function httpPost(string $url, array $fields): array
{
    $ch = buildCurl($url);
    curl_setopt_array($ch, [
        CURLOPT_POST       => true,
        CURLOPT_POSTFIELDS => http_build_query($fields),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json, text/javascript, */*; q=0.01',
            'X-Requested-With: XMLHttpRequest',
            'Referer: ' . LOOKUP_URL,
        ],
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['body' => (string) $body, 'code' => $code];
}

// ---------------------------------------------------------------------------
// Fetch single dancer (with one automatic token refresh on failure)
// ---------------------------------------------------------------------------

function fetchDancer(int $wscid, string &$token): ?array
{
    for ($attempt = 0; $attempt < 2; $attempt++) {
        $result = httpPost(FIND_URL, ['num' => $wscid, '_token' => $token]);
        $code   = $result['code'];
        $body   = $result['body'];

        if ($code === 419) {
            log_msg("Session expired (419) for wscid={$wscid}, refreshing token…");
            $token = fetchToken();
            continue;
        }

        if ($code !== 200) {
            log_msg("Unexpected HTTP {$code} for wscid={$wscid}, skipping.");
            return null;
        }

        $data = json_decode($body, true);
        if (!is_array($data)) {
            log_msg("Invalid JSON for wscid={$wscid} (attempt " . ($attempt + 1) . "), refreshing token…");
            $token = fetchToken();
            continue;
        }

        return $data;
    }

    log_msg("Failed to fetch wscid={$wscid} after 2 attempts.");
    return null;
}

// ---------------------------------------------------------------------------
// Normalisation logic (ported from sample-python-wsdc-adapter.py)
// ---------------------------------------------------------------------------

/**
 * Extract a flat role summary from one role key inside the API response.
 *
 * @param  array       $data     Full decoded API response
 * @param  string      $roleKey  'leader' or 'follower'
 * @return array|null
 */
function extractRoleSummary(array $data, string $roleKey): ?array
{
    if (empty($data[$roleKey]) || !is_array($data[$roleKey])) {
        return null;
    }

    $roleData = $data[$roleKey];
    $dancer   = $roleData['dancer'] ?? [];

    $level = [
        'required' => $roleData['level']['required'] ?? null,
        'allowed'  => $roleData['level']['allowed']  ?? null,
    ];

    $placements = $roleData['placements'] ?? [];
    $placements = $placements['West Coast Swing'] ?? [];

    // Per-division point totals
    $divPoints = array_fill_keys(DIVISIONS, 0);
    foreach (DIVISIONS as $div) {
        if (isset($placements[$div]['total_points'])) {
            $divPoints[$div] = (int) $placements[$div]['total_points'];
        }
    }

    // Collect all (competition, division) pairs
    $compLevels = [];
    foreach ($placements as $div => $divData) {
        foreach ($divData['competitions'] ?? [] as $comp) {
            $compLevels[] = [$comp, $div];
        }
    }

    if (empty($placements) || empty($compLevels)) {
        return [
            'wscid'             => $dancer['wscid'] ?? null,
            'first_name'        => $dancer['first_name'] ?? null,
            'last_name'         => $dancer['last_name'] ?? null,
            'level'             => $level,
            'points'            => $divPoints,
            'first_competition' => null,
            'best_competition'  => null,
        ];
    }

    // First competition: earliest event date (lexical sort on ISO date string)
    usort($compLevels, fn($a, $b) => strcmp(
        $a[0]['event']['date'] ?? '',
        $b[0]['event']['date'] ?? ''
    ));
    [$firstComp, $firstLevel] = $compLevels[0];

    // Best competition: highest division first, then most points
    $divOrder = array_flip(DIVISIONS); // CHA=0, ALS=1, …, NEW=5
    usort($compLevels, function ($a, $b) use ($divOrder) {
        $divDiff = ($divOrder[$a[1]] ?? 99) - ($divOrder[$b[1]] ?? 99);
        if ($divDiff !== 0) {
            return $divDiff;
        }
        return ($b[0]['points'] ?? 0) - ($a[0]['points'] ?? 0);
    });
    [$bestComp, $bestLevel] = $compLevels[0];

    return [
        'wscid'             => $dancer['wscid'] ?? null,
        'first_name'        => $dancer['first_name'] ?? null,
        'last_name'         => $dancer['last_name'] ?? null,
        'level'             => $level,
        'points'            => $divPoints,
        'first_competition' => compInfo($firstComp, $firstLevel),
        'best_competition'  => compInfo($bestComp, $bestLevel),
    ];
}

function compInfo(array $comp, string $level): array
{
    return [
        'event_name'     => $comp['event']['name']     ?? null,
        'event_location' => $comp['event']['location'] ?? null,
        'event_date'     => $comp['event']['date']     ?? null,
        'points'         => $comp['points']            ?? 0,
        'result'         => $comp['result']            ?? null,
        'level'          => $level,
    ];
}

function buildDancerEntry(int $wscid, string $csvName, array $raw): array
{
    return [
        'wscid'    => $wscid,
        'csv_name' => $csvName,
        'leader'   => extractRoleSummary($raw, 'leader'),
        'follower' => extractRoleSummary($raw, 'follower'),
    ];
}

// ---------------------------------------------------------------------------
// Logging
// ---------------------------------------------------------------------------

function log_msg(string $msg): void
{
    $ts = date('Y-m-d H:i:s');
    echo "[{$ts}] {$msg}" . PHP_EOL;
}

// ---------------------------------------------------------------------------
// Main
// ---------------------------------------------------------------------------

// Read dancer IDs from CSV
if (!file_exists(CSV_FILE)) {
    log_msg('ERROR: ' . CSV_FILE . ' not found.');
    exit(1);
}

$rows = [];
if (($fh = fopen(CSV_FILE, 'r')) !== false) {
    $header = fgetcsv($fh); // skip header row
    while (($row = fgetcsv($fh)) !== false) {
        if (count($row) >= 2) {
            $rows[] = ['id' => (int) $row[0], 'name' => trim($row[1])];
        }
    }
    fclose($fh);
}

if (empty($rows)) {
    log_msg('ERROR: No dancer IDs found in CSV.');
    exit(1);
}

log_msg('Starting fetch for ' . count($rows) . ' dancers…');

$token   = fetchToken();
$dancers = [];
$failed  = 0;

foreach ($rows as $i => $row) {
    $wscid = $row['id'];
    $name  = $row['name'];

    log_msg("[" . ($i + 1) . "/" . count($rows) . "] Fetching wscid={$wscid} ({$name})");

    $raw = fetchDancer($wscid, $token);

    if ($raw === null) {
        $failed++;
        usleep(REQUEST_DELAY_US);
        continue;
    }

    // Save raw response
    $rawPath = RAW_DIR . "/{$wscid}.json";
    file_put_contents($rawPath, json_encode($raw, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    // Build normalised entry
    $dancers[$wscid] = buildDancerEntry($wscid, $name, $raw);

    usleep(REQUEST_DELAY_US);
}

// Write combined dancers index
$dancersPath = DATA_DIR . '/dancers.json';
file_put_contents($dancersPath, json_encode($dancers, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
log_msg("Wrote {$dancersPath} with " . count($dancers) . " entries.");

// Write timestamp
$tsPath = DATA_DIR . '/last_updated.txt';
file_put_contents($tsPath, date('c') . PHP_EOL);
log_msg("Wrote {$tsPath}.");

$total = count($rows);
$ok    = $total - $failed;
log_msg("Done. {$ok}/{$total} dancers fetched successfully" . ($failed ? ", {$failed} failed." : '.'));

exit($failed > 0 ? 1 : 0);
