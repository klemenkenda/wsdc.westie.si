<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\DataService;
use App\Services\HistoryService;
use Carbon\Carbon;
use Illuminate\View\View;

class AnalysisController extends Controller
{
    private const DIVISIONS = ['CHA', 'ALS', 'ADV', 'INT', 'NOV', 'NEW'];

    public function __construct(
        private HistoryService $history,
        private DataService    $data,
    ) {}

    public function index(): View
    {
        // snapshots() returns newest-first; reverse for chronological order
        $snapshots = array_reverse($this->history->snapshots());

        $chartData  = $this->buildChartData($snapshots);
        $pointsData = $this->history->pointsByYear();

        return view('analysis.index', [
            'chartData'   => $chartData,
            'pointsData'  => $pointsData,
            'lastUpdated' => $this->data->lastUpdated(),
        ]);
    }

    private function buildChartData(array $snapshots): array
    {
        $labels      = [];
        $isYearEnd   = [];
        $divCounts   = array_fill_keys(self::DIVISIONS, []);
        $leaderCounts    = [];
        $followerCounts  = [];

        foreach ($snapshots as $snap) {
            if ($snap['is_year_end'] && !$this->hasEventSameMonth($snap)) {
                $year     = intdiv((int)$snap['ym'], 100);
                $labels[] = "Dec 31, {$year}";
            } else {
                $labels[] = Carbon::createFromFormat('Ym', (string)$snap['ym'])->format('M Y');
            }

            $isYearEnd[] = $snap['is_year_end'];

            $counts    = array_fill_keys(self::DIVISIONS, 0);
            $leaders   = 0;
            $followers = 0;

            foreach ($snap['rankings'] as $entry) {
                $div = $entry['required'] ?? null;
                if ($div && isset($counts[$div])) {
                    $counts[$div]++;
                }
                if ($entry['role'] === 'leader') {
                    $leaders++;
                } else {
                    $followers++;
                }
            }

            foreach (self::DIVISIONS as $div) {
                $divCounts[$div][] = $counts[$div];
            }
            $leaderCounts[]   = $leaders;
            $followerCounts[] = $followers;
        }

        // Styling lives in the view, where it can follow the light/dark theme.
        $divisionDatasets = [];
        foreach (self::DIVISIONS as $div) {
            $divisionDatasets[] = ['label' => $div, 'data' => $divCounts[$div]];
        }

        $roleDatasets = [
            ['label' => 'Leaders',   'role' => 'leader',   'data' => $leaderCounts],
            ['label' => 'Followers', 'role' => 'follower', 'data' => $followerCounts],
        ];

        return [
            'labels'           => $labels,
            'isYearEnd'        => $isYearEnd,
            'divisionDatasets' => $divisionDatasets,
            'roleDatasets'     => $roleDatasets,
        ];
    }

    /** Year-end snapshots that coincide with an actual event are not synthetic. */
    private function hasEventSameMonth(array $snap): bool
    {
        // If label is "December 31, YYYY" it was a synthetic year-end entry;
        // otherwise a real December event was just flagged as year-end too.
        return !str_starts_with((string)($snap['label'] ?? ''), 'December 31');
    }
}
