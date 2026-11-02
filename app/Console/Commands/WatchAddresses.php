<?php

namespace App\Console\Commands;

use App\Models\FlaggedAddress;
use App\Models\Watch;
use App\Services\Telegram;
use App\Services\TronScanner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class WatchAddresses extends Command
{
    protected $signature = 'tron:watch';

    protected $description = 'Send Telegram alerts for new transfers / blacklisting of watched addresses';

    public function handle(TronScanner $scanner, Telegram $telegram): int
    {
        if (! Telegram::configured()) {
            $this->warn('TELEGRAM_BOT_TOKEN is not set.');

            return self::SUCCESS;
        }

        foreach (Watch::all()->groupBy('address') as $address => $watches) {
            $since = (int) $watches->min('last_seen_ms');
            $transfers = $this->transfersSince($address, $since);
            $flags = FlaggedAddress::lookup(array_merge(array_column($transfers, 'from'), array_column($transfers, 'to')));
            $blacklisted = $scanner->usdtBlacklistedNow($address);

            foreach ($watches as $watch) {
                $name = $watch->label ? e($watch->label).' — ' : '';
                $new = array_filter($transfers, fn ($t) => $t['ms'] > $watch->last_seen_ms);

                foreach (array_reverse($new) as $t) { // oldest first
                    $telegram->send($watch->chat_id, $this->message($name, $address, $t, $flags));
                }
                if ($blacklisted && ! $watch->was_blacklisted) {
                    $telegram->send($watch->chat_id, "⛔ <b>{$name}بلاک‌لیست کرا!</b>\n<code>$address</code>\nTether ئەم ئادرەسەی فریز کرد — USDT ەکەی ناجووڵێت.");
                }

                $watch->update([
                    'last_seen_ms' => max([$watch->last_seen_ms, ...array_column($new, 'ms')]),
                    'was_blacklisted' => (bool) $blacklisted,
                ]);
            }
            $this->line("$address: ".count($transfers).' new');
        }

        return self::SUCCESS;
    }

    private function message(string $name, string $address, array $t, array $flags): string
    {
        $in = $t['to'] === $address;
        $other = $in ? $t['from'] : $t['to'];
        $amount = rtrim(rtrim(number_format($t['amount'], 6, '.', ','), '0'), '.');
        $warn = isset($flags[$other])
            ? "\n⚠️ <b>لایەنی بەرامبەر نیشانە کراوە:</b> ".implode('، ', array_map(fn ($s) => FlaggedAddress::SOURCES[$s] ?? $s, $flags[$other]))
            : '';

        return ($in ? '⬇️ <b>'.$name.'پارە هات</b>' : '⬆️ <b>'.$name.'پارە ڕۆیشت</b>')
            ."\n<b>$amount {$t['token']}</b>"
            ."\n".($in ? 'لە' : 'بۆ').": <code>$other</code>"
            ."\nوالێت: <code>$address</code>"
            ."\n🕒 ".date('Y-m-d H:i', intdiv($t['ms'], 1000))
            .$warn
            ."\n<a href=\"https://tronscan.org/#/transaction/{$t['tx']}\">Tronscan</a>"
            .(config('app.url') ? ' · <a href="'.e(rtrim(config('app.url'), '/').'/?address='.$other).'">سکانی لایەنەکە</a>' : '');
    }

    private function transfersSince(string $address, int $sinceMs): array
    {
        $http = Http::timeout(20)->acceptJson()->retry(3, 1000, throw: false)
            ->withHeaders(array_filter(['TRON-PRO-API-KEY' => config('services.tron.trongrid_key')]));
        $out = [];

        $trc20 = $http->get("https://api.trongrid.io/v1/accounts/$address/transactions/trc20",
            ['limit' => 50, 'min_timestamp' => $sinceMs + 1, 'only_confirmed' => 'true'])->json('data') ?? [];
        foreach ($trc20 as $t) {
            if (($t['type'] ?? '') === 'Transfer') {
                $out[] = ['tx' => $t['transaction_id'], 'ms' => $t['block_timestamp'], 'from' => $t['from'], 'to' => $t['to'],
                    'token' => $t['token_info']['symbol'] ?? '?',
                    'amount' => (float) bcdiv($t['value'], bcpow('10', (string) ($t['token_info']['decimals'] ?? 0)), 6)];
            }
        }

        $trx = $http->get("https://api.trongrid.io/v1/accounts/$address/transactions",
            ['limit' => 50, 'min_timestamp' => $sinceMs + 1, 'only_confirmed' => 'true'])->json('data') ?? [];
        foreach ($trx as $tx) {
            $c = $tx['raw_data']['contract'][0] ?? null;
            // ignore dust spam (< 1 TRX), a common address-poisoning trick
            if (($c['type'] ?? '') === 'TransferContract' && $c['parameter']['value']['amount'] >= 1_000_000) {
                $v = $c['parameter']['value'];
                $out[] = ['tx' => $tx['txID'], 'ms' => $tx['block_timestamp'], 'from' => TronScanner::hexToAddress($v['owner_address']),
                    'to' => TronScanner::hexToAddress($v['to_address']), 'token' => 'TRX', 'amount' => $v['amount'] / 1_000_000];
            }
        }

        usort($out, fn ($a, $b) => $b['ms'] <=> $a['ms']);

        return $out;
    }
}
