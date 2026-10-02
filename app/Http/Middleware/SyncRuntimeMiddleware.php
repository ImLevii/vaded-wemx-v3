<?php

namespace App\Http\Middleware;

use App\Support\LocalLicense;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class SyncRuntimeMiddleware
{
    public array $except = [
        'default.livewire.update',
        'livewire.update',
        'admin.license.index',
        'admin.license.verify',
        'admin.license.update',
        'admin.reauthenticate',
        'logout',
    ];

    protected $syncRuntimeUrl = 'https://api-v3.wemx.org/api/v1/licenses/validate';

    public function handle(Request $request, Closure $next)
    {
        if (LocalLicense::isBypassed()) {
            return $next($request);
        }

        if (auth()->guest()) {
            return $next($request);
        }

        if (empty(config('app.license_key')) or ! str_starts_with(config('app.license_key'), 'WMX-')) {
            if (! auth()->user()->hasPermission('admin.settings.index')) {
                abort(403, 'License Expired');
            }

            if ($request->route() && ! in_array($request->route()->getName(), $this->except)) {
                return redirect()->route('admin.license.index');
            }

            return $next($request);
        }

        if (Cache::has('lcs_checked_at')) {
            return $next($request);
        }

        $response = Http::post($this->syncRuntimeUrl, [
            'license_key' => config('app.license_key'),
            'domain' => request()->getHost(),
        ]);

        if ($response->successful() and $response->json('success')) {

            Cache::put('lcs_checked_at', now(), 21600);

            settings([
                'encrypted:lcs_plan_data' => $response->json('data'),
            ]);

            return $next($request);
        }

        // redirect user to license page and skip the except routes
        if (auth()->user()->hasPermission('admin.settings.index')) {
            if ($request->route() && ! in_array($request->route()->getName(), $this->except)) {
                return redirect()->route('admin.license.index');
            }
        } else {
            abort(403, 'License Expired');
        }

        return $next($request);
    }
}
