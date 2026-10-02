<?php

use App\Models\User;
use Livewire\Volt\Component;
use PragmaRX\Google2FA\Google2FA;

// Bacon QR Code
use BaconQrCode\Writer;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;

new class extends Component {

    public $tfa_code;

    public $QRCode;   // will hold SVG markup

    public function mount(Google2FA $google2fa)
    {
        if (auth()->user()) {
            $secretKey = auth()->user()->generateTwoFactorSecret();

            $QRCodeUrl = $google2fa->getQRCodeUrl(
                settings('app_name', config('app.name')),
                auth()->user()->email,
                $secretKey
            );

            $renderer = new ImageRenderer(
                new RendererStyle(240, 2), // size, margin
                new SvgImageBackEnd()
            );

            $writer = new Writer($renderer);
            $this->QRCode = $writer->writeString($QRCodeUrl); // SVG XML string
        }
    }

    public function enableTwoFactorAuth()
    {
        User::authActions()->enableTwoFactorAuthAsClient([
            'user_id' => auth()->id(),
            'tfa_code' => $this->tfa_code,
        ]);

        $this->redirect(route('account.settings'), true);
    }
};

?>


<div
    class="flex flex-col items-center justify-center"
    x-data="{ secret: @js(auth()->user()->tfa_secret), copied: false }"
>
    <x-theme::text.h4 class="mb-4">Two Factor Authentication</x-theme::text.h4>
    <div
        class="mb-6 flex cursor-pointer justify-center rounded"
        data-tooltip-target="tooltip-secret_key"
        @click.prevent="
            navigator.clipboard.writeText(secret).then(() => {
                copied = true;
                setTimeout(() => copied = false, 1800);
            });
        "
    >
        {!! $QRCode !!}
    </div>
    <div id="tooltip-secret_key" role="tooltip" class="absolute z-10 invisible inline-block px-3 py-2 text-sm font-medium text-white transition-opacity duration-300 bg-gray-900 rounded-lg shadow-xs opacity-0 tooltip dark:bg-gray-700">
        <span x-show="!copied" x-text="secret"></span>
        <span x-show="copied">Copied!</span>
        <div class="tooltip-arrow" data-popper-arrow></div>
    </div>
    <x-theme::text.p class="mb-4 text-center">Scan the QR code using the authenticator app, and enter in the 2FA code.
        Hover over the QR code to view the secret key.
    </x-theme::text.p>
    <form wire:submit="enableTwoFactorAuth" class="w-full">
        <div class="mb-6 w-full">
            <x-theme::form.input type="text" placeholder="123456" wire:model="tfa_code" autocomplete="one-time-code"/>
            @error('tfa_code')
                <x-theme::form.error :text="$message"/>
            @enderror
        </div>
        <x-theme::button.primary type="submit" text="Enable" class="mb-3 w-full"/>
    </form>
    <x-theme::text.link href="{{ route('dashboard') }}" class="dark:text-primary-500 text-primary-600">Return to
        Dashboard
    </x-theme::text.link>
</div>
