<div class="vh-store vh-vps-store">
    <section class="vh-vps-hero" aria-labelledby="vps-title">
        <span class="vh-kicker"><x-theme::icon name="cloud" /> CLOUD VPS</span>
        <h1 id="vps-title">Your cloud. <em>At full power.</em></h1>
        <p>{{ $selectedCategory->description ?: 'A home for your applications, projects, and next big idea. Choose your resources and make your server your own.' }}</p>
        <div class="vh-vps-hero-note"><span></span> YOUR RESOURCES. YOUR WORKLOAD. YOUR WAY.</div>
    </section>

    <div id="pricing" class="vh-vps-catalog">
        @livewire(client_view_path('categories.livewire.product-list'), ['category' => $selectedCategory->slug, 'vpsLayout' => true], key('vps-plans-'.$selectedCategory->id))
    </div>

    <section class="vh-vps-benefits grid grid-cols-1 gap-6 md:grid-cols-3" aria-label="Your hosting workspace">
        @foreach([
            ['sliders', 'A plan that fits.', 'Compare included resources and review your available configuration before checkout.'],
            ['receipt', 'Every charge, clear.', 'See your billing cycle, setup fees, and any extras before placing your order.'],
            ['console', 'One connected workspace.', 'Keep your service details, renewals, and account together in your client area.'],
        ] as [$icon, $title, $description])
            <div class="vh-vps-benefit flex items-start gap-4"><x-theme::icon :name="$icon" /><div><h2>{{ $title }}</h2><p>{{ $description }}</p></div></div>
        @endforeach
    </section>

    <section id="vps-details" class="vh-section vh-vps-workloads" aria-labelledby="vps-workloads-title">
        <div class="vh-section-heading"><div><span class="vh-kicker">A SMALL SERVER. BIG POSSIBILITIES.</span><h2 id="vps-workloads-title">What will you build?</h2><p>Start with your workload. Choose the resources to match.</p></div><a href="#pricing" class="vh-text-link">Find your VPS <x-theme::icon name="arrow" /></a></div>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            @foreach([
                ['cloud', '01', 'Websites & applications', 'Give your website, API, or personal project a place to run. Pick the stack that works for you.'],
                ['console', '02', 'Development & testing', 'Create a separate environment for experiments, staging, and the things you are still figuring out.'],
                ['bot', '03', 'Bots & background jobs', 'Bring your bots, scheduled tasks, and lightweight services together on your own server.'],
            ] as [$icon, $number, $title, $description])
                <article class="vh-vps-workload"><div class="flex items-center justify-between gap-4"><x-theme::icon :name="$icon" /><span>{{ $number }}</span></div><h3>{{ $title }}</h3><p>{{ $description }}</p></article>
            @endforeach
        </div>
    </section>

    <section id="faq" class="vh-section vh-vps-faq grid grid-cols-1 gap-8 lg:grid-cols-[1fr_1.6fr]" aria-labelledby="vps-faq-title">
        <div><span class="vh-kicker">BEFORE YOU DEPLOY</span><h2 id="vps-faq-title">A few things<br>worth knowing.</h2><p>Clear answers for your next server.</p></div>
        <div>
            @foreach([
                ['What resources come with my VPS?', 'Each plan lists its included resources and features. Open the plan configuration to review the full details, available options, and final price before ordering.'],
                ['Which operating systems and regions can I choose?', 'The options depend on your chosen plan and its server integration. Review the available operating systems and deployment options on the configuration page.'],
                ['How does VPS billing work?', 'Choose one of the billing cycles offered for your plan. The listed price covers that cycle; any setup fees, configurable extras, and applicable taxes appear before checkout.'],
                ['Where do I manage my server?', 'Your client dashboard keeps your services and payments in one place. Open your VPS service to see its details, available controls, and any linked management panel.'],
            ] as [$question, $answer])
                <details class="vh-vps-question"><summary>{{ $question }}<span aria-hidden="true">+</span></summary><p>{{ $answer }}</p></details>
            @endforeach
        </div>
    </section>

    <section class="vh-vps-cta flex flex-col items-start justify-between gap-6 md:flex-row md:items-center"><div><span class="vh-kicker">MAKE ROOM FOR YOUR NEXT IDEA</span><h2>Build something yours.</h2><p>Your next project starts with the right server.</p></div><a href="#pricing" class="vh-action">Choose your VPS <x-theme::icon name="arrow" /></a></section>
</div>
