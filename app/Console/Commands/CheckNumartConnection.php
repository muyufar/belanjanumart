<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckNumartConnection extends Command
{
    protected $signature = 'marketplace:check-numart';

    protected $description = 'Cek koneksi database Numart (katalog & member)';

    public function handle(): int
    {
        $cfg = config('database.connections.numart');
        $this->line('Host: '.($cfg['host'] ?? '?'));
        $this->line('Database: '.($cfg['database'] ?? '?'));
        $this->line('Username: '.($cfg['username'] ?? '?'));

        try {
            $count = DB::connection('numart')->table('kategori')->count();
            $this->info("OK — kategori: {$count} baris");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('GAGAL: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
