<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\DataService;
use App\Services\RankingService;
use Illuminate\View\View;

class RankingController extends Controller
{
    public function __construct(
        private DataService   $data,
        private RankingService $ranking,
    ) {}

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
            'roleLabel'   => 'Followers',
            'role'        => 'follower',
            'scope'       => $scope,
            'lastUpdated' => $this->data->lastUpdated(),
        ]);
    }

    /** Absolute ranking regardless of role. */
    public function absolute(): View
    {
        return view('ranking.absolute', [
            'entries'     => $this->ranking->absolute(),
            'lastUpdated' => $this->data->lastUpdated(),
        ]);
    }
}
