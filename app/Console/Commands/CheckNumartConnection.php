<?php

namespace App\Console\Commands;

use App\Services\CatalogService;
use App\Services\Erp\ErpBranchMap;
use App\Support\NumartSource;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckNumartConnection extends Command
{
    protected $signature = 'marketplace:check-numart';

    protected $description = 'Cek koneksi database Numart (katalog & member)';

    public function handle(): int
    {
        if (NumartSource::erp()) {
            $this->line('Sumber: ERP '.config('services.numart_erp.url'));
            try {
                $map = app(ErpBranchMap::class)->map();
                $this->info('OK — cabang POS→ERP: '.json_encode($map));
                $this->line('Kategori cabang tampilan: '.app(CatalogService::class)->categoriesFromPusat(null)->count());

                return self::SUCCESS;
            } catch (\Throwable $e) {
                $this->error('GAGAL: '.$e->getMessage());

                return self::FAILURE;
            }
        }

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
