<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\DataService;
use App\Services\HistoryService;
use Illuminate\View\View;

class HistoryController extends Controller
{
    public function __construct(
        private HistoryService $history,
        private DataService    $data,
    ) {}

    public function index(): View
    {
        return view('history.index', [
            'snapshots'   => $this->history->snapshots(),
            'lastUpdated' => $this->data->lastUpdated(),
        ]);
    }

    public function recent(): View
    {
        return view('history.recent', [
            'events'      => $this->history->recentPoints(20),
            'lastUpdated' => $this->data->lastUpdated(),
        ]);
    }

    public function firsts(): View
    {
        return view('history.firsts', [
            'firsts'      => $this->history->firsts(),
            'national'    => $this->history->nationalFirsts(),
            'lastUpdated' => $this->data->lastUpdated(),
        ]);
    }
}
