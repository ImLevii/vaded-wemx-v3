<div class="vh-store vh-vps-store">
    <section class="vh-vps-hero" aria-labelledby="vps-title">
        <span class="vh-vps-eyebrow"><x-theme::icon name="cloud" /> Cloud VPS</span>
        <h1 id="vps-title">Your cloud. <em>At full power.</em></h1>
        <p>{{ $selectedCategory->description ?: 'A home for your applications, projects, and next big idea. Choose your resources and make your server your own.' }}</p>
        <div class="vh-vps-hero-note"><span aria-hidden="true"></span> Your resources. Your workload. Your way.</div>
    </section>

    <div id="pricing" class="vh-vps-catalog">
        @livewire(client_view_path('categories.livewire.product-list'), ['category' => $selectedCategory->slug, 'vpsLayout' => true], key('vps-plans-'.$selectedCategory->id))
    </div>

    <section class="vh-vps-benefits" aria-label="Your hosting workspace">
        @foreach([
            ['sliders', 'A plan that fits.', 'Compare included resources and review your available configuration before checkout.'],
            ['receipt', 'Every charge, clear.', 'See your billing cycle, setup fees, and any extras before placing your order.'],
            ['console', 'One connected workspace.', 'Keep your service details, renewals, and account together in your client area.'],
        ] as [$icon, $title, $description])
            <div class="vh-vps-benefit"><span class="vh-vps-icon-chip"><x-theme::icon :name="$icon" /></span><div><h2>{{ $title }}</h2><p>{{ $description }}</p></div></div>
        @endforeach
    </section>

    <section id="operating-systems" class="vh-vps-section vh-vps-distro" aria-labelledby="vps-os-title">
        <div class="vh-vps-section-heading">
            <div><span class="vh-vps-label">One-click installs</span><h2 id="vps-os-title">VPS Operating Systems</h2><p>We offer a variety of operating systems installed via one click using your control panel.</p></div>
            <a href="#pricing" class="vh-vps-link">Choose your VPS <x-theme::icon name="arrow" /></a>
        </div>
        <div class="vh-vps-distro-grid">
            @foreach(config('hosting.vps_operating_systems', []) as $key => $system)
                @if(count($system['versions']) > 1)
                    <article class="vh-vps-distro-card" data-os="{{ $key }}">
                        <span class="vh-vps-distro-mark" aria-hidden="true">@if($system['logo'] ?? null)<img src="{{ asset($system['logo']) }}" alt="" width="26" height="26" loading="lazy" decoding="async">@else{{ $system['mark'] }}@endif</span>
                        <div class="vh-vps-distro-copy">
                            <span class="vh-vps-distro-label">VPS OS</span>
                            <h3>{{ $system['name'] }} @isset($system['badge'])<span class="vh-vps-distro-badge" title="Bring Your Own License">{{ $system['badge'] }}</span>@endisset</h3>
                            <ul class="vh-vps-distro-versions" aria-label="{{ $system['name'] }} versions">
                                @foreach($system['versions'] as $version)<li>{{ $version }}</li>@endforeach
                            </ul>
                        </div>
                    </article>
                @else
                    <article class="vh-vps-distro-card vh-vps-distro-preinstall" data-os="{{ $key }}">
                        <span class="vh-vps-distro-mark" aria-hidden="true">@if($system['logo'] ?? null)<img src="{{ asset($system['logo']) }}" alt="" width="26" height="26" loading="lazy" decoding="async">@else{{ $system['mark'] }}@endif</span>
                        <div class="vh-vps-distro-copy">
                            <span class="vh-vps-distro-label">VPS OS</span>
                            <h3>{{ $system['name'] }}</h3>
                            @isset($system['note'])<p>{{ $system['note'] }}</p>@endisset
                        </div>
                        <ul class="vh-vps-distro-versions" aria-label="{{ $system['name'] }} base">
                            @foreach($system['versions'] as $version)<li>{{ $version }}</li>@endforeach
                        </ul>
                    </article>
                @endif
            @endforeach
        </div>
        <p class="vh-vps-distro-note"><span class="vh-vps-distro-badge">BYOL</span> <span><strong>Bring Your Own License</strong> means the operating system/game panel will not come with a license key.</span></p>
    </section>

    <section id="vps-details" class="vh-vps-section vh-vps-workloads" aria-labelledby="vps-workloads-title">
        <div class="vh-vps-section-heading">
            <div><span class="vh-vps-label">A small server. Big possibilities.</span><h2 id="vps-workloads-title">What will you build?</h2><p>Start with your workload. Choose the resources to match.</p></div>
            <a href="#pricing" class="vh-vps-link">Find your VPS <x-theme::icon name="arrow" /></a>
        </div>
        <div class="vh-vps-workload-grid">
            @foreach([
                ['cloud', '01', 'Websites & applications', 'Give your website, API, or personal project a place to run. Pick the stack that works for you.'],
                ['console', '02', 'Development & testing', 'Create a separate environment for experiments, staging, and the things you are still figuring out.'],
                ['bot', '03', 'Bots & background jobs', 'Bring your bots, scheduled tasks, and lightweight services together on your own server.'],
            ] as [$icon, $number, $title, $description])
                <article class="vh-vps-workload"><div class="vh-vps-workload-top"><span class="vh-vps-icon-chip"><x-theme::icon :name="$icon" /></span><span class="vh-vps-workload-number">{{ $number }}</span></div><h3>{{ $title }}</h3><p>{{ $description }}</p></article>
            @endforeach
        </div>
    </section>

    <section id="faq" class="vh-vps-section vh-vps-faq" aria-labelledby="vps-faq-title">
        <div class="vh-vps-faq-intro"><span class="vh-vps-label">Before you deploy</span><h2 id="vps-faq-title">A few things<br>worth knowing.</h2><p>Clear answers for your next server.</p></div>
        <div class="vh-vps-faq-list">
            @foreach([
                ['What resources come with my VPS?', 'Each plan lists its included resources and features. Open the plan configuration to review the full details, available options, and final price before ordering.'],
                ['Which operating systems can I choose?', 'Rocky Linux 8 & 9, Alma Linux 8 & 9, Windows Server 2019 & 2022 (BYOL), Debian 10 & 11, Ubuntu 18.04, 20.04 & 22.04, Fedora 36, 37 & 38, or a Pterodactyl preinstall built on Rocky Linux. Each one installs with one click from your control panel.'],
                ['What does BYOL mean?', 'Bring Your Own License. A BYOL operating system or game panel is installed without a license key, so you activate it with your own.'],
                ['How does VPS billing work?', 'Choose one of the billing cycles offered for your plan. The listed price covers that cycle; any setup fees, configurable extras, and applicable taxes appear before checkout.'],
                ['Where do I manage my server?', 'Your client dashboard keeps your services and payments in one place. Open your VPS service to see its details, available controls, and any linked management panel.'],
            ] as [$question, $answer])
                <details class="vh-vps-question"><summary><span>{{ $question }}</span><span class="vh-vps-question-toggle" aria-hidden="true"></span></summary><p>{{ $answer }}</p></details>
            @endforeach
        </div>
    </section>

    <section class="vh-vps-cta" aria-labelledby="vps-cta-title">
        <div><span class="vh-vps-label">Make room for your next idea</span><h2 id="vps-cta-title">Build something yours.</h2><p>Your next project starts with the right server.</p></div>
        <a href="#pricing" class="vh-action vh-vps-cta-action">Choose your VPS <x-theme::icon name="arrow" /></a>
    </section>
</div>
