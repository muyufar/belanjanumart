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
        ['keys' => ['alat listrik', 'listrik', 'lampu', 'lilin', 'kabel', 'elektronik'], 'emoji' => '💡'],
        ['keys' => ['aksesoris', 'asesoris', 'accessor', 'jam tangan'], 'emoji' => '⌚'],
        ['keys' => ['atk', 'alat tulis', 'pena', 'buku', 'perekat'], 'emoji' => '✏️'],
        ['keys' => ['baterai'], 'emoji' => '🔋'],
        ['keys' => ['gas elpiji', 'gas '], 'emoji' => '⛽'],
        ['keys' => ['ice cream', 'es krim'], 'emoji' => '🍦'],
        ['keys' => ['jelly', 'puding'], 'emoji' => '🍮'],
        ['keys' => ['bahan makanan', 'bahan pembuat', 'rempah', 'sembako', 'beras', 'mie', 'tepung', 'gula', 'minyak', 'bumbu'], 'emoji' => '🍚'],
        ['keys' => ['minuman', 'soda', 'jus', 'teh'], 'emoji' => '🥤'],
        ['keys' => ['kopi'], 'emoji' => '☕'],
        ['keys' => ['snack', 'keripik', 'kerupuk', 'biskuit', 'coklat', 'permen', 'camilan'], 'emoji' => '🍿'],
        ['keys' => ['sabun', 'deterjen', 'det bubuk', 'cuci', 'pembersih', 'kebersihan'], 'emoji' => '🧴'],
        ['keys' => ['bodycare', 'kosmetik', 'shampo', 'skincare'], 'emoji' => '💄'],
        ['keys' => ['susu', 'yogurt', 'keju', 'dairy'], 'emoji' => '🥛'],
        ['keys' => ['daging', 'ayam', 'ikan', 'seafood'], 'emoji' => '🍗'],
        ['keys' => ['sayur', 'buah', 'segar', 'organik'], 'emoji' => '🥬'],
        ['keys' => ['roti', 'kue', 'bakery'], 'emoji' => '🍞'],
        ['keys' => ['bayi', 'popok', 'diaper'], 'emoji' => '👶'],
        ['keys' => ['kesehatan', 'obat', 'vitamin'], 'emoji' => '💊'],
        ['keys' => ['frozen', 'beku'], 'emoji' => '🧊'],
        ['keys' => ['rumah tangga', 'plastik'], 'emoji' => '🏠'],
        ['keys' => ['infaq', 'sedekah'], 'emoji' => '🤲'],
        ['keys' => ['rokok'], 'emoji' => '🚬'],
        ['keys' => ['perkakas', 'alat '], 'emoji' => '🔧'],
    ];

    /**
     * @return array{kind: string, emoji?: string, letter?: string, accent: string, from: string, to: string, tone: string}
     */
    public function forAll(): array
    {
        $palette = self::PALETTES[0];

        return [
            'kind' => 'emoji',
            'emoji' => '🛍️',
            'accent' => $palette['from'],
            'from' => $palette['from'],
            'to' => $palette['to'],
            'tone' => 'all',
        ];
    }

    /**
     * @return array{kind: string, emoji?: string, letter?: string, accent: string, from: string, to: string, tone: string}
     */
    public function forProductType(string $tipe): array
    {
        return match ($tipe) {
            'terbaru' => $this->emojiVisual(0, '✨', 'terbaru'),
            'terlaris' => $this->emojiVisual(0, '🔥', 'terlaris'),
            default => $this->forAll(),
        };
    }

    /**
     * @return array{kind: string, emoji?: string, letter?: string, accent: string, from: string, to: string, tone: string}
     */
    public function forCategory(object $category): array
    {
        $name = trim((string) ($category->kategori_nama ?? 'Kategori'));
        $normalized = mb_strtolower($name);
        $id = (int) ($category->kategori_id ?? 0);

        foreach (self::RULES as $rule) {
            foreach ($rule['keys'] as $key) {
                if (str_contains($normalized, $key)) {
                    return $this->emojiVisual($id, $rule['emoji'], $key);
                }
            }
        }

        $palette = $this->paletteFor($id, 'letter');

        return [
            'kind' => 'letter',
            'letter' => mb_strtoupper(mb_substr($name, 0, 1)),
            'accent' => $palette['from'],
            'from' => $palette['from'],
            'to' => $palette['to'],
            'tone' => $palette['tone'],
        ];
    }

    /**
     * @return array{kind: string, emoji: string, accent: string, from: string, to: string, tone: string}
     */
    protected function emojiVisual(int $id, string $emoji, string $seed): array
    {
        $palette = $this->paletteFor($id, $seed);

        return [
            'kind' => 'emoji',
            'emoji' => $emoji,
            'accent' => $palette['from'],
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
