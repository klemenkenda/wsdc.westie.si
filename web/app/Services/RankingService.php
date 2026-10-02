<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Computes rankings for Slovenian WCS dancers.
 *
 * Ranking rules (from TODO.md):
 *  1. Rank by highest required division (CHA > ALS > ADV > INT > NOV > NEW).
 *  2. Tie-break within the same required division: points in that division (desc).
 *  3. Continue tie-breaking through lower divisions in order.
 *  4. If all points are equal, the rank is the same.
 *
 * Available ranking modes:
 *  - leaders_primary        : dancers whose primary role is leader
 *  - followers_primary      : dancers whose primary role is follower
 *  - leaders_all            : all dancers who have any leader data
 *  - followers_all          : all dancers who have any follower data
 *  - absolute               : all dancers, using their best role
 */
class RankingService
{
    // Highest division first
    private const DIVISIONS = ['CHA', 'ALS', 'ADV', 'INT', 'NOV', 'NEW'];
    private const DIV_ORDER  = ['CHA' => 0, 'ALS' => 1, 'ADV' => 2, 'INT' => 3, 'NOV' => 4, 'NEW' => 5];

    public function __construct(private DataService $data) {}

    // -----------------------------------------------------------------------
    // Public ranking methods
    // -----------------------------------------------------------------------

    /** Rankings for leaders by primary role only. */
    public function leadersPrimary(): array
    {
        $list = [];
        foreach ($this->data->all() as $dancer) {
            if ($dancer['leader'] === null || !$this->hasCompeted($dancer['leader'])) {
                continue;
            }
            if ($this->data->primaryRole($dancer) !== 'leader') {
                continue;
            }
            $list[] = $this->entry($dancer, 'leader');
        }
        return $this->rank($list);
    }

    /** Rankings for leaders including secondary-role leaders. */
    public function leadersAll(): array
    {
        $list = [];
        foreach ($this->data->all() as $dancer) {
            if ($dancer['leader'] === null || !$this->hasCompeted($dancer['leader'])) {
                continue;
            }
            $list[] = $this->entry($dancer, 'leader');
        }
        return $this->rank($list);
    }

    /** Rankings for followers by primary role only. */
    public function followersPrimary(): array
    {
        $list = [];
        foreach ($this->data->all() as $dancer) {
            if ($dancer['follower'] === null || !$this->hasCompeted($dancer['follower'])) {
                continue;
            }
            if ($this->data->primaryRole($dancer) !== 'follower') {
                continue;
            }
            $list[] = $this->entry($dancer, 'follower');
        }
        return $this->rank($list);
    }

    /** Rankings for followers including secondary-role followers. */
    public function followersAll(): array
    {
        $list = [];
        foreach ($this->data->all() as $dancer) {
            if ($dancer['follower'] === null || !$this->hasCompeted($dancer['follower'])) {
                continue;
            }
            $list[] = $this->entry($dancer, 'follower');
        }
        return $this->rank($list);
    }

    /**
     * Absolute ranking — all dancers, each represented by their best role.
     * "Best" = role with higher required division; tie-break = more total points.
     */
    public function absolute(): array
    {
        $list = [];
        foreach ($this->data->all() as $dancer) {
            $role = $this->bestRole($dancer);
            if ($role === null) {
                continue;
            }
            $list[] = $this->entry($dancer, $role);
        }
        return $this->rank($list);
    }

    // -----------------------------------------------------------------------
    // Internal helpers
    // -----------------------------------------------------------------------

    /** Build a ranking entry array for one dancer + role. */
    private function entry(array $dancer, string $role): array
    {
        $roleData = $dancer[$role];
        $points   = $roleData['points']  ?? array_fill_keys(self::DIVISIONS, 0);
        $level    = $roleData['level']   ?? [];
        $required = $level['required']   ?? null;
        $allowed  = $level['allowed']    ?? null;

        // If the API returned null for the required division, infer it using
        // WSDC promotion thresholds.
        if ($required === null) {
            $required = $this->inferRequired($points);
        }

        return [
            'wscid'     => $dancer['wscid'],
            'name'      => $this->data->fullName($dancer),
            'role'      => $role,
            'primary'   => $this->data->primaryRole($dancer),
            'required'  => $required,   // current mandatory division
            'allowed'   => $allowed,    // can compete up to here
            'points'    => $points,
            'best'      => $roleData['best_competition']  ?? null,
            'first'     => $roleData['first_competition'] ?? null,
            'rank'      => 0,           // filled in by rank()
        ];
    }

    /**
     * Sort entries and assign ranks.
     * Rank is NOT a sequential counter — equal entries share a rank.
     */
    private function rank(array $entries): array
    {
        usort($entries, [$this, 'compareEntries']);

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

    /**
     * Infer the required division from points using WSDC promotion thresholds.
     * Mirrors DataService::inferRequired().
     */
    private function inferRequired(array $points): ?string
    {
        $thresholds = [
            'ALS' => ['required' => 'CHA', 'min' => 150],
            'ADV' => ['required' => 'ALS', 'min' => 60],
            'INT' => ['required' => 'ADV', 'min' => 45],
            'NOV' => ['required' => 'INT', 'min' => 30],
            'NEW' => ['required' => 'NOV', 'min' => 16],
        ];

        foreach (['ALS', 'ADV', 'INT', 'NOV', 'NEW'] as $div) {
            $pts = (int) ($points[$div] ?? 0);
            if ($pts >= $thresholds[$div]['min']) {
                return $thresholds[$div]['required'];
            }
        }

        foreach (self::DIVISIONS as $div) {
            if ((int) ($points[$div] ?? 0) > 0) {
                return $div;
            }
        }

        return null;
    }

    /** True if a role data entry has at least one real competition. */
    private function hasCompeted(array $roleData): bool
    {
        if (!empty($roleData['best_competition'])) {
            return true;
        }
        foreach ($roleData['points'] ?? [] as $pts) {
            if ((int) $pts > 0) {
                return true;
            }
        }
        return false;
    }

    /** Compare two entries for sorting (returns negative/0/positive). */
    private function compareEntries(array $a, array $b): int
    {
        // 1. Required division (lower index = higher division = better)
        $aDiv = self::DIV_ORDER[$a['required'] ?? ''] ?? 99;
        $bDiv = self::DIV_ORDER[$b['required'] ?? ''] ?? 99;

        if ($aDiv !== $bDiv) {
            return $aDiv - $bDiv;
        }

        // 2. Cascade through divisions top-to-bottom by points (desc)
        foreach (self::DIVISIONS as $div) {
            $ap = (int) ($a['points'][$div] ?? 0);
            $bp = (int) ($b['points'][$div] ?? 0);
            if ($ap !== $bp) {
                return $bp - $ap; // more points = better rank
            }
        }

        return 0; // completely equal → same rank
    }

    /** True if two entries are completely equal in rank criteria. */
    private function sameScore(array $a, array $b): bool
    {
        return $this->compareEntries($a, $b) === 0;
    }

    /**
     * Pick the "best" role for absolute ranking — role with higher required
     * division; tie-break by total points.
     */
    private function bestRole(array $dancer): ?string
    {
        $l = $dancer['leader']   ?? null;
        $f = $dancer['follower'] ?? null;

        if ($l === null && $f === null) {
            return null;
        }
        if ($l === null) {
            return 'follower';
        }
        if ($f === null) {
            return 'leader';
        }

        $lRank = self::DIV_ORDER[$l['level']['required'] ?? ''] ?? 99;
        $fRank = self::DIV_ORDER[$f['level']['required'] ?? ''] ?? 99;

        if ($lRank !== $fRank) {
            return $lRank < $fRank ? 'leader' : 'follower';
        }

        return array_sum($l['points'] ?? []) >= array_sum($f['points'] ?? [])
            ? 'leader'
            : 'follower';
    }
}
