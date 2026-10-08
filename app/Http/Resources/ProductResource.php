<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Product */
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'price' => $this->price,
            'price_formatted' => rupiah($this->price),
            'stock' => $this->stock,
            'status' => $this->status,
            'is_available' => $this->isActive() && $this->stock > 0,
            'description' => $this->description,
            'images' => $this->whenLoaded('images', fn () => $this->images->map->url()->values()),
            'url' => route('shop.show', $this->resource),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
