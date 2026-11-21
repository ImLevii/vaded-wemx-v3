<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CartItemOption extends Model
{
    protected $table = 'cart_item_options';

    protected $fillable = [
        'cart_item_id',
        'name',
        'price',
        'key',
        'value',
        'data',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:8',
            'data' => 'array',
        ];
    }

    public function item()
    {
        return $this->belongsTo(CartItem::class, 'cart_item_id');
    }

    /**
     * Option value safe for client-facing cart UI (keys containing "password" are redacted).
     */
    public function displayValueForCart(): string
    {
        if ($this->value === null || $this->value === '') {
            return '';
        }

        if (str_contains(strtolower((string) $this->key), 'password')) {
            return '********';
        }

        // A select/radio choice shows its name ("Miami, Florida") instead of its stored value ("1").
        $item = $this->relationLoaded('item') ? $this->item : $this->item()->with('cartable.package.configOptions')->first();
        $option = $item?->cartable instanceof PackagePrice
            ? $item->cartable->package?->configOptions->firstWhere('key', $this->key) : null;
        if ($option && in_array($option->type, ['select', 'radio'], true)) {
            $choice = collect($option->data['options'] ?? [])->first(fn ($choice): bool => is_array($choice) && (string) ($choice['value'] ?? '') === (string) $this->value);
            if (is_string($choice['name'] ?? null) && $choice['name'] !== '') {
                return $choice['name'];
            }
        }

        return (string) $this->value;
    }
}
