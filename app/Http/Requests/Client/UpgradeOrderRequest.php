<?php

namespace App\Http\Requests\Client;

use App\Models\Order;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpgradeOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $order = $this->route('order');

        return $order instanceof Order && (int) $order->user_id === $this->user()?->id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'package_price_id' => ['required', 'integer', 'exists:package_prices,id'],
            'quote_token' => ['required', 'string', 'size:64'],
            'quoted_at' => ['required', 'integer', 'min:0'],
            'confirm_upgrade' => ['required', 'accepted'],
        ];
    }
}
