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

    /** @var list<array{keys: list<string>, emoji: string}> */
    private const RULES = [
        ['keys' => ['air mineral', 'air '], 'emoji' => '💧'],
        ['keys' => ['alat listrik', 'listrik', 'lampu', 'kabel', 'elektronik'], 'emoji' => '💡'],
        ['keys' => ['aksesoris', 'asesoris', 'accessor', 'jam'], 'emoji' => '⌚'],
        ['keys' => ['atk', 'alat tulis', 'pena', 'buku'], 'emoji' => '✏️'],
        ['keys' => ['sembako', 'beras', 'mie', 'tepung', 'gula', 'minyak', 'bumbu'], 'emoji' => '🍚'],
        ['keys' => ['minuman', 'soda', 'jus', 'teh', 'kopi'], 'emoji' => '🥤'],
        ['keys' => ['snack', 'keripik', 'biskuit', 'coklat', 'permen', 'camilan'], 'emoji' => '🍿'],
        ['keys' => ['sabun', 'deterjen', 'cuci', 'pembersih', 'kebersihan'], 'emoji' => '🧴'],
        ['keys' => ['susu', 'yogurt', 'keju', 'dairy'], 'emoji' => '🥛'],
        ['keys' => ['daging', 'ayam', 'ikan', 'seafood'], 'emoji' => '🍗'],
        ['keys' => ['sayur', 'buah', 'segar', 'organik'], 'emoji' => '🥬'],
        ['keys' => ['roti', 'kue', 'bakery'], 'emoji' => '🍞'],
        ['keys' => ['bayi', 'popok', 'diaper'], 'emoji' => '👶'],
        ['keys' => ['kesehatan', 'obat', 'vitamin'], 'emoji' => '💊'],
        ['keys' => ['kosmetik', 'shampo', 'skincare'], 'emoji' => '✨'],
        ['keys' => ['frozen', 'beku', 'es krim'], 'emoji' => '🧊'],
        ['keys' => ['rumah tangga', 'plastik'], 'emoji' => '🏠'],
        ['keys' => ['rokok'], 'emoji' => '📦'],
    ];

    /**
     * @return array{kind: string, emoji?: string, letter?: string, from: string, to: string, tone: string}
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
     * @return array{kind: string, emoji?: string, letter?: string, from: string, to: string, tone: string}
     */
    public function forProductType(string $tipe): array
    {
        return match ($tipe) {
            'terbaru' => [
                'kind' => 'emoji',
                'emoji' => '✨',
                'from' => '#4ECDC4',
                'to' => '#2E86AB',
                'tone' => 'teal',
            ],
            'terlaris' => [
                'kind' => 'emoji',
                'emoji' => '🔥',
                'from' => '#FF8A5C',
                'to' => '#FF6B8A',
                'tone' => 'coral',
            ],
            default => $this->forAll(),
        };
    }

    /**
     * @return array{kind: string, emoji?: string, letter?: string, from: string, to: string, tone: string}
     */
    public function forCategory(object $category): array
    {
        $name = trim((string) ($category->kategori_nama ?? 'Kategori'));
        $normalized = mb_strtolower($name);
        $id = (int) ($category->kategori_id ?? 0);

        foreach (self::RULES as $rule) {
            foreach ($rule['keys'] as $key) {
                if (str_contains($normalized, $key)) {
                    $palette = $this->paletteFor($id, $rule['emoji']);

                    return [
                        'kind' => 'emoji',
                        'emoji' => $rule['emoji'],
                        'from' => $palette['from'],
                        'to' => $palette['to'],
                        'tone' => $palette['tone'],
                    ];
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
     * @return array{from: string, to: string, tone: string}
     */
    protected function paletteFor(int $id, string $seed): array
    {
        $index = ($id + abs(crc32($seed))) % count(self::PALETTES);

        return self::PALETTES[$index];
    }
}
