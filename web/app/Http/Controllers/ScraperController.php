<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScraperController extends Controller
{
    private function liveLog(): string
    {
        return dirname(base_path()) . '/data/scraper_live.log';
    }

    /** Start the scraper as a background process and return immediately. */
    public function start(Request $request): JsonResponse
    {
        try {
            $secret = env('SCRAPER_SECRET', '');

            if ($secret === '') {
                return response()->json(['ok' => false, 'error' => 'SCRAPER_SECRET ni nastavljen v .env.']);
            }

            if (!hash_equals($secret, (string) $request->input('secret', ''))) {
                return response()->json(['ok' => false, 'error' => 'Napacno geslo.']);
            }

            $scraperPath = dirname(base_path()) . '/scraper/fetch.php';
            if (!file_exists($scraperPath)) {
                return response()->json(['ok' => false, 'error' => 'Scraper ni bil najden: ' . $scraperPath]);
            }

            $phpBin  = $this->findPhpCli();
            $liveLog = $this->liveLog();

            // Clear previous live log
            file_put_contents($liveLog, '');

            // Run scraper in background, redirect all output to live log,
            // append exit sentinel when done.
            $cmd = sprintf(
                '%s %s >> %s 2>&1; echo "__EXIT__:$?" >> %s',
                escapeshellarg($phpBin),
                escapeshellarg($scraperPath),
                escapeshellarg($liveLog),
                escapeshellarg($liveLog)
            );

            exec('nohup sh -c ' . escapeshellarg($cmd) . ' > /dev/null 2>&1 &');

            return response()->json(['ok' => true, 'phpBin' => $phpBin]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    /** Return new lines from the live log since the given byte offset. */
    public function tail(Request $request): JsonResponse
    {
        $liveLog = $this->liveLog();
        $offset  = max(0, (int) $request->query('offset', 0));

        if (!file_exists($liveLog)) {
            return response()->json(['lines' => [], 'offset' => 0, 'done' => false, 'exitOk' => false]);
        }

        $content = file_get_contents($liveLog, false, null, $offset);
        if ($content === false || $content === '') {
            return response()->json(['lines' => [], 'offset' => $offset, 'done' => false, 'exitOk' => false]);
        }

        $newOffset = $offset + strlen($content);
        $rawLines  = array_filter(explode("\n", $content), fn($l) => $l !== '');
        $lines     = [];
        $done      = false;
        $exitOk    = false;

        foreach (array_values($rawLines) as $line) {
            if (str_starts_with($line, '__EXIT__:')) {
                $done   = true;
                $exitOk = ((int) substr($line, 9)) === 0;
            } else {
                $lines[] = $line;
            }
        }

        return response()->json([
            'lines'  => $lines,
            'offset' => $newOffset,
            'done'   => $done,
            'exitOk' => $exitOk,
        ]);
    }

    private function findPhpCli(): string
    {
        $binary = PHP_BINARY;
        if (!str_contains($binary, 'fpm') && !str_contains($binary, 'cgi')) {
            return $binary;
        }

        $candidates = [
            dirname(PHP_BINARY) . '/php',
            '/usr/local/bin/php',
            '/usr/bin/php',
            '/usr/bin/php8',
            '/usr/bin/php83',
        ];

        foreach ($candidates as $path) {
            if (is_executable($path)) {
                return $path;
            }
        }

        return 'php';
    }
}
