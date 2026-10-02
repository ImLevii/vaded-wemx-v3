<?php

namespace Extensions\Gateways\TebexCheckout;

use App\Extensions\Foundation\GatewayExtension;
use App\Models\GatewayConfig;
use App\Models\Payment;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class Gateway extends GatewayExtension
{
    /**
     * Define the extension identifier. This identifier should be unique.
     * For example, if the extension name is "Example Module", the extension identifier should be "module-example".
     */
    protected string $id = 'gateway-tebex-checkout';

    /**
     * Define the extension display name
     */
    protected string $name = 'Tebex Checkout';

    /**
     * Define the extension description.
     */
    protected string $description = 'Accept payments online using Tebex Checkout.';

    /**
     * Define the extension type. For example, if the extension is a module, the extension type should be "Module".
     */
    protected string $type = 'Gateway';

    /**
     * Define the gateway type. Can be "subscription" or "payment".
     */
    protected string $gatewayType = 'payment';

    /**
     * Define the supported currencies.
     */
    protected array $currencies = [
        'USD',
    ];

    /**
     * Define the extension version.
     */
    protected string $version = '1.0.0';

    /**
     * Define the WemX versions that the extension is compatible with.
     * Use * to define that the extension is compatible with all versions.
     */
    protected array $wemxVersions = [
        '1.0.0',
    ];

    /**
     * Define the authors of the extension.
     */
    protected array $authors = [
        [
            'name' => 'WemX',
            'email' => 'team@wemx.net',
        ],
    ];

    /**
     * Define the extension display description to customers.
     */
    public string $gatewayDescription = 'Pay easily using Tebex\'s hosted checkout page.';

    protected string $api_url = 'https://checkout.tebex.io/api';

    /**
     * Define the default configuration values required to setup this gateway
     * i.e host, api key, or other values. Use Laravel validation rules for
     *
     * Laravel validation rules: https://laravel.com/docs/10.x/validation
     */
    public static function setConfig(): array
    {
        return [
            'username' => [
                'label' => 'Tebex Username',
                'description' => 'Enter your Tebex username',
                'type' => 'text',
                'rules' => ['required'],
            ],
            'password' => [
                'label' => 'Tebex Password',
                'description' => 'Enter your Tebex password',
                'type' => 'text',
                'rules' => ['required'],
            ],
            'webhook_key' => [
                'label' => 'Webhook Key',
                'description' => 'Enter your Tebex webhook key',
                'type' => 'text',
                'rules' => ['required'],
            ],
        ];
    }

    /**
     * Handle the payment process.
     * This method should create a payment, then redirect the user to the payment gateway for approval.
     *
     * If something goes wrong, you can throw an exception to return a 500 error.
     * Do not include any sensitive in the exception message.
     *
     * To retrieve config values, you can use $gatewayConfig->config('key', 'default_value').
     *
     * @return RedirectResponse
     *
     * @throws Exception
     */
    public function pay(Payment $payment, GatewayConfig $gatewayConfig)
    {
        $checkout = $this->api('post', '/checkout',
            [
                'username' => $gatewayConfig->config('username', 'empty'),
                'password' => $gatewayConfig->config('password', 'empty'),
            ],
            [
                'basket' => [
                    'custom' => [
                        'payment_id' => $payment->id,
                    ],
                    'return_url' => $payment->cancelUrl(),
                    'complete_url' => $payment->callbackUrl(),
                ],
                'items' => [
                    [
                        'package' => [
                            'name' => $payment->description,
                            'price' => $payment->total(),
                            'metaData' => [
                                'payment_id' => $payment->id,
                            ],
                        ],
                        'type' => 'single',
                    ],
                ],
            ]);

        if (! $checkout->successful()) {
            throw new Exception('Failed to create checkout using Tebex API');
        }

        // store transaction id locally
        $payment->update([
            'transaction_id' => $checkout['id'],
        ]);

        return redirect()->away($checkout['links']['checkout']);
    }

    public function callback(Request $request, GatewayConfig $gatewayConfig)
    {
        $payment = Payment::find($request->get('payment_id'));

        if (! $payment) {
            throw new Exception('Payment not found');
        }

        if ($payment->isPaid()) {
            return redirect($payment->successUrl());
        }

        // in the future, make api call to tebex to verify payment status

        return redirect($payment->successUrl());
    }

    /**
     * Handle (webhooks) from Tebex.
     * PayPal calls this endpoint whenever the payment status changes.
     *
     * If something goes wrong, you can throw an exception to return a 500 error.
     * Do not include any sensitive in the exception message.
     *
     * Return a 200 json response to acknowledge the webhook.
     *
     * @param  GatewayConfig  $gatwewayConfig
     * @return JsonResponse
     *
     * @throws Exception
     *
     * Example response:
     * return response()->json([
     *     'success' => true,
     *     'message' => 'Webhook received successfully',
     * ]);
     */
    public function webhook(Request $request, GatewayConfig $gatewayConfig)
    {
        // WebHook validation
        if ($request->get('type', 'none') == 'validation.webhook') {
            return response()->json(['id' => $request->get('id')], 200);
        }

        $payment_id = $request->get('subject')['custom']['payment_id'];
        $payment = Payment::find($payment_id);

        if ($this->isSignatureValid($request, $gatewayConfig->config('webhook_key', 'empty'))) {
            // Skip Subscription
            if ($request->get('subject')['recurring_payment_reference'] != null) {
                return response()->json(['success' => 'The event has been canceled, we are waiting for the event from the subscription'], 200);
            }

            if ($request->get('type', 'none') == 'payment.completed') {
                $transaction_id = $request->get('subject')['transaction_id'];
                $status = $request->get('subject')['status']['description'];

                if ($status == 'Complete') {
                    $payment->completed($transaction_id, $request->all());

                    return response()->json(['success' => 'Payment completed successfully'], 200);
                } else {
                    return response()->json(['error' => "Payment status: {$status}"], 403);
                }
            }
        } else {
            return response()->json(['error' => 'WebHook signature error'], 403);
        }
    }

    private function isSignatureValid(Request $request, $webhookKey): bool
    {
        $payload = $request->getContent();
        $signature = $request->header('X-Signature', 'empty');
        $calculatedSignature = hash_hmac('sha256', hash('sha256', $payload), $webhookKey);

        return hash_equals($calculatedSignature, $signature);
    }

    private function api($method, $endpoint, $credentials = [], $data = [])
    {
        return Http::withBasicAuth($credentials['username'], $credentials['password'])->asJson()
            ->$method($this->api_url.$endpoint, $data);
    }
}
