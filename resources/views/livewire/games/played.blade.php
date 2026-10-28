@php
    $byDay = $this->playsByDay;
    $allPlays = $byDay->flatten(1);
@endphp

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('Played') }}</flux:heading>
        <flux:subheading>{{ __('Steam games played each day, recorded by the overnight library sync.') }}</flux:subheading>
    </div>

    <div class="flex flex-wrap gap-2">
        @foreach (\App\Livewire\Games\Played::RANGES as $range)
            <flux:button size="sm" :variant="$days === $range ? 'primary' : 'outline'" wire:click="setDays({{ $range }})">
                {{ $range === 365 ? __('Last year') : __('Last :n days', ['n' => $range]) }}
            </flux:button>
        @endforeach
    </div>

    @if ($byDay->isNotEmpty())
        <div class="grid grid-cols-3 gap-3">
            <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                <flux:text size="sm">{{ __('Days played') }}</flux:text>
                <flux:heading size="lg">{{ $byDay->count() }}</flux:heading>
            </div>
            <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                <flux:text size="sm">{{ __('Different games') }}</flux:text>
                <flux:heading size="lg">{{ $allPlays->pluck('steam_game_id')->unique()->count() }}</flux:heading>
            </div>
            <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                <flux:text size="sm">{{ __('Total time') }}</flux:text>
                <flux:heading size="lg">{{ \App\Livewire\Games\Played::formatMinutes($allPlays->sum('minutes')) }}</flux:heading>
            </div>
        </div>
    @endif

    <div class="flex flex-col gap-4">
        @forelse ($byDay as $date => $plays)
            <div wire:key="day-{{ $date }}" class="rounded-lg border border-zinc-200 dark:border-zinc-700">
                <div class="flex items-baseline justify-between gap-3 border-b border-zinc-200 px-4 py-3 dark:border-zinc-700">
                    <flux:heading size="md">{{ \Illuminate\Support\Carbon::parse($date)->format('D j M Y') }}</flux:heading>
                    <flux:text size="sm" class="whitespace-nowrap">
                        {{ trans_choice(':count game|:count games', $plays->count()) }}
                        · {{ \App\Livewire\Games\Played::formatMinutes($plays->sum('minutes')) }}
                    </flux:text>
                </div>

                <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @foreach ($plays as $play)
                        <li wire:key="play-{{ $play->id }}" class="flex items-center gap-3 px-4 py-2">
                            <img src="{{ $play->game->headerImageUrl() }}" alt="" loading="lazy" class="h-8 w-[70px] shrink-0 rounded object-cover">
                            <flux:text class="min-w-0 flex-1 truncate">{{ $play->game->name }}</flux:text>
                            <flux:text size="sm" class="whitespace-nowrap tabular-nums">
                                {{ $play->minutes > 0 ? \App\Livewire\Games\Played::formatMinutes($play->minutes) : __('< 1m') }}
                            </flux:text>
                        </li>
                    @endforeach
                </ul>
            </div>
        @empty
            <flux:text class="text-center text-zinc-500">
                {{ __('Nothing recorded in this period yet. Plays are logged by the 04:00 Steam library sync.') }}
            </flux:text>
        @endforelse
    </div>
</div>
