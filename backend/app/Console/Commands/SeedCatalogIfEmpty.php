<?php

namespace App\Console\Commands;

use App\Models\Product;
use Database\Seeders\RealCatalogSeeder;
use Illuminate\Console\Command;

class SeedCatalogIfEmpty extends Command
{
    protected $signature = 'catalog:seed-if-empty';

    protected $description = 'Seed the real product catalog only if the products table has no rows yet';

    public function handle(): int
    {
        if (Product::count() > 0) {
            $this->info('Products already exist — skipping catalog seed.');

            return self::SUCCESS;
        }

        $this->call('db:seed', ['--class' => RealCatalogSeeder::class, '--force' => true]);

        return self::SUCCESS;
    }
}
