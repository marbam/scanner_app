{{-- The TV or films step of the watching flow. Used by Watching\Today and Day\Index. --}}
@php
    $isTv = $this->mediaType() === \App\Models\WatchTitle::TV;
    $stepEntries = $this->entries->filter(fn ($e) => $e->title->isTv() === $isTv);
    $selected = $this->selectedTitle;
    $tmdb = $this->tmdbResults;
    $next = $this->nextStep();
@endphp

<div>
    <flux:heading size="lg">{{ $isTv ? __('Any TV shows?') : __('Any films?') }}</flux:heading>
    @if ($isTv)
        <flux:text class="text-sm">{{ __('Tap the shows you watched. Tap again to untick.') }}</flux:text>
    @endif
</div>

{{-- Films already logged on this date (shows are ticked in "Recently watched" instead) --}}
@if (! $isTv && $stepEntries->isNotEmpty())
    <div class="flex flex-col gap-2">
        @foreach ($stepEntries as $entry)
            <div wire:key="entry-{{ $entry->id }}" class="flex items-center justify-between gap-3 rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-2 dark:border-emerald-800 dark:bg-emerald-950/40">
                <div class="flex min-w-0 items-center gap-3">
                    <flux:icon.check-circle class="size-5 shrink-0 text-emerald-600 dark:text-emerald-400" />
                    <span class="truncate font-medium text-zinc-800 dark:text-zinc-100">{{ $entry->title->title }}</span>
                    @if ($entry->location === \App\Models\WatchEntry::CINEMA)
                        <flux:badge size="sm" color="amber">{{ __('Cinema') }}</flux:badge>
                    @endif
                </div>
                <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="remove({{ $entry->id }})" :aria-label="__('Remove')" />
            </div>
        @endforeach
    </div>
@endif

@if ($selected)
    {{-- Films only: choose Home or Cinema before adding --}}
    <form wire:submit="add" class="flex flex-col gap-4 rounded-lg border border-zinc-300 bg-white p-4 dark:border-zinc-600 dark:bg-zinc-900">
        <div class="flex items-center gap-3">
            @include('livewire.watching.partials.poster', ['url' => $selected->posterUrl(), 'tv' => false])
            <div>
                <div class="font-semibold text-zinc-900 dark:text-white">{{ $selected->title }}</div>
                @if ($selected->year)
                    <div class="text-sm text-zinc-500">{{ $selected->year }}</div>
                @endif
            </div>
        </div>

        <div class="flex flex-col gap-2">
            <flux:text class="text-sm font-medium">{{ __('Where did you watch it?') }}</flux:text>

            <div class="grid grid-cols-2 gap-2">
                @foreach ([\App\Models\WatchEntry::HOME => [__('Home'), 'home'], \App\Models\WatchEntry::CINEMA => [__('Cinema'), 'film']] as $value => [$label, $icon])
                    @php $isSelected = $location === $value; @endphp
                    <button
                        type="button"
                        wire:click="$set('location', '{{ $value }}')"
                        wire:key="location-{{ $value }}"
                        aria-pressed="{{ $isSelected ? 'true' : 'false' }}"
                        @class([
                            'flex items-center justify-center gap-2 rounded-lg border-2 px-3 py-3 font-semibold transition',
                            'border-amber-400 bg-amber-500 text-zinc-950 shadow-md' => $isSelected,
                            'border-zinc-200 bg-white text-zinc-500 hover:border-zinc-400 hover:text-zinc-700 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-400 dark:hover:border-zinc-500 dark:hover:text-zinc-200' => ! $isSelected,
                        ])
                    >
                        <flux:icon :name="$isSelected ? 'check-circle' : $icon" variant="mini" class="shrink-0" />
                        <span>{{ $label }}</span>
                    </button>
                @endforeach
            </div>
        </div>

        <div class="flex gap-2">
            <flux:button type="submit" variant="primary" class="flex-1">{{ __('Add') }}</flux:button>
            <flux:button type="button" variant="ghost" wire:click="cancelSelection">{{ __('Cancel') }}</flux:button>
        </div>
    </form>
@else
    {{-- Search --}}
    <div class="flex flex-col gap-3">
        <flux:input
            wire:model.live.debounce.400ms="search"
            icon="magnifying-glass"
            :placeholder="$isTv ? __('Search for a show…') : __('Search for a film…')"
            clearable
        />

        @unless (\App\Support\Tmdb::configured())
            <flux:text class="text-sm">{{ __('Add TMDB_API_KEY to .env to search for new titles. Only your own history is searched for now.') }}</flux:text>
        @endunless

        @if ($this->historyResults->isNotEmpty())
            <flux:text class="text-xs font-medium uppercase tracking-wide">{{ __('From your history') }}</flux:text>
            @foreach ($this->historyResults as $result)
                <button
                    type="button"
                    wire:key="history-{{ $result->id }}"
                    wire:click="chooseTitle({{ $result->id }})"
                    class="flex w-full items-center gap-3 rounded-lg border border-zinc-200 bg-white px-3 py-2 text-left transition hover:border-zinc-400 dark:border-zinc-700 dark:bg-zinc-900"
                >
                    @include('livewire.watching.partials.poster', ['url' => $result->posterUrl(), 'tv' => $isTv])
                    <div class="min-w-0 flex-1">
                        <div class="truncate font-medium text-zinc-800 dark:text-zinc-100">
                            {{ $result->title }}
                            @if ($result->year) <span class="font-normal text-zinc-500">({{ $result->year }})</span> @endif
                        </div>
                        <div class="text-sm text-zinc-500">
                            {{ trans_choice('{1} Watched once|[2,*] Watched :count times', $result->entries_count, ['count' => $result->entries_count]) }},
                            {{ __('last on :date', ['date' => \Illuminate\Support\Carbon::parse($result->getAttribute('entries_max_watched_on'))->format('D j M Y')]) }}
                        </div>
                    </div>
                    <flux:icon.plus class="size-5 text-zinc-400" />
                </button>
            @endforeach
        @endif

        @if ($tmdb['results'] !== [])
            <flux:text class="text-xs font-medium uppercase tracking-wide">{{ __('From TMDB') }}</flux:text>
            @foreach ($tmdb['results'] as $result)
                <button
                    type="button"
                    wire:key="tmdb-{{ $result['tmdb_id'] }}"
                    wire:click="chooseTmdb({{ $result['tmdb_id'] }})"
                    class="flex w-full items-center gap-3 rounded-lg border border-zinc-200 bg-white px-3 py-2 text-left transition hover:border-zinc-400 dark:border-zinc-700 dark:bg-zinc-900"
                >
                    @include('livewire.watching.partials.poster', ['url' => $result['poster_path'] ? \App\Support\Tmdb::IMAGE_BASE.'w92'.$result['poster_path'] : null, 'tv' => $isTv])
                    <div class="min-w-0 flex-1 truncate font-medium text-zinc-800 dark:text-zinc-100">
                        {{ $result['title'] }}
                        @if ($result['year']) <span class="font-normal text-zinc-500">({{ $result['year'] }})</span> @endif
                    </div>
                    <flux:icon.plus class="size-5 text-zinc-400" />
                </button>
            @endforeach
        @elseif ($tmdb['error'])
            <flux:text class="text-sm text-red-600">{{ __('TMDB search isn\'t responding right now.') }}</flux:text>
        @elseif (mb_strlen(trim($search)) >= 2 && $this->historyResults->isEmpty() && \App\Support\Tmdb::configured())
            <flux:text class="text-sm">{{ __('No matches.') }}</flux:text>
        @endif
    </div>

    {{-- Shows from the last couple of weeks, ticked if logged on this date --}}
    @if ($isTv && $this->recentShows !== [])
        <div class="flex flex-col gap-2">
            <flux:text class="text-xs font-medium uppercase tracking-wide">{{ __('Recently watched') }}</flux:text>

            @foreach ($this->recentShows as $show)
                <button
                    type="button"
                    wire:key="show-{{ $show['title']->id }}"
                    wire:click="toggleShow({{ $show['title']->id }})"
                    aria-pressed="{{ $show['logged'] ? 'true' : 'false' }}"
                    @class([
                        'flex w-full items-center gap-3 rounded-lg border px-3 py-2 text-left transition',
                        'border-emerald-400 bg-emerald-50 dark:border-emerald-700 dark:bg-emerald-950/40' => $show['logged'],
                        'border-zinc-200 bg-white hover:border-zinc-400 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-zinc-500' => ! $show['logged'],
                    ])
                >
                    @include('livewire.watching.partials.poster', ['url' => $show['title']->posterUrl(), 'tv' => true])
                    <div class="min-w-0 flex-1">
                        <div class="truncate font-medium text-zinc-800 dark:text-zinc-100">{{ $show['title']->title }}</div>
                        <div class="text-sm text-zinc-500">
                            {{ $show['last_watched'] ? __('Last watched :when', ['when' => $show['last_watched']->format('D j M')]) : __('First time') }}
                        </div>
                    </div>
                    @if ($show['logged'])
                        <flux:icon.check-circle variant="solid" class="size-7 shrink-0 text-emerald-600 dark:text-emerald-400" />
                    @else
                        <span class="size-6 shrink-0 rounded-full border-2 border-zinc-300 dark:border-zinc-600"></span>
                    @endif
                </button>
            @endforeach
        </div>
    @endif

    {{-- Films started recently, to log again --}}
    @if ($this->quickPicks !== [])
        <div class="flex flex-col gap-2">
            <flux:text class="text-xs font-medium uppercase tracking-wide">{{ __('Watched recently') }}</flux:text>

            @foreach ($this->quickPicks as $pick)
                <button
                    type="button"
                    wire:key="pick-{{ $pick['title']->id }}"
                    wire:click="chooseTitle({{ $pick['title']->id }})"
                    class="flex w-full items-center gap-3 rounded-lg border border-zinc-200 bg-white px-3 py-2 text-left transition hover:border-zinc-400 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-zinc-500"
                >
                    @include('livewire.watching.partials.poster', ['url' => $pick['title']->posterUrl(), 'tv' => $isTv])
                    <div class="min-w-0 flex-1">
                        <div class="truncate font-medium text-zinc-800 dark:text-zinc-100">{{ $pick['title']->title }}</div>
                        <div class="text-sm text-zinc-500">
                            {{ __('Last watched :when', ['when' => $pick['last_watched']->format('D j M')]) }}
                        </div>
                    </div>
                    <flux:icon.plus class="size-5 text-zinc-400" />
                </button>
            @endforeach
        </div>
    @endif
@endif

@if ($next)
    <flux:button variant="filled" icon:trailing="arrow-right" wire:click="goTo('{{ $next }}')">
        @if ($next === 'films')
            {{ $stepEntries->isEmpty() ? __('No TV, on to films') : __('On to films') }}
        @else
            {{ $stepEntries->isEmpty() ? __('No films, all done') : __('All done') }}
        @endif
    </flux:button>
@endif
