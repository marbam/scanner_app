<div class="mx-auto flex h-full w-full max-w-3xl flex-1 flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('Watch history') }}</flux:heading>
        <flux:subheading>{{ __('Everything you\'ve logged, most recent first.') }}</flux:subheading>
    </div>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        @foreach ([
            __('Films') => $this->stats['films'],
            __('Cinema trips') => $this->stats['cinema'],
            __('Shows') => $this->stats['shows'],
            __('Days with TV') => $this->stats['tv_days'],
        ] as $label => $value)
            <div class="rounded-lg border border-zinc-200 bg-white px-4 py-3 dark:border-zinc-700 dark:bg-zinc-900">
                <div class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ number_format($value) }}</div>
                <div class="text-sm text-zinc-500">{{ $label }}{{ $year !== '' ? ' · '.$year : '' }}</div>
            </div>
        @endforeach
    </div>

    <div class="flex flex-col gap-3 sm:flex-row">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search your history…')" clearable class="flex-1" />

        <flux:select wire:model.live="type" class="sm:max-w-36">
            <flux:select.option value="all">{{ __('Films & TV') }}</flux:select.option>
            <flux:select.option value="{{ \App\Models\WatchTitle::MOVIE }}">{{ __('Films') }}</flux:select.option>
            <flux:select.option value="{{ \App\Models\WatchTitle::TV }}">{{ __('TV shows') }}</flux:select.option>
        </flux:select>

        <flux:select wire:model.live="year" class="sm:max-w-32">
            <flux:select.option value="">{{ __('All time') }}</flux:select.option>
            @foreach ($this->years as $y)
                <flux:select.option value="{{ $y }}">{{ $y }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <div class="flex flex-col gap-3">
        @forelse ($this->titles as $title)
            <div wire:key="title-{{ $title->id }}" class="flex gap-3 rounded-lg border border-zinc-200 bg-white p-3 dark:border-zinc-700 dark:bg-zinc-900">
                @include('livewire.watching.partials.poster', ['url' => $title->posterUrl(), 'tv' => $title->isTv()])

                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-semibold text-zinc-900 dark:text-white">{{ $title->title }}</span>
                        @if ($title->year)
                            <span class="text-sm text-zinc-500">({{ $title->year }})</span>
                        @endif
                        <flux:badge size="sm" color="{{ $title->isTv() ? 'sky' : 'violet' }}">{{ $title->isTv() ? __('TV') : __('Film') }}</flux:badge>
                    </div>

                    <div class="mt-1 flex flex-col gap-0.5 text-sm text-zinc-600 dark:text-zinc-300">
                        @foreach ($title->entries->groupBy(fn ($e) => $e->watched_on->toDateString()) as $day => $dayEntries)
                            <div wire:key="day-{{ $title->id }}-{{ $day }}">
                                <span class="text-zinc-500">{{ \Illuminate\Support\Carbon::parse($day)->format('D j M Y') }}</span>
                                @unless ($title->isTv())
                                    @foreach ($dayEntries as $entry)
                                        · {{ $entry->location === \App\Models\WatchEntry::CINEMA ? __('Cinema') : __('Home') }}
                                    @endforeach
                                @endunless
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @empty
            <div class="rounded-lg border border-dashed border-zinc-300 p-6 text-center text-zinc-500 dark:border-zinc-700">
                {{ trim($search) !== '' || $year !== '' || $type !== 'all' ? __('Nothing matches.') : __('Nothing logged yet.') }}
                <flux:link :href="route('watching.today')" wire:navigate>{{ __('Log today.') }}</flux:link>
            </div>
        @endforelse
    </div>

    {{ $this->titles->links() }}

    @include('livewire.watching.partials.tmdb-credit')
</div>
