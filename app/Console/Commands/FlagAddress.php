<?php

namespace App\Console\Commands;

use App\Models\FlaggedAddress;
use App\Services\TronScanner;
use Illuminate\Console\Command;

class FlagAddress extends Command
{
    protected $signature = 'tron:flag {address} {note? : Why it is flagged, e.g. "scam reported by customer"} {--remove}';

    protected $description = 'Add (or remove) an address on your own flagged list — scams, hacks, fraud reports';

    public function handle(): int
    {
        $address = $this->argument('address');
        if (! TronScanner::isValidAddress($address)) {
            $this->error('Not a valid TRON address.');

            return self::FAILURE;
        }

        if ($this->option('remove')) {
            FlaggedAddress::where(['address' => $address, 'source' => 'manual'])->delete();
            $this->info("Removed $address from your list.");

            return self::SUCCESS;
        }

        FlaggedAddress::updateOrCreate(
            ['address' => $address, 'source' => 'manual'],
            ['note' => $this->argument('note'), 'flagged_at' => now()]
        );
        $this->info("Flagged $address.");

        return self::SUCCESS;
    }
}
