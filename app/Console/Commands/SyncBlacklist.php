<?php

namespace App\Console\Commands;

use App\Models\FlaggedAddress;
use App\Services\TronScanner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class SyncBlacklist extends Command
{
    protected $signature = 'tron:sync-blacklist {--full : Re-read every Tether event from the beginning}';

    protected $description = 'Sync Tether USDT freezes (TRON) and OFAC-sanctioned TRON addresses into flagged_addresses';

    private const USDT = 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t';

    private const OFAC_URL = 'https://raw.githubusercontent.com/0xB10C/ofac-sanctioned-digital-currency-addresses/lists/sanctioned_addresses_TRX.txt';

    public function handle(): int
    {
        $this->syncTether();
        $this->syncOfac();

        $counts = FlaggedAddress::selectRaw('source, count(*) n')->groupBy('source')->pluck('n', 'source');
        $this->table(['source', 'addresses'], $counts->map(fn ($n, $s) => [$s, $n])->values());

        return self::SUCCESS;
    }

    private function syncTether(): void
    {
        $since = $this->option('full') ? 0 : (int) DB::table('sync_states')->where('name', 'tether_ms')->value('value');
        $this->info('Tether events since '.($since ? date('Y-m-d H:i', intdiv($since, 1000)) : 'the beginning').'…');

        $events = array_merge($this->events('AddedBlackList', $since), $this->events('RemovedBlackList', $since));
        usort($events, fn ($a, $b) => $a['block_timestamp'] <=> $b['block_timestamp']);

        $last = $since;
        DB::beginTransaction(); // one SQLite write instead of thousands
        foreach ($events as $e) {
            $hex = $e['result']['_user'] ?? $e['result']['0'] ?? null;
            if (! $hex) {
                continue;
            }
            $address = TronScanner::hexToAddress('41'.substr($hex, 2));
            if ($e['event_name'] === 'AddedBlackList') {
                FlaggedAddress::updateOrCreate(
                    ['address' => $address, 'source' => 'tether'],
                    ['flagged_at' => date('Y-m-d H:i:s', intdiv($e['block_timestamp'], 1000)), 'note' => 'tx '.$e['transaction_id']]
                );
            } else {
                FlaggedAddress::where(['address' => $address, 'source' => 'tether'])->delete();
            }
            $last = max($last, $e['block_timestamp']);
        }
        DB::commit();

        DB::table('sync_states')->updateOrInsert(['name' => 'tether_ms'], ['value' => (string) $last, 'updated_at' => now()]);
        $this->info(count($events).' Tether events applied.');
    }

    private function events(string $name, int $since): array
    {
        $headers = ($key = config('services.tron.trongrid_key')) ? ['TRON-PRO-API-KEY' => $key] : [];
        $query = ['event_name' => $name, 'limit' => 200, 'order_by' => 'block_timestamp,asc', 'min_block_timestamp' => $since + 1];
        $out = [];

        $bar = $this->output->createProgressBar();
        $bar->setFormat(" $name: %current% events");
        do {
            $res = Http::withHeaders($headers)->timeout(30)->retry(4, fn ($n) => $n * 1500, throw: false)
                ->get('https://api.trongrid.io/v1/contracts/'.self::USDT.'/events', $query);
            if (! $res->ok()) {
                $this->warn(" TronGrid answered {$res->status()} — stopping here, the next run continues.");
                break;
            }
            $page = $res->json('data') ?? [];
            array_push($out, ...$page);
            $bar->advance(count($page));
            $query['fingerprint'] = $res->json('meta.fingerprint');
            usleep($headers ? 100_000 : 1_000_000);
        } while ($query['fingerprint'] && $page);
        $bar->finish();
        $this->newLine();

        return $out;
    }

    private function syncOfac(): void
    {
        $res = Http::timeout(30)->get(self::OFAC_URL);
        if (! $res->ok()) {
            $this->warn('Could not download the OFAC list ('.$res->status().').');

            return;
        }
        $addresses = array_values(array_filter(
            array_map('trim', explode("\n", $res->body())),
            fn ($a) => TronScanner::isValidAddress($a)
        ));
        if (! $addresses) {
            return;
        }

        DB::transaction(function () use ($addresses) {
            FlaggedAddress::where('source', 'ofac')->delete();
            foreach (array_chunk($addresses, 200) as $chunk) {
                FlaggedAddress::insert(array_map(fn ($a) => [
                    'address' => $a, 'source' => 'ofac', 'note' => 'OFAC SDN', 'created_at' => now(), 'updated_at' => now(),
                ], $chunk));
            }
        });
        $this->info(count($addresses).' OFAC addresses.');
    }
}
