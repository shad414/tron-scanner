<?php

namespace App\Services;

use Illuminate\Http\Client\Pool;
use App\Models\FlaggedAddress;
use GuzzleHttp\Middleware;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class TronScanner
{
    private const TRONGRID = 'https://api.trongrid.io';
    private const TRONSCAN = 'https://apilist.tronscanapi.com';
    private const USDT = 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t';
    private const ALPHABET = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';

    /** Tokens valued at ~1 USD, used to rank counterparties by volume. */
    private const STABLES = ['USDT', 'USDC', 'USDD', 'TUSD', 'USDJ'];

    private const TOP_COUNTERPARTIES = 10;

    /** A deposit address sweeping into a wallet holding this much USDT is treated as an exchange deposit. */
    private const HOT_WALLET_USDT = 10_000_000;

    /** Regex on a label → exchange name. */
    private const EXCHANGES = [
        'binance' => 'Binance',
        'okx|okex' => 'OKX',
        'htx|huobi' => 'HTX',
        'bybit' => 'Bybit',
        'kucoin' => 'KuCoin',
        'gate\.?io|^gate' => 'Gate',
        'mexc' => 'MEXC',
        'bitget' => 'Bitget',
        'bingx' => 'BingX',
        'poloniex' => 'Poloniex',
        'kraken' => 'Kraken',
        'bitfinex' => 'Bitfinex',
        'coinbase' => 'Coinbase',
        'crypto\.com' => 'Crypto.com',
        'whitebit' => 'WhiteBIT',
        'hitbtc' => 'HitBTC',
        'coinex' => 'CoinEx',
        'bitmart' => 'BitMart',
        'lbank' => 'LBank',
        'nobitex' => 'Nobitex',
        'garantex' => 'Garantex',
    ];

    /** Transfers listed under "recent transactions" (the page shows 50, then the rest on demand). */
    private const RECENT_LIMIT = 300;

    /** Tronscan pages (50 transfers each) read for counterparty name tags. */
    private const TAG_PAGES = 4;

    /** Token approvals granted by the scanned address (filled while reading transfers). */
    private array $approvals = [];

    /** address => Tronscan tag ('' = seen, no tag), collected from the scanned address's transfers. */
    private array $tags = [];

    /** First-round responses fired in parallel by warmUp(). */
    private array $pre = [];

    public function scan(string $address): array
    {
        $address = trim($address);
        if (! self::isValidAddress($address)) {
            throw new InvalidArgumentException('ئەم ئادرەسە ئادرەسێکی دروستی TRON نییە.');
        }

        return Cache::remember("tron-scan:$address", now()->addMinutes(10), function () use ($address) {
            $this->approvals = [];
            $this->tags = [];
            $this->warmUp($address);
            $account = $this->account($address);
            $transfers = $this->trc20Transfers($address);
            $trxTransfers = $this->trxTransfers($address);
            $all = array_merge($transfers, $trxTransfers);
            usort($all, fn ($a, $b) => strcmp($b['time'], $a['time']));

            $flows = $this->flows($address, $all);
            $counterparties = $this->counterparties($address, $all);
            $this->enrichCounterparties($counterparties);

            $self = $this->identify($address);
            $tag = $self['label'];
            $security = $this->tronscanSecurity($address);
            $usdtBlacklisted = $this->isUsdtBlacklisted($address);

            $result = [
                'address' => $address,
                'scanned_at' => now()->toDateTimeString(),
                'exists' => $account !== null,
                'balance' => $this->balances($account, $transfers),
                'created_at' => isset($account['create_time']) ? $this->time($account['create_time']) : null,
                'last_activity' => isset($account['latest_opration_time']) ? $this->time($account['latest_opration_time']) : null,
                'label' => $tag,
                'exchange' => $self['exchange'],
                'exchange_kind' => $self['kind'],
                'forwards_to' => $self['forwards_to'],
                'security' => $security,
                'usdt_blacklisted' => $usdtBlacklisted,
                'flows' => $flows,
                'senders' => array_values(array_filter($counterparties, fn ($c) => $c['in_count'] > 0)),
                'receivers' => array_values(array_filter($counterparties, fn ($c) => $c['out_count'] > 0)),
                'approvals' => $this->approvals,
                'recent' => array_slice($all, 0, self::RECENT_LIMIT),
                'analyzed' => ['trc20' => count($transfers), 'trx' => count($trxTransfers)],
                'tronscan_enabled' => (bool) config('services.tron.tronscan_key'),
            ];

            $result['senders'] = $this->rank($result['senders'], 'in');
            $result['receivers'] = $this->rank($result['receivers'], 'out');
            $result['exchanges'] = $this->exchangeSummary($result['senders'], $result['receivers']);
            $result['taint'] = $this->taint($result);
            $result['risk'] = $this->risk($result);

            return $result;
        });
    }

    // ---------------------------------------------------------------- data sources

    private function grid()
    {
        $key = config('services.tron.trongrid_key');
        $http = Http::baseUrl(self::TRONGRID)->timeout(20)->acceptJson()
            // TronGrid answers 429 when rate-limited; back off and try again
            ->retry(4, fn ($attempt) => $attempt * 1200, function ($e) {
                return ! $e instanceof RequestException || $e->response->status() === 429 || $e->response->serverError();
            }, throw: false)
            ->withMiddleware(Middleware::mapRequest(function ($request) use ($key) {
                // Without a key TronGrid allows only a few requests per second
                static $last = 0.0;
                $gap = $key ? 0.07 : 1.0;
                $wait = $last + $gap - microtime(true);
                if ($wait > 0) {
                    usleep((int) ($wait * 1_000_000));
                }
                $last = microtime(true);

                return $request;
            }));

        return $key ? $http->withHeaders(['TRON-PRO-API-KEY' => $key]) : $http;
    }

    private function scanApi()
    {
        return Http::baseUrl(self::TRONSCAN)->timeout(20)->acceptJson()
            ->withHeaders(['TRON-PRO-API-KEY' => (string) config('services.tron.tronscan_key')])
            ->retry(4, fn ($attempt) => $attempt * 1000, function ($e) {
                return ! $e instanceof RequestException || $e->response->status() === 429 || $e->response->serverError();
            }, throw: false)
            ->withMiddleware(Middleware::mapRequest(function ($request) {
                // Tronscan allows only a few requests per second per key
                static $last = 0.0;
                $wait = $last + 0.6 - microtime(true);
                if ($wait > 0) {
                    usleep((int) ($wait * 1_000_000));
                }
                $last = microtime(true);

                return $request;
            }));
    }

    /**
     * Fires the independent first-round requests at once: TronGrid account + transfers,
     * and Tronscan's transfer list, which carries the name tag of every counterparty.
     */
    private function warmUp(string $address): void
    {
        $this->pre = [];
        $gridKey = config('services.tron.trongrid_key');
        $scanKey = config('services.tron.tronscan_key');
        $gridHeaders = ['TRON-PRO-API-KEY' => (string) $gridKey];
        $scanHeaders = ['TRON-PRO-API-KEY' => (string) $scanKey];

        if ($gridKey) {
            $this->pre = Http::pool(function ($pool) use ($address, $scanKey, $gridHeaders, $scanHeaders) {
                $grid = fn ($name) => $pool->as($name)->withHeaders($gridHeaders)->timeout(20)->acceptJson();
                $reqs = [
                    $grid('account')->get(self::TRONGRID."/v1/accounts/$address"),
                    $grid('trc20')->get(self::TRONGRID."/v1/accounts/$address/transactions/trc20", ['limit' => 200, 'only_confirmed' => 'true']),
                    $grid('trx')->get(self::TRONGRID."/v1/accounts/$address/transactions", ['limit' => 200, 'only_confirmed' => 'true']),
                    $grid('blacklist')->post(self::TRONGRID.'/wallet/triggerconstantcontract', [
                        'owner_address' => $address,
                        'contract_address' => self::USDT,
                        'function_selector' => 'isBlackListed(address)',
                        'parameter' => str_pad(substr(self::addressToHex($address), 2), 64, '0', STR_PAD_LEFT),
                        'visible' => true,
                    ]),
                ];
                if ($scanKey) {
                    // two Tronscan calls side by side is within its limit; more gets 429
                    $reqs[] = $pool->as('tags')->withHeaders($scanHeaders)->timeout(20)
                        ->get(self::TRONSCAN.'/api/token_trc20/transfers', ['relatedAddress' => $address, 'limit' => 50, 'start' => 0]);
                    $reqs[] = $pool->as('self')->withHeaders($scanHeaders)->timeout(20)
                        ->get(self::TRONSCAN.'/api/accountv2', ['address' => $address]);
                    $reqs[] = $pool->as('security')->withHeaders($scanHeaders)->timeout(20)
                        ->get(self::TRONSCAN.'/api/security/account/data', ['address' => $address]);
                }

                return $reqs;
            });
            if (($bl = $this->pre['blacklist'] ?? null) instanceof Response && $bl->ok()
                && ($hex = $bl->json('constant_result.0')) !== null) {
                Cache::put("tron-usdt-bl:$address", hexdec(substr($hex, -1)) === 1, now()->addHour());
            }
        }

        if (! $scanKey) {
            return;
        }
        if (($self = $this->pre['self'] ?? null) instanceof Response && $self->ok() && is_array($self->json())) {
            Cache::put("tron-tag:$address", $this->parseTag($self->json()), now()->addDay());
        }

        // Up to TAG_PAGES × 50 transfers of counterparty names
        $first = $this->fromPre('tags', fn () => $this->scanApi()
            ->get('/api/token_trc20/transfers', ['relatedAddress' => $address, 'limit' => 50, 'start' => 0]));
        $this->collectTags($first->json() ?? []);
        $total = (int) $first->json('total', 0);
        for ($start = 50; $start < min($total, self::TAG_PAGES * 50); $start += 50) {
            $res = $this->scanApi()->get('/api/token_trc20/transfers', ['relatedAddress' => $address, 'limit' => 50, 'start' => $start]);
            $this->collectTags($res->json() ?? []);
        }
    }

    /** The warm-up response when it succeeded, otherwise a fresh (retrying) request. */
    private function fromPre(string $name, callable $fetch): Response
    {
        $res = $this->pre[$name] ?? null;

        return $res instanceof Response && $res->ok() ? $res : $fetch();
    }

    private function collectTags(array $d): void
    {
        $put = function (?string $address, ?string $name) {
            if ($address && (! isset($this->tags[$address]) || $this->tags[$address] === '')) {
                $this->tags[$address] = trim((string) $name);
            }
        };
        foreach ($d['token_transfers'] ?? [] as $t) {
            $put($t['from_address'] ?? null, $t['from_address_tag']['from_address_tag'] ?? '');
            $put($t['to_address'] ?? null, $t['to_address_tag']['to_address_tag'] ?? '');
        }
        foreach ($d['contractInfo'] ?? [] as $address => $c) {
            $name = ($c['publicTag'] ?? '') ?: (($c['tag1'] ?? '') ?: ($c['name'] ?? ''));
            $put($address, $name === 'CreatedByContract' ? '' : $name);
        }
    }

    private function account(string $address): ?array
    {
        $data = $this->fromPre('account', fn () => $this->grid()->get("/v1/accounts/$address"))->json('data');

        return $data[0] ?? null;
    }

    private function trc20Transfers(string $address): array
    {
        $max = (int) config('services.tron.max_transfers', 1000);
        $out = [];
        $url = "/v1/accounts/$address/transactions/trc20";
        $query = ['limit' => 200, 'only_confirmed' => 'true'];

        while (count($out) < $max) {
            $res = isset($query['fingerprint'])
                ? $this->grid()->get($url, $query)
                : $this->fromPre('trc20', fn () => $this->grid()->get($url, $query));
            if (! $res->ok()) {
                break;
            }
            foreach ($res->json('data', []) as $t) {
                $decimals = (int) ($t['token_info']['decimals'] ?? 0);
                if (($t['type'] ?? 'Transfer') === 'Approval') {
                    if ($t['from'] === $address) {
                        $this->approvals[] = [
                            'spender' => $t['to'],
                            'token' => $t['token_info']['symbol'] ?? '?',
                            'unlimited' => strlen($t['value'] ?? '') > 30,
                            'time' => $this->time($t['block_timestamp']),
                        ];
                    }

                    continue;
                }
                $out[] = [
                    'tx' => $t['transaction_id'],
                    'time' => $this->time($t['block_timestamp']),
                    'from' => $t['from'],
                    'to' => $t['to'],
                    'token' => $t['token_info']['symbol'] ?? '?',
                    'contract' => $t['token_info']['address'] ?? null,
                    'amount' => $this->units($t['value'] ?? '0', $decimals),
                ];
            }
            $fingerprint = $res->json('meta.fingerprint');
            if (! $fingerprint) {
                break;
            }
            $query['fingerprint'] = $fingerprint;
        }

        return array_slice($out, 0, $max);
    }

    private function trxTransfers(string $address): array
    {
        $res = $this->fromPre('trx', fn () => $this->grid()
            ->get("/v1/accounts/$address/transactions", ['limit' => 200, 'only_confirmed' => 'true']));
        $out = [];
        foreach ($res->json('data', []) ?? [] as $tx) {
            $c = $tx['raw_data']['contract'][0] ?? null;
            if (($c['type'] ?? null) !== 'TransferContract') {
                continue;
            }
            $v = $c['parameter']['value'];
            $out[] = [
                'tx' => $tx['txID'],
                'time' => $this->time($tx['block_timestamp'] ?? $tx['raw_data']['timestamp']),
                'from' => self::hexToAddress($v['owner_address']),
                'to' => self::hexToAddress($v['to_address']),
                'token' => 'TRX',
                'contract' => null,
                'amount' => $v['amount'] / 1_000_000,
            ];
        }

        return $out;
    }

    /** Label from config/tron_labels.php, then Tronscan (full account data, or tags seen in transfers). */
    private function label(string $address): ?array
    {
        $simple = fn ($name) => ['name' => $name ?: null, 'logo' => null, 'red' => null, 'grey' => null, 'blue' => null,
            'risk' => false, 'tx_count' => null, 'is_contract' => false];

        if ($name = config("tron_labels.$address")) {
            return $simple($name);
        }
        if (Cache::has("tron-tag:$address")) {
            return Cache::get("tron-tag:$address");
        }
        if (array_key_exists($address, $this->tags)) {
            return $simple($this->tags[$address]);
        }

        return $this->tronscanAccount($address);
    }

    public static function exchangeFromName(?string $name): ?string
    {
        if (! $name) {
            return null;
        }
        foreach (self::EXCHANGES as $pattern => $exchange) {
            if (preg_match("/$pattern/i", $name)) {
                return $exchange;
            }
        }

        return null;
    }

    /**
     * Exchange deposit addresses sweep their funds into the exchange hot wallet.
     * Returns the address that receives most of this address's outgoing transfers.
     */
    private function sweepTarget(string $address): ?string
    {
        return $this->dominantDestination($address, $this->cpTransfers($address)) ?: null;
    }

    /** Latest 50 TRC20 transfers of a counterparty, compacted — used for sweeps and the second hop. */
    private function cpTransfers(string $address): array
    {
        return Cache::remember("tron-cptx:$address", now()->addDay(), fn () => $this->compact(
            $this->grid()->get("/v1/accounts/$address/transactions/trc20", ['limit' => 50, 'only_confirmed' => 'true'])->json('data') ?? []
        ));
    }

    private function compact(array $data): array
    {
        $out = [];
        foreach ($data as $t) {
            if (($t['type'] ?? '') === 'Transfer') {
                $out[] = ['f' => $t['from'], 't' => $t['to'], 's' => $t['token_info']['symbol'] ?? '?',
                    'v' => $this->units($t['value'] ?? '0', (int) ($t['token_info']['decimals'] ?? 0))];
            }
        }

        return $out;
    }

    private function usdtBalance(string $address): float
    {
        return Cache::remember("tron-usdt-bal:$address", now()->addHours(6), function () use ($address) {
            $rows = $this->grid()->get("/v1/accounts/$address")->json('data.0.trc20') ?? [];
            foreach ($rows as $row) {
                if (isset($row[self::USDT])) {
                    return $this->units($row[self::USDT], 6);
                }
            }

            return 0.0;
        });
    }

    /**
     * Warms the per-address caches (blacklist, sweeps, balances) with parallel requests,
     * so identify() and friends only read from cache. Needs a TronGrid key: keyless TronGrid rejects bursts.
     */
    private function prefetchCounterparties(array $addresses): void
    {
        $grid = ['TRON-PRO-API-KEY' => (string) config('services.tron.trongrid_key')];
        $unlabeled = array_filter($addresses, fn ($a) => ! self::exchangeFromName($this->label($a)['name'] ?? null));

        // Round 1: blacklist + sweep pattern of every counterparty, all at once
        $this->runJobs(array_merge(
            $this->jobs($addresses, 'tron-usdt-bl', now()->addHour(),
                fn ($p, $a) => $p->withHeaders($grid)->post(self::TRONGRID.'/wallet/triggerconstantcontract', [
                    'owner_address' => $a,
                    'contract_address' => self::USDT,
                    'function_selector' => 'isBlackListed(address)',
                    'parameter' => str_pad(substr(self::addressToHex($a), 2), 64, '0', STR_PAD_LEFT),
                    'visible' => true,
                ]),
                fn ($res) => ($hex = $res->json('constant_result.0')) === null ? null : hexdec(substr($hex, -1)) === 1),
            $this->jobs($unlabeled, 'tron-cptx', now()->addDay(),
                fn ($p, $a) => $p->withHeaders($grid)->get(self::TRONGRID."/v1/accounts/$a/transactions/trc20", ['limit' => 50, 'only_confirmed' => 'true']),
                fn ($res) => $this->compact($res->json('data') ?? [])),
        ));

        // Round 2: USDT holdings of the wallets the deposit addresses sweep into
        $targets = array_unique(array_filter(array_map(fn ($a) => Cache::has("tron-cptx:$a") ? $this->sweepTarget($a) : null, $unlabeled)));
        $this->runJobs($this->jobs($targets, 'tron-usdt-bal', now()->addHours(6),
            fn ($p, $a) => $p->withHeaders($grid)->get(self::TRONGRID."/v1/accounts/$a"),
            function ($res) {
                foreach ($res->json('data.0.trc20') ?? [] as $row) {
                    if (isset($row[self::USDT])) {
                        return $this->units($row[self::USDT], 6);
                    }
                }

                return 0.0;
            }));
    }

    /** One cache-warming job per uncached address. */
    private function jobs(array $addresses, string $prefix, $ttl, callable $request, callable $parse): array
    {
        $jobs = [];
        foreach ($addresses as $a) {
            if (! Cache::has("$prefix:$a")) {
                $jobs["$prefix:$a"] = ['ttl' => $ttl, 'request' => fn ($p) => $request($p, $a), 'parse' => fn ($res) => $parse($res, $a)];
            }
        }

        return $jobs;
    }

    /**
     * Runs jobs in parallel batches and caches each parsed response.
     * TronGrid allows ~15 req/s per key (bursts above that get 429/403), so one batch per second;
     * slow or failed requests are cut at 4s and go into the next batch instead of holding this one.
     */
    private function runJobs(array $jobs, int $batch = 10): void
    {
        static $lastBatch = 0.0;

        for ($attempt = 0; $jobs && $attempt < 3; $attempt++) {
            $queue = array_chunk($jobs, $batch, true);
            $jobs = [];
            while ($chunk = array_shift($queue)) {
                $wait = $lastBatch + 1.0 - microtime(true);
                if ($wait > 0) {
                    usleep((int) ($wait * 1_000_000));
                }
                $lastBatch = microtime(true);

                $responses = Http::pool(fn ($pool) => array_map(
                    fn ($key) => $chunk[$key]['request']($pool->as($key)->connectTimeout(3)->timeout(4)->acceptJson()),
                    array_keys($chunk)
                ));
                foreach ($chunk as $key => $job) {
                    $res = $responses[$key] ?? null;
                    if ($res instanceof Response && $res->ok()) {
                        Cache::put($key, $job['parse']($res), $job['ttl']);
                    } else {
                        $jobs[$key] = $job; // retried next round; still-missing ones fall back to the getters
                    }
                }
            }
        }
    }

    private function dominantDestination(string $address, array $transfers): string
    {
        $out = array_filter($transfers, fn ($t) => $t['f'] === $address);
        if (! $out) {
            return '';
        }
        $counts = array_count_values(array_column($out, 't'));
        arsort($counts);
        $top = array_key_first($counts);

        return $counts[$top] / count($out) >= 0.6 ? $top : '';
    }

    /** Works out whether an address belongs to an exchange: its own label, or where it sweeps funds. */
    private function identify(string $address): array
    {
        $label = $this->label($address);
        if ($exchange = self::exchangeFromName($label['name'] ?? null)) {
            return ['label' => $label, 'exchange' => $exchange, 'kind' => 'hot', 'forwards_to' => null];
        }

        $target = $this->sweepTarget($address);
        if ($target) {
            $targetLabel = $this->label($target);
            if ($exchange = self::exchangeFromName($targetLabel['name'] ?? null)) {
                return ['label' => $label, 'exchange' => $exchange, 'kind' => 'deposit', 'forwards_to' => $target];
            }
            // Unlabeled, but only exchanges/custodians sit on this much USDT
            if (! ($targetLabel['name'] ?? null) && $this->usdtBalance($target) >= self::HOT_WALLET_USDT) {
                return ['label' => $label, 'exchange' => 'ئێکسچەینجی نەناسراو', 'kind' => 'deposit', 'forwards_to' => $target];
            }
        }

        return ['label' => $label, 'exchange' => null, 'kind' => null, 'forwards_to' => $target];
    }

    /** Tronscan label (exchange / wallet name) — needs TRONSCAN_API_KEY. */
    private function tronscanAccount(string $address): ?array
    {
        if (! config('services.tron.tronscan_key')) {
            return null;
        }

        return Cache::remember("tron-tag:$address", now()->addDay(), function () use ($address) {
            $d = $this->scanApi()->get('/api/accountv2', ['address' => $address])->json();

            return is_array($d) ? $this->parseTag($d) : null;
        });
    }

    private function parseTag(array $d): array
    {
        return [
            'name' => ($d['addressTag'] ?? '') ?: (($d['publicTag'] ?? '') ?: null),
            'logo' => $d['addressTagLogo'] ?? null,
            'red' => $d['redTag'] ?? null,
            'grey' => $d['greyTag'] ?? null,
            'blue' => $d['blueTag'] ?? null,
            'risk' => (bool) ($d['feedbackRisk'] ?? false),
            'tx_count' => $d['totalTransactionCount'] ?? null,
            'is_contract' => ($d['accountType'] ?? 0) == 2,
        ];
    }

    private function tronscanSecurity(string $address): ?array
    {
        if (! config('services.tron.tronscan_key')) {
            return null;
        }
        $d = $this->fromPre('security', fn () => $this->scanApi()
            ->get('/api/security/account/data', ['address' => $address]))->json();
        if (! is_array($d)) {
            return null;
        }

        return [
            'blacklist' => (bool) ($d['is_black_list'] ?? false),
            'fraud_tx' => (bool) ($d['has_fraud_transaction'] ?? false),
            'fraud_token_creator' => (bool) ($d['fraud_token_creator'] ?? false),
            'spam_memo' => (bool) ($d['send_ad_by_memo'] ?? false),
        ];
    }

    /** Live (uncached) Tether blacklist check, for alerts. */
    public function usdtBlacklistedNow(string $address): ?bool
    {
        Cache::forget("tron-usdt-bl:$address");

        return $this->isUsdtBlacklisted($address);
    }

    /** Calls USDT contract isBlackListed(address) — Tether's freeze list. */
    private function isUsdtBlacklisted(string $address): ?bool
    {
        return $this->usdtBlacklistMany([$address])[$address] ?? null;
    }

    private function usdtBlacklistMany(array $addresses): array
    {
        $out = [];
        foreach ($addresses as $a) {
            // Sequential + retry: TronGrid without a key rejects bursts with 429
            $out[$a] = Cache::remember("tron-usdt-bl:$a", now()->addHour(), function () use ($a) {
                try {
                    $hex = $this->grid()
                        ->post('/wallet/triggerconstantcontract', [
                            'owner_address' => $a,
                            'contract_address' => self::USDT,
                            'function_selector' => 'isBlackListed(address)',
                            'parameter' => str_pad(substr(self::addressToHex($a), 2), 64, '0', STR_PAD_LEFT),
                            'visible' => true,
                        ])->json('constant_result.0');
                } catch (\Throwable) {
                    $hex = null;
                }

                return $hex === null ? null : hexdec(substr($hex, -1)) === 1;
            });
        }

        return $out;
    }

    // ---------------------------------------------------------------- analysis

    private function balances(?array $account, array $transfers): array
    {
        $known = [self::USDT => ['USDT', 6]];
        foreach ($transfers as $t) {
            if ($t['contract'] && ! isset($known[$t['contract']])) {
                $known[$t['contract']] = [$t['token'], null];
            }
        }

        $tokens = [];
        foreach ($account['trc20'] ?? [] as $row) {
            foreach ($row as $contract => $raw) {
                [$symbol, $decimals] = $known[$contract] ?? [null, null];
                if ($symbol === null) {
                    continue; // unknown/airdropped spam tokens
                }
                $decimals ??= $this->decimals($contract);
                $tokens[] = ['token' => $symbol, 'contract' => $contract, 'amount' => $this->units($raw, $decimals)];
            }
        }
        usort($tokens, fn ($a, $b) => $b['amount'] <=> $a['amount']);

        $frozen = 0;
        foreach ($account['frozenV2'] ?? [] as $f) {
            $frozen += $f['amount'] ?? 0;
        }

        return [
            'trx' => ($account['balance'] ?? 0) / 1_000_000,
            'trx_staked' => $frozen / 1_000_000,
            'tokens' => $tokens,
        ];
    }

    private function decimals(string $contract): int
    {
        return Cache::rememberForever("tron-decimals:$contract", function () use ($contract) {
            $hex = $this->grid()->post('/wallet/triggerconstantcontract', [
                'owner_address' => self::USDT,
                'contract_address' => $contract,
                'function_selector' => 'decimals()',
                'visible' => true,
            ])->json('constant_result.0');

            return $hex ? hexdec($hex) : 0;
        });
    }

    private function flows(string $address, array $transfers): array
    {
        $flows = [];
        foreach ($transfers as $t) {
            $f = &$flows[$t['token']];
            $f ??= ['token' => $t['token'], 'in' => 0, 'out' => 0, 'in_count' => 0, 'out_count' => 0];
            if ($t['to'] === $address) {
                $f['in'] += $t['amount'];
                $f['in_count']++;
            } else {
                $f['out'] += $t['amount'];
                $f['out_count']++;
            }
            unset($f);
        }
        uasort($flows, fn ($a, $b) => ($b['in'] + $b['out']) <=> ($a['in'] + $a['out']));

        return array_values($flows);
    }

    private function counterparties(string $address, array $transfers): array
    {
        $map = [];
        foreach ($transfers as $t) {
            $incoming = $t['to'] === $address;
            $other = $incoming ? $t['from'] : $t['to'];
            if ($other === $address) {
                continue;
            }
            $c = &$map[$other];
            $c ??= ['address' => $other, 'in_count' => 0, 'out_count' => 0, 'in' => [], 'out' => [],
                'in_usd' => 0, 'out_usd' => 0, 'last' => $t['time']];
            $dir = $incoming ? 'in' : 'out';
            $c[$dir.'_count']++;
            $c[$dir][$t['token']] = ($c[$dir][$t['token']] ?? 0) + $t['amount'];
            if (in_array($t['token'], self::STABLES, true)) {
                $c[$dir.'_usd'] += $t['amount'];
            }
            unset($c);
        }

        return $map;
    }

    private function rank(array $list, string $dir): array
    {
        usort($list, fn ($a, $b) => [$b[$dir.'_usd'], $b[$dir.'_count']] <=> [$a[$dir.'_usd'], $a[$dir.'_count']]);

        return array_slice($list, 0, self::TOP_COUNTERPARTIES);
    }

    /** Adds labels + USDT blacklist flag to the top senders/receivers. */
    private function enrichCounterparties(array &$map): void
    {
        $top = array_unique(array_merge(
            array_column($this->rank(array_filter($map, fn ($c) => $c['in_count']), 'in'), 'address'),
            array_column($this->rank(array_filter($map, fn ($c) => $c['out_count']), 'out'), 'address'),
        ));
        if (! $top) {
            return;
        }

        if (config('services.tron.trongrid_key')) {
            $this->prefetchCounterparties($top); // parallel only when the key allows bursts
        }
        $black = $this->usdtBlacklistMany($top);
        foreach ($top as $a) {
            $id = $this->identify($a);
            $map[$a]['blacklisted'] = $black[$a] ?? null;
            $map[$a]['label'] = $id['label'];
            $map[$a]['exchange'] = $id['exchange'];
            $map[$a]['exchange_kind'] = $id['kind'];
            $map[$a]['forwards_to'] = $id['forwards_to'];
        }
    }

    /**
     * Source-of-funds / exposure analysis over two hops, checked against flagged_addresses
     * (Tether freezes, OFAC, your list) plus the live USDT blacklist.
     * Hop 2 reuses the counterparties' cached transfers, so it costs no extra requests.
     * Exchanges break the trail: their pooled funds are not attributed to any single source.
     * Adds 'flags' to the result and to each counterparty.
     */
    private function taint(array &$r): array
    {
        $self = $r['address'];
        $hop1 = array_unique(array_merge(array_column($r['senders'], 'address'), array_column($r['receivers'], 'address')));
        $all = [$self, ...$hop1];
        foreach ($hop1 as $a) {
            foreach (Cache::get("tron-cptx:$a") ?? [] as $t) {
                $all[] = $t['f'];
                $all[] = $t['t'];
            }
        }
        $flags = FlaggedAddress::lookup($all);

        $r['flags'] = $flags[$self] ?? [];
        if ($r['usdt_blacklisted']) {
            $r['flags'] = array_values(array_unique([...$r['flags'], 'tether']));
        }
        foreach (['senders', 'receivers'] as $k) {
            foreach ($r[$k] as &$c) {
                $c['flags'] = $flags[$c['address']] ?? [];
                if ($c['blacklisted'] ?? false) {
                    $c['flags'] = array_values(array_unique([...$c['flags'], 'tether']));
                }
            }
            unset($c);
        }

        $side = function (string $dir) use ($r, $flags, $self) {
            $list = $dir === 'in' ? $r['senders'] : $r['receivers'];
            $total = collect($r['flows'])->whereIn('token', self::STABLES)->sum($dir);
            if ($total <= 0) {
                return ['percent' => 0, 'amount' => 0, 'paths' => [], 'coverage' => 0];
            }

            $tainted = 0;
            $covered = 0;
            $paths = [];
            foreach ($list as $c) {
                $amount = $c[$dir.'_usd'];
                if ($amount <= 0) {
                    continue;
                }
                $covered += $amount;

                if ($c['flags']) {
                    $tainted += $amount;
                    $paths[] = ['hops' => [$c['address']], 'amount' => $amount, 'sources' => $c['flags']];

                    continue;
                }
                if ($c['exchange'] ?? null) {
                    continue;
                }

                // Hop 2: where this counterparty's own stablecoins came from / went to
                $legs = array_filter(Cache::get("tron-cptx:{$c['address']}") ?? [], function ($t) use ($dir, $c, $self) {
                    $other = $dir === 'in' ? $t['f'] : $t['t'];

                    return in_array($t['s'], self::STABLES, true) && $other !== $self
                        && ($dir === 'in' ? $t['t'] : $t['f']) === $c['address'];
                });
                $sum = array_sum(array_column($legs, 'v'));
                if ($sum <= 0) {
                    continue;
                }
                $bad = [];
                foreach ($legs as $t) {
                    $other = $dir === 'in' ? $t['f'] : $t['t'];
                    if (isset($flags[$other])) {
                        $bad[$other] = ($bad[$other] ?? 0) + $t['v'];
                    }
                }
                foreach ($bad as $other => $v) {
                    $share = $amount * $v / $sum;
                    $tainted += $share;
                    $paths[] = ['hops' => $dir === 'in' ? [$other, $c['address']] : [$c['address'], $other],
                        'amount' => $share, 'sources' => $flags[$other]];
                }
            }
            usort($paths, fn ($a, $b) => $b['amount'] <=> $a['amount']);

            return [
                'percent' => round(100 * min($tainted, $total) / $total, 2),
                'amount' => $tainted,
                'paths' => array_slice($paths, 0, 10),
                'coverage' => (int) round(100 * min($covered, $total) / $total),
            ];
        };

        return ['in' => $side('in'), 'out' => $side('out')];
    }

    /** Stablecoin volume received from / sent to each exchange. */
    private function exchangeSummary(array $senders, array $receivers): array
    {
        $sum = [];
        foreach ([['in', $senders], ['out', $receivers]] as [$dir, $list]) {
            foreach ($list as $c) {
                if ($ex = $c['exchange'] ?? null) {
                    $sum[$ex] ??= ['exchange' => $ex, 'in' => 0, 'out' => 0];
                    $sum[$ex][$dir] += $c[$dir.'_usd'];
                }
            }
        }
        usort($sum, fn ($a, $b) => ($b['in'] + $b['out']) <=> ($a['in'] + $a['out']));

        return $sum;
    }

    private function risk(array $r): array
    {
        $reasons = [];
        $level = 0; // 0 clean, 1 caution, 2 high

        if ($r['usdt_blacklisted']) {
            $level = 2;
            $reasons[] = 'ئەم ئادرەسە لە لایەن Tether ەوە بلاک‌لیست کراوە (USDT ی فریز کراوە).';
        }
        if ($r['security']['blacklist'] ?? false) {
            $level = 2;
            $reasons[] = 'Tronscan ئەم ئادرەسەی خستووەتە لیستی ڕەشەوە.';
        }
        if (($r['security']['fraud_tx'] ?? false) || ($r['security']['fraud_token_creator'] ?? false)) {
            $level = 2;
            $reasons[] = 'Tronscan مامەڵەی فێڵ‌بازی (fraud) لەم ئادرەسەدا دۆزیوەتەوە.';
        }
        if (! empty($r['label']['red'])) {
            $level = 2;
            $reasons[] = 'نیشانەی سووری Tronscan: '.$r['label']['red'];
        }
        if (($r['label']['risk'] ?? false) || ! empty($r['label']['grey'])) {
            $level = max($level, 1);
            $reasons[] = 'بەکارهێنەران ئەم ئادرەسەیان وەک مەترسیدار ڕاپۆرت کردووە.';
        }
        if ($r['security']['spam_memo'] ?? false) {
            $level = max($level, 1);
            $reasons[] = 'ئەم ئادرەسە ڕیکلامی سپام لە memo دا دەنێرێت.';
        }

        if (in_array('ofac', $r['flags'], true)) {
            $level = 2;
            $reasons[] = 'ئەم ئادرەسە لە لیستی سزاکانی OFAC (ئەمریکا) دایە.';
        }
        if (in_array('manual', $r['flags'], true)) {
            $level = 2;
            $reasons[] = 'ئەم ئادرەسە لە لیستی خۆتدا نیشانە کراوە.';
        }

        // Source of funds / exposure, up to two hops away
        $in = $r['taint']['in'];
        $out = $r['taint']['out'];
        if ($in['percent'] >= 10) {
            $level = 2;
        } elseif ($in['percent'] > 0) {
            $level = max($level, 1);
        }
        if ($in['percent'] > 0) {
            $reasons[] = $in['percent'].'٪ ی پارە هاتووەکان ('.number_format($in['amount']).' USDT) لە سەرچاوەی پیس (بلاک‌لیست / سزادراو) ەوە هاتووە، تا ٢ هەنگاو دوور.';
        }
        if ($out['percent'] > 0) {
            $level = max($level, $out['percent'] >= 10 ? 2 : 1);
            $reasons[] = $out['percent'].'٪ ی پارە ڕۆیشتووەکان ('.number_format($out['amount']).' USDT) گەیشتووە بە ئادرەسی پیس، تا ٢ هەنگاو دوور.';
        }

        // Informational notes — worth knowing but not proof of anything
        $notes = [];
        if (! $r['exists']) {
            $notes[] = 'ئەم ئادرەسە هێشتا لەسەر بلۆکچەین چالاک نەکراوە.';
        }
        $unlimited = array_filter($r['approvals'], fn ($a) => $a['unlimited']);
        if ($unlimited) {
            $notes[] = 'ڕێگەپێدانی (approve) بێسنووری داوە بە '.count($unlimited).' ئادرەس — ئەگەر نایناسیت، دەتوانرێت پارەکەی ببرێت.';
        }
        $usdt = collect($r['flows'])->firstWhere('token', 'USDT');
        $usdtBalance = collect($r['balance']['tokens'])->firstWhere('token', 'USDT')['amount'] ?? 0;
        if ($usdt && $usdt['in'] >= 10_000 && $usdtBalance < $usdt['in'] * 0.02) {
            $notes[] = 'والێتی تێپەڕاندنە: '.number_format($usdt['in']).' USDT هاتووە و نزیکەی هەمووی ڕۆیشتووە.';
        }
        if ($r['created_at'] && strtotime($r['created_at']) > strtotime('-30 days') && ($usdt['in'] ?? 0) >= 100_000) {
            $notes[] = 'ئەکاونتێکی نوێیە (کەمتر لە ٣٠ ڕۆژ) بەڵام بڕێکی گەورەی پارەی پێدا تێپەڕیوە.';
        }

        return [
            'level' => $level,
            'text' => ['پاکە — هیچ نیشانەیەکی مەترسی نەدۆزرایەوە', 'ئاگاداربە — نیشانەی گومانلێکراو هەیە', 'پیسە / مەترسیدارە'][$level],
            'reasons' => $reasons,
            'notes' => $notes,
        ];
    }

    // ---------------------------------------------------------------- helpers

    private function units(string|int $raw, int $decimals): float
    {
        return (float) bcdiv((string) $raw, bcpow('10', (string) $decimals), $decimals ?: 0);
    }

    private function time(int $ms): string
    {
        return date('Y-m-d H:i', intdiv($ms, 1000));
    }

    public static function isValidAddress(string $address): bool
    {
        if (! preg_match('/^T[1-9A-HJ-NP-Za-km-z]{33}$/', $address)) {
            return false;
        }
        $bytes = self::base58Decode($address);
        if (strlen($bytes) !== 25 || $bytes[0] !== "\x41") {
            return false;
        }

        return substr(hash('sha256', hash('sha256', substr($bytes, 0, 21), true), true), 0, 4) === substr($bytes, 21);
    }

    /** Base58 address → 21-byte hex (41…). */
    public static function addressToHex(string $address): string
    {
        return bin2hex(substr(self::base58Decode($address), 0, 21));
    }

    public static function hexToAddress(string $hex): string
    {
        $bytes = hex2bin($hex);
        $bytes .= substr(hash('sha256', hash('sha256', $bytes, true), true), 0, 4);
        $num = gmp_import($bytes);
        $out = '';
        while (gmp_cmp($num, 0) > 0) {
            [$num, $rem] = gmp_div_qr($num, 58);
            $out = self::ALPHABET[gmp_intval($rem)].$out;
        }

        return $out;
    }

    private static function base58Decode(string $s): string
    {
        $num = gmp_init(0);
        foreach (str_split($s) as $ch) {
            $num = gmp_add(gmp_mul($num, 58), strpos(self::ALPHABET, $ch));
        }

        return gmp_export($num);
    }
}
