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
}
