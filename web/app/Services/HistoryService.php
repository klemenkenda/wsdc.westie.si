<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Builds timestamped absolute-ranking snapshots from raw competition data.
 *
 * Snapshot dates:
 *  - One per unique event month/year found across all raw files.
 *  - One extra "Dec 31, YYYY" snapshot per calendar year present in the data.
 *    If December of that year already has an event, it is simply flagged as a
 *    year-end snapshot rather than duplicated.
 *
 * Rankings are computed by re-summing only the competitions that occurred on
 * or before each snapshot date (year-month granularity).
 */
class HistoryService
{
    private const DIVISIONS = ['CHA', 'ALS', 'ADV', 'INT', 'NOV', 'NEW'];
    private const DIV_ORDER  = ['CHA' => 0, 'ALS' => 1, 'ADV' => 2, 'INT' => 3, 'NOV' => 4, 'NEW' => 5];
    private const THRESHOLDS = [
        'ALS' => ['required' => 'CHA', 'min' => 150],
        'ADV' => ['required' => 'ALS', 'min' => 60],
        'INT' => ['required' => 'ADV', 'min' => 45],
        'NOV' => ['required' => 'INT', 'min' => 30],
        'NEW' => ['required' => 'NOV', 'min' => 16],
    ];

    private string $dataDir;
    private ?array $rawCache     = null;
    private ?array $nameMap      = null;

    public function __construct(private DataService $data)
    {
        $this->dataDir = dirname(base_path()) . '/data';
    }

    // -----------------------------------------------------------------------
    // Public API
    // -----------------------------------------------------------------------

    /**
     * Returns all snapshots newest-first.
     *
     * Each item:
     * [
     *   'ym'          => 202412,           // YYYYMM integer
     *   'label'       => 'December 2024',  // display label
     *   'is_year_end' => true,             // Dec-31 highlighted snapshot
     *   'rankings'    => [ … ],            // absolute ranking entries
     * ]
     */
    public function snapshots(): array
    {
        $rawData = $this->loadAllRaw();
        $nameMap = $this->buildNameMap();

        // ── Collect all event year-months ──────────────────────────────────
        $eventYms = []; // ym => first "Month YYYY" string seen for that ym
        foreach ($rawData as $raw) {
            foreach (['leader', 'follower'] as $role) {
                $placements = $raw[$role]['placements']['West Coast Swing'] ?? [];
                foreach ($placements as $divData) {
                    foreach ($divData['competitions'] ?? [] as $comp) {
                        $date = $comp['event']['date'] ?? '';
                        if ($date) {
                            $ym = $this->toYearMonth($date);
                            if ($ym && !isset($eventYms[$ym])) {
                                $eventYms[$ym] = $date;
                            }
                        }
                    }
                }
            }
        }

        if (empty($eventYms)) {
            return [];
        }

        // ── Build merged date map ──────────────────────────────────────────
        // Start from event dates, then overlay year-end dates.
        $allDates = [];
        foreach ($eventYms as $ym => $label) {
            $allDates[$ym] = ['label' => $label, 'is_year_end' => false];
        }

        $years = array_unique(array_map(fn($ym) => (int) ($ym / 100), array_keys($eventYms)));
        foreach ($years as $year) {
            $ym = $year * 100 + 12; // December of that year
            if (isset($allDates[$ym])) {
                // Already an event in December — just mark it as year-end
                $allDates[$ym]['is_year_end'] = true;
            } else {
                $allDates[$ym] = [
                    'label'       => "December 31, {$year}",
                    'is_year_end' => true,
                ];
            }
        }

        // Sort descending (newest first)
        krsort($allDates);

        // ── Build snapshots ────────────────────────────────────────────────
        $snapshots = [];
        foreach ($allDates as $ym => $meta) {
            $snapshots[] = [
                'ym'          => $ym,
                'label'       => $meta['label'],
                'is_year_end' => $meta['is_year_end'],
                'rankings'    => $this->absoluteRankingsAsOf($ym, $rawData, $nameMap),
            ];
        }

        return $snapshots;
    }

    /**
     * First points, newest-first: one item per dancer/role/division
     * marking the first competition in which they earned points there.
     * The dancer's earliest appearance overall is flagged as 'is_debut'.
     *
     * Event dates only have month precision, so ties within a month are
     * broken by division (lowest first).
     *
     * Each item:
     * [
     *   'ym'       => 202408,
     *   'wscid'    => 12345,
     *   'name'     => 'Ana Novak',
     *   'role'     => 'follower',
     *   'division' => 'NOV',
     *   'event'    => 'Budafest',
     *   'location' => 'Budapest, Hungary',
     *   'result'   => '3',
     *   'points'   => 6,
     *   'is_debut' => true,
     * ]
     */
    public function firsts(): array
    {
        $rawData = $this->loadAllRaw();
        $nameMap = $this->buildNameMap();
        $items   = [];

        foreach ($rawData as $wscid => $raw) {
            $wscid = (int) $wscid;

            foreach (['leader', 'follower'] as $role) {
                $roleData   = $raw[$role] ?? null;
                $placements = $roleData['placements']['West Coast Swing'] ?? [];
                $dancer     = $roleData['dancer'] ?? [];
                $name       = $nameMap[$wscid]
                    ?? trim(($dancer['first_name'] ?? '') . ' ' . ($dancer['last_name'] ?? ''));

                foreach ($placements as $div => $divData) {
                    if (!isset(self::DIV_ORDER[$div])) {
                        continue; // skip non-standard divisions (e.g. SPH)
                    }

                    $first = null;
                    foreach ($divData['competitions'] ?? [] as $comp) {
                        $ym = $this->toYearMonth($comp['event']['date'] ?? '');
                        if ((int) ($comp['points'] ?? 0) <= 0) {
                            continue;
                        }
                        if ($ym && ($first === null || $ym < $first['ym'])) {
                            $first = [
                                'ym'       => $ym,
                                'wscid'    => $wscid,
                                'name'     => $name,
                                'role'     => $role,
                                'division' => $div,
                                'event'    => $comp['event']['name'] ?? '',
                                'location' => $comp['event']['location'] ?? '',
                                'result'   => (string) ($comp['result'] ?? ''),
                                'points'   => (int) ($comp['points'] ?? 0),
                                'is_debut' => false,
                            ];
                        }
                    }

                    if ($first !== null) {
                        $items[] = $first;
                    }
                }
            }
        }

        // Oldest first, lowest division first, to pick each dancer's debut.
        usort($items, fn(array $a, array $b): int =>
            [$a['ym'], -self::DIV_ORDER[$a['division']]] <=> [$b['ym'], -self::DIV_ORDER[$b['division']]]);

        $seen = [];
        foreach ($items as &$item) {
            if (!isset($seen[$item['wscid']])) {
                $item['is_debut']       = true;
                $seen[$item['wscid']] = true;
            }
        }
        unset($item);

        return array_reverse($items);
    }

    /**
     * The most recent events where Slovenian dancers earned points, newest
     * first. WSDC event ids identify a recurring event rather than one
     * edition, so events are keyed by id and month. Order within a month is
     * by event id (descending), as dates have month precision only.
     *
     * Each item:
     * [
     *   'ym'       => 202608,
     *   'event'    => 'Lisbon Westie Fest',
     *   'location' => 'Lisbon, Lisbon, Portugal',
     *   'url'      => 'https://lisbonwestiefest.com/',
     *   'points'   => 16,                  // total for our dancers
     *   'entries'  => [ [wscid, name, role, division, result, points], … ],
     * ]
     */
    public function recentPoints(int $limit = 20): array
    {
        $rawData = $this->loadAllRaw();
        $nameMap = $this->buildNameMap();
        $events  = [];

        foreach ($rawData as $wscid => $raw) {
            $wscid = (int) $wscid;

            foreach (['leader', 'follower'] as $role) {
                $roleData   = $raw[$role] ?? null;
                $placements = $roleData['placements']['West Coast Swing'] ?? [];
                $dancer     = $roleData['dancer'] ?? [];
                $name       = $nameMap[$wscid]
                    ?? trim(($dancer['first_name'] ?? '') . ' ' . ($dancer['last_name'] ?? ''));

                foreach ($placements as $div => $divData) {
                    if (!isset(self::DIV_ORDER[$div])) {
                        continue; // skip non-standard divisions (e.g. SPH)
                    }

                    foreach ($divData['competitions'] ?? [] as $comp) {
                        $points = (int) ($comp['points'] ?? 0);
                        $ym     = $this->toYearMonth($comp['event']['date'] ?? '');
                        if ($points <= 0 || !$ym) {
                            continue;
                        }

                        $eventId = (int) ($comp['event']['id'] ?? 0);
                        $key     = $ym . '-' . $eventId;
                        $events[$key] ??= [
                            'ym'       => $ym,
                            'id'       => $eventId,
                            'event'    => $comp['event']['name'] ?? '',
                            'location' => $comp['event']['location'] ?? '',
                            'url'      => $comp['event']['url'] ?? '',
                            'points'   => 0,
                            'entries'  => [],
                        ];
                        $events[$key]['points']   += $points;
                        $events[$key]['entries'][] = [
                            'wscid'    => $wscid,
                            'name'     => $name,
                            'role'     => $role,
                            'division' => $div,
                            'result'   => (string) ($comp['result'] ?? ''),
                            'points'   => $points,
                        ];
                    }
                }
            }
        }

        usort($events, fn(array $a, array $b): int => [$b['ym'], $b['id']] <=> [$a['ym'], $a['id']]);
        $events = array_slice($events, 0, $limit);

        foreach ($events as &$event) {
            usort($event['entries'], fn(array $a, array $b): int =>
                [self::DIV_ORDER[$a['division']], $b['points'], $a['name']]
                <=> [self::DIV_ORDER[$b['division']], $a['points'], $b['name']]);
        }
        unset($event);

        return $events;
    }

    /**
     * Points earned by all Slovenian dancers per calendar year, from the
     * first year with points to the last, with empty years filled in.
     *
     * [
     *   'years'     => [2013, 2014, …],
     *   'divisions' => ['CHA' => [0, 0, …], …],  // points per year
     *   'roles'     => ['leader' => […], 'follower' => […]],
     *   'total'     => [3, 12, …],
     *   'dancers'   => [2, 5, …],                 // dancers who scored that year
     * ]
     */
    public function pointsByYear(): array
    {
        $byYear = [];

        foreach ($this->loadAllRaw() as $wscid => $raw) {
            foreach (['leader', 'follower'] as $role) {
                $placements = $raw[$role]['placements']['West Coast Swing'] ?? [];

                foreach ($placements as $div => $divData) {
                    if (!isset(self::DIV_ORDER[$div])) {
                        continue; // skip non-standard divisions (e.g. SPH)
                    }

                    foreach ($divData['competitions'] ?? [] as $comp) {
                        $points = (int) ($comp['points'] ?? 0);
                        $ym     = $this->toYearMonth($comp['event']['date'] ?? '');
                        if ($points <= 0 || !$ym) {
                            continue;
                        }

                        $year = intdiv($ym, 100);
                        $byYear[$year]['divisions'][$div] = ($byYear[$year]['divisions'][$div] ?? 0) + $points;
                        $byYear[$year]['roles'][$role]    = ($byYear[$year]['roles'][$role] ?? 0) + $points;
                        $byYear[$year]['dancers'][(int) $wscid] = true;
                    }
                }
            }
        }

        $result = [
            'years'     => [],
            'divisions' => array_fill_keys(self::DIVISIONS, []),
            'roles'     => ['leader' => [], 'follower' => []],
            'total'     => [],
            'dancers'   => [],
        ];

        if (empty($byYear)) {
            return $result;
        }

        for ($year = min(array_keys($byYear)); $year <= max(array_keys($byYear)); $year++) {
            $row = $byYear[$year] ?? [];
            $result['years'][] = $year;
            foreach (self::DIVISIONS as $div) {
                $result['divisions'][$div][] = $row['divisions'][$div] ?? 0;
            }
            foreach (['leader', 'follower'] as $role) {
                $result['roles'][$role][] = $row['roles'][$role] ?? 0;
            }
            $result['total'][]   = array_sum($row['divisions'] ?? []);
            $result['dancers'][] = count($row['dancers'] ?? []);
        }

        return $result;
    }

    /**
     * Points each dancer earned per role in the last $months calendar months,
     * the current month included. With $weighted, each division counts double
     * the one below it: NEW ×1, NOV ×2, INT ×4, ADV ×8, ALS ×16, CHA ×32.
     *
     * @return array<int, array{leader:int, follower:int}> keyed by wscid
     */
    public function pointsInLastMonths(int $months = 12, bool $weighted = false): array
    {
        $cutoff = (int) date('Ym', strtotime('first day of -' . ($months - 1) . ' months'));
        $result = [];

        foreach ($this->loadAllRaw() as $wscid => $raw) {
            $totals = ['leader' => 0, 'follower' => 0];

            foreach (['leader', 'follower'] as $role) {
                $placements = $raw[$role]['placements']['West Coast Swing'] ?? [];
                foreach ($placements as $div => $divData) {
                    if (!isset(self::DIV_ORDER[$div])) {
                        continue; // skip non-standard divisions (e.g. SPH)
                    }
                    foreach ($divData['competitions'] ?? [] as $comp) {
                        $ym = $this->toYearMonth($comp['event']['date'] ?? '');
                        if ($ym >= $cutoff) {
                            $weight = $weighted ? 2 ** (self::DIV_ORDER['NEW'] - self::DIV_ORDER[$div]) : 1;
                            $totals[$role] += (int) ($comp['points'] ?? 0) * $weight;
                        }
                    }
                }
            }

            $result[(int) $wscid] = $totals;
        }

        return $result;
    }

    /**
     * National firsts: for each role and division, the first Slovenian
     * dancer(s) to earn points there. Everyone who scored in that earliest
     * month is listed, since event dates only have month precision.
     *
     * [
     *   'leader'   => ['CHA' => null, 'ALS' => ['ym' => 201905, 'entries' => [ … ]], … ],
     *   'follower' => [ … ],
     * ]
     *
     * Each entry: wscid, name, event, location, result, points.
     */
    public function nationalFirsts(): array
    {
        $rawData = $this->loadAllRaw();
        $nameMap = $this->buildNameMap();
        $result  = [];

        foreach (['leader', 'follower'] as $role) {
            $result[$role] = array_fill_keys(self::DIVISIONS, null);

            foreach ($rawData as $wscid => $raw) {
                $wscid      = (int) $wscid;
                $roleData   = $raw[$role] ?? null;
                $placements = $roleData['placements']['West Coast Swing'] ?? [];
                $dancer     = $roleData['dancer'] ?? [];
                $name       = $nameMap[$wscid]
                    ?? trim(($dancer['first_name'] ?? '') . ' ' . ($dancer['last_name'] ?? ''));

                foreach ($placements as $div => $divData) {
                    if (!array_key_exists($div, $result[$role])) {
                        continue; // skip non-standard divisions (e.g. SPH)
                    }

                    foreach ($divData['competitions'] ?? [] as $comp) {
                        $points = (int) ($comp['points'] ?? 0);
                        $ym     = $this->toYearMonth($comp['event']['date'] ?? '');
                        if ($points <= 0 || !$ym) {
                            continue;
                        }

                        $current = $result[$role][$div];
                        if ($current !== null && $ym > $current['ym']) {
                            continue;
                        }
                        if ($current === null || $ym < $current['ym']) {
                            $current = ['ym' => $ym, 'entries' => []];
                        }

                        $current['entries'][] = [
                            'wscid'    => $wscid,
                            'name'     => $name,
                            'event'    => $comp['event']['name'] ?? '',
                            'location' => $comp['event']['location'] ?? '',
                            'result'   => (string) ($comp['result'] ?? ''),
                            'points'   => $points,
                        ];
                        $result[$role][$div] = $current;
                    }
                }
            }
        }

        return $result;
    }

    // -----------------------------------------------------------------------
    // Internal
    // -----------------------------------------------------------------------

    private function absoluteRankingsAsOf(int $upToYm, array $rawData, array $nameMap): array
    {
        $entries = [];

        foreach ($rawData as $wscid => $raw) {
            $bestEntry = null;

            foreach (['leader', 'follower'] as $role) {
                $entry = $this->buildEntryAsOf((int) $wscid, $role, $raw, $upToYm, $nameMap);
                if ($entry === null) {
                    continue;
                }
                if ($bestEntry === null || $this->isBetter($entry, $bestEntry)) {
                    $bestEntry = $entry;
                }
            }

            if ($bestEntry !== null) {
                $entries[] = $bestEntry;
            }
        }

        return $this->rank($entries);
    }

    private function buildEntryAsOf(
        int    $wscid,
        string $role,
        array  $raw,
        int    $upToYm,
        array  $nameMap
    ): ?array {
        $roleData = $raw[$role] ?? null;
        if (!$roleData) {
            return null;
        }

        $placements = $roleData['placements']['West Coast Swing'] ?? [];
        $points     = array_fill_keys(self::DIVISIONS, 0);
        $hasAny     = false;

        foreach ($placements as $div => $divData) {
            if (!isset($points[$div])) {
                continue; // skip non-standard divisions (e.g. SPH)
            }
            foreach ($divData['competitions'] ?? [] as $comp) {
                $date = $comp['event']['date'] ?? '';
                $ym   = $date ? $this->toYearMonth($date) : 0;
                if ($ym && $ym <= $upToYm) {
                    $points[$div] += (int) ($comp['points'] ?? 0);
                    $hasAny = true;
                }
            }
        }

        if (!$hasAny) {
            return null;
        }

        $required = $this->inferRequired($points);
        $dancer   = $roleData['dancer'] ?? [];
        $name     = $nameMap[$wscid]
            ?? trim(($dancer['first_name'] ?? '') . ' ' . ($dancer['last_name'] ?? ''));

        return [
            'wscid'    => $wscid,
            'name'     => $name,
            'role'     => $role,
            'required' => $required,
            'points'   => $points,
            'rank'     => 0,
        ];
    }

    private function rank(array $entries): array
    {
        usort($entries, function (array $a, array $b): int {
            $aDiv = self::DIV_ORDER[$a['required'] ?? ''] ?? 99;
            $bDiv = self::DIV_ORDER[$b['required'] ?? ''] ?? 99;
            if ($aDiv !== $bDiv) {
                return $aDiv - $bDiv;
            }
            foreach (self::DIVISIONS as $div) {
                $diff = ($b['points'][$div] ?? 0) - ($a['points'][$div] ?? 0);
                if ($diff !== 0) {
                    return $diff;
                }
            }
            return 0;
        });

        $position = 1;
        foreach ($entries as $i => &$entry) {
            if ($i === 0) {
                $entry['rank'] = 1;
            } else {
                $entry['rank'] = $this->sameScore($entries[$i - 1], $entry)
                    ? $entries[$i - 1]['rank']
                    : $position;
            }
            $position++;
        }
        unset($entry);

        return $entries;
    }

    private function sameScore(array $a, array $b): bool
    {
        if (($a['required'] ?? null) !== ($b['required'] ?? null)) {
            return false;
        }
        foreach (self::DIVISIONS as $div) {
            if (($a['points'][$div] ?? 0) !== ($b['points'][$div] ?? 0)) {
                return false;
            }
        }
        return true;
    }

    private function isBetter(array $a, array $b): bool
    {
        $aDiv = self::DIV_ORDER[$a['required'] ?? ''] ?? 99;
        $bDiv = self::DIV_ORDER[$b['required'] ?? ''] ?? 99;
        if ($aDiv !== $bDiv) {
            return $aDiv < $bDiv;
        }
        foreach (self::DIVISIONS as $div) {
            $ap = (int) ($a['points'][$div] ?? 0);
            $bp = (int) ($b['points'][$div] ?? 0);
            if ($ap !== $bp) {
                return $ap > $bp;
            }
        }
        return false;
    }

    private function inferRequired(array $points): ?string
    {
        foreach (['ALS', 'ADV', 'INT', 'NOV', 'NEW'] as $div) {
            if ((int) ($points[$div] ?? 0) >= self::THRESHOLDS[$div]['min']) {
                return self::THRESHOLDS[$div]['required'];
            }
        }
        foreach (self::DIVISIONS as $div) {
            if ((int) ($points[$div] ?? 0) > 0) {
                return $div;
            }
        }
        return null;
    }

    /** Convert "Month YYYY" string to YYYYMM integer. Returns 0 on failure. */
    private function toYearMonth(string $date): int
    {
        $ts = strtotime($date);
        return $ts !== false ? (int) date('Ym', $ts) : 0;
    }

    private function loadAllRaw(): array
    {
        if ($this->rawCache !== null) {
            return $this->rawCache;
        }
        $dir    = $this->dataDir . '/raw';
        $result = [];
        foreach (glob($dir . '/*.json') ?: [] as $file) {
            $wscid = (int) basename($file, '.json');
            $data  = json_decode(file_get_contents($file), true);
            if ($data) {
                $result[$wscid] = $data;
            }
        }
        $this->rawCache = $result;
        return $result;
    }

    /** wscid => display name map from dancers.json (has proper UTF-8 names). */
    private function buildNameMap(): array
    {
        if ($this->nameMap !== null) {
            return $this->nameMap;
        }
        $map = [];
        foreach ($this->data->all() as $dancer) {
            $map[(int) $dancer['wscid']] = $this->data->fullName($dancer);
        }
        $this->nameMap = $map;
        return $map;
    }
}
