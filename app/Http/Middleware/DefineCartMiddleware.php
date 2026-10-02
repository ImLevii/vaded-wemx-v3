<?php

namespace App\Http\Middleware;

use App\Models\Cart;
use Closure;
use Illuminate\Http\Request;

class DefineCartMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (! config('app.installed', false)) {
            return $next($request);
        }

        $sessionId = session()->getId();
        $userId = auth()->id();

        $cart = Cart::query()
            ->where('session_id', $sessionId)
            ->when($userId, fn ($query) => $query->orWhere('user_id', $userId))
            ->first();

        if (! $cart) {
            $cart = Cart::actions()->createCartForClient([
                'session_id' => $sessionId,
                'user_id' => $userId,
            ]);
        }

        $request->merge(['cart' => $cart]);

        return $next($request);
    }
}
