<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\DataService;
use App\Services\RankingService;
use Illuminate\Http\Response;
use Illuminate\View\View;

class DancerController extends Controller
{
    public function __construct(
        private DataService    $data,
        private RankingService $ranking,
    ) {}

    public function show(int $wscid): View|Response
    {
        $dancer = $this->data->find($wscid);

        if ($dancer === null) {
            abort(404, 'Plesalec ni bil najden.');
        }

        $raw = $this->data->rawData($wscid);

        // Gather full competition history per role from raw data
        $history = $this->extractHistory($raw);

        // Community rankings for this dancer
        $ranks = $this->dancerRanks($wscid);

        return view('dancer.show', [
            'dancer'      => $dancer,
            'name'        => $this->data->fullName($dancer),
            'primary'     => $this->data->primaryRole($dancer),
            'history'     => $history,
            'ranks'       => $ranks,
            'lastUpdated' => $this->data->lastUpdated(),
        ]);
    }

    // -----------------------------------------------------------------------

    /**
     * Returns all competitions sorted by date descending, grouped by role.
     *
     * [
     *   'leader'   => [ ['division'=>, 'event'=>, 'date'=>, 'points'=>, 'result'=>], … ],
     *   'follower' => [ … ],
     * ]
     */
    private function extractHistory(?array $raw): array
    {
        $history = ['leader' => [], 'follower' => []];

        if ($raw === null) {
            return $history;
        }

        foreach (['leader', 'follower'] as $role) {
            $roleData   = $raw[$role] ?? null;
            if ($roleData === null) {
                continue;
            }

            $placements = $roleData['placements']['West Coast Swing'] ?? [];
            $comps      = [];

            foreach ($placements as $division => $divData) {
                foreach ($divData['competitions'] ?? [] as $comp) {
                    $comps[] = [
                        'division' => $division,
                        'event'    => $comp['event']['name']     ?? '',
                        'location' => $comp['event']['location'] ?? '',
                        'date'     => $comp['event']['date']     ?? '',
                        'url'      => $comp['event']['url']      ?? null,
                        'points'   => $comp['points']            ?? 0,
                        'result'   => $comp['result']            ?? '',
                    ];
                }
            }

            // Sort newest first (lexical on month-year strings like "September 2025")
            usort($comps, fn($a, $b) => $this->compareDates($b['date'], $a['date']));

            $history[$role] = $comps;
        }

        return $history;
    }

    /**
     * Compare two date strings of the form "Month YYYY".
     * Returns negative/0/positive like strcmp.
     */
    private function compareDates(string $a, string $b): int
    {
        $ta = strtotime($a) ?: 0;
        $tb = strtotime($b) ?: 0;
        return $ta - $tb;
    }

    /**
     * Find this dancer's rank in each applicable ranking table.
     *
     * Returns array keyed by ranking name with ['rank', 'total'] entries.
     */
    private function dancerRanks(int $wscid): array
    {
        $tables = [
            'Leaders (primary)'       => $this->ranking->leadersPrimary(),
            'Leaders (vsi)'           => $this->ranking->leadersAll(),
            'Followers (primary)'     => $this->ranking->followersPrimary(),
            'Followers (vsi)'         => $this->ranking->followersAll(),
            'Skupna lestvica'         => $this->ranking->absolute(),
        ];

        $ranks = [];
        foreach ($tables as $label => $entries) {
            foreach ($entries as $entry) {
                if ((int) $entry['wscid'] === $wscid) {
                    $ranks[$label] = [
                        'rank'  => $entry['rank'],
                        'total' => count($entries),
                    ];
                    break;
                }
            }
        }

        return $ranks;
    }
}
