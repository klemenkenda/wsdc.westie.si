<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\DataService;
use App\Services\HistoryService;
use App\Services\RankingService;
use Illuminate\View\View;

class RankingController extends Controller
{
    public function __construct(
        private DataService   $data,
        private RankingService $ranking,
        private HistoryService $history,
    ) {}

    private const TOP      = 10;
    private const TOP_ROLE = 5;
    private const RISING = 3;

    /** Home page: absolute ranking by default. */
    public function home(): View
    {
        return $this->absolute();
    }

    /** Leaders: primary-role only or primary+secondary. */
    public function leaders(string $scope = 'primary'): View
    {
        $entries = $scope === 'all'
            ? $this->ranking->leadersAll()
            : $this->ranking->leadersPrimary();

        return view('ranking.index', [
            'entries'     => $entries,
            'top'         => self::TOP_ROLE,
            'topCount'    => $this->topCount($entries, self::TOP_ROLE),
            'rising'      => $this->risingStars($entries, 'leader', self::TOP_ROLE),
            'roleLabel'   => 'Leaders',
            'role'        => 'leader',
            'scope'       => $scope,
            'lastUpdated' => $this->data->lastUpdated(),
        ]);
    }

    /** Followers: primary-role only or primary+secondary. */
    public function followers(string $scope = 'primary'): View
    {
        $entries = $scope === 'all'
            ? $this->ranking->followersAll()
            : $this->ranking->followersPrimary();

        return view('ranking.index', [
            'entries'     => $entries,
            'top'         => self::TOP_ROLE,
            'topCount'    => $this->topCount($entries, self::TOP_ROLE),
            'rising'      => $this->risingStars($entries, 'follower', self::TOP_ROLE),
            'roleLabel'   => 'Followers',
            'role'        => 'follower',
            'scope'       => $scope,
            'lastUpdated' => $this->data->lastUpdated(),
        ]);
    }

    /** Absolute ranking regardless of role. */
    public function absolute(): View
    {
        $entries = $this->ranking->absolute();

        return view('ranking.absolute', [
            'entries'     => $entries,
            'top'         => self::TOP,
            'topCount'    => $this->topCount($entries, self::TOP),
            'rising'      => $this->risingStars($entries, null, self::TOP),
            'lastUpdated' => $this->data->lastUpdated(),
        ]);
    }

    /** Rows in the top of the table: the top $top, plus anyone tied for the last place. */
    private function topCount(array $entries, int $top): int
    {
        return count(array_filter($entries, fn($e) => $e['rank'] <= $top));
    }

    /**
     * Dancers outside the top with the most points in the last 12 months.
     * They are ranked by weighted points, each division counting double the
     * one below it (NEW ×1, NOV ×2, INT ×4, ADV ×8, ALS ×16, CHA ×32), but the
     * actual points are what is shown. $role limits the points to one role;
     * null counts both.
     *
     * @return array<int, array{entry: array, points: int, top_div: ?string, top_points: int}>
     */
    private function risingStars(array $entries, ?string $role, int $top): array
    {
        $divisions = DataService::divisions();          // highest first
        $recent    = $this->history->pointsInLastMonths(12);
        $stars     = [];

        foreach (array_slice($entries, $this->topCount($entries, $top)) as $e) {
            $byRole = $recent[(int) $e['wscid']] ?? null;
            if ($byRole === null) {
                continue;
            }

            $byDiv = [];
            foreach ($divisions as $div) {
                $byDiv[$div] = $role
                    ? $byRole[$role][$div]
                    : $byRole['leader'][$div] + $byRole['follower'][$div];
            }

            $points   = array_sum($byDiv);
            $weighted = 0;
            $topDiv   = null;
            foreach ($divisions as $i => $div) {
                $weighted += $byDiv[$div] * 2 ** (count($divisions) - 1 - $i);
                if ($topDiv === null && $byDiv[$div] > 0) {
                    $topDiv = $div;
                }
            }

            if ($points > 0) {
                $stars[] = [
                    'entry'      => $e,
                    'points'     => $points,
                    'weighted'   => $weighted,
                    'top_div'    => $topDiv,
                    'top_points' => $topDiv ? $byDiv[$topDiv] : 0,
                ];
            }
        }

        usort($stars, fn($a, $b) => [$b['weighted'], $b['points'], $a['entry']['name']]
            <=> [$a['weighted'], $a['points'], $b['entry']['name']]);

        return array_slice($stars, 0, self::RISING);
    }
}
