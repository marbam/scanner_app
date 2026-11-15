<div class="mx-auto flex h-full w-full max-w-3xl flex-1 flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('Highlights') }}</flux:heading>
        <flux:subheading>{{ __('The highlight of each day, newest first.') }}</flux:subheading>
    </div>

    <div class="flex flex-wrap gap-2">
        <flux:button :href="route('day', ['step' => 'highlight'])" wire:navigate size="sm" icon="sparkles">{{ __('Write today\'s highlight') }}</flux:button>
        <flux:button :href="route('day.backdate', ['step' => 'highlight'])" wire:navigate size="sm" variant="ghost" icon="clock">{{ __('Write one for an earlier day') }}</flux:button>
    </div>

    <div class="flex flex-col gap-3 sm:flex-row">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search your highlights…')" clearable class="flex-1" />

        <flux:select wire:model.live="year" class="sm:max-w-32">
            <flux:select.option value="">{{ __('All time') }}</flux:select.option>
            @foreach ($this->years as $y)
                <flux:select.option value="{{ $y }}">{{ $y }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <flux:text class="text-sm text-zinc-500">
        {{ trans_choice('{0} No highlights|{1} 1 highlight|[2,*] :count highlights', $this->total, ['count' => number_format($this->total)]) }}{{ $year !== '' ? ' · '.$year : '' }}
    </flux:text>

    <div class="flex flex-col gap-3">
        @forelse ($this->highlights as $highlight)
            <a
                href="{{ route('day.backdate', ['date' => $highlight->date->toDateString(), 'step' => 'highlight']) }}"
                wire:navigate
                wire:key="highlight-{{ $highlight->id }}"
                class="group flex flex-col gap-1 rounded-lg border-l-4 border-amber-400 bg-amber-50/50 p-3 shadow-sm transition hover:bg-amber-100/60 dark:border-amber-500 dark:bg-amber-950/20 dark:hover:bg-amber-950/40"
            >
                <div class="flex items-center justify-between gap-2">
                    <flux:heading size="sm">{{ $highlight->date->format('l j F Y') }}</flux:heading>
                    <flux:icon name="pencil-square" variant="mini" class="shrink-0 text-amber-600 opacity-60 group-hover:opacity-100 dark:text-amber-400" />
                </div>
                <flux:text class="whitespace-pre-line break-words">{{ $highlight->body }}</flux:text>
            </a>
        @empty
            <div class="rounded-lg border border-dashed border-zinc-300 p-6 text-center text-zinc-500 dark:border-zinc-700">
                {{ trim($search) !== '' || $year !== '' ? __('Nothing matches.') : __('No highlights written yet.') }}
                <flux:link :href="route('day', ['step' => 'highlight'])" wire:navigate>{{ __('Write today\'s.') }}</flux:link>
            </div>
        @endforelse
    </div>

    {{ $this->highlights->links() }}
</div>
