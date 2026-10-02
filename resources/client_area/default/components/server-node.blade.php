<figure {{ $attributes->class('vh-node') }} aria-label="Vaded hosting infrastructure illustration">
    <div class="vh-node-grid" aria-hidden="true"></div>
    <div class="vh-node-top"><span>VADED / INFRASTRUCTURE</span><span>01 — COMPUTE</span></div>
    <div class="vh-node-core" aria-hidden="true">
        <div class="vh-node-chip"><span>V</span><small>VADED</small></div>
        @foreach(['COMPUTE', 'MEMORY', 'STORAGE'] as $label)
            <div class="vh-node-unit"><x-theme::icon :name="['COMPUTE' => 'cpu', 'MEMORY' => 'memory', 'STORAGE' => 'storage'][$label]" /><span>{{ $label }}</span><i></i><i></i><b></b></div>
        @endforeach
        <div class="vh-node-base"></div>
    </div>
    <div class="vh-node-connection" aria-hidden="true"><span></span><x-theme::icon name="network" /><span></span></div>
    <figcaption><span>BUILT AROUND YOUR WORKLOAD</span><span>Infrastructure illustration</span></figcaption>
</figure>
