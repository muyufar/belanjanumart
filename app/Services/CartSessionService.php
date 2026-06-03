<?php

namespace App\Services;

class CartSessionService
{
    protected string $key = 'marketplace_cart';

    /**
     * @return array<int, array{barang_id: int, barang_kode: string, barang_nama: string, qty: int, price: int, image_url: ?string}>
     */
    public function all(): array
    {
        return session($this->key, []);
    }

    public function count(): int
    {
        return count($this->all());
    }

    public function add(object $product, int $qty = 1): void
    {
        $cart = $this->all();
        $id = (int) $product->barang_id;
        $found = false;

        foreach ($cart as &$row) {
            if ($row['barang_id'] === $id) {
                $row['qty'] += $qty;
                $found = true;
                break;
            }
        }

        if (! $found) {
            $cart[] = [
                'barang_id' => $id,
                'barang_kode' => $product->barang_kode,
                'barang_nama' => $product->barang_nama,
                'qty' => $qty,
                'price' => (int) $product->price,
                'image_url' => $product->image_url ?? null,
            ];
        }

        session([$this->key => $cart]);
    }

    public function update(int $barangId, int $qty): void
    {
        $cart = $this->all();
        foreach ($cart as $i => $row) {
            if ($row['barang_id'] === $barangId) {
                if ($qty < 1) {
                    unset($cart[$i]);
                } else {
                    $cart[$i]['qty'] = $qty;
                }
                break;
            }
        }
        session([$this->key => array_values($cart)]);
    }

    public function remove(int $barangId): void
    {
        $cart = array_values(array_filter(
            $this->all(),
            fn ($r) => $r['barang_id'] !== $barangId
        ));
        session([$this->key => $cart]);
    }

    public function clear(): void
    {
        session()->forget($this->key);
    }

    public function subtotal(): int
    {
        return array_reduce($this->all(), fn ($s, $r) => $s + ($r['price'] * $r['qty']), 0);
    }
}
