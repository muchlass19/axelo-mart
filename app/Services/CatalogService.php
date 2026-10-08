<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Logika baca data produk & stok, dipakai bersama oleh API /api/v1 dan chatbot.
 * $publicOnly = true  -> hanya produk berstatus active (untuk guest/customer).
 */
class CatalogService
{
    /** @param array{search?: ?string, status?: ?string, available?: bool} $filters */
    public function query(array $filters = [], bool $publicOnly = false): Builder
    {
        $status = $publicOnly ? Product::STATUS_ACTIVE : ($filters['status'] ?? null);

        return Product::query()
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where('name', 'like', "%{$search}%"))
            ->when($status, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['available'] ?? false, fn ($q) => $q->available())
            ->orderBy('name');
    }

    public function find(int $id, bool $publicOnly = false): ?Product
    {
        return Product::with('images')
            ->when($publicOnly, fn ($q) => $q->where('status', Product::STATUS_ACTIVE))
            ->find($id);
    }

    /** Cek stok berdasarkan id (maks 1 hasil) atau nama (maks 20 hasil). */
    public function stockCheck(?int $id, ?string $name, bool $publicOnly = false): Collection
    {
        if ($id) {
            return collect([$this->find($id, $publicOnly)])->filter()->values();
        }

        return $this->query(['search' => $name], $publicOnly)->limit(20)->get();
    }

    public static function stockRow(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'stock' => $product->stock,
            'status' => $product->status,
            'is_available' => $product->isActive() && $product->stock > 0,
            'price' => $product->price,
        ];
    }

    /** Ringkasan produk yang ringkas (dipakai chatbot). */
    public static function summary(Product $product, bool $withDescription = false): array
    {
        return array_filter([
            ...self::stockRow($product),
            'price_formatted' => rupiah($product->price),
            'description' => $withDescription ? Str::limit((string) $product->description, 300) : null,
            'url' => route('shop.show', $product),
        ], fn ($v) => $v !== null);
    }
}
