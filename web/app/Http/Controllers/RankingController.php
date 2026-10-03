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
     * Dancers outside the top with the most points in the last 12 months,
     * weighted so each division counts double the one below it.
     * $role limits the points to one role; null counts both.
     *
     * @return array<int, array{entry: array, points: int}>
     */
    private function risingStars(array $entries, ?string $role, int $top): array
    {
        $recent = $this->history->pointsInLastMonths(12, weighted: true);
        $stars  = [];

        foreach (array_slice($entries, $this->topCount($entries, $top)) as $e) {
            $pts = $recent[(int) $e['wscid']] ?? ['leader' => 0, 'follower' => 0];
            $points = $role ? $pts[$role] : $pts['leader'] + $pts['follower'];
            if ($points > 0) {
                $stars[] = ['entry' => $e, 'points' => $points];
            }
        }

        usort($stars, fn($a, $b) => [$b['points'], $a['entry']['name']] <=> [$a['points'], $b['entry']['name']]);

        return array_slice($stars, 0, self::RISING);
    }
}
