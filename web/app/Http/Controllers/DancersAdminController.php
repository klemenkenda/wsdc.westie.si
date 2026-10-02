<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DancersAdminController extends Controller
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
        fgetcsv($handle); // skip header row
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
        $authed  = session('dancers_admin_authed', false);
        $dancers = $authed ? $this->readCsv() : [];
        return view('dancer.edit', compact('authed', 'dancers'));
    }

    public function login(Request $request): RedirectResponse
    {
        $secret = env('SCRAPER_SECRET', '');
        if ($secret !== '' && hash_equals($secret, (string) $request->input('secret', ''))) {
            session(['dancers_admin_authed' => true]);
            return redirect('/dancers/edit');
        }
        return redirect('/dancers/edit')->with('login_error', 'Napačno geslo.');
    }

    public function store(Request $request): RedirectResponse
    {
        if (!session('dancers_admin_authed')) {
            return redirect('/dancers/edit');
        }

        $wscid = trim((string) $request->input('wscid', ''));
        $name  = trim((string) $request->input('name', ''));

        if (!ctype_digit($wscid) || $wscid === '' || $name === '') {
            return redirect('/dancers/edit')->with('flash_error', 'WSDCID mora biti številka in ime ne sme biti prazno.');
        }

        $dancers = $this->readCsv();
        foreach ($dancers as $d) {
            if ($d['wscid'] === $wscid) {
                return redirect('/dancers/edit')->with('flash_error', "WSDCID {$wscid} že obstaja.");
            }
        }

        $dancers[] = ['wscid' => $wscid, 'name' => $name];
        usort($dancers, fn($a, $b) => (int) $a['wscid'] <=> (int) $b['wscid']);
        $this->writeCsv($dancers);

        return redirect('/dancers/edit')->with('flash_success', "Plesalec {$name} ({$wscid}) dodan.");
    }

    public function destroy(string $wscid): RedirectResponse
    {
        if (!session('dancers_admin_authed')) {
            return redirect('/dancers/edit');
        }

        $dancers = $this->readCsv();
        $removed = array_filter($dancers, fn($d) => $d['wscid'] === $wscid);
        $dancers = array_values(array_filter($dancers, fn($d) => $d['wscid'] !== $wscid));
        $this->writeCsv($dancers);

        $label = $removed ? reset($removed)['name'] : $wscid;
        return redirect('/dancers/edit')->with('flash_success', "Plesalec {$label} odstranjen.");
    }
}
