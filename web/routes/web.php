<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AnalysisController;
use App\Http\Controllers\DancerController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\RankingController;
use App\Http\Controllers\ScraperController;
use Illuminate\Support\Facades\Route;

Route::get('/',                           [RankingController::class, 'home']);

Route::get('/ranking/leaders',            [RankingController::class, 'leaders'])->defaults('scope', 'primary');
Route::get('/ranking/leaders/all',        [RankingController::class, 'leaders'])->defaults('scope', 'all');

Route::get('/ranking/followers',          [RankingController::class, 'followers'])->defaults('scope', 'primary');
Route::get('/ranking/followers/all',      [RankingController::class, 'followers'])->defaults('scope', 'all');

Route::get('/ranking/absolute',           [RankingController::class, 'absolute']);

Route::get('/history',                    [HistoryController::class, 'index']);

Route::get('/analysis',                   [AnalysisController::class, 'index']);

Route::post('/scrape',                    [ScraperController::class, 'start']);
Route::get('/scrape/tail',                [ScraperController::class, 'tail']);

Route::get('/admin',                         [AdminController::class, 'index']);
Route::post('/admin/login',                  [AdminController::class, 'login']);
Route::post('/admin/dancers',                [AdminController::class, 'addDancer']);
Route::post('/admin/dancers/{wscid}/delete', [AdminController::class, 'removeDancer'])
    ->where('wscid', '[0-9]+');
Route::post('/admin/scrape/start',           [AdminController::class, 'startScraper']);
Route::get('/admin/scrape/tail',             [AdminController::class, 'tailScraper']);

Route::get('/dancer/{wscid}',             [DancerController::class, 'show'])
    ->where('wscid', '[0-9]+');

