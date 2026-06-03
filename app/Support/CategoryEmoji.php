<?php

namespace App\Support;

class CategoryEmoji
{
    /** Kode file Twemoji 72x72 untuk emoji yang dipakai di katalog */
    private const FILES = [
        '💧' => '1f4a7',
        '💡' => '1f4a1',
        '⌚' => '231a',
        '✏️' => '270f-fe0f',
        '✏' => '270f-fe0f',
        '🍚' => '1f35a',
        '🥤' => '1f964',
        '🍿' => '1f37f',
        '🧴' => '1f9f4',
        '🥛' => '1f95b',
        '🍗' => '1f357',
        '🥬' => '1f96c',
        '🍞' => '1f35e',
        '👶' => '1f476',
        '💊' => '1f48a',
        '✨' => '2728',
        '🧊' => '1f9ca',
        '🏠' => '1f3e0',
        '📦' => '1f4e6',
        '🏪' => '1f3ea',
    ];

    public static function twemojiUrl(string $emoji): string
    {
        $emoji = trim($emoji);
        $base = 'https://cdn.jsdelivr.net/gh/twitter/twemoji@14.0.2/assets/72x72/';

        if (isset(self::FILES[$emoji])) {
            return $base.self::FILES[$emoji].'.png';
        }

        $chars = mb_str_split($emoji, 1, 'UTF-8');
        $parts = [];

        foreach ($chars as $char) {
            $code = mb_ord($char, 'UTF-8');
            if ($code === 65039 || $code === 8205) {
                continue;
            }
            $parts[] = strtolower(dechex($code));
        }

        $file = ($parts !== [] ? implode('-', $parts) : '1f3ea').'.png';

        return $base.$file;
    }
}
