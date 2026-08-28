<?php

namespace App\Services;

class CategoryIconService
{
    /** @var list<array{from: string, to: string, tone: string}> */
    private const PALETTES = [
        ['from' => '#5B8DEF', 'to' => '#7B5BEF', 'tone' => 'violet'],
        ['from' => '#2D9B6E', 'to' => '#56C596', 'tone' => 'green'],
        ['from' => '#FF8A5C', 'to' => '#FF6B8A', 'tone' => 'coral'],
        ['from' => '#4ECDC4', 'to' => '#2E86AB', 'tone' => 'teal'],
        ['from' => '#F7B731', 'to' => '#F97F51', 'tone' => 'sunset'],
        ['from' => '#A29BFE', 'to' => '#6C5CE7', 'tone' => 'purple'],
        ['from' => '#FD79A8', 'to' => '#E84393', 'tone' => 'pink'],
        ['from' => '#74B9FF', 'to' => '#0984E3', 'tone' => 'blue'],
    ];

    /** @var list<array{keys: list<string>, icon: string}> */
    private const RULES = [
        ['keys' => ['air mineral', 'air '], 'icon' => 'water'],
        ['keys' => ['alat listrik', 'listrik', 'lampu', 'lilin', 'kabel', 'elektronik'], 'icon' => 'bulb'],
        ['keys' => ['aksesoris', 'asesoris', 'accessor', 'jam tangan'], 'icon' => 'watch'],
        ['keys' => ['atk', 'alat tulis', 'pena', 'buku', 'perekat'], 'icon' => 'pencil'],
        ['keys' => ['baterai'], 'icon' => 'battery'],
        ['keys' => ['gas elpiji', 'gas '], 'icon' => 'gas'],
        ['keys' => ['ice cream', 'es krim'], 'icon' => 'ice'],
        ['keys' => ['jelly', 'puding'], 'icon' => 'dessert'],
        ['keys' => ['bahan makanan', 'bahan pembuat', 'rempah', 'sembako', 'beras', 'mie', 'tepung', 'gula', 'minyak', 'bumbu'], 'icon' => 'rice'],
        ['keys' => ['minuman', 'soda', 'jus', 'teh', 'kopi'], 'icon' => 'drink'],
        ['keys' => ['snack', 'keripik', 'biskuit', 'coklat', 'permen', 'camilan'], 'icon' => 'snack'],
        ['keys' => ['sabun', 'deterjen', 'cuci', 'pembersih', 'kebersihan'], 'icon' => 'bottle'],
        ['keys' => ['susu', 'yogurt', 'keju', 'dairy'], 'icon' => 'milk'],
        ['keys' => ['daging', 'ayam', 'ikan', 'seafood'], 'icon' => 'meat'],
        ['keys' => ['sayur', 'buah', 'segar', 'organik'], 'icon' => 'leaf'],
        ['keys' => ['roti', 'kue', 'bakery'], 'icon' => 'bread'],
        ['keys' => ['bayi', 'popok', 'diaper'], 'icon' => 'baby'],
        ['keys' => ['kesehatan', 'obat', 'vitamin'], 'icon' => 'pill'],
        ['keys' => ['kosmetik', 'shampo', 'skincare'], 'icon' => 'sparkle'],
        ['keys' => ['frozen', 'beku'], 'icon' => 'ice'],
        ['keys' => ['rumah tangga', 'plastik'], 'icon' => 'home'],
        ['keys' => ['rokok'], 'icon' => 'box'],
    ];

    /**
     * @return array{kind: string, icon?: string, letter?: string, from: string, to: string, tone: string}
     */
    public function forAll(): array
    {
        $palette = self::PALETTES[0];

        return [
            'kind' => 'grid',
            'from' => $palette['from'],
            'to' => $palette['to'],
            'tone' => 'all',
        ];
    }

    /**
     * @return array{kind: string, icon?: string, letter?: string, from: string, to: string, tone: string}
     */
    public function forProductType(string $tipe): array
    {
        return match ($tipe) {
            'terbaru' => $this->iconVisual(0, 'sparkle', 'terbaru'),
            'terlaris' => $this->iconVisual(0, 'fire', 'terlaris'),
            default => $this->forAll(),
        };
    }

    /**
     * @return array{kind: string, icon?: string, letter?: string, from: string, to: string, tone: string}
     */
    public function forCategory(object $category): array
    {
        $name = trim((string) ($category->kategori_nama ?? 'Kategori'));
        $normalized = mb_strtolower($name);
        $id = (int) ($category->kategori_id ?? 0);

        foreach (self::RULES as $rule) {
            foreach ($rule['keys'] as $key) {
                if (str_contains($normalized, $key)) {
                    return $this->iconVisual($id, $rule['icon'], $key);
                }
            }
        }

        $palette = $this->paletteFor($id, 'letter');

        return [
            'kind' => 'letter',
            'letter' => mb_strtoupper(mb_substr($name, 0, 1)),
            'from' => $palette['from'],
            'to' => $palette['to'],
            'tone' => $palette['tone'],
        ];
    }

    /**
     * @return array{kind: string, icon: string, from: string, to: string, tone: string}
     */
    protected function iconVisual(int $id, string $icon, string $seed): array
    {
        $palette = $this->paletteFor($id, $seed);

        return [
            'kind' => 'icon',
            'icon' => $icon,
            'from' => $palette['from'],
            'to' => $palette['to'],
            'tone' => $palette['tone'],
        ];
    }

    /**
     * @return array{from: string, to: string, tone: string}
     */
    protected function paletteFor(int $id, string $seed): array
    {
        $index = ($id + abs(crc32($seed))) % count(self::PALETTES);

        return self::PALETTES[$index];
    }
}
