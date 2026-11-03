<?php

namespace Tests\Feature;

use App\Models\FlaggedAddress;
use App\Models\Scan;
use App\Models\Watch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ScannerTest extends TestCase
{
    use RefreshDatabase;

    private const SELF = 'TXTZYpwrm5LUkLtTryDKg6c5F44rV2rwgU';
    private const MIDDLE = 'TJjg5gxF1k3gTaHSaUAKuKiyRyyPtpY8oJ';
    private const DIRTY = 'TU8ZiF5GWYP63SoqXF3VZRJg7dkEXtMoqk';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.tron.trongrid_key' => 'test-grid',
            'services.tron.tronscan_key' => 'test-scan',
            'services.tron.password' => null,
        ]);
    }

    /** DIRTY → MIDDLE → SELF: the scanned wallet's funds come from a blacklisted address two hops back. */
    private function fakeChain(int $ts = 1793600000000): void
    {
        $usdt = fn ($from, $to, $amount, $ts) => [
            'transaction_id' => md5($from.$to.$ts), 'block_timestamp' => $ts, 'from' => $from, 'to' => $to, 'type' => 'Transfer',
            'value' => (string) ($amount * 1_000_000),
            'token_info' => ['symbol' => 'USDT', 'address' => 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t', 'decimals' => 6, 'name' => 'Tether USD'],
        ];

        Http::fake([
            'api.trongrid.io/v1/accounts/'.self::SELF.'/transactions/trc20*' => Http::response(['data' => [$usdt(self::MIDDLE, self::SELF, 1000, $ts)], 'meta' => []]),
            'api.trongrid.io/v1/accounts/'.self::MIDDLE.'/transactions/trc20*' => Http::response(['data' => [
                $usdt(self::MIDDLE, self::SELF, 1000, $ts), $usdt(self::DIRTY, self::MIDDLE, 1000, $ts - 1000),
            ], 'meta' => []]),
            'api.trongrid.io/v1/accounts/*/transactions*' => Http::response(['data' => [], 'meta' => []]),
            'api.trongrid.io/v1/accounts/*' => Http::response(['data' => [[
                'balance' => 5_000_000, 'create_time' => $ts - 86_400_000, 'latest_opration_time' => $ts,
                'trc20' => [['TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t' => '1000000000']],
            ]]]),
            'api.trongrid.io/wallet/triggerconstantcontract' => Http::response(['constant_result' => [str_repeat('0', 64)]]),
            'apilist.tronscanapi.com/api/token_trc20/transfers*' => Http::response(['total' => 0, 'token_transfers' => []]),
            'apilist.tronscanapi.com/*' => Http::response([]),
            'api.telegram.org/*' => Http::response(['ok' => true]),
        ]);
    }

    public function test_two_hop_taint_marks_the_wallet_dirty_and_saves_history(): void
    {
        FlaggedAddress::create(['address' => self::DIRTY, 'source' => 'tether']);
        $this->fakeChain();

        $this->get('/?address='.self::SELF)
            ->assertOk()
            ->assertSee('پیسە')
            ->assertSee('100٪', false);

        $scan = Scan::sole();
        $this->assertSame(2, $scan->risk_level);
        $this->assertEquals(100, $scan->taint_percent);
        $this->assertSame([self::DIRTY, self::MIDDLE], $scan->result['taint']['in']['paths'][0]['hops']);

        $this->get('/history')->assertOk()->assertSee(self::SELF);
        $this->get('/history/'.$scan->id)->assertOk()->assertSee('100٪', false);
    }

    public function test_clean_chain_stays_clean(): void
    {
        $this->fakeChain();

        $this->get('/?address='.self::SELF)->assertOk()->assertSee('پاکە');
        $this->assertSame(0, Scan::sole()->risk_level);
    }

    public function test_invalid_address_shows_an_error(): void
    {
        $this->get('/?address=Tnotanaddress')->assertOk()->assertSee('ئادرەسێکی دروستی TRON نییە');
    }

    public function test_scans_are_rate_limited_per_visitor(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->get('/?address=Tbad'.$i)->assertOk();
        }
        $this->get('/?address=Tbad')->assertStatus(429)->assertSee('سکانی زۆرت کردووە');
        $this->get('/')->assertOk(); // the empty page is never limited
    }

    public function test_password_gate(): void
    {
        config(['services.tron.password' => 'secret']);

        $this->get('/history')->assertRedirect('/login');
        $this->post('/login', ['password' => 'wrong'])->assertSessionHasErrors('password');
        $this->post('/login', ['password' => 'secret'])->assertRedirect();
        $this->get('/history')->assertOk();
    }

    public function test_admin_pages_need_admin_password_but_scanning_is_public(): void
    {
        config(['services.tron.admin_password' => 'boss']);

        $this->get('/?address=Tbad')->assertOk();
        $this->get('/history')->assertRedirect('/login?admin=1');
        $this->get('/watches')->assertRedirect('/login?admin=1');
        $this->post('/watches', ['address' => self::SELF, 'chat_id' => '123456'])->assertRedirect('/login?admin=1');
        $this->assertSame(0, Watch::count());

        $this->post('/login', ['password' => 'boss'])->assertRedirect();
        $this->get('/history')->assertOk();
    }

    public function test_watch_sends_telegram_alert_for_new_transfer(): void
    {
        config(['services.telegram.bot_token' => 'bot-token']);
        $this->fakeChain(ts: (int) (microtime(true) * 1000) + 60_000); // a transfer after the watch was created

        $this->post('/watches', ['address' => self::SELF, 'chat_id' => '123456', 'label' => 'کڕیار'])->assertRedirect();
        $this->assertSame(1, Watch::count());

        $this->artisan('tron:watch')->assertSuccessful();

        Http::assertSent(fn ($req) => str_contains($req->url(), 'api.telegram.org')
            && $req['chat_id'] === '123456'
            && str_contains($req['text'], '1,000 USDT')
            && str_contains($req['text'], self::MIDDLE));

        // already-seen transfers are not sent twice
        $sent = count(Http::recorded(fn ($req) => str_contains($req->url(), 'api.telegram.org')));
        $this->artisan('tron:watch')->assertSuccessful();
        $this->assertCount($sent, Http::recorded(fn ($req) => str_contains($req->url(), 'api.telegram.org')));
    }

    public function test_flag_command_adds_to_own_list(): void
    {
        $this->artisan('tron:flag', ['address' => self::DIRTY, 'note' => 'scam'])->assertSuccessful();
        $this->assertDatabaseHas('flagged_addresses', ['address' => self::DIRTY, 'source' => 'manual', 'note' => 'scam']);

        $this->artisan('tron:flag', ['address' => self::DIRTY, '--remove' => true])->assertSuccessful();
        $this->assertDatabaseMissing('flagged_addresses', ['address' => self::DIRTY, 'source' => 'manual']);
    }
}
