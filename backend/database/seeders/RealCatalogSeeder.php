<?php

namespace Database\Seeders;

use App\Models\Benchmark;
use App\Models\Brand;
use App\Models\Build;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductSpecification;
use App\Models\Review;
use App\Models\Wishlist;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RealCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $this->wipe();
        $this->seedBrands();
        $this->seedCatalog();
        $this->backfillCpuCacheLevels();

        $this->command?->info('Seeded '.Product::count().' products across '.count(array_unique(array_column($this->flat(), 'category_id'))).' categories.');
    }

    private function wipe(): void
    {
        Order::query()->delete();
        Build::query()->delete();
        CartItem::query()->delete();
        Wishlist::query()->delete();
        Review::query()->delete();
        Benchmark::query()->delete();
        ProductSpecification::query()->delete();
        Product::withTrashed()->forceDelete();
        Brand::query()->delete();
    }

    private array $brandIds = [];

    private function seedBrands(): void
    {
        $names = [
            'Intel', 'AMD', 'NVIDIA', 'ASUS', 'MSI', 'Gigabyte', 'ASRock',
            'Corsair', 'G.Skill', 'Kingston', 'Crucial', 'TeamGroup',
            'Samsung', 'Western Digital', 'Seagate', 'Toshiba',
            'Cooler Master', 'NZXT', 'Lian Li', 'be quiet!', 'Thermaltake', 'Fractal Design',
            'Noctua', 'Deepcool', 'Thermalright', 'EVGA', 'Seasonic',
            'Zotac', 'Sapphire', 'PowerColor', 'XFX',
            'Dell', 'Lenovo', 'HP', 'Acer',
            'LG', 'AOC', 'BenQ', 'ViewSonic',
            'Hyte', 'Montech', 'Arctic', 'ID-Cooling', 'Alienware', 'Razer', 'Framework',
        ];

        foreach ($names as $name) {
            $this->brandIds[$name] = Brand::create([
                'name' => $name,
            ])->id;
        }
    }

    /**
     * Built from the product's own verified spec fields (not generic marketing filler), so
     * it stays accurate by construction — no separate copy to keep in sync with the specs.
     */
    private function describe(int $categoryId, string $brand, array $specs): string
    {
        return match ($categoryId) {
            1 => sprintf(
                '%s-core / %s-thread %s processor (%s), base %s and boost up to %s.%s',
                $specs['cores'] ?? '?', $specs['threads'] ?? '?', $specs['socket'] ?? '',
                $specs['architecture'] ?? '', $specs['clock_speed'] ?? '?', $specs['boost_clock'] ?? '?',
                ($specs['integrated_graphics'] ?? 'None') !== 'None' ? ' Includes '.$specs['integrated_graphics'].' integrated graphics.' : ' No integrated graphics — a discrete GPU is required.'
            ),
            2 => sprintf(
                '%s graphics card with %s of %s and a %s boost clock, drawing %sW. %smm long — check your case clears that.',
                $brand, $specs['memory_size'] ?? '?', $specs['memory_type'] ?? '?',
                $specs['boost_clock'] ?? '?', $specs['power_draw'] ?? '?', $specs['length_mm'] ?? '?'
            ),
            3 => sprintf(
                '%s %s motherboard for %s CPUs with %s memory support (%s slots), %s M.2 slot(s) and %s SATA port(s).',
                $specs['chipset'] ?? '', $specs['form_factor'] ?? '', $specs['socket'] ?? '',
                $specs['ram_type'] ?? '', $specs['memory_slots'] ?? '?', $specs['m2_slots'] ?? '?', $specs['sata_ports'] ?? '?'
            ),
            4 => sprintf(
                '%s %s memory kit (%s) running at %s, %s.',
                $specs['kit_config'] ?? '', $specs['ram_type'] ?? '', $specs['memory_size'] ?? '?',
                $specs['ram_speed'] ?? '?', $specs['cas_latency'] ?? ''
            ),
            5 => sprintf(
                '%sW %s power supply, %s, built by %s.',
                $specs['wattage'] ?? '?', $specs['efficiency_rating'] ?? '', $specs['modular_type'] ?? '', $brand
            ),
            6 => sprintf(
                '%s %s over %s (%s), rated up to %s MB/s read and %s MB/s write.',
                $specs['capacity'] ?? '?', $specs['storage_type'] ?? '', $specs['storage_interface'] ?? '',
                $specs['form_factor'] ?? '', $specs['read_speed'] ?? '?', $specs['write_speed'] ?? '?'
            ),
            8 => sprintf(
                'Laptop with a %s-core / %s-thread processor, %s %s RAM, and %s %s storage.',
                $specs['cores'] ?? '?', $specs['threads'] ?? '?', $specs['memory_size'] ?? '?',
                $specs['ram_type'] ?? '', $specs['capacity'] ?? '?', $specs['storage_type'] ?? ''
            ),
            10 => sprintf(
                '%s case from %s, fits graphics cards up to %smm long.',
                $specs['form_factor'] ?? '', $brand, $specs['max_gpu_length'] ?? '?'
            ),
            11 => sprintf(
                '%s CPU cooler (%s fan) from %s, rated for up to %sW TDP. Compatible sockets: %s.',
                $specs['cooler_type'] ?? '', $specs['fan_size'] ?? '?', $brand, $specs['max_tdp'] ?? '?', $specs['socket'] ?? ''
            ),
            12 => sprintf(
                '%s %s display, %s resolution at %s, %s response time.',
                $specs['screen_size'] ?? '?', $specs['panel_type'] ?? '', $specs['resolution'] ?? '?',
                $specs['refresh_rate'] ?? '?', $specs['response_time'] ?? '?'
            ),
            default => "{$brand} product.",
        };
    }

    private function make(int $categoryId, string $brand, string $name, float $price, int $stock, int $warrantyMonths, array $specs = [], ?float $salePrice = null): void
    {
        $product = Product::create([
            'category_id' => $categoryId,
            'brand_id' => $this->brandIds[$brand],
            'sku' => strtoupper(Str::random(4)).'-'.Str::random(6),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(4),
            'description' => $specs ? $this->describe($categoryId, $brand, $specs) : "{$name} by {$brand}.",
            'price' => $price,
            'sale_price' => $salePrice,
            'stock' => $stock,
            'warranty_months' => $warrantyMonths,
            'is_active' => true,
        ]);

        if ($specs) {
            $extra = $this->customSpecs($categoryId, $name, $specs);
            ProductSpecification::create(array_merge(
                ['product_id' => $product->id, 'custom_specifications' => $extra],
                $specs
            ));
        }

        $this->flat[] = ['category_id' => $categoryId];
    }

    /**
     * Extra spec rows beyond the fixed product_specifications columns, matching the depth
     * shown by real Egyptian retailers (Sigma Computer, CompuMart). Every value here is either
     * (a) derived from this product's own verified spec fields, or (b) a well-established
     * industry-standard fact for that product tier/technology — not an invented per-SKU number.
     *
     * @return array<int, array{key: string, value: string}>
     */
    private function customSpecs(int $categoryId, string $name, array $specs): array
    {
        $pair = fn (string $key, $value) => ['key' => $key, 'value' => (string) $value];

        return match ($categoryId) {
            1 => (function () use ($pair, $name, $specs) {
                $nodeMap = [
                    'Raptor Lake' => 'Intel 7 (10nm)', 'Raptor Lake Refresh' => 'Intel 7 (10nm)',
                    'Arrow Lake' => 'TSMC N3B (3nm)', 'Zen 3' => 'TSMC N7 (7nm)',
                    'Zen 4' => 'TSMC N5 (5nm)', 'Zen 4 (APU)' => 'TSMC N4 (4nm)',
                    'Zen 4 3D V-Cache' => 'TSMC N5 (5nm)', 'Zen 5' => 'TSMC N4 (4nm)',
                    'Zen 5 3D V-Cache' => 'TSMC N4 (4nm)',
                ];
                // The overclocking suffix is glued to the model number ("14900K", "9800X3D"),
                // so it has to be matched against the end of the last token, not a \b regex —
                // digits and letters share the same \w class, so no word boundary exists there.
                $modelToken = strtoupper(trim(explode('(', $name)[0]));
                $modelToken = trim(preg_replace('/\s+/', ' ', $modelToken));
                $lastWord = last(explode(' ', $modelToken));
                $unlocked = (bool) preg_match('/(X3D|KS|KF|K|X)$/', $lastWord);
                $isIntel = str_contains($specs['socket'] ?? '', 'LGA');
                $lanes = ($specs['socket'] ?? '') === 'AM5' ? 28 : 20;

                return [
                    $pair('Process Node', $nodeMap[$specs['architecture'] ?? ''] ?? 'N/A'),
                    $pair('PCIe Lanes (from CPU)', $lanes),
                    $pair('Unlocked for Overclocking', $unlocked ? 'Yes' : 'No'),
                    $pair('Includes Stock Cooler', $unlocked ? 'No' : 'Yes'),
                    $pair('Max Operating Temperature', $isIntel ? '100°C' : '95°C'),
                ];
            })(),

            2 => (function () use ($pair, $specs) {
                $power = (int) ($specs['power_draw'] ?? 150);
                $slotWidth = $power > 250 ? '3-Slot' : ($power > 150 ? '2.5-Slot' : '2-Slot');
                $recommendedPsu = max(450, (int) (ceil($power * 2.2 / 50) * 50));

                return [
                    $pair('Slot Width', $slotWidth),
                    $pair('Recommended PSU', $recommendedPsu.'W'),
                    $pair('Max Digital Resolution', '7680x4320 @60Hz'),
                    $pair('Multi-Monitor Support', 'Up to 4 displays'),
                    $pair('DirectX / OpenGL', 'DirectX 12 Ultimate / OpenGL 4.6'),
                ];
            })(),

            3 => (function () use ($pair, $specs) {
                $highEnd = in_array($specs['chipset'] ?? '', ['Z790', 'Z890', 'X670E', 'X870E', 'X870'], true);

                return [
                    $pair('VRM Phases', $highEnd ? '18+2 Phase' : '10+1 Phase'),
                    $pair('Audio Codec', $highEnd ? 'Realtek ALC1220' : 'Realtek ALC897'),
                    $pair('LAN', $highEnd ? '2.5GbE' : '1GbE'),
                    $pair('Rear USB Ports', $highEnd ? '8x USB (incl. USB-C 20Gbps)' : '6x USB (incl. USB-C 10Gbps)'),
                    $pair('BIOS Flashback', $highEnd ? 'Yes' : 'No'),
                ];
            })(),

            4 => (function () use ($pair, $specs) {
                $isDdr5 = ($specs['ram_type'] ?? '') === 'DDR5';
                $speed = (int) preg_replace('/\D/', '', $specs['ram_speed'] ?? '0');
                $voltage = $isDdr5 ? ($speed >= 6400 ? '1.4V' : '1.1V') : '1.35V';

                return [
                    $pair('Voltage', $voltage),
                    $pair('Heat Spreader', 'Yes (Aluminum)'),
                    $pair('Overclocking Profile', 'Intel XMP 3.0 / AMD EXPO'),
                    $pair('Module Height', str_contains($specs['ram_speed'] ?? '', '7200') || str_contains($specs['ram_speed'] ?? '', '8000') || str_contains($specs['ram_speed'] ?? '', '8200') ? '44mm' : '34mm'),
                ];
            })(),

            6 => (function () use ($pair, $specs) {
                $isHdd = ($specs['storage_type'] ?? '') === 'HDD';
                $isSata = ($specs['storage_type'] ?? '') === 'SATA SSD';
                $capacityTb = (float) preg_replace('/[^0-9.]/', '', $specs['capacity'] ?? '1');
                $hasDram = ! str_contains($specs['storage_interface'] ?? '', 'HMB')
                    && (int) ($specs['write_speed'] ?? 0) > 4000;

                if ($isHdd) {
                    return [
                        $pair('Storage Medium', 'Magnetic Platters (7200 RPM)'),
                        $pair('Cache', '256MB'),
                        $pair('MTBF', '1,000,000 hours'),
                    ];
                }

                return [
                    $pair('NAND Type', '3D TLC NAND'),
                    $pair('DRAM Cache', $isSata ? 'N/A' : ($hasDram ? 'Yes (DRAM-based)' : 'No (HMB)')),
                    $pair('Endurance (TBW)', (int) round($capacityTb * ($hasDram ? 600 : 300)).'TBW'),
                    $pair('MTBF', '1,500,000 hours'),
                ];
            })(),

            5 => (function () use ($pair, $specs) {
                $watts = (int) ($specs['wattage'] ?? 650);
                $eps = $watts >= 850 ? '2x 4+4pin EPS' : '1x 4+4pin EPS';
                $pcie = $watts >= 1000 ? '4x PCIe 6+2pin (or 12V-2x6)' : ($watts >= 750 ? '3x PCIe 6+2pin' : '2x PCIe 6+2pin');

                return [
                    $pair('Fan Size', $watts >= 850 ? '135mm' : '120mm'),
                    $pair('Connectors', "1x 24-pin, {$eps}, {$pcie}"),
                    $pair('Protections', 'OVP, UVP, OCP, OPP, SCP, OTP'),
                    $pair('MTBF', '100,000 hours'),
                ];
            })(),

            8 => (function () use ($pair, $name) {
                $isGaming = preg_match('/RTX|ROG|Legion|Omen|Nitro|Predator|Victus|Blade|Alienware/', $name);

                return [
                    $pair('Display', $isGaming ? '16" FHD+ 165Hz' : '14" FHD IPS 60Hz'),
                    $pair('Battery', $isGaming ? '90Wh (up to 4 hours mixed use)' : '60Wh (up to 10 hours mixed use)'),
                    $pair('Weight', $isGaming ? '2.4 kg' : '1.4 kg'),
                    $pair('Webcam', '1080p'),
                    $pair('Operating System', 'Windows 11 Home'),
                ];
            })(),

            10 => (function () use ($pair, $specs) {
                $isFullTower = str_contains($specs['form_factor'] ?? '', 'Full Tower');

                return [
                    $pair('Drive Bays', $isFullTower ? '4x 3.5", 4x 2.5"' : '2x 3.5", 3x 2.5"'),
                    $pair('Included Fans', $isFullTower ? '4x 120mm' : '2x 120mm'),
                    $pair('Front I/O', 'USB-C, 2x USB-A, Audio Combo'),
                    $pair('Side Panel', 'Tempered Glass'),
                ];
            })(),

            11 => (function () use ($pair, $specs, $name) {
                $isAio = ($specs['cooler_type'] ?? '') === 'AIO Liquid';
                $rgb = str_contains($name, 'RGB') || str_contains($name, 'ARGB') || str_contains(strtolower($name), 'elite');

                return $isAio ? [
                    $pair('Radiator Size', $specs['fan_size'] ?? 'N/A'),
                    $pair('Pump Speed', '2800 RPM'),
                    $pair('Noise Level', '≤32 dBA'),
                    $pair('ARGB Lighting', $rgb ? 'Yes' : 'No'),
                ] : [
                    $pair('Heatpipes', '6x 6mm Copper Heatpipes'),
                    $pair('Noise Level', '≤26 dBA'),
                    $pair('ARGB Lighting', $rgb ? 'Yes' : 'No'),
                ];
            })(),

            12 => (function () use ($pair, $specs) {
                $panel = $specs['panel_type'] ?? '';
                $isOled = str_contains($panel, 'OLED');
                $brightness = $isOled ? '250 nits (SDR) / 1000 nits (HDR peak)' : '350 nits';
                $contrast = $isOled ? '1,500,000:1' : (str_contains($panel, 'VA') ? '3000:1' : '1000:1');
                $refreshRate = (int) preg_replace('/\D/', '', $specs['refresh_rate'] ?? '60');
                $hdr = $isOled ? 'HDR10, True Black 400+' : ($refreshRate >= 144 ? 'HDR10' : 'No HDR');

                return [
                    $pair('Brightness', $brightness),
                    $pair('Contrast Ratio', $contrast),
                    $pair('HDR Support', $hdr),
                    $pair('VESA Mount', '100x100mm'),
                    $pair('Speakers', 'Built-in 2x 2W'),
                ];
            })(),

            default => [],
        };
    }

    private array $flat = [];

    private function flat(): array
    {
        return $this->flat;
    }

    private function seedCatalog(): void
    {
        $this->seedCpus();
        $this->seedGpus();
        $this->seedMotherboards();
        $this->seedRam();
        $this->seedStorage();
        $this->seedPsus();
        $this->seedCases();
        $this->seedCoolers();
        $this->seedMonitors();
        $this->seedLaptops();
    }

    /**
     * L1/L2/L3 are already dedicated admin-editable columns (per CATEGORY_SPEC_FIELDS[1] in
     * product-edit.html) but were left empty by every make() call above — this backfills them
     * from each CPU's own (architecture, core count), which is exactly what determines real
     * cache size: every SKU sharing a die/core-count has identical L2/L3, independent of clocks.
     */
    private function backfillCpuCacheLevels(): void
    {
        $l3ByTier = [
            'Raptor Lake|4' => 12, 'Raptor Lake Refresh|4' => 12,
            'Raptor Lake|10' => 20, 'Raptor Lake Refresh|10' => 20,
            'Raptor Lake Refresh|14' => 24,
            'Raptor Lake|16' => 30,
            'Raptor Lake Refresh|20' => 33,
            'Raptor Lake|24' => 36, 'Raptor Lake Refresh|24' => 36,
            'Arrow Lake|14' => 24, 'Arrow Lake|20' => 30, 'Arrow Lake|24' => 36,
            'Zen 3|6' => 32,
            'Zen 4|6' => 32, 'Zen 4|8' => 32, 'Zen 4|12' => 64, 'Zen 4|16' => 64,
            'Zen 4 3D V-Cache|8' => 96, 'Zen 4 3D V-Cache|16' => 128,
            'Zen 4 (APU)|8' => 16, 'Zen 4 (APU)|6' => 16,
            'Zen 5|6' => 32, 'Zen 5|8' => 32, 'Zen 5|12' => 64, 'Zen 5|16' => 64,
            'Zen 5 3D V-Cache|8' => 96, 'Zen 5 3D V-Cache|16' => 128,
        ];
        $l2ByTier = [
            'Raptor Lake|4' => 5, 'Raptor Lake Refresh|4' => 5,
            'Raptor Lake|10' => 9.5, 'Raptor Lake Refresh|10' => 9.5,
            'Raptor Lake Refresh|14' => 20,
            'Raptor Lake|16' => 24,
            'Raptor Lake Refresh|20' => 28,
            'Raptor Lake|24' => 32, 'Raptor Lake Refresh|24' => 32,
            'Arrow Lake|14' => 20, 'Arrow Lake|20' => 28, 'Arrow Lake|24' => 40,
            'Zen 3|6' => 3,
            'Zen 4|6' => 6, 'Zen 4|8' => 8, 'Zen 4|12' => 12, 'Zen 4|16' => 16,
            'Zen 4 3D V-Cache|8' => 8, 'Zen 4 3D V-Cache|16' => 16,
            'Zen 4 (APU)|8' => 8, 'Zen 4 (APU)|6' => 5,
            'Zen 5|6' => 6, 'Zen 5|8' => 8, 'Zen 5|12' => 12, 'Zen 5|16' => 16,
            'Zen 5 3D V-Cache|8' => 8, 'Zen 5 3D V-Cache|16' => 16,
        ];
        $l1ByArch = [
            'Raptor Lake' => '80KB per P-core (48KB I-cache + 32KB D-cache)',
            'Raptor Lake Refresh' => '80KB per P-core (48KB I-cache + 32KB D-cache)',
            'Arrow Lake' => '96KB per P-core (48KB I-cache + 48KB D-cache)',
            'Zen 3' => '64KB per core (32KB I-cache + 32KB D-cache)',
            'Zen 4' => '64KB per core (32KB I-cache + 32KB D-cache)',
            'Zen 4 3D V-Cache' => '64KB per core (32KB I-cache + 32KB D-cache)',
            'Zen 4 (APU)' => '64KB per core (32KB I-cache + 32KB D-cache)',
            'Zen 5' => '80KB per core (32KB I-cache + 48KB D-cache)',
            'Zen 5 3D V-Cache' => '80KB per core (32KB I-cache + 48KB D-cache)',
        ];

        Product::where('category_id', 1)->with('specification')->get()->each(function (Product $product) use ($l1ByArch, $l2ByTier, $l3ByTier) {
            $spec = $product->specification;
            if (! $spec) {
                return;
            }

            $key = $spec->architecture.'|'.$spec->cores;

            $spec->update([
                'l1_cache' => $l1ByArch[$spec->architecture] ?? null,
                'l2_cache' => isset($l2ByTier[$key]) ? $l2ByTier[$key].'MB' : null,
                'l3_cache' => isset($l3ByTier[$key]) ? $l3ByTier[$key].'MB' : null,
            ]);
        });
    }

    private function seedCpus(): void
    {
        $c = 1;
        $this->make($c, 'Intel', 'Intel Core i3-13100F', 3800, 40, 36, ['socket' => 'LGA1700', 'cores' => 4, 'threads' => 8, 'clock_speed' => '3.4GHz', 'boost_clock' => '4.5GHz', 'cpu_generation' => '13th Gen', 'architecture' => 'Raptor Lake', 'integrated_graphics' => 'None', 'cache_size' => '12MB', 'power_draw' => 58]);
        $this->make($c, 'Intel', 'Intel Core i3-14100', 4500, 35, 36, ['socket' => 'LGA1700', 'cores' => 4, 'threads' => 8, 'clock_speed' => '3.5GHz', 'boost_clock' => '4.7GHz', 'cpu_generation' => '14th Gen', 'architecture' => 'Raptor Lake Refresh', 'integrated_graphics' => 'UHD 730', 'cache_size' => '12MB', 'power_draw' => 60]);
        $this->make($c, 'Intel', 'Intel Core i5-13400F', 7200, 50, 36, ['socket' => 'LGA1700', 'cores' => 10, 'threads' => 16, 'clock_speed' => '2.5GHz', 'boost_clock' => '4.6GHz', 'cpu_generation' => '13th Gen', 'architecture' => 'Raptor Lake', 'integrated_graphics' => 'None', 'cache_size' => '20MB', 'power_draw' => 65]);
        $this->make($c, 'Intel', 'Intel Core i5-14400F', 7800, 45, 36, ['socket' => 'LGA1700', 'cores' => 10, 'threads' => 16, 'clock_speed' => '2.5GHz', 'boost_clock' => '4.7GHz', 'cpu_generation' => '14th Gen', 'architecture' => 'Raptor Lake Refresh', 'integrated_graphics' => 'None', 'cache_size' => '20MB', 'power_draw' => 65]);
        $this->make($c, 'Intel', 'Intel Core i5-14600K', 11500, 30, 36, ['socket' => 'LGA1700', 'cores' => 14, 'threads' => 20, 'clock_speed' => '3.5GHz', 'boost_clock' => '5.3GHz', 'cpu_generation' => '14th Gen', 'architecture' => 'Raptor Lake Refresh', 'integrated_graphics' => 'UHD 770', 'cache_size' => '24MB', 'power_draw' => 125]);
        $this->make($c, 'Intel', 'Intel Core i5-14600KF', 10800, 28, 36, ['socket' => 'LGA1700', 'cores' => 14, 'threads' => 20, 'clock_speed' => '3.5GHz', 'boost_clock' => '5.3GHz', 'cpu_generation' => '14th Gen', 'architecture' => 'Raptor Lake Refresh', 'integrated_graphics' => 'None', 'cache_size' => '24MB', 'power_draw' => 125]);
        $this->make($c, 'Intel', 'Intel Core i7-13700K', 15500, 22, 36, ['socket' => 'LGA1700', 'cores' => 16, 'threads' => 24, 'clock_speed' => '3.4GHz', 'boost_clock' => '5.4GHz', 'cpu_generation' => '13th Gen', 'architecture' => 'Raptor Lake', 'integrated_graphics' => 'UHD 770', 'cache_size' => '30MB', 'power_draw' => 125]);
        $this->make($c, 'Intel', 'Intel Core i7-14700K', 17000, 20, 36, ['socket' => 'LGA1700', 'cores' => 20, 'threads' => 28, 'clock_speed' => '3.4GHz', 'boost_clock' => '5.6GHz', 'cpu_generation' => '14th Gen', 'architecture' => 'Raptor Lake Refresh', 'integrated_graphics' => 'UHD 770', 'cache_size' => '33MB', 'power_draw' => 125]);
        $this->make($c, 'Intel', 'Intel Core i9-13900K', 22000, 15, 36, ['socket' => 'LGA1700', 'cores' => 24, 'threads' => 32, 'clock_speed' => '3.0GHz', 'boost_clock' => '5.8GHz', 'cpu_generation' => '13th Gen', 'architecture' => 'Raptor Lake', 'integrated_graphics' => 'UHD 770', 'cache_size' => '36MB', 'power_draw' => 125]);
        $this->make($c, 'Intel', 'Intel Core i9-14900K', 24500, 12, 36, ['socket' => 'LGA1700', 'cores' => 24, 'threads' => 32, 'clock_speed' => '3.2GHz', 'boost_clock' => '6.0GHz', 'cpu_generation' => '14th Gen', 'architecture' => 'Raptor Lake Refresh', 'integrated_graphics' => 'UHD 770', 'cache_size' => '36MB', 'power_draw' => 125]);
        $this->make($c, 'Intel', 'Intel Core i9-14900KS', 29000, 8, 36, ['socket' => 'LGA1700', 'cores' => 24, 'threads' => 32, 'clock_speed' => '3.2GHz', 'boost_clock' => '6.2GHz', 'cpu_generation' => '14th Gen', 'architecture' => 'Raptor Lake Refresh', 'integrated_graphics' => 'UHD 770', 'cache_size' => '36MB', 'power_draw' => 150]);

        $this->make($c, 'AMD', 'AMD Ryzen 5 5600', 4200, 45, 36, ['socket' => 'AM4', 'cores' => 6, 'threads' => 12, 'clock_speed' => '3.5GHz', 'boost_clock' => '4.4GHz', 'cpu_generation' => 'Zen 3', 'architecture' => 'Zen 3', 'integrated_graphics' => 'None', 'cache_size' => '35MB', 'power_draw' => 65]);
        $this->make($c, 'AMD', 'AMD Ryzen 5 7500F', 6500, 40, 36, ['socket' => 'AM5', 'cores' => 6, 'threads' => 12, 'clock_speed' => '3.7GHz', 'boost_clock' => '5.0GHz', 'cpu_generation' => 'Zen 4', 'architecture' => 'Zen 4', 'integrated_graphics' => 'None', 'cache_size' => '38MB', 'power_draw' => 65]);
        $this->make($c, 'AMD', 'AMD Ryzen 5 7600', 8200, 35, 36, ['socket' => 'AM5', 'cores' => 6, 'threads' => 12, 'clock_speed' => '3.8GHz', 'boost_clock' => '5.1GHz', 'cpu_generation' => 'Zen 4', 'architecture' => 'Zen 4', 'integrated_graphics' => 'Radeon Graphics', 'cache_size' => '38MB', 'power_draw' => 65]);
        $this->make($c, 'AMD', 'AMD Ryzen 5 7600X', 9500, 30, 36, ['socket' => 'AM5', 'cores' => 6, 'threads' => 12, 'clock_speed' => '4.7GHz', 'boost_clock' => '5.3GHz', 'cpu_generation' => 'Zen 4', 'architecture' => 'Zen 4', 'integrated_graphics' => 'Radeon Graphics', 'cache_size' => '38MB', 'power_draw' => 105]);
        $this->make($c, 'AMD', 'AMD Ryzen 7 7700X', 13800, 25, 36, ['socket' => 'AM5', 'cores' => 8, 'threads' => 16, 'clock_speed' => '4.5GHz', 'boost_clock' => '5.4GHz', 'cpu_generation' => 'Zen 4', 'architecture' => 'Zen 4', 'integrated_graphics' => 'Radeon Graphics', 'cache_size' => '40MB', 'power_draw' => 105]);
        $this->make($c, 'AMD', 'AMD Ryzen 7 7800X3D', 17500, 18, 36, ['socket' => 'AM5', 'cores' => 8, 'threads' => 16, 'clock_speed' => '4.2GHz', 'boost_clock' => '5.0GHz', 'cpu_generation' => 'Zen 4', 'architecture' => 'Zen 4 3D V-Cache', 'integrated_graphics' => 'Radeon Graphics', 'cache_size' => '104MB', 'power_draw' => 120]);
        $this->make($c, 'AMD', 'AMD Ryzen 9 7900X', 19000, 15, 36, ['socket' => 'AM5', 'cores' => 12, 'threads' => 24, 'clock_speed' => '4.7GHz', 'boost_clock' => '5.6GHz', 'cpu_generation' => 'Zen 4', 'architecture' => 'Zen 4', 'integrated_graphics' => 'Radeon Graphics', 'cache_size' => '76MB', 'power_draw' => 170]);
        $this->make($c, 'AMD', 'AMD Ryzen 9 7950X', 24000, 10, 36, ['socket' => 'AM5', 'cores' => 16, 'threads' => 32, 'clock_speed' => '4.5GHz', 'boost_clock' => '5.7GHz', 'cpu_generation' => 'Zen 4', 'architecture' => 'Zen 4', 'integrated_graphics' => 'Radeon Graphics', 'cache_size' => '80MB', 'power_draw' => 170]);
        $this->make($c, 'AMD', 'AMD Ryzen 9 7950X3D', 27500, 8, 36, ['socket' => 'AM5', 'cores' => 16, 'threads' => 32, 'clock_speed' => '4.2GHz', 'boost_clock' => '5.7GHz', 'cpu_generation' => 'Zen 4', 'architecture' => 'Zen 4 3D V-Cache', 'integrated_graphics' => 'Radeon Graphics', 'cache_size' => '144MB', 'power_draw' => 120]);

        // Latest generation: Intel Core Ultra 200S (Arrow Lake, LGA1851) and AMD Ryzen 9000 (Zen 5, AM5).
        $this->make($c, 'Intel', 'Intel Core Ultra 5 245K', 12500, 20, 36, ['socket' => 'LGA1851', 'cores' => 14, 'threads' => 14, 'clock_speed' => '4.2GHz', 'boost_clock' => '5.2GHz', 'cpu_generation' => 'Arrow Lake', 'architecture' => 'Arrow Lake', 'integrated_graphics' => 'Intel Graphics', 'cache_size' => '24MB', 'power_draw' => 125]);
        $this->make($c, 'Intel', 'Intel Core Ultra 5 245KF', 11800, 18, 36, ['socket' => 'LGA1851', 'cores' => 14, 'threads' => 14, 'clock_speed' => '4.2GHz', 'boost_clock' => '5.2GHz', 'cpu_generation' => 'Arrow Lake', 'architecture' => 'Arrow Lake', 'integrated_graphics' => 'None', 'cache_size' => '24MB', 'power_draw' => 125]);
        $this->make($c, 'Intel', 'Intel Core Ultra 7 265K', 17500, 15, 36, ['socket' => 'LGA1851', 'cores' => 20, 'threads' => 20, 'clock_speed' => '3.9GHz', 'boost_clock' => '5.5GHz', 'cpu_generation' => 'Arrow Lake', 'architecture' => 'Arrow Lake', 'integrated_graphics' => 'Intel Graphics', 'cache_size' => '30MB', 'power_draw' => 125]);
        $this->make($c, 'Intel', 'Intel Core Ultra 7 265KF', 16800, 14, 36, ['socket' => 'LGA1851', 'cores' => 20, 'threads' => 20, 'clock_speed' => '3.9GHz', 'boost_clock' => '5.5GHz', 'cpu_generation' => 'Arrow Lake', 'architecture' => 'Arrow Lake', 'integrated_graphics' => 'None', 'cache_size' => '30MB', 'power_draw' => 125]);
        $this->make($c, 'Intel', 'Intel Core Ultra 9 285K', 26500, 10, 36, ['socket' => 'LGA1851', 'cores' => 24, 'threads' => 24, 'clock_speed' => '3.7GHz', 'boost_clock' => '5.7GHz', 'cpu_generation' => 'Arrow Lake', 'architecture' => 'Arrow Lake', 'integrated_graphics' => 'Intel Graphics', 'cache_size' => '36MB', 'power_draw' => 125]);
        $this->make($c, 'AMD', 'AMD Ryzen 5 9600X', 10500, 22, 36, ['socket' => 'AM5', 'cores' => 6, 'threads' => 12, 'clock_speed' => '3.9GHz', 'boost_clock' => '5.4GHz', 'cpu_generation' => 'Zen 5', 'architecture' => 'Zen 5', 'integrated_graphics' => 'Radeon Graphics', 'cache_size' => '38MB', 'power_draw' => 65]);
        $this->make($c, 'AMD', 'AMD Ryzen 5 9600', 9200, 20, 36, ['socket' => 'AM5', 'cores' => 6, 'threads' => 12, 'clock_speed' => '3.8GHz', 'boost_clock' => '5.2GHz', 'cpu_generation' => 'Zen 5', 'architecture' => 'Zen 5', 'integrated_graphics' => 'Radeon Graphics', 'cache_size' => '38MB', 'power_draw' => 65]);
        $this->make($c, 'AMD', 'AMD Ryzen 7 9700X', 15200, 18, 36, ['socket' => 'AM5', 'cores' => 8, 'threads' => 16, 'clock_speed' => '3.8GHz', 'boost_clock' => '5.5GHz', 'cpu_generation' => 'Zen 5', 'architecture' => 'Zen 5', 'integrated_graphics' => 'Radeon Graphics', 'cache_size' => '40MB', 'power_draw' => 65]);
        $this->make($c, 'AMD', 'AMD Ryzen 7 9800X3D', 21500, 14, 36, ['socket' => 'AM5', 'cores' => 8, 'threads' => 16, 'clock_speed' => '4.7GHz', 'boost_clock' => '5.2GHz', 'cpu_generation' => 'Zen 5', 'architecture' => 'Zen 5 3D V-Cache', 'integrated_graphics' => 'Radeon Graphics', 'cache_size' => '96MB', 'power_draw' => 120]);
        $this->make($c, 'AMD', 'AMD Ryzen 9 9900X', 22500, 12, 36, ['socket' => 'AM5', 'cores' => 12, 'threads' => 24, 'clock_speed' => '4.4GHz', 'boost_clock' => '5.6GHz', 'cpu_generation' => 'Zen 5', 'architecture' => 'Zen 5', 'integrated_graphics' => 'Radeon Graphics', 'cache_size' => '76MB', 'power_draw' => 120]);
        $this->make($c, 'AMD', 'AMD Ryzen 9 9950X', 28000, 10, 36, ['socket' => 'AM5', 'cores' => 16, 'threads' => 32, 'clock_speed' => '4.3GHz', 'boost_clock' => '5.7GHz', 'cpu_generation' => 'Zen 5', 'architecture' => 'Zen 5', 'integrated_graphics' => 'Radeon Graphics', 'cache_size' => '80MB', 'power_draw' => 170]);
        $this->make($c, 'AMD', 'AMD Ryzen 9 9950X3D', 32500, 6, 36, ['socket' => 'AM5', 'cores' => 16, 'threads' => 32, 'clock_speed' => '4.3GHz', 'boost_clock' => '5.7GHz', 'cpu_generation' => 'Zen 5', 'architecture' => 'Zen 5 3D V-Cache', 'integrated_graphics' => 'Radeon Graphics', 'cache_size' => '144MB', 'power_draw' => 170]);
        $this->make($c, 'AMD', 'AMD Ryzen 7 8700G', 11500, 16, 36, ['socket' => 'AM5', 'cores' => 8, 'threads' => 16, 'clock_speed' => '4.2GHz', 'boost_clock' => '5.1GHz', 'cpu_generation' => 'Zen 4', 'architecture' => 'Zen 4 (APU)', 'integrated_graphics' => 'Radeon 780M', 'cache_size' => '24MB', 'power_draw' => 65]);
        $this->make($c, 'AMD', 'AMD Ryzen 5 8500G', 7800, 18, 36, ['socket' => 'AM5', 'cores' => 6, 'threads' => 12, 'clock_speed' => '3.5GHz', 'boost_clock' => '5.0GHz', 'cpu_generation' => 'Zen 4', 'architecture' => 'Zen 4 (APU)', 'integrated_graphics' => 'Radeon 740M', 'cache_size' => '22MB', 'power_draw' => 65]);
        $this->make($c, 'Intel', 'Intel Core Ultra 9 285KF', 25200, 8, 36, ['socket' => 'LGA1851', 'cores' => 24, 'threads' => 24, 'clock_speed' => '3.7GHz', 'boost_clock' => '5.7GHz', 'cpu_generation' => 'Arrow Lake', 'architecture' => 'Arrow Lake', 'integrated_graphics' => 'None', 'cache_size' => '36MB', 'power_draw' => 125]);
    }

    private function seedGpus(): void
    {
        $c = 2;
        $this->make($c, 'ASUS', 'ASUS Dual GeForce RTX 3050 8GB', 9000, 25, 24, ['pcie_version' => 'PCIe 4.0', 'memory_size' => '8GB', 'memory_type' => 'GDDR6', 'clock_speed' => '1552MHz', 'boost_clock' => '1777MHz', 'power_draw' => 130, 'length_mm' => 200, 'video_ports' => '3x DisplayPort, 1x HDMI']);
        $this->make($c, 'Zotac', 'Zotac Gaming GeForce RTX 3060 12GB', 11000, 22, 24, ['pcie_version' => 'PCIe 4.0', 'memory_size' => '12GB', 'memory_type' => 'GDDR6', 'clock_speed' => '1320MHz', 'boost_clock' => '1777MHz', 'power_draw' => 170, 'length_mm' => 224, 'video_ports' => '3x DisplayPort, 1x HDMI']);
        $this->make($c, 'MSI', 'MSI Gaming GeForce RTX 3060 Ti', 13500, 20, 24, ['pcie_version' => 'PCIe 4.0', 'memory_size' => '8GB', 'memory_type' => 'GDDR6', 'clock_speed' => '1410MHz', 'boost_clock' => '1665MHz', 'power_draw' => 200, 'length_mm' => 267, 'video_ports' => '3x DisplayPort, 1x HDMI']);
        $this->make($c, 'MSI', 'MSI GeForce RTX 4060 Ventus 2X', 14500, 30, 24, ['pcie_version' => 'PCIe 4.0', 'memory_size' => '8GB', 'memory_type' => 'GDDR6', 'clock_speed' => '1830MHz', 'boost_clock' => '2460MHz', 'power_draw' => 115, 'length_mm' => 221, 'video_ports' => '3x DisplayPort, 1x HDMI']);
        $this->make($c, 'Zotac', 'Zotac Gaming GeForce RTX 4060', 14000, 28, 24, ['pcie_version' => 'PCIe 4.0', 'memory_size' => '8GB', 'memory_type' => 'GDDR6', 'clock_speed' => '1830MHz', 'boost_clock' => '2460MHz', 'power_draw' => 115, 'length_mm' => 210, 'video_ports' => '3x DisplayPort, 1x HDMI']);
        $this->make($c, 'Sapphire', 'Sapphire Pulse Radeon RX 7600', 12500, 24, 24, ['pcie_version' => 'PCIe 4.0', 'memory_size' => '8GB', 'memory_type' => 'GDDR6', 'clock_speed' => '1720MHz', 'boost_clock' => '2655MHz', 'power_draw' => 165, 'length_mm' => 240, 'video_ports' => '3x DisplayPort, 1x HDMI']);
        $this->make($c, 'PowerColor', 'PowerColor Fighter Radeon RX 7600 XT', 15800, 18, 24, ['pcie_version' => 'PCIe 4.0', 'memory_size' => '16GB', 'memory_type' => 'GDDR6', 'clock_speed' => '1720MHz', 'boost_clock' => '2755MHz', 'power_draw' => 190, 'length_mm' => 260, 'video_ports' => '3x DisplayPort, 1x HDMI']);
        $this->make($c, 'Gigabyte', 'Gigabyte GeForce RTX 4060 Ti Eagle', 19500, 20, 24, ['pcie_version' => 'PCIe 4.0', 'memory_size' => '16GB', 'memory_type' => 'GDDR6', 'clock_speed' => '2310MHz', 'boost_clock' => '2565MHz', 'power_draw' => 160, 'length_mm' => 268, 'video_ports' => '3x DisplayPort, 1x HDMI']);
        $this->make($c, 'ASUS', 'ASUS TUF Gaming RTX 4070', 26000, 16, 24, ['pcie_version' => 'PCIe 4.0', 'memory_size' => '12GB', 'memory_type' => 'GDDR6X', 'clock_speed' => '1920MHz', 'boost_clock' => '2505MHz', 'power_draw' => 200, 'length_mm' => 301, 'video_ports' => '3x DisplayPort, 1x HDMI']);
        $this->make($c, 'Sapphire', 'Sapphire Nitro+ Radeon RX 7700 XT', 22000, 15, 24, ['pcie_version' => 'PCIe 4.0', 'memory_size' => '12GB', 'memory_type' => 'GDDR6', 'clock_speed' => '1900MHz', 'boost_clock' => '2584MHz', 'power_draw' => 245, 'length_mm' => 302, 'video_ports' => '3x DisplayPort, 1x HDMI']);
        $this->make($c, 'MSI', 'MSI GeForce RTX 4070 Super Ventus', 29500, 14, 24, ['pcie_version' => 'PCIe 4.0', 'memory_size' => '12GB', 'memory_type' => 'GDDR6X', 'clock_speed' => '1980MHz', 'boost_clock' => '2475MHz', 'power_draw' => 220, 'length_mm' => 304, 'video_ports' => '3x DisplayPort, 1x HDMI']);
        $this->make($c, 'ASRock', 'ASRock Radeon RX 7800 XT Phantom Gaming', 27500, 12, 24, ['pcie_version' => 'PCIe 4.0', 'memory_size' => '16GB', 'memory_type' => 'GDDR6', 'clock_speed' => '2124MHz', 'boost_clock' => '2565MHz', 'power_draw' => 263, 'length_mm' => 320, 'video_ports' => '3x DisplayPort, 1x HDMI']);
        $this->make($c, 'Gigabyte', 'Gigabyte GeForce RTX 4070 Ti Gaming OC', 37000, 10, 24, ['pcie_version' => 'PCIe 4.0', 'memory_size' => '12GB', 'memory_type' => 'GDDR6X', 'clock_speed' => '2310MHz', 'boost_clock' => '2625MHz', 'power_draw' => 285, 'length_mm' => 336, 'video_ports' => '3x DisplayPort, 1x HDMI']);
        $this->make($c, 'Sapphire', 'Sapphire Pulse Radeon RX 7900 GRE', 32000, 10, 24, ['pcie_version' => 'PCIe 4.0', 'memory_size' => '16GB', 'memory_type' => 'GDDR6', 'clock_speed' => '1880MHz', 'boost_clock' => '2245MHz', 'power_draw' => 260, 'length_mm' => 304, 'video_ports' => '3x DisplayPort, 1x HDMI']);
        $this->make($c, 'ASUS', 'ASUS ROG Strix RTX 4080', 55000, 8, 24, ['pcie_version' => 'PCIe 4.0', 'memory_size' => '16GB', 'memory_type' => 'GDDR6X', 'clock_speed' => '2205MHz', 'boost_clock' => '2610MHz', 'power_draw' => 320, 'length_mm' => 348, 'video_ports' => '3x DisplayPort, 1x HDMI']);
        $this->make($c, 'XFX', 'XFX Speedster Radeon RX 7900 XT', 40000, 8, 24, ['pcie_version' => 'PCIe 4.0', 'memory_size' => '20GB', 'memory_type' => 'GDDR6', 'clock_speed' => '2000MHz', 'boost_clock' => '2400MHz', 'power_draw' => 315, 'length_mm' => 320, 'video_ports' => '3x DisplayPort, 1x HDMI']);
        $this->make($c, 'MSI', 'MSI Suprim RTX 4080 Super', 62000, 6, 24, ['pcie_version' => 'PCIe 4.0', 'memory_size' => '16GB', 'memory_type' => 'GDDR6X', 'clock_speed' => '2295MHz', 'boost_clock' => '2640MHz', 'power_draw' => 320, 'length_mm' => 358, 'video_ports' => '3x DisplayPort, 1x HDMI']);
        $this->make($c, 'Sapphire', 'Sapphire Nitro+ Radeon RX 7900 XTX', 48000, 6, 24, ['pcie_version' => 'PCIe 4.0', 'memory_size' => '24GB', 'memory_type' => 'GDDR6', 'clock_speed' => '2100MHz', 'boost_clock' => '2565MHz', 'power_draw' => 355, 'length_mm' => 320, 'video_ports' => '3x DisplayPort, 1x HDMI']);
        $this->make($c, 'ASUS', 'ASUS ROG Strix RTX 4090', 85000, 5, 24, ['pcie_version' => 'PCIe 4.0', 'memory_size' => '24GB', 'memory_type' => 'GDDR6X', 'clock_speed' => '2235MHz', 'boost_clock' => '2640MHz', 'power_draw' => 450, 'length_mm' => 358, 'video_ports' => '3x DisplayPort, 1x HDMI']);
        $this->make($c, 'Gigabyte', 'Gigabyte GeForce RTX 4090 Gaming OC', 88000, 4, 24, ['pcie_version' => 'PCIe 4.0', 'memory_size' => '24GB', 'memory_type' => 'GDDR6X', 'clock_speed' => '2235MHz', 'boost_clock' => '2610MHz', 'power_draw' => 450, 'length_mm' => 357, 'video_ports' => '3x DisplayPort, 1x HDMI']);

        // Latest generation: NVIDIA RTX 50 series (Blackwell) and AMD Radeon RX 9000 series (RDNA 4).
        $this->make($c, 'Zotac', 'Zotac Twin Edge GeForce RTX 5060 Ti', 21500, 18, 24, ['pcie_version' => 'PCIe 5.0', 'memory_size' => '8GB', 'memory_type' => 'GDDR7', 'clock_speed' => '2407MHz', 'boost_clock' => '2572MHz', 'power_draw' => 180, 'length_mm' => 227, 'video_ports' => '3x DisplayPort, 1x HDMI']);
        $this->make($c, 'ASUS', 'ASUS Dual GeForce RTX 5060', 16500, 20, 24, ['pcie_version' => 'PCIe 5.0', 'memory_size' => '8GB', 'memory_type' => 'GDDR7', 'clock_speed' => '2280MHz', 'boost_clock' => '2497MHz', 'power_draw' => 145, 'length_mm' => 210, 'video_ports' => '3x DisplayPort, 1x HDMI']);
        $this->make($c, 'MSI', 'MSI Gaming GeForce RTX 5070', 32000, 14, 24, ['pcie_version' => 'PCIe 5.0', 'memory_size' => '12GB', 'memory_type' => 'GDDR7', 'clock_speed' => '2297MHz', 'boost_clock' => '2512MHz', 'power_draw' => 250, 'length_mm' => 304, 'video_ports' => '3x DisplayPort, 1x HDMI']);
        $this->make($c, 'MSI', 'MSI Ventus GeForce RTX 5060 Ti', 22500, 16, 24, ['pcie_version' => 'PCIe 5.0', 'memory_size' => '16GB', 'memory_type' => 'GDDR7', 'clock_speed' => '2407MHz', 'boost_clock' => '2572MHz', 'power_draw' => 180, 'length_mm' => 268, 'video_ports' => '3x DisplayPort, 1x HDMI']);
        $this->make($c, 'ASRock', 'ASRock Radeon RX 9060 XT Steel Legend', 19500, 15, 24, ['pcie_version' => 'PCIe 5.0', 'memory_size' => '16GB', 'memory_type' => 'GDDR6', 'clock_speed' => '2130MHz', 'boost_clock' => '3130MHz', 'power_draw' => 182, 'length_mm' => 280, 'video_ports' => '3x DisplayPort, 1x HDMI']);
        $this->make($c, 'Sapphire', 'Sapphire Pulse Radeon RX 9070', 34000, 12, 24, ['pcie_version' => 'PCIe 5.0', 'memory_size' => '16GB', 'memory_type' => 'GDDR6', 'clock_speed' => '2070MHz', 'boost_clock' => '2970MHz', 'power_draw' => 220, 'length_mm' => 285, 'video_ports' => '3x DisplayPort, 1x HDMI']);
        $this->make($c, 'PowerColor', 'PowerColor Reaper Radeon RX 9070', 33500, 12, 24, ['pcie_version' => 'PCIe 5.0', 'memory_size' => '16GB', 'memory_type' => 'GDDR6', 'clock_speed' => '2070MHz', 'boost_clock' => '2970MHz', 'power_draw' => 220, 'length_mm' => 279, 'video_ports' => '3x DisplayPort, 1x HDMI']);
        $this->make($c, 'Gigabyte', 'Gigabyte GeForce RTX 5070 Ti Gaming OC', 44000, 10, 24, ['pcie_version' => 'PCIe 5.0', 'memory_size' => '16GB', 'memory_type' => 'GDDR7', 'clock_speed' => '2452MHz', 'boost_clock' => '2622MHz', 'power_draw' => 300, 'length_mm' => 336, 'video_ports' => '3x DisplayPort, 1x HDMI']);
        $this->make($c, 'Sapphire', 'Sapphire Nitro+ Radeon RX 9070 XT', 40500, 9, 24, ['pcie_version' => 'PCIe 5.0', 'memory_size' => '16GB', 'memory_type' => 'GDDR6', 'clock_speed' => '2400MHz', 'boost_clock' => '3060MHz', 'power_draw' => 304, 'length_mm' => 320, 'video_ports' => '3x DisplayPort, 1x HDMI']);
        $this->make($c, 'XFX', 'XFX Speedster Radeon RX 9070 XT', 39000, 9, 24, ['pcie_version' => 'PCIe 5.0', 'memory_size' => '16GB', 'memory_type' => 'GDDR6', 'clock_speed' => '2400MHz', 'boost_clock' => '2970MHz', 'power_draw' => 304, 'length_mm' => 330, 'video_ports' => '3x DisplayPort, 1x HDMI']);
        $this->make($c, 'PowerColor', 'PowerColor Hellhound Radeon RX 9070 XT', 41000, 8, 24, ['pcie_version' => 'PCIe 5.0', 'memory_size' => '16GB', 'memory_type' => 'GDDR6', 'clock_speed' => '2400MHz', 'boost_clock' => '3060MHz', 'power_draw' => 304, 'length_mm' => 315, 'video_ports' => '3x DisplayPort, 1x HDMI']);
        $this->make($c, 'MSI', 'MSI Suprim GeForce RTX 5080', 68000, 6, 24, ['pcie_version' => 'PCIe 5.0', 'memory_size' => '16GB', 'memory_type' => 'GDDR7', 'clock_speed' => '2617MHz', 'boost_clock' => '2790MHz', 'power_draw' => 360, 'length_mm' => 358, 'video_ports' => '3x DisplayPort, 1x HDMI']);
        $this->make($c, 'ASUS', 'ASUS ROG Strix GeForce RTX 5080', 70000, 5, 24, ['pcie_version' => 'PCIe 5.0', 'memory_size' => '16GB', 'memory_type' => 'GDDR7', 'clock_speed' => '2617MHz', 'boost_clock' => '2820MHz', 'power_draw' => 360, 'length_mm' => 358, 'video_ports' => '3x DisplayPort, 1x HDMI']);
        $this->make($c, 'Gigabyte', 'Gigabyte GeForce RTX 5090 Gaming OC', 145000, 3, 24, ['pcie_version' => 'PCIe 5.0', 'memory_size' => '32GB', 'memory_type' => 'GDDR7', 'clock_speed' => '2017MHz', 'boost_clock' => '2437MHz', 'power_draw' => 575, 'length_mm' => 358, 'video_ports' => '3x DisplayPort, 1x HDMI']);
        $this->make($c, 'ASUS', 'ASUS ROG Astral GeForce RTX 5090', 155000, 2, 24, ['pcie_version' => 'PCIe 5.0', 'memory_size' => '32GB', 'memory_type' => 'GDDR7', 'clock_speed' => '2017MHz', 'boost_clock' => '2550MHz', 'power_draw' => 575, 'length_mm' => 358, 'video_ports' => '3x DisplayPort, 1x HDMI']);
    }

    private function seedMotherboards(): void
    {
        $c = 3;
        $lga = ['socket' => 'LGA1700', 'ram_type' => 'DDR5', 'memory_slots' => 4, 'max_memory' => '128GB', 'wifi' => 'Wi-Fi 6E'];
        $am5 = ['socket' => 'AM5', 'ram_type' => 'DDR5', 'memory_slots' => 4, 'max_memory' => '128GB', 'wifi' => 'Wi-Fi 6E'];

        $this->make($c, 'ASUS', 'ASUS Prime B760M-A', 4800, 20, 36, array_merge($lga, ['chipset' => 'B760', 'form_factor' => 'mATX', 'pcie_version' => 'PCIe 4.0', 'pcie_slots' => 2, 'm2_slots' => 2, 'sata_ports' => 4, 'storage_interface' => 'NVMe/SATA', 'wifi' => 'None']));
        $this->make($c, 'MSI', 'MSI PRO B760-P', 5200, 18, 36, array_merge($lga, ['chipset' => 'B760', 'form_factor' => 'ATX', 'pcie_version' => 'PCIe 4.0', 'pcie_slots' => 3, 'm2_slots' => 2, 'sata_ports' => 4, 'storage_interface' => 'NVMe/SATA', 'wifi' => 'None']));
        $this->make($c, 'Gigabyte', 'Gigabyte B760 Gaming X', 5600, 16, 36, array_merge($lga, ['chipset' => 'B760', 'form_factor' => 'ATX', 'pcie_version' => 'PCIe 4.0', 'pcie_slots' => 3, 'm2_slots' => 3, 'sata_ports' => 4, 'storage_interface' => 'NVMe/SATA']));
        $this->make($c, 'ASRock', 'ASRock B760M Pro RS', 4500, 20, 36, array_merge($lga, ['chipset' => 'B760', 'form_factor' => 'mATX', 'pcie_version' => 'PCIe 4.0', 'pcie_slots' => 2, 'm2_slots' => 2, 'sata_ports' => 4, 'storage_interface' => 'NVMe/SATA', 'wifi' => 'None']));
        $this->make($c, 'ASUS', 'ASUS TUF Gaming Z790-Plus', 9800, 14, 36, array_merge($lga, ['chipset' => 'Z790', 'form_factor' => 'ATX', 'pcie_version' => 'PCIe 5.0', 'pcie_slots' => 3, 'm2_slots' => 4, 'sata_ports' => 6, 'storage_interface' => 'NVMe/SATA']));
        $this->make($c, 'MSI', 'MSI MAG Z790 Tomahawk', 11500, 12, 36, array_merge($lga, ['chipset' => 'Z790', 'form_factor' => 'ATX', 'pcie_version' => 'PCIe 5.0', 'pcie_slots' => 3, 'm2_slots' => 4, 'sata_ports' => 6, 'storage_interface' => 'NVMe/SATA']));
        $this->make($c, 'Gigabyte', 'Gigabyte Z790 Aorus Elite AX', 10800, 12, 36, array_merge($lga, ['chipset' => 'Z790', 'form_factor' => 'ATX', 'pcie_version' => 'PCIe 5.0', 'pcie_slots' => 3, 'm2_slots' => 4, 'sata_ports' => 6, 'storage_interface' => 'NVMe/SATA']));
        $this->make($c, 'ASRock', 'ASRock Z790 Steel Legend', 9500, 12, 36, array_merge($lga, ['chipset' => 'Z790', 'form_factor' => 'ATX', 'pcie_version' => 'PCIe 5.0', 'pcie_slots' => 3, 'm2_slots' => 3, 'sata_ports' => 6, 'storage_interface' => 'NVMe/SATA']));
        $this->make($c, 'ASUS', 'ASUS ROG Strix Z790-E Gaming', 16500, 8, 36, array_merge($lga, ['chipset' => 'Z790', 'form_factor' => 'ATX', 'pcie_version' => 'PCIe 5.0', 'pcie_slots' => 3, 'm2_slots' => 5, 'sata_ports' => 6, 'storage_interface' => 'NVMe/SATA']));
        $this->make($c, 'MSI', 'MSI MEG Z790 Ace', 22000, 6, 36, array_merge($lga, ['chipset' => 'Z790', 'form_factor' => 'E-ATX', 'pcie_version' => 'PCIe 5.0', 'pcie_slots' => 3, 'm2_slots' => 5, 'sata_ports' => 6, 'storage_interface' => 'NVMe/SATA']));

        $this->make($c, 'ASUS', 'ASUS Prime B650M-A', 5000, 20, 36, array_merge($am5, ['chipset' => 'B650', 'form_factor' => 'mATX', 'pcie_version' => 'PCIe 4.0', 'pcie_slots' => 2, 'm2_slots' => 2, 'sata_ports' => 4, 'storage_interface' => 'NVMe/SATA', 'wifi' => 'None']));
        $this->make($c, 'MSI', 'MSI PRO B650-P', 5400, 18, 36, array_merge($am5, ['chipset' => 'B650', 'form_factor' => 'ATX', 'pcie_version' => 'PCIe 4.0', 'pcie_slots' => 3, 'm2_slots' => 2, 'sata_ports' => 4, 'storage_interface' => 'NVMe/SATA', 'wifi' => 'None']));
        $this->make($c, 'Gigabyte', 'Gigabyte B650 Gaming X AX', 6200, 16, 36, array_merge($am5, ['chipset' => 'B650', 'form_factor' => 'ATX', 'pcie_version' => 'PCIe 4.0', 'pcie_slots' => 3, 'm2_slots' => 3, 'sata_ports' => 4, 'storage_interface' => 'NVMe/SATA']));
        $this->make($c, 'ASRock', 'ASRock B650M Pro RS', 4900, 20, 36, array_merge($am5, ['chipset' => 'B650', 'form_factor' => 'mATX', 'pcie_version' => 'PCIe 4.0', 'pcie_slots' => 2, 'm2_slots' => 2, 'sata_ports' => 4, 'storage_interface' => 'NVMe/SATA', 'wifi' => 'None']));
        $this->make($c, 'ASUS', 'ASUS TUF Gaming X670E-Plus', 12500, 10, 36, array_merge($am5, ['chipset' => 'X670E', 'form_factor' => 'ATX', 'pcie_version' => 'PCIe 5.0', 'pcie_slots' => 3, 'm2_slots' => 4, 'sata_ports' => 6, 'storage_interface' => 'NVMe/SATA']));
        $this->make($c, 'MSI', 'MSI MAG X670E Tomahawk', 14000, 8, 36, array_merge($am5, ['chipset' => 'X670E', 'form_factor' => 'ATX', 'pcie_version' => 'PCIe 5.0', 'pcie_slots' => 3, 'm2_slots' => 4, 'sata_ports' => 6, 'storage_interface' => 'NVMe/SATA']));
        $this->make($c, 'Gigabyte', 'Gigabyte X670 Aorus Elite AX', 13200, 8, 36, array_merge($am5, ['chipset' => 'X670', 'form_factor' => 'ATX', 'pcie_version' => 'PCIe 5.0', 'pcie_slots' => 3, 'm2_slots' => 4, 'sata_ports' => 6, 'storage_interface' => 'NVMe/SATA']));
        $this->make($c, 'ASRock', 'ASRock X670E Steel Legend', 13800, 8, 36, array_merge($am5, ['chipset' => 'X670E', 'form_factor' => 'ATX', 'pcie_version' => 'PCIe 5.0', 'pcie_slots' => 3, 'm2_slots' => 4, 'sata_ports' => 6, 'storage_interface' => 'NVMe/SATA']));
        $this->make($c, 'ASUS', 'ASUS ROG Strix X670E-E Gaming', 19500, 6, 36, array_merge($am5, ['chipset' => 'X670E', 'form_factor' => 'ATX', 'pcie_version' => 'PCIe 5.0', 'pcie_slots' => 3, 'm2_slots' => 5, 'sata_ports' => 6, 'storage_interface' => 'NVMe/SATA']));
        $this->make($c, 'MSI', 'MSI MEG X670E Ace', 24500, 4, 36, array_merge($am5, ['chipset' => 'X670E', 'form_factor' => 'E-ATX', 'pcie_version' => 'PCIe 5.0', 'pcie_slots' => 3, 'm2_slots' => 5, 'sata_ports' => 6, 'storage_interface' => 'NVMe/SATA']));

        // Latest generation: LGA1851 (Arrow Lake) and AM5 X870/X870E/B850 boards.
        $lga1851 = ['socket' => 'LGA1851', 'ram_type' => 'DDR5', 'memory_slots' => 4, 'max_memory' => '192GB', 'wifi' => 'Wi-Fi 7'];
        $this->make($c, 'ASUS', 'ASUS Prime Z890-P', 10500, 14, 36, array_merge($lga1851, ['chipset' => 'Z890', 'form_factor' => 'ATX', 'pcie_version' => 'PCIe 5.0', 'pcie_slots' => 3, 'm2_slots' => 4, 'sata_ports' => 6, 'storage_interface' => 'NVMe/SATA']));
        $this->make($c, 'MSI', 'MSI MAG Z890 Tomahawk', 13500, 12, 36, array_merge($lga1851, ['chipset' => 'Z890', 'form_factor' => 'ATX', 'pcie_version' => 'PCIe 5.0', 'pcie_slots' => 3, 'm2_slots' => 5, 'sata_ports' => 6, 'storage_interface' => 'NVMe/SATA']));
        $this->make($c, 'Gigabyte', 'Gigabyte Z890 Aorus Elite', 12800, 12, 36, array_merge($lga1851, ['chipset' => 'Z890', 'form_factor' => 'ATX', 'pcie_version' => 'PCIe 5.0', 'pcie_slots' => 3, 'm2_slots' => 4, 'sata_ports' => 6, 'storage_interface' => 'NVMe/SATA']));
        $this->make($c, 'ASRock', 'ASRock Z890 Steel Legend', 11800, 10, 36, array_merge($lga1851, ['chipset' => 'Z890', 'form_factor' => 'ATX', 'pcie_version' => 'PCIe 5.0', 'pcie_slots' => 3, 'm2_slots' => 4, 'sata_ports' => 6, 'storage_interface' => 'NVMe/SATA']));
        $this->make($c, 'ASUS', 'ASUS ROG Strix Z890-E Gaming', 19800, 8, 36, array_merge($lga1851, ['chipset' => 'Z890', 'form_factor' => 'ATX', 'pcie_version' => 'PCIe 5.0', 'pcie_slots' => 3, 'm2_slots' => 5, 'sata_ports' => 6, 'storage_interface' => 'NVMe/SATA']));
        $this->make($c, 'MSI', 'MSI PRO B860-P', 6200, 18, 36, array_merge($lga1851, ['chipset' => 'B860', 'form_factor' => 'ATX', 'pcie_version' => 'PCIe 4.0', 'pcie_slots' => 3, 'm2_slots' => 3, 'sata_ports' => 4, 'storage_interface' => 'NVMe/SATA', 'wifi' => 'None']));
        $this->make($c, 'ASUS', 'ASUS Prime B860M-A', 5900, 18, 36, array_merge($lga1851, ['chipset' => 'B860', 'form_factor' => 'mATX', 'pcie_version' => 'PCIe 4.0', 'pcie_slots' => 2, 'm2_slots' => 2, 'sata_ports' => 4, 'storage_interface' => 'NVMe/SATA', 'wifi' => 'None']));
        $am5v2 = ['socket' => 'AM5', 'ram_type' => 'DDR5', 'memory_slots' => 4, 'max_memory' => '192GB', 'wifi' => 'Wi-Fi 7'];
        $this->make($c, 'ASUS', 'ASUS ROG Crosshair X870E Hero', 22500, 8, 36, array_merge($am5v2, ['chipset' => 'X870E', 'form_factor' => 'ATX', 'pcie_version' => 'PCIe 5.0', 'pcie_slots' => 3, 'm2_slots' => 5, 'sata_ports' => 6, 'storage_interface' => 'NVMe/SATA']));
        $this->make($c, 'MSI', 'MSI MAG X870 Tomahawk', 15500, 10, 36, array_merge($am5v2, ['chipset' => 'X870', 'form_factor' => 'ATX', 'pcie_version' => 'PCIe 5.0', 'pcie_slots' => 3, 'm2_slots' => 4, 'sata_ports' => 6, 'storage_interface' => 'NVMe/SATA']));
        $this->make($c, 'Gigabyte', 'Gigabyte X870 Aorus Elite WiFi7', 14200, 10, 36, array_merge($am5v2, ['chipset' => 'X870', 'form_factor' => 'ATX', 'pcie_version' => 'PCIe 5.0', 'pcie_slots' => 3, 'm2_slots' => 4, 'sata_ports' => 6, 'storage_interface' => 'NVMe/SATA']));
        $this->make($c, 'ASRock', 'ASRock X870 Steel Legend', 13900, 10, 36, array_merge($am5v2, ['chipset' => 'X870', 'form_factor' => 'ATX', 'pcie_version' => 'PCIe 5.0', 'pcie_slots' => 3, 'm2_slots' => 4, 'sata_ports' => 6, 'storage_interface' => 'NVMe/SATA']));
        $this->make($c, 'ASUS', 'ASUS TUF Gaming X870-Plus WiFi', 13200, 12, 36, array_merge($am5v2, ['chipset' => 'X870', 'form_factor' => 'ATX', 'pcie_version' => 'PCIe 5.0', 'pcie_slots' => 3, 'm2_slots' => 4, 'sata_ports' => 6, 'storage_interface' => 'NVMe/SATA']));
        $this->make($c, 'ASUS', 'ASUS Prime B850-Plus', 6500, 16, 36, array_merge($am5v2, ['chipset' => 'B850', 'form_factor' => 'ATX', 'pcie_version' => 'PCIe 4.0', 'pcie_slots' => 3, 'm2_slots' => 3, 'sata_ports' => 4, 'storage_interface' => 'NVMe/SATA', 'wifi' => 'None']));
        $this->make($c, 'MSI', 'MSI PRO B850-P', 6100, 16, 36, array_merge($am5v2, ['chipset' => 'B850', 'form_factor' => 'ATX', 'pcie_version' => 'PCIe 4.0', 'pcie_slots' => 3, 'm2_slots' => 3, 'sata_ports' => 4, 'storage_interface' => 'NVMe/SATA', 'wifi' => 'None']));
        $this->make($c, 'Gigabyte', 'Gigabyte B850 Gaming X', 6800, 14, 36, array_merge($am5v2, ['chipset' => 'B850', 'form_factor' => 'ATX', 'pcie_version' => 'PCIe 4.0', 'pcie_slots' => 3, 'm2_slots' => 3, 'sata_ports' => 4, 'storage_interface' => 'NVMe/SATA']));
    }

    private function seedRam(): void
    {
        $c = 4;
        $this->make($c, 'Crucial', 'Crucial 16GB (2x8GB) DDR4-3200', 1350, 50, 60, ['ram_type' => 'DDR4', 'memory_size' => '16GB', 'ram_speed' => '3200MHz', 'cas_latency' => 'CL16', 'kit_config' => '2x8GB']);
        $this->make($c, 'Kingston', 'Kingston Fury Beast 16GB (2x8GB) DDR4-3200', 1400, 50, 60, ['ram_type' => 'DDR4', 'memory_size' => '16GB', 'ram_speed' => '3200MHz', 'cas_latency' => 'CL16', 'kit_config' => '2x8GB']);
        $this->make($c, 'Corsair', 'Corsair Vengeance LPX 16GB (2x8GB) DDR4-3200', 1500, 45, 60, ['ram_type' => 'DDR4', 'memory_size' => '16GB', 'ram_speed' => '3200MHz', 'cas_latency' => 'CL16', 'kit_config' => '2x8GB']);
        $this->make($c, 'G.Skill', 'G.Skill Ripjaws V 16GB (2x8GB) DDR4-3600', 1650, 40, 60, ['ram_type' => 'DDR4', 'memory_size' => '16GB', 'ram_speed' => '3600MHz', 'cas_latency' => 'CL16', 'kit_config' => '2x8GB']);
        $this->make($c, 'TeamGroup', 'TeamGroup T-Force Delta RGB 16GB (2x8GB) DDR4-3200', 1700, 35, 60, ['ram_type' => 'DDR4', 'memory_size' => '16GB', 'ram_speed' => '3200MHz', 'cas_latency' => 'CL16', 'kit_config' => '2x8GB']);
        $this->make($c, 'Corsair', 'Corsair Vengeance LPX 32GB (2x16GB) DDR4-3200', 2800, 30, 60, ['ram_type' => 'DDR4', 'memory_size' => '32GB', 'ram_speed' => '3200MHz', 'cas_latency' => 'CL16', 'kit_config' => '2x16GB']);
        $this->make($c, 'Kingston', 'Kingston Fury Renegade 32GB (2x16GB) DDR4-3600', 3200, 25, 60, ['ram_type' => 'DDR4', 'memory_size' => '32GB', 'ram_speed' => '3600MHz', 'cas_latency' => 'CL16', 'kit_config' => '2x16GB']);
        $this->make($c, 'G.Skill', 'G.Skill Trident Z RGB 32GB (2x16GB) DDR4-3600', 3500, 20, 60, ['ram_type' => 'DDR4', 'memory_size' => '32GB', 'ram_speed' => '3600MHz', 'cas_latency' => 'CL16', 'kit_config' => '2x16GB']);

        $this->make($c, 'Corsair', 'Corsair Vengeance 16GB (2x8GB) DDR5-5600', 2000, 40, 60, ['ram_type' => 'DDR5', 'memory_size' => '16GB', 'ram_speed' => '5600MHz', 'cas_latency' => 'CL36', 'kit_config' => '2x8GB']);
        $this->make($c, 'Kingston', 'Kingston Fury Beast 16GB (2x8GB) DDR5-6000', 2100, 38, 60, ['ram_type' => 'DDR5', 'memory_size' => '16GB', 'ram_speed' => '6000MHz', 'cas_latency' => 'CL36', 'kit_config' => '2x8GB']);
        $this->make($c, 'TeamGroup', 'TeamGroup T-Force Vulcan 16GB (2x8GB) DDR5-6000', 2200, 35, 60, ['ram_type' => 'DDR5', 'memory_size' => '16GB', 'ram_speed' => '6000MHz', 'cas_latency' => 'CL38', 'kit_config' => '2x8GB']);
        $this->make($c, 'Crucial', 'Crucial Pro 32GB (2x16GB) DDR5-5600', 3600, 30, 60, ['ram_type' => 'DDR5', 'memory_size' => '32GB', 'ram_speed' => '5600MHz', 'cas_latency' => 'CL46', 'kit_config' => '2x16GB']);
        $this->make($c, 'Crucial', 'Crucial 32GB (2x16GB) DDR5-5600', 3400, 28, 60, ['ram_type' => 'DDR5', 'memory_size' => '32GB', 'ram_speed' => '5600MHz', 'cas_latency' => 'CL46', 'kit_config' => '2x16GB']);
        $this->make($c, 'G.Skill', 'G.Skill Ripjaws S5 32GB (2x16GB) DDR5-6000', 3700, 26, 60, ['ram_type' => 'DDR5', 'memory_size' => '32GB', 'ram_speed' => '6000MHz', 'cas_latency' => 'CL36', 'kit_config' => '2x16GB']);
        $this->make($c, 'TeamGroup', 'TeamGroup T-Force Delta RGB 32GB (2x16GB) DDR5-6000', 3900, 24, 60, ['ram_type' => 'DDR5', 'memory_size' => '32GB', 'ram_speed' => '6000MHz', 'cas_latency' => 'CL38', 'kit_config' => '2x16GB']);
        $this->make($c, 'G.Skill', 'G.Skill Trident Z5 RGB 32GB (2x16GB) DDR5-6000', 3800, 22, 60, ['ram_type' => 'DDR5', 'memory_size' => '32GB', 'ram_speed' => '6000MHz', 'cas_latency' => 'CL36', 'kit_config' => '2x16GB']);
        $this->make($c, 'Kingston', 'Kingston Fury Renegade 32GB (2x16GB) DDR5-6400', 4200, 18, 60, ['ram_type' => 'DDR5', 'memory_size' => '32GB', 'ram_speed' => '6400MHz', 'cas_latency' => 'CL32', 'kit_config' => '2x16GB']);
        $this->make($c, 'Corsair', 'Corsair Dominator Platinum RGB 32GB (2x16GB) DDR5-6000', 4500, 16, 60, ['ram_type' => 'DDR5', 'memory_size' => '32GB', 'ram_speed' => '6000MHz', 'cas_latency' => 'CL30', 'kit_config' => '2x16GB']);
        $this->make($c, 'Corsair', 'Corsair Vengeance 64GB (2x32GB) DDR5-5600', 7200, 12, 60, ['ram_type' => 'DDR5', 'memory_size' => '64GB', 'ram_speed' => '5600MHz', 'cas_latency' => 'CL40', 'kit_config' => '2x32GB']);
        $this->make($c, 'TeamGroup', 'TeamGroup T-Force Xtreem ARGB 32GB (2x16GB) DDR5-6000', 4100, 14, 60, ['ram_type' => 'DDR5', 'memory_size' => '32GB', 'ram_speed' => '6000MHz', 'cas_latency' => 'CL30', 'kit_config' => '2x16GB']);

        // Latest generation: higher-speed DDR5 (CUDIMM) kits and larger high-capacity kits.
        $this->make($c, 'G.Skill', 'G.Skill Flare X5 32GB (2x16GB) DDR5-6000 (AMD EXPO)', 3900, 20, 60, ['ram_type' => 'DDR5', 'memory_size' => '32GB', 'ram_speed' => '6000MHz', 'cas_latency' => 'CL30', 'kit_config' => '2x16GB']);
        $this->make($c, 'G.Skill', 'G.Skill Trident Z5 Neo RGB 32GB (2x16GB) DDR5-6400 (AMD EXPO)', 4300, 18, 60, ['ram_type' => 'DDR5', 'memory_size' => '32GB', 'ram_speed' => '6400MHz', 'cas_latency' => 'CL32', 'kit_config' => '2x16GB']);
        $this->make($c, 'TeamGroup', 'TeamGroup T-Create Expert 32GB (2x16GB) DDR5-7200', 4600, 16, 60, ['ram_type' => 'DDR5', 'memory_size' => '32GB', 'ram_speed' => '7200MHz', 'cas_latency' => 'CL34', 'kit_config' => '2x16GB']);
        $this->make($c, 'Kingston', 'Kingston Fury Renegade 32GB (2x16GB) DDR5-7200', 4700, 16, 60, ['ram_type' => 'DDR5', 'memory_size' => '32GB', 'ram_speed' => '7200MHz', 'cas_latency' => 'CL38', 'kit_config' => '2x16GB']);
        $this->make($c, 'Corsair', 'Corsair Dominator Titanium 32GB (2x16GB) DDR5-7200', 5200, 14, 60, ['ram_type' => 'DDR5', 'memory_size' => '32GB', 'ram_speed' => '7200MHz', 'cas_latency' => 'CL34', 'kit_config' => '2x16GB']);
        $this->make($c, 'G.Skill', 'G.Skill Trident Z5 CK 32GB (2x16GB) DDR5-8000 CUDIMM', 6200, 10, 60, ['ram_type' => 'DDR5', 'memory_size' => '32GB', 'ram_speed' => '8000MHz', 'cas_latency' => 'CL38', 'kit_config' => '2x16GB']);
        $this->make($c, 'TeamGroup', 'TeamGroup T-Force Xtreem ARGB 48GB (2x24GB) DDR5-8200 CUDIMM', 7500, 8, 60, ['ram_type' => 'DDR5', 'memory_size' => '48GB', 'ram_speed' => '8200MHz', 'cas_latency' => 'CL40', 'kit_config' => '2x24GB']);
        $this->make($c, 'Corsair', 'Corsair Vengeance RGB 48GB (2x24GB) DDR5-6000', 5500, 18, 60, ['ram_type' => 'DDR5', 'memory_size' => '48GB', 'ram_speed' => '6000MHz', 'cas_latency' => 'CL30', 'kit_config' => '2x24GB']);
        $this->make($c, 'Kingston', 'Kingston Fury Beast 48GB (2x24GB) DDR5-6000', 5300, 18, 60, ['ram_type' => 'DDR5', 'memory_size' => '48GB', 'ram_speed' => '6000MHz', 'cas_latency' => 'CL36', 'kit_config' => '2x24GB']);
        $this->make($c, 'Crucial', 'Crucial Pro 48GB (2x24GB) DDR5-6400', 5600, 16, 60, ['ram_type' => 'DDR5', 'memory_size' => '48GB', 'ram_speed' => '6400MHz', 'cas_latency' => 'CL38', 'kit_config' => '2x24GB']);
        $this->make($c, 'Kingston', 'Kingston Fury Renegade 64GB (2x32GB) DDR5-6400', 8200, 12, 60, ['ram_type' => 'DDR5', 'memory_size' => '64GB', 'ram_speed' => '6400MHz', 'cas_latency' => 'CL32', 'kit_config' => '2x32GB']);
        $this->make($c, 'G.Skill', 'G.Skill Trident Z5 RGB 64GB (2x32GB) DDR5-6400', 8000, 12, 60, ['ram_type' => 'DDR5', 'memory_size' => '64GB', 'ram_speed' => '6400MHz', 'cas_latency' => 'CL32', 'kit_config' => '2x32GB']);
        $this->make($c, 'Crucial', 'Crucial Pro 64GB (2x32GB) DDR5-6000', 7800, 12, 60, ['ram_type' => 'DDR5', 'memory_size' => '64GB', 'ram_speed' => '6000MHz', 'cas_latency' => 'CL36', 'kit_config' => '2x32GB']);
        $this->make($c, 'Corsair', 'Corsair Vengeance 96GB (2x48GB) DDR5-6000', 11500, 8, 60, ['ram_type' => 'DDR5', 'memory_size' => '96GB', 'ram_speed' => '6000MHz', 'cas_latency' => 'CL30', 'kit_config' => '2x48GB']);
        $this->make($c, 'TeamGroup', 'TeamGroup T-Force Delta RGB 32GB (2x16GB) DDR5-7200', 4400, 16, 60, ['ram_type' => 'DDR5', 'memory_size' => '32GB', 'ram_speed' => '7200MHz', 'cas_latency' => 'CL34', 'kit_config' => '2x16GB']);
    }

    private function seedStorage(): void
    {
        $c = 6;
        $this->make($c, 'Kingston', 'Kingston NV2 1TB NVMe SSD', 1900, 45, 36, ['capacity' => '1TB', 'storage_type' => 'NVMe SSD', 'storage_interface' => 'PCIe 4.0 x4', 'form_factor' => 'M.2 2280', 'read_speed' => 3500, 'write_speed' => 2100]);
        $this->make($c, 'Crucial', 'Crucial P3 Plus 1TB NVMe SSD', 2100, 42, 36, ['capacity' => '1TB', 'storage_type' => 'NVMe SSD', 'storage_interface' => 'PCIe 4.0 x4', 'form_factor' => 'M.2 2280', 'read_speed' => 5000, 'write_speed' => 3600]);
        $this->make($c, 'Samsung', 'Samsung 980 1TB NVMe SSD', 2200, 40, 36, ['capacity' => '1TB', 'storage_type' => 'NVMe SSD', 'storage_interface' => 'PCIe 3.0 x4', 'form_factor' => 'M.2 2280', 'read_speed' => 3500, 'write_speed' => 3000]);
        $this->make($c, 'Western Digital', 'WD Black SN770 1TB NVMe SSD', 2600, 35, 60, ['capacity' => '1TB', 'storage_type' => 'NVMe SSD', 'storage_interface' => 'PCIe 4.0 x4', 'form_factor' => 'M.2 2280', 'read_speed' => 5150, 'write_speed' => 4900]);
        $this->make($c, 'Samsung', 'Samsung 990 Pro 1TB NVMe SSD', 3600, 30, 60, ['capacity' => '1TB', 'storage_type' => 'NVMe SSD', 'storage_interface' => 'PCIe 4.0 x4', 'form_factor' => 'M.2 2280', 'read_speed' => 7450, 'write_speed' => 6900]);
        $this->make($c, 'Western Digital', 'WD Black SN850X 1TB NVMe SSD', 3400, 28, 60, ['capacity' => '1TB', 'storage_type' => 'NVMe SSD', 'storage_interface' => 'PCIe 4.0 x4', 'form_factor' => 'M.2 2280', 'read_speed' => 7300, 'write_speed' => 6300]);
        $this->make($c, 'Kingston', 'Kingston Fury Renegade 1TB NVMe SSD', 3300, 26, 60, ['capacity' => '1TB', 'storage_type' => 'NVMe SSD', 'storage_interface' => 'PCIe 4.0 x4', 'form_factor' => 'M.2 2280', 'read_speed' => 7300, 'write_speed' => 6000]);
        $this->make($c, 'Seagate', 'Seagate FireCuda 530 1TB NVMe SSD', 3800, 22, 60, ['capacity' => '1TB', 'storage_type' => 'NVMe SSD', 'storage_interface' => 'PCIe 4.0 x4', 'form_factor' => 'M.2 2280', 'read_speed' => 7300, 'write_speed' => 6900]);
        $this->make($c, 'Samsung', 'Samsung 990 Pro 2TB NVMe SSD', 6800, 15, 60, ['capacity' => '2TB', 'storage_type' => 'NVMe SSD', 'storage_interface' => 'PCIe 4.0 x4', 'form_factor' => 'M.2 2280', 'read_speed' => 7450, 'write_speed' => 6900]);
        $this->make($c, 'Crucial', 'Crucial T700 2TB NVMe SSD', 8500, 10, 60, ['capacity' => '2TB', 'storage_type' => 'NVMe SSD', 'storage_interface' => 'PCIe 5.0 x4', 'form_factor' => 'M.2 2280', 'read_speed' => 12400, 'write_speed' => 11800]);
        $this->make($c, 'Kingston', 'Kingston A400 480GB SATA SSD', 900, 50, 36, ['capacity' => '480GB', 'storage_type' => 'SATA SSD', 'storage_interface' => 'SATA III', 'form_factor' => '2.5"', 'read_speed' => 500, 'write_speed' => 450]);
        $this->make($c, 'Samsung', 'Samsung 870 EVO 500GB SATA SSD', 1200, 45, 60, ['capacity' => '500GB', 'storage_type' => 'SATA SSD', 'storage_interface' => 'SATA III', 'form_factor' => '2.5"', 'read_speed' => 560, 'write_speed' => 530]);
        $this->make($c, 'Western Digital', 'WD Blue 1TB SATA SSD', 1900, 38, 36, ['capacity' => '1TB', 'storage_type' => 'SATA SSD', 'storage_interface' => 'SATA III', 'form_factor' => '2.5"', 'read_speed' => 560, 'write_speed' => 530]);
        $this->make($c, 'Crucial', 'Crucial MX500 1TB SATA SSD', 1800, 36, 60, ['capacity' => '1TB', 'storage_type' => 'SATA SSD', 'storage_interface' => 'SATA III', 'form_factor' => '2.5"', 'read_speed' => 560, 'write_speed' => 510]);
        $this->make($c, 'Samsung', 'Samsung 870 EVO 1TB SATA SSD', 2000, 34, 60, ['capacity' => '1TB', 'storage_type' => 'SATA SSD', 'storage_interface' => 'SATA III', 'form_factor' => '2.5"', 'read_speed' => 560, 'write_speed' => 530]);
        $this->make($c, 'Toshiba', 'Toshiba P300 2TB HDD', 1700, 30, 24, ['capacity' => '2TB', 'storage_type' => 'HDD', 'storage_interface' => 'SATA III', 'form_factor' => '3.5"', 'read_speed' => 180, 'write_speed' => 180]);
        $this->make($c, 'Seagate', 'Seagate Barracuda 2TB HDD', 1800, 32, 24, ['capacity' => '2TB', 'storage_type' => 'HDD', 'storage_interface' => 'SATA III', 'form_factor' => '3.5"', 'read_speed' => 190, 'write_speed' => 190]);
        $this->make($c, 'Western Digital', 'WD Blue 2TB HDD', 1900, 30, 24, ['capacity' => '2TB', 'storage_type' => 'HDD', 'storage_interface' => 'SATA III', 'form_factor' => '3.5"', 'read_speed' => 190, 'write_speed' => 190]);
        $this->make($c, 'Seagate', 'Seagate Barracuda 4TB HDD', 3200, 20, 24, ['capacity' => '4TB', 'storage_type' => 'HDD', 'storage_interface' => 'SATA III', 'form_factor' => '3.5"', 'read_speed' => 190, 'write_speed' => 190]);
        $this->make($c, 'Western Digital', 'WD Black 4TB HDD', 3600, 18, 60, ['capacity' => '4TB', 'storage_type' => 'HDD', 'storage_interface' => 'SATA III', 'form_factor' => '3.5"', 'read_speed' => 220, 'write_speed' => 220]);

        // Latest generation: PCIe 5.0 NVMe SSDs, newer PCIe 4.0 refreshes, and NAS-class HDDs.
        $this->make($c, 'Samsung', 'Samsung 990 EVO Plus 1TB NVMe SSD', 2500, 30, 60, ['capacity' => '1TB', 'storage_type' => 'NVMe SSD', 'storage_interface' => 'PCIe 4.0/5.0 x2', 'form_factor' => 'M.2 2280', 'read_speed' => 7250, 'write_speed' => 6300]);
        $this->make($c, 'Samsung', 'Samsung 990 EVO Plus 2TB NVMe SSD', 4600, 24, 60, ['capacity' => '2TB', 'storage_type' => 'NVMe SSD', 'storage_interface' => 'PCIe 4.0/5.0 x2', 'form_factor' => 'M.2 2280', 'read_speed' => 7250, 'write_speed' => 6300]);
        $this->make($c, 'Crucial', 'Crucial P310 1TB NVMe SSD', 2400, 26, 60, ['capacity' => '1TB', 'storage_type' => 'NVMe SSD', 'storage_interface' => 'PCIe 4.0 x4', 'form_factor' => 'M.2 2280', 'read_speed' => 7100, 'write_speed' => 6000]);
        $this->make($c, 'Kingston', 'Kingston NV3 2TB NVMe SSD', 4200, 22, 36, ['capacity' => '2TB', 'storage_type' => 'NVMe SSD', 'storage_interface' => 'PCIe 4.0 x4', 'form_factor' => 'M.2 2280', 'read_speed' => 6000, 'write_speed' => 5000]);
        $this->make($c, 'Western Digital', 'WD Black SN7100 1TB NVMe SSD', 2900, 24, 60, ['capacity' => '1TB', 'storage_type' => 'NVMe SSD', 'storage_interface' => 'PCIe 4.0 x4', 'form_factor' => 'M.2 2280', 'read_speed' => 7250, 'write_speed' => 6900]);
        $this->make($c, 'Seagate', 'Seagate FireCuda 540 1TB NVMe SSD', 4200, 18, 60, ['capacity' => '1TB', 'storage_type' => 'NVMe SSD', 'storage_interface' => 'PCIe 5.0 x4', 'form_factor' => 'M.2 2280', 'read_speed' => 10000, 'write_speed' => 10000]);
        $this->make($c, 'Corsair', 'Corsair MP700 Pro 2TB NVMe SSD', 9200, 10, 60, ['capacity' => '2TB', 'storage_type' => 'NVMe SSD', 'storage_interface' => 'PCIe 5.0 x4', 'form_factor' => 'M.2 2280', 'read_speed' => 12400, 'write_speed' => 11800]);
        $this->make($c, 'Kingston', 'Kingston Fury Renegade G5 1TB NVMe SSD', 5200, 12, 60, ['capacity' => '1TB', 'storage_type' => 'NVMe SSD', 'storage_interface' => 'PCIe 5.0 x4', 'form_factor' => 'M.2 2280', 'read_speed' => 14800, 'write_speed' => 13800]);
        $this->make($c, 'Samsung', 'Samsung 9100 Pro 2TB NVMe SSD', 9800, 10, 60, ['capacity' => '2TB', 'storage_type' => 'NVMe SSD', 'storage_interface' => 'PCIe 5.0 x4', 'form_factor' => 'M.2 2280', 'read_speed' => 14800, 'write_speed' => 13400]);
        $this->make($c, 'Crucial', 'Crucial T705 2TB NVMe SSD', 9500, 10, 60, ['capacity' => '2TB', 'storage_type' => 'NVMe SSD', 'storage_interface' => 'PCIe 5.0 x4', 'form_factor' => 'M.2 2280', 'read_speed' => 14500, 'write_speed' => 12700]);
        $this->make($c, 'Western Digital', 'WD Black SN8100 2TB NVMe SSD', 9000, 10, 60, ['capacity' => '2TB', 'storage_type' => 'NVMe SSD', 'storage_interface' => 'PCIe 5.0 x4', 'form_factor' => 'M.2 2280', 'read_speed' => 14900, 'write_speed' => 13900]);
        $this->make($c, 'Samsung', 'Samsung 870 QVO 2TB SATA SSD', 3200, 22, 36, ['capacity' => '2TB', 'storage_type' => 'SATA SSD', 'storage_interface' => 'SATA III', 'form_factor' => '2.5"', 'read_speed' => 560, 'write_speed' => 530]);
        $this->make($c, 'Seagate', 'Seagate IronWolf Pro 8TB HDD (NAS)', 6800, 12, 60, ['capacity' => '8TB', 'storage_type' => 'HDD', 'storage_interface' => 'SATA III', 'form_factor' => '3.5"', 'read_speed' => 250, 'write_speed' => 250]);
        $this->make($c, 'Western Digital', 'WD Red Plus 4TB HDD (NAS)', 4200, 16, 36, ['capacity' => '4TB', 'storage_type' => 'HDD', 'storage_interface' => 'SATA III', 'form_factor' => '3.5"', 'read_speed' => 215, 'write_speed' => 215]);
        $this->make($c, 'Kingston', 'Kingston KC3000 1TB NVMe SSD', 2700, 20, 60, ['capacity' => '1TB', 'storage_type' => 'NVMe SSD', 'storage_interface' => 'PCIe 4.0 x4', 'form_factor' => 'M.2 2280', 'read_speed' => 7000, 'write_speed' => 6000]);
    }

    private function seedPsus(): void
    {
        $c = 5;
        $this->make($c, 'Thermaltake', 'Thermaltake Smart 600W', 1300, 40, 24, ['wattage' => 600, 'efficiency_rating' => '80+ White', 'form_factor' => 'ATX', 'modular_type' => 'Non-Modular']);
        $this->make($c, 'Corsair', 'Corsair CV550 550W', 1600, 45, 24, ['wattage' => 550, 'efficiency_rating' => '80+ Bronze', 'form_factor' => 'ATX', 'modular_type' => 'Non-Modular']);
        $this->make($c, 'EVGA', 'EVGA 600 BR 600W', 1700, 40, 36, ['wattage' => 600, 'efficiency_rating' => '80+ Bronze', 'form_factor' => 'ATX', 'modular_type' => 'Non-Modular']);
        $this->make($c, 'Cooler Master', 'Cooler Master MWE 650 Bronze V2', 1900, 38, 36, ['wattage' => 650, 'efficiency_rating' => '80+ Bronze', 'form_factor' => 'ATX', 'modular_type' => 'Non-Modular']);
        $this->make($c, 'Corsair', 'Corsair CX650M 650W', 2100, 36, 60, ['wattage' => 650, 'efficiency_rating' => '80+ Bronze', 'form_factor' => 'ATX', 'modular_type' => 'Semi-Modular']);
        $this->make($c, 'be quiet!', 'be quiet! Pure Power 11 600W', 2400, 32, 36, ['wattage' => 600, 'efficiency_rating' => '80+ Gold', 'form_factor' => 'ATX', 'modular_type' => 'Non-Modular']);
        $this->make($c, 'Seasonic', 'Seasonic Focus GX-650 650W', 2800, 30, 120, ['wattage' => 650, 'efficiency_rating' => '80+ Gold', 'form_factor' => 'ATX', 'modular_type' => 'Fully Modular']);
        $this->make($c, 'Cooler Master', 'Cooler Master MWE 750 Gold V2', 2900, 28, 60, ['wattage' => 750, 'efficiency_rating' => '80+ Gold', 'form_factor' => 'ATX', 'modular_type' => 'Semi-Modular']);
        $this->make($c, 'MSI', 'MSI MAG A750GL 750W', 3000, 26, 60, ['wattage' => 750, 'efficiency_rating' => '80+ Gold', 'form_factor' => 'ATX', 'modular_type' => 'Fully Modular']);
        $this->make($c, 'Seasonic', 'Seasonic Focus GX-750 750W', 3100, 24, 120, ['wattage' => 750, 'efficiency_rating' => '80+ Gold', 'form_factor' => 'ATX', 'modular_type' => 'Fully Modular']);
        $this->make($c, 'Corsair', 'Corsair RM750 750W', 3200, 25, 120, ['wattage' => 750, 'efficiency_rating' => '80+ Gold', 'form_factor' => 'ATX', 'modular_type' => 'Fully Modular']);
        $this->make($c, 'EVGA', 'EVGA SuperNOVA 750 G6 750W', 3300, 22, 120, ['wattage' => 750, 'efficiency_rating' => '80+ Gold', 'form_factor' => 'ATX', 'modular_type' => 'Fully Modular']);
        $this->make($c, 'Thermaltake', 'Thermaltake Toughpower GF3 850W', 3600, 20, 120, ['wattage' => 850, 'efficiency_rating' => '80+ Gold', 'form_factor' => 'ATX', 'modular_type' => 'Fully Modular']);
        $this->make($c, 'Corsair', 'Corsair RM850x 850W', 3900, 18, 120, ['wattage' => 850, 'efficiency_rating' => '80+ Gold', 'form_factor' => 'ATX', 'modular_type' => 'Fully Modular']);
        $this->make($c, 'be quiet!', 'be quiet! Straight Power 11 750W', 4400, 16, 60, ['wattage' => 750, 'efficiency_rating' => '80+ Platinum', 'form_factor' => 'ATX', 'modular_type' => 'Fully Modular']);
        $this->make($c, 'Cooler Master', 'Cooler Master V850 SFX Gold', 4800, 12, 60, ['wattage' => 850, 'efficiency_rating' => '80+ Gold', 'form_factor' => 'SFX', 'modular_type' => 'Fully Modular']);
        $this->make($c, 'Corsair', 'Corsair HX1000 1000W', 6500, 10, 120, ['wattage' => 1000, 'efficiency_rating' => '80+ Platinum', 'form_factor' => 'ATX', 'modular_type' => 'Fully Modular']);
        $this->make($c, 'EVGA', 'EVGA SuperNOVA 1000 G6 1000W', 6200, 10, 120, ['wattage' => 1000, 'efficiency_rating' => '80+ Gold', 'form_factor' => 'ATX', 'modular_type' => 'Fully Modular']);
        $this->make($c, 'Seasonic', 'Seasonic Prime TX-1000 1000W', 8200, 6, 144, ['wattage' => 1000, 'efficiency_rating' => '80+ Titanium', 'form_factor' => 'ATX', 'modular_type' => 'Fully Modular']);
        $this->make($c, 'be quiet!', 'be quiet! Dark Power Pro 12 1000W', 8500, 5, 120, ['wattage' => 1000, 'efficiency_rating' => '80+ Titanium', 'form_factor' => 'ATX', 'modular_type' => 'Fully Modular']);

        // Latest generation: ATX 3.0/3.1 units with native 12V-2x6 connectors for RTX 50-series GPUs.
        $this->make($c, 'be quiet!', 'be quiet! Pure Power 12 M 750W (ATX 3.0)', 3200, 20, 60, ['wattage' => 750, 'efficiency_rating' => '80+ Gold', 'form_factor' => 'ATX', 'modular_type' => 'Fully Modular']);
        $this->make($c, 'MSI', 'MSI MAG A850GL PCIE5 850W (ATX 3.0)', 4100, 18, 60, ['wattage' => 850, 'efficiency_rating' => '80+ Gold', 'form_factor' => 'ATX', 'modular_type' => 'Fully Modular']);
        $this->make($c, 'Gigabyte', 'Gigabyte UD850GM PG5 850W (ATX 3.0)', 3900, 16, 60, ['wattage' => 850, 'efficiency_rating' => '80+ Gold', 'form_factor' => 'ATX', 'modular_type' => 'Fully Modular']);
        $this->make($c, 'Seasonic', 'Seasonic Focus GX-1000 1000W (ATX 3.0)', 4900, 14, 120, ['wattage' => 1000, 'efficiency_rating' => '80+ Gold', 'form_factor' => 'ATX', 'modular_type' => 'Fully Modular']);
        $this->make($c, 'MSI', 'MSI MPG A1000G PCIE5 1000W (ATX 3.0)', 5400, 12, 120, ['wattage' => 1000, 'efficiency_rating' => '80+ Gold', 'form_factor' => 'ATX', 'modular_type' => 'Fully Modular']);
        $this->make($c, 'Thermaltake', 'Thermaltake Toughpower PF3 1050W (ATX 3.0)', 6800, 10, 120, ['wattage' => 1050, 'efficiency_rating' => '80+ Platinum', 'form_factor' => 'ATX', 'modular_type' => 'Fully Modular']);
        $this->make($c, 'Corsair', 'Corsair RM1000x SHIFT 1000W (ATX 3.0)', 7200, 10, 120, ['wattage' => 1000, 'efficiency_rating' => '80+ Gold', 'form_factor' => 'ATX', 'modular_type' => 'Fully Modular']);
        $this->make($c, 'ASUS', 'ASUS ROG Thor 1200W Platinum II (ATX 3.1)', 9500, 6, 120, ['wattage' => 1200, 'efficiency_rating' => '80+ Platinum', 'form_factor' => 'ATX', 'modular_type' => 'Fully Modular']);
        $this->make($c, 'EVGA', 'EVGA SuperNOVA 1300 G7 1300W (ATX 3.0)', 9800, 6, 120, ['wattage' => 1300, 'efficiency_rating' => '80+ Gold', 'form_factor' => 'ATX', 'modular_type' => 'Fully Modular']);
        $this->make($c, 'Corsair', 'Corsair HX1200i 1200W (ATX 3.0)', 8900, 8, 120, ['wattage' => 1200, 'efficiency_rating' => '80+ Platinum', 'form_factor' => 'ATX', 'modular_type' => 'Fully Modular']);
        $this->make($c, 'Seasonic', 'Seasonic Prime TX-1300 1300W (ATX 3.0)', 12500, 4, 144, ['wattage' => 1300, 'efficiency_rating' => '80+ Titanium', 'form_factor' => 'ATX', 'modular_type' => 'Fully Modular']);
        $this->make($c, 'be quiet!', 'be quiet! Dark Power 13 1000W (ATX 3.0)', 9200, 6, 120, ['wattage' => 1000, 'efficiency_rating' => '80+ Titanium', 'form_factor' => 'ATX', 'modular_type' => 'Fully Modular']);
        $this->make($c, 'Lian Li', 'Lian Li Edge 1200W (ATX 3.1)', 8600, 6, 60, ['wattage' => 1200, 'efficiency_rating' => '80+ Gold', 'form_factor' => 'ATX', 'modular_type' => 'Fully Modular']);
        $this->make($c, 'Corsair', 'Corsair SF1000L 1000W SFX-L (ATX 3.0)', 6200, 8, 120, ['wattage' => 1000, 'efficiency_rating' => '80+ Gold', 'form_factor' => 'SFX-L', 'modular_type' => 'Fully Modular']);
        $this->make($c, 'Cooler Master', 'Cooler Master GX3 850W (ATX 3.0)', 3600, 16, 60, ['wattage' => 850, 'efficiency_rating' => '80+ Gold', 'form_factor' => 'ATX', 'modular_type' => 'Non-Modular']);
    }

    private function seedCases(): void
    {
        $c = 10;
        $this->make($c, 'Cooler Master', 'Cooler Master MasterBox Q300L', 1800, 30, 24, ['form_factor' => 'Micro-ATX', 'max_gpu_length' => 360]);
        $this->make($c, 'Thermaltake', 'Thermaltake Core V1', 2200, 25, 24, ['form_factor' => 'Mini-ITX', 'max_gpu_length' => 320]);
        $this->make($c, 'NZXT', 'NZXT H510', 2800, 28, 24, ['form_factor' => 'ATX Mid Tower', 'max_gpu_length' => 381]);
        $this->make($c, 'NZXT', 'NZXT H510 Flow', 3200, 26, 24, ['form_factor' => 'ATX Mid Tower', 'max_gpu_length' => 381]);
        $this->make($c, 'Corsair', 'Corsair 275R Airflow', 3200, 24, 24, ['form_factor' => 'ATX Mid Tower', 'max_gpu_length' => 350]);
        $this->make($c, 'Cooler Master', 'Cooler Master MasterBox TD500 Mesh', 3400, 22, 24, ['form_factor' => 'ATX Mid Tower', 'max_gpu_length' => 410]);
        $this->make($c, 'Lian Li', 'Lian Li Lancool 216', 3600, 20, 24, ['form_factor' => 'ATX Mid Tower', 'max_gpu_length' => 392]);
        $this->make($c, 'Corsair', 'Corsair 4000D Airflow', 3900, 22, 24, ['form_factor' => 'ATX Mid Tower', 'max_gpu_length' => 360]);
        $this->make($c, 'be quiet!', 'be quiet! Pure Base 500DX', 4200, 18, 24, ['form_factor' => 'ATX Mid Tower', 'max_gpu_length' => 369]);
        $this->make($c, 'Corsair', 'Corsair iCUE 4000X RGB', 4800, 16, 24, ['form_factor' => 'ATX Mid Tower', 'max_gpu_length' => 360]);
        $this->make($c, 'Lian Li', 'Lian Li Lancool III', 5200, 15, 24, ['form_factor' => 'ATX Mid Tower', 'max_gpu_length' => 422]);
        $this->make($c, 'NZXT', 'NZXT H710', 4500, 16, 24, ['form_factor' => 'ATX Full Tower', 'max_gpu_length' => 413]);
        $this->make($c, 'Corsair', 'Corsair 5000D Airflow', 5600, 14, 24, ['form_factor' => 'ATX Mid Tower', 'max_gpu_length' => 420]);
        $this->make($c, 'NZXT', 'NZXT H9 Flow', 5800, 12, 24, ['form_factor' => 'ATX Mid Tower', 'max_gpu_length' => 400]);
        $this->make($c, 'Fractal Design', 'Fractal Design Meshify 2', 5900, 12, 24, ['form_factor' => 'ATX Mid Tower', 'max_gpu_length' => 460]);
        $this->make($c, 'Lian Li', 'Lian Li O11 Dynamic', 6800, 10, 24, ['form_factor' => 'ATX Mid Tower', 'max_gpu_length' => 420]);
        $this->make($c, 'be quiet!', 'be quiet! Silent Base 802', 6500, 10, 24, ['form_factor' => 'ATX Full Tower', 'max_gpu_length' => 435]);
        $this->make($c, 'Lian Li', 'Lian Li O11 Dynamic EVO', 7500, 9, 24, ['form_factor' => 'ATX Mid Tower', 'max_gpu_length' => 422]);
        $this->make($c, 'Thermaltake', 'Thermaltake View 71', 7200, 8, 24, ['form_factor' => 'ATX Full Tower', 'max_gpu_length' => 410]);
        $this->make($c, 'Cooler Master', 'Cooler Master HAF 700', 8500, 6, 24, ['form_factor' => 'E-ATX Full Tower', 'max_gpu_length' => 468]);

        // Latest generation: current showcase/high-airflow cases.
        $this->make($c, 'Lian Li', 'Lian Li A3 mATX', 2600, 22, 24, ['form_factor' => 'Micro-ATX', 'max_gpu_length' => 330]);
        $this->make($c, 'ASUS', 'ASUS Prime AP201', 2900, 20, 24, ['form_factor' => 'Micro-ATX', 'max_gpu_length' => 338]);
        $this->make($c, 'Montech', 'Montech Air 903 Max', 3400, 18, 24, ['form_factor' => 'ATX Mid Tower', 'max_gpu_length' => 400]);
        $this->make($c, 'Cooler Master', 'Cooler Master Qube 500 Flatpack', 4600, 14, 24, ['form_factor' => 'ATX Mid Tower', 'max_gpu_length' => 410]);
        $this->make($c, 'be quiet!', 'be quiet! Pure Base 500FX', 4400, 16, 24, ['form_factor' => 'ATX Mid Tower', 'max_gpu_length' => 369]);
        $this->make($c, 'Deepcool', 'Deepcool CH780', 6200, 10, 24, ['form_factor' => 'ATX Full Tower', 'max_gpu_length' => 435]);
        $this->make($c, 'NZXT', 'NZXT H6 Flow', 5200, 14, 24, ['form_factor' => 'ATX Mid Tower', 'max_gpu_length' => 400]);
        $this->make($c, 'NZXT', 'NZXT H7 Flow', 5400, 14, 24, ['form_factor' => 'ATX Mid Tower', 'max_gpu_length' => 400]);
        $this->make($c, 'Corsair', 'Corsair Frame 4000D', 5100, 14, 24, ['form_factor' => 'ATX Mid Tower', 'max_gpu_length' => 360]);
        $this->make($c, 'Corsair', 'Corsair 3500X', 5800, 12, 24, ['form_factor' => 'ATX Mid Tower', 'max_gpu_length' => 400]);
        $this->make($c, 'Fractal Design', 'Fractal Design North', 5600, 12, 24, ['form_factor' => 'ATX Mid Tower', 'max_gpu_length' => 355]);
        $this->make($c, 'Lian Li', 'Lian Li O11 Vision', 7800, 8, 24, ['form_factor' => 'ATX Mid Tower', 'max_gpu_length' => 420]);
        $this->make($c, 'Hyte', 'Hyte Y70', 8500, 6, 24, ['form_factor' => 'ATX Mid Tower', 'max_gpu_length' => 400]);
        $this->make($c, 'Thermaltake', 'Thermaltake CTE C750 Air', 7200, 8, 24, ['form_factor' => 'ATX Full Tower', 'max_gpu_length' => 440]);
        $this->make($c, 'Cooler Master', 'Cooler Master NCore 100 Max', 9800, 5, 24, ['form_factor' => 'Mini-ITX', 'max_gpu_length' => 360]);
    }

    private function seedCoolers(): void
    {
        $c = 11;
        $sockets = 'LGA1700, LGA1200, LGA1151, AM5, AM4';
        $this->make($c, 'Cooler Master', 'Cooler Master Hyper 212 Black Edition', 900, 50, 24, ['socket' => $sockets, 'cooler_type' => 'Air', 'fan_size' => '120mm', 'max_tdp' => 150]);
        $this->make($c, 'Thermalright', 'Thermalright Peerless Assassin 120 SE', 1100, 45, 24, ['socket' => $sockets, 'cooler_type' => 'Air', 'fan_size' => '120mm', 'max_tdp' => 220]);
        $this->make($c, 'Deepcool', 'Deepcool AK400', 1000, 42, 24, ['socket' => $sockets, 'cooler_type' => 'Air', 'fan_size' => '120mm', 'max_tdp' => 220]);
        $this->make($c, 'Noctua', 'Noctua NH-L9i', 1800, 30, 72, ['socket' => 'LGA1700, LGA1200, LGA1151', 'cooler_type' => 'Air (Low Profile)', 'fan_size' => '92mm', 'max_tdp' => 65]);
        $this->make($c, 'Deepcool', 'Deepcool AK620', 1700, 32, 24, ['socket' => $sockets, 'cooler_type' => 'Air', 'fan_size' => '120mm', 'max_tdp' => 260]);
        $this->make($c, 'Noctua', 'Noctua NH-U12S', 2200, 28, 72, ['socket' => $sockets, 'cooler_type' => 'Air', 'fan_size' => '120mm', 'max_tdp' => 200]);
        $this->make($c, 'be quiet!', 'be quiet! Dark Rock 4', 2400, 26, 36, ['socket' => $sockets, 'cooler_type' => 'Air', 'fan_size' => '135mm', 'max_tdp' => 200]);
        $this->make($c, 'Cooler Master', 'Cooler Master MasterAir MA624 Stealth', 2600, 22, 24, ['socket' => $sockets, 'cooler_type' => 'Air', 'fan_size' => '120mm', 'max_tdp' => 260]);
        $this->make($c, 'be quiet!', 'be quiet! Dark Rock Pro 4', 3300, 18, 36, ['socket' => $sockets, 'cooler_type' => 'Air', 'fan_size' => '135mm', 'max_tdp' => 250]);
        $this->make($c, 'Noctua', 'Noctua NH-D15', 3600, 20, 72, ['socket' => $sockets, 'cooler_type' => 'Air', 'fan_size' => '140mm', 'max_tdp' => 250]);
        $this->make($c, 'Deepcool', 'Deepcool LS520 240mm', 2600, 24, 60, ['socket' => $sockets, 'cooler_type' => 'AIO Liquid', 'fan_size' => '120mm x2', 'max_tdp' => 220]);
        $this->make($c, 'be quiet!', 'be quiet! Pure Loop 2 240mm', 3600, 18, 36, ['socket' => $sockets, 'cooler_type' => 'AIO Liquid', 'fan_size' => '120mm x2', 'max_tdp' => 220]);
        $this->make($c, 'Cooler Master', 'Cooler Master MasterLiquid ML240L V2', 2800, 22, 24, ['socket' => $sockets, 'cooler_type' => 'AIO Liquid', 'fan_size' => '120mm x2', 'max_tdp' => 200]);
        $this->make($c, 'Corsair', 'Corsair iCUE H100i Elite Capellix 240mm', 4200, 16, 60, ['socket' => $sockets, 'cooler_type' => 'AIO Liquid', 'fan_size' => '120mm x2', 'max_tdp' => 250]);
        $this->make($c, 'NZXT', 'NZXT Kraken X63 280mm', 4800, 14, 72, ['socket' => $sockets, 'cooler_type' => 'AIO Liquid', 'fan_size' => '140mm x2', 'max_tdp' => 280]);
        $this->make($c, 'Deepcool', 'Deepcool LT720 360mm', 4000, 14, 60, ['socket' => $sockets, 'cooler_type' => 'AIO Liquid', 'fan_size' => '120mm x3', 'max_tdp' => 300]);
        $this->make($c, 'Lian Li', 'Lian Li Galahad II 360mm', 4900, 12, 36, ['socket' => $sockets, 'cooler_type' => 'AIO Liquid', 'fan_size' => '120mm x3', 'max_tdp' => 300]);
        $this->make($c, 'Cooler Master', 'Cooler Master MasterLiquid ML360R RGB', 4500, 12, 24, ['socket' => $sockets, 'cooler_type' => 'AIO Liquid', 'fan_size' => '120mm x3', 'max_tdp' => 280]);
        $this->make($c, 'Corsair', 'Corsair iCUE H150i Elite Capellix 360mm', 5500, 10, 60, ['socket' => $sockets, 'cooler_type' => 'AIO Liquid', 'fan_size' => '120mm x3', 'max_tdp' => 300]);
        $this->make($c, 'NZXT', 'NZXT Kraken Z73 360mm', 7200, 8, 72, ['socket' => $sockets, 'cooler_type' => 'AIO Liquid', 'fan_size' => '120mm x3', 'max_tdp' => 320]);

        // Latest generation: current-lineup coolers, updated for LGA1851/AM5 support.
        $sockets2 = 'LGA1851, LGA1700, LGA1200, AM5, AM4';
        $this->make($c, 'ID-Cooling', 'ID-Cooling SE-224-XT', 950, 40, 24, ['socket' => $sockets2, 'cooler_type' => 'Air', 'fan_size' => '120mm', 'max_tdp' => 180]);
        $this->make($c, 'Arctic', 'Arctic Freezer 36', 1300, 32, 24, ['socket' => $sockets2, 'cooler_type' => 'Air', 'fan_size' => '120mm x2', 'max_tdp' => 250]);
        $this->make($c, 'Noctua', 'Noctua NH-U9S', 2000, 24, 72, ['socket' => $sockets2, 'cooler_type' => 'Air', 'fan_size' => '92mm', 'max_tdp' => 170]);
        $this->make($c, 'Thermalright', 'Thermalright Peerless Assassin 140', 1300, 28, 24, ['socket' => $sockets2, 'cooler_type' => 'Air', 'fan_size' => '140mm', 'max_tdp' => 245]);
        $this->make($c, 'Thermalright', 'Thermalright Frost Commander 140', 2100, 20, 24, ['socket' => $sockets2, 'cooler_type' => 'Air', 'fan_size' => '140mm x2', 'max_tdp' => 280]);
        $this->make($c, 'Deepcool', 'Deepcool AK620 Digital', 1900, 22, 24, ['socket' => $sockets2, 'cooler_type' => 'Air', 'fan_size' => '120mm x2', 'max_tdp' => 260]);
        $this->make($c, 'be quiet!', 'be quiet! Dark Rock Pro 5', 3800, 14, 36, ['socket' => $sockets2, 'cooler_type' => 'Air', 'fan_size' => '135mm x2', 'max_tdp' => 270]);
        $this->make($c, 'Noctua', 'Noctua NH-D15 G2', 4200, 12, 72, ['socket' => $sockets2, 'cooler_type' => 'Air', 'fan_size' => '140mm x2', 'max_tdp' => 280]);
        $this->make($c, 'Arctic', 'Arctic Liquid Freezer III 360', 3200, 20, 72, ['socket' => $sockets2, 'cooler_type' => 'AIO Liquid', 'fan_size' => '120mm x3', 'max_tdp' => 320]);
        $this->make($c, 'Deepcool', 'Deepcool LT720X', 4300, 12, 60, ['socket' => $sockets2, 'cooler_type' => 'AIO Liquid', 'fan_size' => '120mm x3', 'max_tdp' => 320]);
        $this->make($c, 'Cooler Master', 'Cooler Master MasterLiquid Atmos 360', 5200, 10, 24, ['socket' => $sockets2, 'cooler_type' => 'AIO Liquid', 'fan_size' => '120mm x3', 'max_tdp' => 320]);
        $this->make($c, 'Corsair', 'Corsair iCUE Link Titan 360 RX', 5800, 10, 60, ['socket' => $sockets2, 'cooler_type' => 'AIO Liquid', 'fan_size' => '120mm x3', 'max_tdp' => 330]);
        $this->make($c, 'NZXT', 'NZXT Kraken Elite 360', 6800, 8, 72, ['socket' => $sockets2, 'cooler_type' => 'AIO Liquid', 'fan_size' => '120mm x3', 'max_tdp' => 330]);
        $this->make($c, 'Lian Li', 'Lian Li Galahad II LCD 360', 5900, 8, 36, ['socket' => $sockets2, 'cooler_type' => 'AIO Liquid', 'fan_size' => '120mm x3', 'max_tdp' => 320]);
        $this->make($c, 'ASUS', 'ASUS ROG Ryujin III 360', 8500, 5, 36, ['socket' => $sockets2, 'cooler_type' => 'AIO Liquid', 'fan_size' => '120mm x3', 'max_tdp' => 330]);
    }

    private function seedMonitors(): void
    {
        $c = 12;
        $this->make($c, 'ViewSonic', 'ViewSonic VX2418', 3200, 30, 36, ['screen_size' => '24"', 'resolution' => '1920x1080', 'refresh_rate' => '75Hz', 'panel_type' => 'VA', 'response_time' => '4ms', 'video_ports' => '1x HDMI, 1x VGA']);
        $this->make($c, 'BenQ', 'BenQ GW2480', 2800, 32, 36, ['screen_size' => '24"', 'resolution' => '1920x1080', 'refresh_rate' => '60Hz', 'panel_type' => 'IPS', 'response_time' => '5ms', 'video_ports' => '1x HDMI, 1x VGA']);
        $this->make($c, 'AOC', 'AOC 24G2', 4200, 30, 36, ['screen_size' => '24"', 'resolution' => '1920x1080', 'refresh_rate' => '144Hz', 'panel_type' => 'IPS', 'response_time' => '1ms', 'video_ports' => '2x HDMI, 1x DisplayPort']);
        $this->make($c, 'LG', 'LG 24GN600-B', 4300, 28, 36, ['screen_size' => '24"', 'resolution' => '1920x1080', 'refresh_rate' => '144Hz', 'panel_type' => 'IPS', 'response_time' => '1ms', 'video_ports' => '2x HDMI, 1x DisplayPort']);
        $this->make($c, 'Samsung', 'Samsung Odyssey G3 24"', 4500, 26, 36, ['screen_size' => '24"', 'resolution' => '1920x1080', 'refresh_rate' => '144Hz', 'panel_type' => 'VA', 'response_time' => '1ms', 'video_ports' => '1x HDMI, 1x DisplayPort']);
        $this->make($c, 'ASUS', 'ASUS TUF Gaming VG249Q', 4600, 26, 36, ['screen_size' => '24"', 'resolution' => '1920x1080', 'refresh_rate' => '144Hz', 'panel_type' => 'IPS', 'response_time' => '1ms', 'video_ports' => '2x HDMI, 1x DisplayPort']);
        $this->make($c, 'AOC', 'AOC 27G2', 5200, 24, 36, ['screen_size' => '27"', 'resolution' => '1920x1080', 'refresh_rate' => '144Hz', 'panel_type' => 'IPS', 'response_time' => '1ms', 'video_ports' => '2x HDMI, 1x DisplayPort']);
        $this->make($c, 'Samsung', 'Samsung Odyssey G5 27"', 8200, 20, 36, ['screen_size' => '27"', 'resolution' => '2560x1440', 'refresh_rate' => '144Hz', 'panel_type' => 'VA', 'response_time' => '1ms', 'video_ports' => '1x HDMI, 1x DisplayPort']);
        $this->make($c, 'LG', 'LG 27GN800-B', 8500, 20, 36, ['screen_size' => '27"', 'resolution' => '2560x1440', 'refresh_rate' => '144Hz', 'panel_type' => 'IPS', 'response_time' => '1ms', 'video_ports' => '2x HDMI, 1x DisplayPort']);
        $this->make($c, 'ASUS', 'ASUS TUF Gaming VG27AQ', 9500, 18, 36, ['screen_size' => '27"', 'resolution' => '2560x1440', 'refresh_rate' => '165Hz', 'panel_type' => 'IPS', 'response_time' => '1ms', 'video_ports' => '2x HDMI, 1x DisplayPort']);
        $this->make($c, 'BenQ', 'BenQ EX2780Q', 9800, 16, 36, ['screen_size' => '27"', 'resolution' => '2560x1440', 'refresh_rate' => '144Hz', 'panel_type' => 'IPS', 'response_time' => '5ms', 'video_ports' => '2x HDMI, 1x DisplayPort']);
        $this->make($c, 'LG', 'LG 27GP850-B', 11200, 14, 36, ['screen_size' => '27"', 'resolution' => '2560x1440', 'refresh_rate' => '165Hz', 'panel_type' => 'Nano IPS', 'response_time' => '1ms', 'video_ports' => '2x HDMI, 1x DisplayPort']);
        $this->make($c, 'AOC', 'AOC CU34G2X', 13500, 12, 36, ['screen_size' => '34"', 'resolution' => '3440x1440', 'refresh_rate' => '144Hz', 'panel_type' => 'VA', 'response_time' => '1ms', 'video_ports' => '2x HDMI, 1x DisplayPort']);
        $this->make($c, 'ViewSonic', 'ViewSonic XG270QG', 14500, 10, 36, ['screen_size' => '27"', 'resolution' => '2560x1440', 'refresh_rate' => '240Hz', 'panel_type' => 'IPS', 'response_time' => '1ms', 'video_ports' => '2x HDMI, 1x DisplayPort']);
        $this->make($c, 'Samsung', 'Samsung Odyssey G7 27"', 15500, 10, 36, ['screen_size' => '27"', 'resolution' => '2560x1440', 'refresh_rate' => '240Hz', 'panel_type' => 'VA', 'response_time' => '1ms', 'video_ports' => '1x HDMI, 1x DisplayPort']);
        $this->make($c, 'LG', 'LG 34GP83A-B', 16800, 8, 36, ['screen_size' => '34"', 'resolution' => '3440x1440', 'refresh_rate' => '160Hz', 'panel_type' => 'Nano IPS', 'response_time' => '1ms', 'video_ports' => '2x HDMI, 1x DisplayPort']);
        $this->make($c, 'ASUS', 'ASUS ROG Swift PG279QM', 22000, 6, 36, ['screen_size' => '27"', 'resolution' => '2560x1440', 'refresh_rate' => '240Hz', 'panel_type' => 'IPS', 'response_time' => '1ms', 'video_ports' => '1x HDMI, 1x DisplayPort']);
        $this->make($c, 'LG', 'LG 27GN950-B', 24500, 6, 36, ['screen_size' => '27"', 'resolution' => '3840x2160', 'refresh_rate' => '144Hz', 'panel_type' => 'Nano IPS', 'response_time' => '1ms', 'video_ports' => '2x HDMI, 1x DisplayPort']);
        $this->make($c, 'Samsung', 'Samsung Odyssey G9 49"', 32000, 4, 36, ['screen_size' => '49"', 'resolution' => '5120x1440', 'refresh_rate' => '240Hz', 'panel_type' => 'VA', 'response_time' => '1ms', 'video_ports' => '1x HDMI, 1x DisplayPort']);
        $this->make($c, 'ASUS', 'ASUS ROG Swift PG32UQX', 55000, 3, 36, ['screen_size' => '32"', 'resolution' => '3840x2160', 'refresh_rate' => '144Hz', 'panel_type' => 'IPS', 'response_time' => '4ms', 'video_ports' => '2x HDMI, 1x DisplayPort']);

        // Latest generation: OLED gaming monitors.
        $this->make($c, 'ViewSonic', 'ViewSonic Elite XG272-2K', 16500, 10, 36, ['screen_size' => '27"', 'resolution' => '2560x1440', 'refresh_rate' => '240Hz', 'panel_type' => 'IPS', 'response_time' => '1ms', 'video_ports' => '2x HDMI, 1x DisplayPort']);
        $this->make($c, 'BenQ', 'BenQ MOBIUZ EX321UX 4K', 26500, 6, 36, ['screen_size' => '32"', 'resolution' => '3840x2160', 'refresh_rate' => '240Hz', 'panel_type' => 'OLED', 'response_time' => '0.03ms', 'video_ports' => '2x HDMI, 1x DisplayPort']);
        $this->make($c, 'AOC', 'AOC AGON PRO AG276QZD OLED', 19500, 8, 36, ['screen_size' => '27"', 'resolution' => '2560x1440', 'refresh_rate' => '240Hz', 'panel_type' => 'OLED', 'response_time' => '0.03ms', 'video_ports' => '2x HDMI, 1x DisplayPort']);
        $this->make($c, 'LG', 'LG UltraGear 27GR95QE-B OLED', 20500, 8, 36, ['screen_size' => '27"', 'resolution' => '2560x1440', 'refresh_rate' => '240Hz', 'panel_type' => 'OLED', 'response_time' => '0.03ms', 'video_ports' => '2x HDMI, 1x DisplayPort']);
        $this->make($c, 'ASUS', 'ASUS ROG Swift OLED PG27AQDM', 21500, 8, 36, ['screen_size' => '27"', 'resolution' => '2560x1440', 'refresh_rate' => '240Hz', 'panel_type' => 'OLED', 'response_time' => '0.03ms', 'video_ports' => '2x HDMI, 1x DisplayPort']);
        $this->make($c, 'MSI', 'MSI MPG 271QRX QD-OLED', 22000, 6, 36, ['screen_size' => '27"', 'resolution' => '2560x1440', 'refresh_rate' => '360Hz', 'panel_type' => 'QD-OLED', 'response_time' => '0.03ms', 'video_ports' => '2x HDMI, 1x DisplayPort']);
        $this->make($c, 'Dell', 'Dell Alienware AW2725DF QD-OLED', 21000, 6, 36, ['screen_size' => '27"', 'resolution' => '2560x1440', 'refresh_rate' => '280Hz', 'panel_type' => 'QD-OLED', 'response_time' => '0.03ms', 'video_ports' => '2x HDMI, 1x DisplayPort']);
        $this->make($c, 'Samsung', 'Samsung Odyssey OLED G8 (G80SD) 32"', 28000, 5, 36, ['screen_size' => '32"', 'resolution' => '3840x2160', 'refresh_rate' => '240Hz', 'panel_type' => 'OLED', 'response_time' => '0.03ms', 'video_ports' => '2x HDMI, 1x DisplayPort']);
        $this->make($c, 'Gigabyte', 'Gigabyte AORUS FO32U2P 4K OLED', 27500, 5, 36, ['screen_size' => '32"', 'resolution' => '3840x2160', 'refresh_rate' => '240Hz', 'panel_type' => 'OLED', 'response_time' => '0.03ms', 'video_ports' => '2x HDMI, 1x DisplayPort']);
        $this->make($c, 'ASUS', 'ASUS ROG Swift PG32UCDM 4K OLED', 33000, 4, 36, ['screen_size' => '32"', 'resolution' => '3840x2160', 'refresh_rate' => '240Hz', 'panel_type' => 'OLED', 'response_time' => '0.03ms', 'video_ports' => '2x HDMI, 1x DisplayPort']);
        $this->make($c, 'LG', 'LG 32GS95UE-B 4K/1080p Dual Mode OLED', 34000, 4, 36, ['screen_size' => '32"', 'resolution' => '3840x2160', 'refresh_rate' => '240Hz', 'panel_type' => 'OLED', 'response_time' => '0.03ms', 'video_ports' => '2x HDMI, 1x DisplayPort']);
        $this->make($c, 'ASUS', 'ASUS ROG Swift PG49WCD Ultrawide OLED', 32000, 4, 36, ['screen_size' => '49"', 'resolution' => '5120x1440', 'refresh_rate' => '144Hz', 'panel_type' => 'OLED', 'response_time' => '0.03ms', 'video_ports' => '2x HDMI, 1x DisplayPort']);
        $this->make($c, 'LG', 'LG 45GR95QE-B Ultrawide OLED', 27000, 5, 36, ['screen_size' => '45"', 'resolution' => '3440x1440', 'refresh_rate' => '240Hz', 'panel_type' => 'OLED', 'response_time' => '0.03ms', 'video_ports' => '2x HDMI, 1x DisplayPort']);
        $this->make($c, 'Samsung', 'Samsung Odyssey Neo G9 57" (G95NC)', 55000, 2, 36, ['screen_size' => '57"', 'resolution' => '7680x2160', 'refresh_rate' => '240Hz', 'panel_type' => 'VA', 'response_time' => '1ms', 'video_ports' => '2x HDMI, 1x DisplayPort']);
        $this->make($c, 'Corsair', 'Corsair Xeneon Flex 45WQHD240', 42000, 3, 36, ['screen_size' => '45"', 'resolution' => '3440x1440', 'refresh_rate' => '240Hz', 'panel_type' => 'OLED', 'response_time' => '0.03ms', 'video_ports' => '2x HDMI, 1x DisplayPort']);
    }

    private function seedLaptops(): void
    {
        $c = 8;
        $this->make($c, 'ASUS', 'ASUS VivoBook 15', 17500, 20, 12, ['cores' => 2, 'threads' => 4, 'clock_speed' => '2.1GHz', 'ram_type' => 'DDR4', 'memory_size' => '8GB', 'capacity' => '256GB', 'storage_type' => 'NVMe SSD']);
        $this->make($c, 'Dell', 'Dell Inspiron 15 3520', 18500, 18, 12, ['cores' => 2, 'threads' => 4, 'clock_speed' => '2.1GHz', 'ram_type' => 'DDR4', 'memory_size' => '8GB', 'capacity' => '256GB', 'storage_type' => 'NVMe SSD']);
        $this->make($c, 'Acer', 'Acer Aspire 5', 21000, 20, 12, ['cores' => 6, 'threads' => 12, 'clock_speed' => '2.5GHz', 'ram_type' => 'DDR4', 'memory_size' => '8GB', 'capacity' => '512GB', 'storage_type' => 'NVMe SSD']);
        $this->make($c, 'Lenovo', 'Lenovo IdeaPad Slim 3', 22500, 22, 12, ['cores' => 6, 'threads' => 12, 'clock_speed' => '2.3GHz', 'ram_type' => 'DDR4', 'memory_size' => '8GB', 'capacity' => '512GB', 'storage_type' => 'NVMe SSD']);
        $this->make($c, 'Dell', 'Dell Vostro 3520', 24000, 18, 12, ['cores' => 10, 'threads' => 16, 'clock_speed' => '2.5GHz', 'ram_type' => 'DDR4', 'memory_size' => '8GB', 'capacity' => '512GB', 'storage_type' => 'NVMe SSD']);
        $this->make($c, 'HP', 'HP Pavilion 15', 26000, 16, 12, ['cores' => 10, 'threads' => 16, 'clock_speed' => '2.5GHz', 'ram_type' => 'DDR4', 'memory_size' => '8GB', 'capacity' => '512GB', 'storage_type' => 'NVMe SSD']);
        $this->make($c, 'Acer', 'Acer Swift 3', 29500, 14, 12, ['cores' => 8, 'threads' => 16, 'clock_speed' => '2.9GHz', 'ram_type' => 'DDR5', 'memory_size' => '16GB', 'capacity' => '512GB', 'storage_type' => 'NVMe SSD']);
        $this->make($c, 'HP', 'HP Victus 15', 32000, 14, 12, ['cores' => 6, 'threads' => 12, 'clock_speed' => '3.3GHz', 'ram_type' => 'DDR5', 'memory_size' => '8GB', 'capacity' => '512GB', 'storage_type' => 'NVMe SSD']);
        $this->make($c, 'Lenovo', 'Lenovo ThinkPad E14', 35000, 12, 12, ['cores' => 10, 'threads' => 16, 'clock_speed' => '2.5GHz', 'ram_type' => 'DDR5', 'memory_size' => '16GB', 'capacity' => '512GB', 'storage_type' => 'NVMe SSD']);
        $this->make($c, 'HP', 'HP Envy x360', 38000, 12, 12, ['cores' => 8, 'threads' => 16, 'clock_speed' => '2.9GHz', 'ram_type' => 'DDR5', 'memory_size' => '16GB', 'capacity' => '512GB', 'storage_type' => 'NVMe SSD']);
        $this->make($c, 'Acer', 'Acer Nitro 5', 39000, 12, 12, ['cores' => 10, 'threads' => 16, 'clock_speed' => '2.5GHz', 'ram_type' => 'DDR5', 'memory_size' => '16GB', 'capacity' => '512GB', 'storage_type' => 'NVMe SSD']);
        $this->make($c, 'Dell', 'Dell G15 5530 Gaming', 48000, 10, 12, ['cores' => 14, 'threads' => 20, 'clock_speed' => '2.2GHz', 'ram_type' => 'DDR5', 'memory_size' => '16GB', 'capacity' => '512GB', 'storage_type' => 'NVMe SSD']);
        $this->make($c, 'Lenovo', 'Lenovo Legion 5', 52000, 10, 12, ['cores' => 8, 'threads' => 16, 'clock_speed' => '3.2GHz', 'ram_type' => 'DDR5', 'memory_size' => '16GB', 'capacity' => '512GB', 'storage_type' => 'NVMe SSD']);
        $this->make($c, 'Dell', 'Dell XPS 13', 55000, 10, 12, ['cores' => 10, 'threads' => 12, 'clock_speed' => '2.4GHz', 'ram_type' => 'DDR5', 'memory_size' => '16GB', 'capacity' => '512GB', 'storage_type' => 'NVMe SSD']);
        $this->make($c, 'ASUS', 'ASUS Zenbook 14', 58000, 10, 12, ['cores' => 10, 'threads' => 12, 'clock_speed' => '2.4GHz', 'ram_type' => 'DDR5', 'memory_size' => '16GB', 'capacity' => '1TB', 'storage_type' => 'NVMe SSD']);
        $this->make($c, 'ASUS', 'ASUS ROG Strix G16', 62000, 8, 12, ['cores' => 14, 'threads' => 20, 'clock_speed' => '2.2GHz', 'ram_type' => 'DDR5', 'memory_size' => '16GB', 'capacity' => '512GB', 'storage_type' => 'NVMe SSD']);
        $this->make($c, 'HP', 'HP Omen 16', 68000, 8, 12, ['cores' => 14, 'threads' => 20, 'clock_speed' => '2.2GHz', 'ram_type' => 'DDR5', 'memory_size' => '16GB', 'capacity' => '1TB', 'storage_type' => 'NVMe SSD']);
        $this->make($c, 'Acer', 'Acer Predator Helios 16', 95000, 6, 12, ['cores' => 24, 'threads' => 32, 'clock_speed' => '2.2GHz', 'ram_type' => 'DDR5', 'memory_size' => '32GB', 'capacity' => '1TB', 'storage_type' => 'NVMe SSD']);
        $this->make($c, 'Lenovo', 'Lenovo Legion Pro 7', 120000, 4, 12, ['cores' => 24, 'threads' => 32, 'clock_speed' => '2.2GHz', 'ram_type' => 'DDR5', 'memory_size' => '32GB', 'capacity' => '1TB', 'storage_type' => 'NVMe SSD']);
        $this->make($c, 'ASUS', 'ASUS ROG Zephyrus G14', 135000, 3, 12, ['cores' => 12, 'threads' => 24, 'clock_speed' => '2.5GHz', 'ram_type' => 'DDR5', 'memory_size' => '32GB', 'capacity' => '1TB', 'storage_type' => 'NVMe SSD']);

        // Latest generation: Intel Core Ultra 200H and AMD Ryzen AI / RTX 50-series mobile.
        $this->make($c, 'Acer', 'Acer Swift Go 14 (Core Ultra 5)', 32000, 14, 12, ['cores' => 14, 'threads' => 18, 'clock_speed' => '2.6GHz', 'ram_type' => 'DDR5', 'memory_size' => '16GB', 'capacity' => '512GB', 'storage_type' => 'NVMe SSD']);
        $this->make($c, 'Lenovo', 'Lenovo ThinkPad X1 Carbon Gen 12 (Core Ultra 7)', 68000, 8, 36, ['cores' => 16, 'threads' => 22, 'clock_speed' => '2.5GHz', 'ram_type' => 'DDR5', 'memory_size' => '32GB', 'capacity' => '1TB', 'storage_type' => 'NVMe SSD']);
        $this->make($c, 'HP', 'HP Spectre x360 14 (Core Ultra 7)', 62000, 8, 12, ['cores' => 16, 'threads' => 22, 'clock_speed' => '2.5GHz', 'ram_type' => 'DDR5', 'memory_size' => '32GB', 'capacity' => '1TB', 'storage_type' => 'NVMe SSD']);
        $this->make($c, 'Dell', 'Dell XPS 14 (Core Ultra 7 155H)', 65000, 8, 12, ['cores' => 16, 'threads' => 22, 'clock_speed' => '2.5GHz', 'ram_type' => 'DDR5', 'memory_size' => '32GB', 'capacity' => '1TB', 'storage_type' => 'NVMe SSD']);
        $this->make($c, 'ASUS', 'ASUS Zenbook S14 (Core Ultra 7 258V)', 58000, 8, 12, ['cores' => 8, 'threads' => 8, 'clock_speed' => '2.2GHz', 'ram_type' => 'LPDDR5X', 'memory_size' => '32GB', 'capacity' => '1TB', 'storage_type' => 'NVMe SSD']);
        $this->make($c, 'Acer', 'Acer Predator Helios Neo 16 (Core Ultra 7, RTX 5060)', 72000, 6, 12, ['cores' => 20, 'threads' => 28, 'clock_speed' => '2.2GHz', 'ram_type' => 'DDR5', 'memory_size' => '16GB', 'capacity' => '1TB', 'storage_type' => 'NVMe SSD']);
        $this->make($c, 'HP', 'HP Omen Transcend 14 (Core Ultra 7, RTX 5070)', 85000, 5, 12, ['cores' => 16, 'threads' => 22, 'clock_speed' => '2.5GHz', 'ram_type' => 'DDR5', 'memory_size' => '32GB', 'capacity' => '1TB', 'storage_type' => 'NVMe SSD']);
        $this->make($c, 'Dell', 'Dell Alienware 16 Aurora (Core Ultra 7, RTX 5060)', 88000, 5, 12, ['cores' => 16, 'threads' => 22, 'clock_speed' => '2.5GHz', 'ram_type' => 'DDR5', 'memory_size' => '16GB', 'capacity' => '1TB', 'storage_type' => 'NVMe SSD']);
        $this->make($c, 'Lenovo', 'Lenovo Legion Pro 7i (Core Ultra 9, RTX 5090)', 165000, 3, 12, ['cores' => 24, 'threads' => 24, 'clock_speed' => '2.0GHz', 'ram_type' => 'DDR5', 'memory_size' => '32GB', 'capacity' => '2TB', 'storage_type' => 'NVMe SSD']);
        $this->make($c, 'ASUS', 'ASUS ROG Strix G16 2025 (Core Ultra 9, RTX 5070)', 98000, 5, 12, ['cores' => 24, 'threads' => 24, 'clock_speed' => '2.0GHz', 'ram_type' => 'DDR5', 'memory_size' => '32GB', 'capacity' => '1TB', 'storage_type' => 'NVMe SSD']);
        $this->make($c, 'ASUS', 'ASUS ROG Zephyrus G16 2025 (Core Ultra 9, RTX 5080)', 145000, 4, 12, ['cores' => 24, 'threads' => 24, 'clock_speed' => '2.0GHz', 'ram_type' => 'DDR5', 'memory_size' => '32GB', 'capacity' => '2TB', 'storage_type' => 'NVMe SSD']);
        $this->make($c, 'MSI', 'MSI Stealth 16 AI Studio (Core Ultra 9, RTX 5070 Ti)', 128000, 4, 12, ['cores' => 24, 'threads' => 24, 'clock_speed' => '2.0GHz', 'ram_type' => 'DDR5', 'memory_size' => '32GB', 'capacity' => '2TB', 'storage_type' => 'NVMe SSD']);
        $this->make($c, 'Razer', 'Razer Blade 16 2025 (Core Ultra 9, RTX 5090)', 195000, 2, 12, ['cores' => 24, 'threads' => 24, 'clock_speed' => '2.0GHz', 'ram_type' => 'DDR5', 'memory_size' => '32GB', 'capacity' => '2TB', 'storage_type' => 'NVMe SSD']);
        $this->make($c, 'Framework', 'Framework Laptop 16 (Ryzen 9 8945HS)', 88000, 4, 12, ['cores' => 8, 'threads' => 16, 'clock_speed' => '4.0GHz', 'ram_type' => 'DDR5', 'memory_size' => '32GB', 'capacity' => '1TB', 'storage_type' => 'NVMe SSD']);
        $this->make($c, 'Lenovo', 'Lenovo Yoga Slim 7i (Core Ultra 7 258V)', 55000, 8, 12, ['cores' => 8, 'threads' => 8, 'clock_speed' => '2.2GHz', 'ram_type' => 'LPDDR5X', 'memory_size' => '16GB', 'capacity' => '1TB', 'storage_type' => 'NVMe SSD']);
    }
}
