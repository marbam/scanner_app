{{-- Step tabs: $tabs is [step => [label, icon, count|null]]. --}}
<div @class(['grid gap-2', 'grid-cols-3' => count($tabs) === 3, 'grid-cols-4' => count($tabs) === 4, 'grid-cols-5' => count($tabs) >= 5])>
    @foreach ($tabs as $key => [$label, $icon, $count])
        <button
            type="button"
            wire:click="goTo('{{ $key }}')"
            wire:key="step-{{ $key }}"
            @class([
                'flex flex-col items-center justify-center gap-1 rounded-lg border px-2 py-2 text-xs font-medium transition sm:flex-row sm:gap-2 sm:px-3 sm:text-sm',
                'border-zinc-800 bg-zinc-800 text-white dark:border-white dark:bg-white dark:text-zinc-900' => $step === $key,
                'border-zinc-200 bg-white text-zinc-700 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200' => $step !== $key,
            ])
        >
            <span class="flex items-center gap-1">
                <flux:icon :name="$icon" variant="mini" />
                @if ($count !== null && $count !== '' && $count !== 0)
                    <span class="rounded-full bg-zinc-200 px-1.5 text-xs text-zinc-800 dark:bg-zinc-700 dark:text-zinc-100">{{ $count }}</span>
                @endif
            </span>
            <span class="truncate">{{ $label }}</span>
        </button>
    @endforeach
</div>
