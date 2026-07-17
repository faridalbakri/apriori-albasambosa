<x-filament::section>
    <h2 class="text-sm font-semibold mb-3" style="color: var(--color-foreground);">Top 5 Bundling Rules</h2>

    @php $rules = $this->getRules(); @endphp

    @if (empty($rules))
        <p class="text-xs" style="color: var(--color-foreground); opacity: 0.6;">No rules yet. Generate from Apriori page.</p>
    @else
        <div class="space-y-1">
            @foreach ($rules as $i => $rule)
                <div class="flex items-center gap-3 py-2 {{ $i < count($rules) - 1 ? 'border-b' : '' }}" style="border-color: var(--color-border);">
                    <span class="flex-shrink-0 w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold text-white"
                          style="background: var(--color-primary);">
                        {{ $i + 1 }}
                    </span>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm truncate" style="color: var(--color-foreground);">
                            <span class="font-medium">{{ $rule['antecedent'] }}</span>
                            <span class="mx-1 opacity-40">→</span>
                            <span class="font-medium">{{ $rule['consequent'] }}</span>
                        </p>
                        <p class="text-xs mt-0.5" style="color: var(--color-foreground); opacity: 0.6;">
                            Confidence {{ $rule['confidence'] }} · Support {{ $rule['support'] }}
                        </p>
                    </div>
                    <span class="flex-shrink-0 text-sm font-bold" style="color: var(--color-accent);">Lift {{ $rule['lift'] }}</span>
                </div>
            @endforeach
        </div>
    @endif
</x-filament::section>
