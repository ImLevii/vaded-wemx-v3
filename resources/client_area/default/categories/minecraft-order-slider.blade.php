@php
    $sliderPlans = $this->minecraftPlans;
    $selectedPlan = $sliderPlans->get($minecraftPlanIndex) ?? $sliderPlans->first();
    $selectedThread = $selectedPlan['threads'][$minecraftThreadIndex] ?? $selectedPlan['threads'][0];
    $selectedRate = collect($selectedPlan['preview']['rates'])->firstWhere('period', $minecraftPeriod) ?? $selectedPlan['preview']['rates'][0];
@endphp

<section class="vh-order-slider" aria-labelledby="minecraft-order-heading"
    x-data="{ plans: @js($sliderPlans->pluck('preview')->all()), planIndex: $wire.entangle('minecraftPlanIndex'), threadIndex: $wire.entangle('minecraftThreadIndex'), period: $wire.entangle('minecraftPeriod'), get selected() { return this.plans[this.planIndex] || this.plans[0] }, get thread() { return this.selected.threads[this.threadIndex] || this.selected.threads[0] }, get rate() { return this.selected.rates.find(rate => rate.period === this.period) || this.selected.rates[0] } }">
    <h3 id="minecraft-order-heading">Order your Minecraft server</h3>
    <div class="vh-order-slider-card">
        <div class="vh-order-slider-body">
            <h4 class="vh-order-slider-title">
                <span x-text="selected.name">{{ $selectedPlan['package']->name }}</span>
                <span aria-hidden="true"> | </span>
                <span x-text="selected.includedThreads === 0 ? 'Unlimited CPU' : (selected.includedThreads + thread.additional) + ' ' + (selected.includedThreads + thread.additional === 1 ? 'Thread' : 'Threads')">@if($selectedPlan['preview']['includedThreads'] === 0)Unlimited CPU@else{{ $selectedPlan['preview']['includedThreads'] + $selectedThread['additional'] }} {{ $selectedPlan['preview']['includedThreads'] + $selectedThread['additional'] === 1 ? 'Thread' : 'Threads' }}@endif</span>
            </h4>

            <div class="vh-order-slider-control">
                <label class="sr-only" for="minecraft-memory-slider-{{ $category->id }}">Minecraft server memory</label>
                <div class="vh-order-slider-track">
                    <output class="vh-order-slider-tooltip" for="minecraft-memory-slider-{{ $category->id }}"
                        :style="{ left: (plans.length > 1 ? planIndex / (plans.length - 1) * 100 : 0) + '%', '--slider-position': plans.length > 1 ? planIndex / (plans.length - 1) * 100 : 0 }"
                        x-text="selected.name">{{ $selectedPlan['package']->name }}</output>
                    <input id="minecraft-memory-slider-{{ $category->id }}" class="vh-order-slider-input" type="range"
                        min="0" max="{{ max(0, $sliderPlans->count() - 1) }}" step="1" value="{{ $minecraftPlanIndex }}"
                        x-model.number="planIndex" @input="threadIndex = 0" @change="$wire.$commit()"
                        :aria-valuetext="selected.memory + ', ' + selected.name" @disabled($sliderPlans->count() === 1)>
                    <div class="vh-order-slider-ticks" aria-hidden="true">
                        @foreach($sliderPlans as $plan)
                            <button type="button" tabindex="-1" style="left: {{ $sliderPlans->count() > 1 ? $loop->index / ($sliderPlans->count() - 1) * 100 : 0 }}%"
                                @click="planIndex = {{ $loop->index }}; threadIndex = 0; $wire.$commit()">{{ $plan['preview']['memory'] }}</button>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="vh-order-slider-control">
                <label class="sr-only" for="minecraft-threads-slider-{{ $category->id }}">Additional CPU threads</label>
                <div class="vh-order-slider-track">
                    <output class="vh-order-slider-tooltip" for="minecraft-threads-slider-{{ $category->id }}"
                        :style="{ left: (selected.threads.length > 1 ? threadIndex / (selected.threads.length - 1) * 100 : 0) + '%', '--slider-position': selected.threads.length > 1 ? threadIndex / (selected.threads.length - 1) * 100 : 0 }"
                        x-text="thread.additional + ' Additional ' + (thread.additional === 1 ? 'Thread' : 'Threads')">{{ $selectedThread['additional'] }} Additional Threads</output>
                    <input id="minecraft-threads-slider-{{ $category->id }}" class="vh-order-slider-input" type="range"
                        min="0" max="{{ count($selectedPlan['threads']) - 1 }}" :max="selected.threads.length - 1" step="1" value="{{ $minecraftThreadIndex }}"
                        x-model.number="threadIndex" @change="$wire.$commit()" :disabled="selected.threads.length === 1"
                        :aria-valuetext="thread.additional + ' additional CPU threads'" @disabled(count($selectedPlan['threads']) === 1)>
                    <div class="vh-order-slider-ticks" aria-hidden="true">
                        <template x-for="(choice, index) in selected.threads" :key="index">
                            <button type="button" tabindex="-1" :style="{ left: (selected.threads.length > 1 ? index / (selected.threads.length - 1) * 100 : 0) + '%' }"
                                @click="threadIndex = index; $wire.$commit()" x-text="choice.additional"></button>
                        </template>
                    </div>
                </div>
                <p class="vh-order-slider-note" x-show="selected.threads.length === 1">This plan includes its CPU allocation. Additional threads are unavailable.</p>
            </div>

            @if($selectedPlan['prices']->count() > 1)
                <label class="vh-order-slider-cycle">Billing cycle
                    <select wire:model.live="minecraftPeriod">
                        @foreach($selectedPlan['prices'] as $planPrice)
                            <option value="{{ $planPrice->period_in_days }}">{{ $planPrice->cycle() }}</option>
                        @endforeach
                    </select>
                </label>
            @endif
            @foreach(['minecraftPlanIndex', 'minecraftThreadIndex', 'minecraftPeriod'] as $field)
                @error($field)<p class="text-sm text-red-500" role="alert">{{ $message }}</p>@enderror
            @endforeach
        </div>
        <div class="vh-order-slider-footer">
            <div class="vh-order-slider-price" aria-live="polite" aria-atomic="true">
                <div><strong x-text="rate.totals[threadIndex] || rate.totals[0]">{{ $selectedRate['totals'][$minecraftThreadIndex] ?? $selectedRate['totals'][0] }}</strong><span x-text="' / ' + rate.cycle"> / {{ $selectedRate['cycle'] }}</span></div>
                <small x-text="rate.setup">{{ $selectedRate['setup'] }}</small>
            </div>
            <button type="button" class="vh-order-slider-configure" wire:click="configureMinecraftServer" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="configureMinecraftServer">Configure Server</span>
                <span wire:loading wire:target="configureMinecraftServer">Opening configuration&hellip;</span>
            </button>
        </div>
    </div>
    <p class="vh-order-slider-disclaimer">Other configuration options and applicable taxes are shown before checkout.</p>
</section>
