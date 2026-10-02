<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminController extends Controller
{
    private function csvPath(): string
    {
        return dirname(base_path()) . '/slo_wsdc_ids.csv';
    }

    private function readCsv(): array
    {
        $path = $this->csvPath();
        if (!file_exists($path)) {
            return [];
        }

        $dancers = [];
        $handle  = fopen($path, 'r');
        fgetcsv($handle); // skip header
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) >= 2 && trim($row[0]) !== '') {
                $dancers[] = ['wscid' => trim($row[0]), 'name' => trim($row[1])];
            }
        }
        fclose($handle);
        return $dancers;
    }

    private function writeCsv(array $dancers): void
    {
        $handle = fopen($this->csvPath(), 'w');
        fputcsv($handle, ['WSDCID', 'Name']);
        foreach ($dancers as $d) {
            fputcsv($handle, [$d['wscid'], $d['name']]);
        }
        fclose($handle);
    }

    public function index(): View
    {
        $authed  = session('admin_authed', false);
        $dancers = $authed ? $this->readCsv() : [];
        return view('admin.index', compact('authed', 'dancers'));
    }

    public function login(Request $request): RedirectResponse
    {
        $secret = env('SCRAPER_SECRET', '');
        if ($secret !== '' && hash_equals($secret, (string) $request->input('secret', ''))) {
            session(['admin_authed' => true]);
            return redirect('/admin');
        }
        return redirect('/admin')->with('login_error', 'Napačno geslo.');
    }

    public function addDancer(Request $request): RedirectResponse
    {
        if (!session('admin_authed')) {
            return redirect('/admin');
        }

        $wscid = trim((string) $request->input('wscid', ''));
        $name  = trim((string) $request->input('name', ''));

        if (!ctype_digit($wscid) || $wscid === '' || $name === '') {
            return redirect('/admin')->with('flash_error', 'WSDCID mora biti številka in ime ne sme biti prazno.');
        }

        $dancers = $this->readCsv();
        foreach ($dancers as $d) {
            if ($d['wscid'] === $wscid) {
                return redirect('/admin')->with('flash_error', "WSDCID {$wscid} že obstaja.");
            }
        }

        $dancers[] = ['wscid' => $wscid, 'name' => $name];
        usort($dancers, fn($a, $b) => (int) $a['wscid'] <=> (int) $b['wscid']);
        $this->writeCsv($dancers);

        return redirect('/admin')->with('flash_success', "Plesalec {$name} ({$wscid}) dodan.");
    }

    public function removeDancer(string $wscid): RedirectResponse
    {
        if (!session('admin_authed')) {
            return redirect('/admin');
        }

        $dancers = $this->readCsv();
        $removed = array_filter($dancers, fn($d) => $d['wscid'] === $wscid);
        $dancers = array_values(array_filter($dancers, fn($d) => $d['wscid'] !== $wscid));
        $this->writeCsv($dancers);

        $label = $removed ? reset($removed)['name'] : $wscid;
        return redirect('/admin')->with('flash_success', "Plesalec {$label} odstranjen.");
    }

    /** Start scraper — session auth (used from /admin page). */
    public function startScraper(): JsonResponse
    {
        if (!session('admin_authed')) {
            return response()->json(['ok' => false, 'error' => 'Ni avtorizacije.'], 403);
        }

        $scraperPath = dirname(base_path()) . '/scraper/fetch.php';
        if (!file_exists($scraperPath)) {
            return response()->json(['ok' => false, 'error' => 'Scraper ni bil najden: ' . $scraperPath]);
        }

        $phpBin  = $this->findPhpCli();
        $liveLog = dirname(base_path()) . '/data/scraper_live.log';

        file_put_contents($liveLog, '');

        $cmd = sprintf(
            '%s %s >> %s 2>&1; echo "__EXIT__:$?" >> %s',
            escapeshellarg($phpBin),
            escapeshellarg($scraperPath),
            escapeshellarg($liveLog),
            escapeshellarg($liveLog)
        );
        exec('nohup sh -c ' . escapeshellarg($cmd) . ' > /dev/null 2>&1 &');

        return response()->json(['ok' => true, 'phpBin' => $phpBin]);
    }

    /** Poll live log — session auth. */
    public function tailScraper(Request $request): JsonResponse
    {
        if (!session('admin_authed')) {
            return response()->json(['ok' => false, 'error' => 'Ni avtorizacije.'], 403);
        }

        $liveLog = dirname(base_path()) . '/data/scraper_live.log';
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

        return response()->json(['lines' => $lines, 'offset' => $newOffset, 'done' => $done, 'exitOk' => $exitOk]);
    }

    private function findPhpCli(): string
    {
        // Explicit override via .env takes priority
        $envBin = env('PHP_CLI_BIN', '');
        if ($envBin !== '' && is_executable($envBin)) {
            return $envBin;
        }

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
