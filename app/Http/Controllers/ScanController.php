<?php

namespace App\Http\Controllers;

use App\Models\Scan;
use App\Models\Watch;
use App\Services\Telegram;
use App\Services\TronScanner;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use InvalidArgumentException;

class ScanController extends Controller
{
    public function index(Request $request, TronScanner $scanner)
    {
        $address = trim((string) $request->query('address'));
        $result = null;
        $error = null;
        $scan = null;

        if ($address !== '') {
            try {
                $result = $scanner->scan($address);
                $scan = $this->remember($result, $request->ip());
            } catch (InvalidArgumentException $e) {
                $error = $e->getMessage();
            } catch (ConnectionException) {
                $error = 'پەیوەندی بە TronGrid ەوە نەکرا، تکایە دووبارە هەوڵ بدەرەوە.';
            }
        }

        return view('scan', compact('address', 'result', 'error', 'scan') + ['watches' => $this->watchesFor($address)]);
    }

    /** A saved scan, exactly as it was at the time (also the printable report). */
    public function show(Scan $scan)
    {
        return view('scan', [
            'address' => $scan->address,
            'result' => $scan->result,
            'error' => null,
            'scan' => $scan,
            'watches' => $this->watchesFor($scan->address),
        ]);
    }

    public function history(Request $request)
    {
        $q = trim((string) $request->query('q'));
        $scans = Scan::query()
            ->when($q !== '', fn ($query) => $query->where('address', 'like', "%$q%"))
            ->latest()
            ->paginate(30, ['id', 'address', 'risk_level', 'usdt_balance', 'taint_percent', 'exchanges', 'created_at'])
            ->withQueryString();

        return view('history', compact('scans', 'q'));
    }

    public function watches()
    {
        return view('watches', ['watches' => Watch::latest()->get(), 'telegram' => Telegram::configured()]);
    }

    public function watch(Request $request)
    {
        $data = $request->validate([
            'address' => ['required', 'string', fn ($a, $v, $fail) => TronScanner::isValidAddress($v) ?: $fail('ئادرەسەکە دروست نییە.')],
            'chat_id' => ['required', 'regex:/^-?\d{3,20}$/'],
            'label' => ['nullable', 'string', 'max:100'],
        ], ['chat_id.regex' => 'Chat ID دەبێت ژمارە بێت.']);

        // Start from "now" so existing history doesn't trigger a flood of alerts
        Watch::updateOrCreate(
            ['address' => $data['address'], 'chat_id' => $data['chat_id']],
            ['label' => $data['label'] ?? null, 'last_seen_ms' => (int) (microtime(true) * 1000)]
        );

        return back()->with('status', 'چاودێری زیاد کرا. هەر جووڵەیەک ڕوو بدات، نامەت بۆ دێت.');
    }

    public function unwatch(Watch $watch)
    {
        $watch->delete();

        return back()->with('status', 'چاودێری لابرا.');
    }

    public function testTelegram(Request $request, Telegram $telegram)
    {
        $request->validate(['chat_id' => ['required', 'regex:/^-?\d{3,20}$/']]);
        $ok = $telegram->send($request->input('chat_id'), '✅ پەیوەندی لەگەڵ پشکنەری TRON کار دەکات.');

        return back()->with('status', $ok ? 'نامەی تاقیکردنەوە نێردرا.' : 'نامەکە نەنێردرا — TELEGRAM_BOT_TOKEN و Chat ID بپشکنە، و دڵنیابە کە /start ت بۆ بۆتەکە ناردووە.');
    }

    /** Saves a scan to history, once per address per 10 minutes (scan results are cached that long). */
    private function remember(array $result, ?string $ip): Scan
    {
        $recent = Scan::where('address', $result['address'])->where('created_at', '>=', now()->subMinutes(10))->latest()->first();
        if ($recent) {
            return $recent;
        }
        $usdt = collect($result['balance']['tokens'])->firstWhere('token', 'USDT')['amount'] ?? 0;

        return Scan::create([
            'address' => $result['address'],
            'risk_level' => $result['risk']['level'],
            'usdt_balance' => $usdt,
            'taint_percent' => $result['taint']['in']['percent'] ?? 0,
            'exchanges' => collect($result['exchanges'] ?? [])->pluck('exchange')->implode(', ') ?: null,
            'result' => $result,
            'ip' => $ip,
        ]);
    }

    private function watchesFor(string $address)
    {
        return $address === '' ? collect() : Watch::where('address', $address)->get();
    }
}
