# پشکنەری ئادرەسی TRON

Laravel app that scans a TRON (TRC20) address: balances, where funds came from / went, exchange detection
(Binance, OKX, HTX, Bybit…, including deposit addresses), Tether/OFAC blacklist, 2-hop source-of-funds (taint)
analysis, money-flow graph, scan history, printable report, and Telegram alerts.

## Setup

```bash
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate
php artisan tron:sync-blacklist          # first run: ~10 min (Tether freezes + OFAC)
php artisan serve
```

`.env`:

| Key | What for |
|---|---|
| `TRONGRID_API_KEY` | speed — required for parallel requests (trongrid.io, free) |
| `TRONSCAN_API_KEY` | exchange / wallet names, Tronscan risk flags (tronscan.org, free) |
| `TELEGRAM_BOT_TOKEN` | watch alerts (@BotFather) |
| `SCANNER_PASSWORD` | when set, the whole site asks for this password |
| `SCANS_PER_MINUTE` | scans per visitor per minute (default 10) |

## Background jobs

```bash
php artisan schedule:work     # or cron: * * * * * php /path/artisan schedule:run
```

- `tron:watch` — every minute, Telegram alerts for watched addresses
- `tron:sync-blacklist` — hourly, new Tether freezes + OFAC list

## Your own list

```bash
php artisan tron:flag TXXXX "scam reported by customer"
php artisan tron:flag TXXXX --remove
```

Exchange labels you know can also go in `config/tron_labels.php`.

## Deploying to a server

1. `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://your-domain` (used in Telegram links)
2. Set `SCANNER_PASSWORD` unless the site is meant to be public
3. Serve over HTTPS; point the web root at `public/` only (never expose `.env`)
4. `composer install --no-dev -o && php artisan config:cache && php artisan route:cache && php artisan view:cache`
5. Run the scheduler (cron above) and keep `storage/` writable

## Tests

```bash
php artisan test
```
