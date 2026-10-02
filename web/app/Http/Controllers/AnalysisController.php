<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\HistoryService;
use Carbon\Carbon;
use Illuminate\View\View;

class AnalysisController extends Controller
{
    private const DIVISIONS = ['CHA', 'ALS', 'ADV', 'INT', 'NOV', 'NEW'];
    private const COLORS = [
        'CHA' => '#8b5cf6',
        'ALS' => '#3b82f6',
        'ADV' => '#10b981',
        'INT' => '#f59e0b',
        'NOV' => '#06b6d4',
        'NEW' => '#94a3b8',
    ];

    public function __construct(private HistoryService $history) {}

    public function index(): View
    {
        // snapshots() returns newest-first; reverse for chronological order
        $snapshots = array_reverse($this->history->snapshots());

        $chartData = $this->buildChartData($snapshots);

        return view('analysis.index', compact('chartData'));
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

        // Division datasets
        $divisionDatasets = [];
        foreach (self::DIVISIONS as $div) {
            $color = self::COLORS[$div];
            $divisionDatasets[] = [
                'label'           => $div,
                'data'            => $divCounts[$div],
                'borderColor'     => $color,
                'backgroundColor' => $color . '33',
                'tension'         => 0.3,
                'fill'            => false,
                'pointRadius'     => 4,
                'pointHoverRadius'=> 6,
            ];
        }

        // Role datasets
        $roleDatasets = [
            [
                'label'           => 'Leaders',
                'data'            => $leaderCounts,
                'borderColor'     => '#6366f1',
                'backgroundColor' => '#6366f133',
                'tension'         => 0.3,
                'fill'            => false,
                'pointRadius'     => 4,
                'pointHoverRadius'=> 6,
            ],
            [
                'label'           => 'Followers',
                'data'            => $followerCounts,
                'borderColor'     => '#f43f5e',
                'backgroundColor' => '#f43f5e33',
                'tension'         => 0.3,
                'fill'            => false,
                'pointRadius'     => 4,
                'pointHoverRadius'=> 6,
            ],
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
