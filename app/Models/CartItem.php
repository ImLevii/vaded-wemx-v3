<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CartItem extends Model
{
    protected $table = 'cart_items';

    protected $fillable = [
        'cart_id',
        'cartable_type',
        'cartable_id',
        'name',
        'icon',
        'price',
        'quantity',
        'handler',
        'data',
    ];

    protected function casts()
    {
        return [
            'price' => 'decimal:8',
            'data' => 'array',
        ];
    }

    public function cart()
    {
        return $this->belongsTo(Cart::class, 'cart_id');
    }

    public function cartable()
    {
        return $this->morphTo();
    }

    public function options()
    {
        return $this->hasMany(CartItemOption::class, 'cart_item_id');
    }

    public function createOrderItem(string $basketIdentifier)
    {
        // create an immutable order item from this cart item
        $orderItem = CartOrderItem::create([
            'basket_identifier' => $basketIdentifier,
            'user_id' => $this->cart->user_id,
            'cartable_type' => $this->cartable_type,
            'cartable_id' => $this->cartable_id,
            'name' => $this->getName(),
            'icon' => $this->getIcon(),
            'price' => $this->price,
            'quantity' => $this->quantity,
            'handler' => $this->handler,
            'data' => $this->data,
        ]);

        // copy options to the order item
        foreach ($this->options as $option) {
            $orderItem->options()->create([
                'name' => $option->name,
                'price' => $option->price,
                'key' => $option->key,
                'value' => $option->value,
                'data' => $option->data,
            ]);
        }

        return $orderItem->id;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getIcon()
    {
        return $this->icon;
    }

    public function incrementQuantity()
    {
        $this->quantity++;
        $this->save();
    }

    public function decrementQuantity()
    {
        if ($this->quantity <= 1) {
            $this->remove();

            return;
        }

        $this->quantity--;
        $this->save();
    }

    public function remove()
    {
        $this->delete();
    }

    public function total()
    {
        return $this->price * $this->quantity;
    }

    public function totalWithOptions()
    {
        return $this->total() + $this->options->sum('price');
    }
}
