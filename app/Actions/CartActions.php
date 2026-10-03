<?php

namespace App\Actions;

use App\Handlers\Cart\PackagePurchasedHandler;
use App\Handlers\CartCompletedHandler;
use App\Models\Cart;
use App\Models\GatewayConfig;
use App\Models\Order;
use App\Models\PackagePrice;
use App\Models\Payment;
use App\Models\User;
use App\Support\LicensePlanLimits;
use DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CartActions extends Action
{
    /**
     * Create a new cart
     *
     * @throws ValidationException
     */
    public static function createCartForClient(array $input)
    {
        $validatedData = Validator::make($input, [
            'session_id' => ['required', 'string'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ])->validate();

        return Cart::create(self::omitNullValues($validatedData));
    }

    /**
     * Checkout as a client.
     *
     * @throws ValidationException
     */
    public function checkoutAsClient(array $input)
    {
        $input['country'] = strtoupper((string) ($input['country'] ?? ''));

        $validatedData = Validator::make($input, [
            'cart_id' => ['required', 'integer', 'exists:carts,id'],
            'user_id' => [auth()->check() ? 'required' : 'nullable', 'integer', 'exists:users,id'],
            'gateway_config_id' => ['required', 'integer', 'exists:gateway_configs,id'],

            'first_name' => [auth()->guest() ? 'required' : 'nullable', 'string', 'max:255'],
            'last_name' => [auth()->guest() ? 'required' : 'nullable', 'string', 'max:255'],
            'username' => [auth()->guest() ? 'required' : 'nullable', 'string', 'max:255', 'unique:users,username'],
            'email' => [auth()->guest() ? 'required' : 'nullable', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => [auth()->guest() ? 'required' : 'nullable', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => [auth()->guest() ? 'required' : 'nullable', 'string'],

            'company_name' => ['nullable', 'string', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:255'],
            'country' => ['required', 'string', 'size:2'], // ISO 3166-1 alpha-2 country code
            'region' => [
                'nullable',
                'string',
                'max:255',
                Rule::requiredIf(fn () => in_array(strtoupper((string) ($input['country'] ?? '')), ['US', 'CA'], true)),
            ],
            'zip_code' => ['required', 'string', 'max:20'], // Postal code
        ])->validate();

        $cart = Cart::find($validatedData['cart_id']);
        $gatewayConfig = GatewayConfig::find($validatedData['gateway_config_id']);

        if (isset($validatedData['user_id']) && auth()->check()) {
            // Ensure the cart belongs to the user
            $user = User::find($validatedData['user_id']);
            if ($cart->user_id !== $user->id) {
                throw ValidationException::withMessages([
                    'user_id' => 'This cart does not belong to the specified user.',
                ]);
            }
        } else {
            // Generate unique username based on guest's firstname + 5 random numbers
            $user = User::authActions()->registerAsClient([
                'first_name' => $validatedData['first_name'],
                'last_name' => $validatedData['last_name'],
                'username' => $validatedData['username'],
                'email' => $validatedData['email'],
                'password' => $validatedData['password'],
                'password_confirmation' => $validatedData['password_confirmation'],
                'log_user_in' => false,
            ]);

            // if user was not created, throw an error
            if (! $user) {
                throw ValidationException::withMessages([
                    'email' => 'Failed to create user account. Please try again.',
                ]);
            }
        }

        if ($cart->items->isEmpty()) {
            throw ValidationException::withMessages([
                'cart_id' => 'Cart is empty.',
            ]);
        }

        $newOrdersFromCart = (int) $cart->items
            ->filter(fn ($item) => $item->cartable instanceof PackagePrice)
            ->sum('quantity');

        LicensePlanLimits::assertCanCreateOrders($newOrdersFromCart, 'cart_id');

        $packageAggregates = [];

        foreach ($cart->items as $item) {
            if (! $item->cartable instanceof PackagePrice) {
                continue;
            }

            $package = $item->cartable->package;

            if (! isset($packageAggregates[$package->id])) {
                $packageAggregates[$package->id] = [
                    'package' => $package,
                    'quantity' => 0,
                ];
            }

            $packageAggregates[$package->id]['quantity'] += $item->quantity;
        }

        foreach ($packageAggregates as $aggregate) {
            $package = $aggregate['package'];
            $requestedQuantity = $aggregate['quantity'];

            $server = $package->serverConnection?->server?->functions();
            if ($server !== null && method_exists($server, 'eventCheckout')) {
                $server->eventCheckout($package, $user);
            }

            if ($package->global_quantity !== -1 && $package->global_quantity < $requestedQuantity) {
                throw ValidationException::withMessages([
                    'cart_id' => 'One or more packages in your cart are out of stock.',
                ]);
            }

            if ($package->client_quantity === -1) {
                continue;
            }

            $clientPackageOrdersCount = Order::query()
                ->where('user_id', $user->id)
                ->where('package_id', $package->id)
                ->where('status', '!=', 'terminated')
                ->count();

            if (($clientPackageOrdersCount + $requestedQuantity) > $package->client_quantity) {
                throw ValidationException::withMessages([
                    'cart_id' => 'You have reached the maximum quantity allowed for one or more packages in your cart.',
                ]);
            }
        }

        // create an immutable version of items in the cart
        $orderItems = [];
        $basketIdentifier = Str::random(32); // Generate a unique identifier for the basket
        foreach ($cart->items as $item) {
            $orderItems[] = $item->createOrderItem($basketIdentifier);
        }

        $total = $cart->total();

        $payment = Payment::createWithTax([
            'user_id' => $user->id,
            'gateway_config_id' => $gatewayConfig->id,
            'description' => 'Payment for cart #'.$cart->id,
            'data' => [
                'basket_identifier' => $basketIdentifier,
                'cart_id' => $cart->id,
                'order_items' => $orderItems,
                'tax_details' => [
                    'company_name' => $validatedData['company_name'] ?? null,
                    'tax_id' => $validatedData['tax_id'] ?? null,
                    'country' => $validatedData['country'] ?? null,
                    'region' => $validatedData['region'] ?? null,
                    'zip_code' => $validatedData['zip_code'] ?? null,
                ],
            ],
            'currency' => baseCurrency(),
            'handler' => CartCompletedHandler::class,
        ], $total, $validatedData['country'], $validatedData['region'] ?? null, $validatedData['tax_id'] ?? null, $gatewayConfig->id);

        return $payment; // Return the payment object
    }

    /**
     * Add package to cart.
     *
     *
     * @return Order
     *
     * @throws ValidationException
     */
    public function addPackageToCart(array $input)
    {
        $validatedData = Validator::make($input, [
            'cart_id' => ['required', 'integer', 'exists:carts,id'],
            'package_price_id' => ['required', 'exists:package_prices,id'],
            'config_options' => ['nullable', 'array'],
        ])->validate();

        $cart = Cart::find($validatedData['cart_id']);
        $price = PackagePrice::find($validatedData['package_price_id']);
        $package = $price?->package;
        $user = $cart?->user ?? auth()->user();

        if (! $package || ! $package->isVisibleToUser($user)) {
            throw ValidationException::withMessages([
                'package_price_id' => 'This package is not available for purchase.',
            ]);
        }

        $packageQuantityInCart = $cart->items
            ->filter(fn ($item) => $item->cartable instanceof PackagePrice && $item->cartable->package_id === $package->id)
            ->sum('quantity');

        $requestedQuantity = $packageQuantityInCart + 1;

        if ($package->global_quantity !== -1 && $package->global_quantity < $requestedQuantity) {
            throw ValidationException::withMessages([
                'package_error' => 'This package is out of stock.',
            ]);
        }

        if ($user && $package->client_quantity !== -1) {
            $clientPackageOrdersCount = Order::query()
                ->where('user_id', $user->id)
                ->where('package_id', $package->id)
                ->where('status', '!=', 'terminated')
                ->count();

            if (($clientPackageOrdersCount + $requestedQuantity) > $package->client_quantity) {
                throw ValidationException::withMessages([
                    'package_error' => 'You have reached the maximum quantity allowed for this package.',
                ]);
            }
        }

        DB::transaction(function () use ($cart, $price, $validatedData) {
            $breakdown = $price->package->configurableOptionCalculator(
                $validatedData['config_options'] ?? [],
                $price->period_in_days
            );

            $item = $cart->items()->create([
                'cartable_type' => PackagePrice::class,
                'cartable_id' => $price->id,
                'name' => $price->package->name,
                'icon' => $price->package->icon(),
                'price' => $price->price + $price->setup_fee,
                'handler' => PackagePurchasedHandler::class,
            ]);

            $options = $item->options()->createMany(collect($breakdown['breakdown'])->map(function ($option) {
                return [
                    'name' => $option['label'],
                    'price' => $option['total'],
                    'key' => $option['key'],
                    'value' => $option['value'],
                ];
            })->toArray()
            );

            return $item;
        });
    }

    /**
     * Remove an item from the cart
     *
     * @throws ValidationException
     */
    public static function removeItemFromCart(array $input)
    {
        $validatedData = Validator::make($input, [
            'cart_id' => ['required', 'integer', 'exists:carts,id'],
            'item_id' => ['required', 'integer', 'exists:cart_items,id'],
        ])->validate();

        $cart = Cart::find($validatedData['cart_id']);

        if (! $cart) {
            throw ValidationException::withMessages([
                'cart_id' => 'Cart not found',
            ]);
        }

        $item = $cart->items()->find($validatedData['item_id']);

        if (! $item) {
            throw ValidationException::withMessages([
                'item_id' => 'Item not found',
            ]);
        }

        $item->remove();

        return true;
    }

    /**
     * Increment the quantity of an item in the cart
     *
     * @throws ValidationException
     */
    public static function incrementItemQuantity(array $input)
    {
        $validatedData = Validator::make($input, [
            'cart_id' => ['required', 'integer', 'exists:carts,id'],
            'item_id' => ['required', 'integer', 'exists:cart_items,id'],
        ])->validate();

        $cart = Cart::find($validatedData['cart_id']);

        if (! $cart) {
            throw ValidationException::withMessages([
                'cart_id' => 'Cart not found',
            ]);
        }

        $item = $cart->items()->find($validatedData['item_id']);

        if (! $item) {
            throw ValidationException::withMessages([
                'item_id' => 'Item not found',
            ]);
        }

        // check if the item is already at the maximum quantity
        if ($item->quantity >= settings('max_cart_quantity', 15)) {
            throw ValidationException::withMessages([
                'quantity' => 'Maximum allowed quantity is '.settings('max_cart_quantity', 15),
            ]);
        }

        $item->incrementQuantity();

        return $item;
    }

    /**
     * Decrement the quantity of an item in the cart
     *
     * @throws ValidationException
     */
    public static function decrementItemQuantity(array $input)
    {
        $validatedData = Validator::make($input, [
            'cart_id' => ['required', 'integer', 'exists:carts,id'],
            'item_id' => ['required', 'integer', 'exists:cart_items,id'],
        ])->validate();

        $cart = Cart::find($validatedData['cart_id']);

        if (! $cart) {
            throw ValidationException::withMessages([
                'cart_id' => 'Cart not found',
            ]);
        }

        $item = $cart->items()->find($validatedData['item_id']);

        if (! $item) {
            throw ValidationException::withMessages([
                'item_id' => 'Item not found',
            ]);
        }

        $item->decrementQuantity();

        return $item;
    }
}
