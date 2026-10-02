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
