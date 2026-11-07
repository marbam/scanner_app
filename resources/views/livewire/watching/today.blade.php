@php
    $isTv = $this->mediaType() === \App\Models\WatchTitle::TV;
    $tvEntries = $this->entries->filter(fn ($e) => $e->title->isTv());
    $filmEntries = $this->entries->reject(fn ($e) => $e->title->isTv());
    $stepEntries = $isTv ? $tvEntries : $filmEntries;
    $selected = $this->selectedTitle;
    $tmdb = $step === 'done' ? ['results' => [], 'error' => false] : $this->tmdbResults;
    $day = \Illuminate\Support\Carbon::parse($date);
@endphp

<div class="mx-auto flex h-full w-full max-w-2xl flex-1 flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('What did you watch?') }}</flux:heading>
        <flux:subheading>
            {{ $date === \App\Livewire\Watching\Today::today() ? __('Today') : '' }}
            {{ $day->format('l j F Y') }}
        </flux:subheading>
    </div>

    @if ($this->isBackdating())
        <div class="flex items-center gap-3">
            <flux:button icon="chevron-left" wire:click="previousDay" size="sm" />
            <flux:input type="date" wire:model.live="date" :max="\App\Livewire\Watching\Today::today()" class="max-w-xs" />
            <flux:button icon="chevron-right" wire:click="nextDay" size="sm" :disabled="$date >= \App\Livewire\Watching\Today::today()" />
        </div>
    @endif

    {{-- Step tabs --}}
    <div class="grid grid-cols-3 gap-2">
        @foreach (['tv' => [__('TV shows'), 'tv', $tvEntries->count()], 'films' => [__('Films'), 'film', $filmEntries->count()], 'done' => [__('Done'), 'check-circle', null]] as $key => [$label, $icon, $count])
            <button
                type="button"
                wire:click="goTo('{{ $key }}')"
                wire:key="step-{{ $key }}"
                @class([
                    'flex items-center justify-center gap-2 rounded-lg border px-3 py-2 text-sm font-medium transition',
                    'border-zinc-800 bg-zinc-800 text-white dark:border-white dark:bg-white dark:text-zinc-900' => $step === $key,
                    'border-zinc-200 bg-white text-zinc-700 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200' => $step !== $key,
                ])
            >
                <flux:icon :name="$icon" variant="mini" />
                {{ $label }}
                @if ($count)
                    <span class="rounded-full bg-zinc-200 px-1.5 text-xs text-zinc-800 dark:bg-zinc-700 dark:text-zinc-100">{{ $count }}</span>
                @endif
            </button>
        @endforeach
    </div>

    @if ($step !== 'done')
        <div>
            <flux:heading size="lg">{{ $isTv ? __('Any TV shows?') : __('Any films?') }}</flux:heading>
        </div>

        {{-- Already logged for this step --}}
        @if ($stepEntries->isNotEmpty())
            <div class="flex flex-col gap-2">
                @foreach ($stepEntries as $entry)
                    <div wire:key="entry-{{ $entry->id }}" class="flex items-center justify-between gap-3 rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-2 dark:border-emerald-800 dark:bg-emerald-950/40">
                        <div class="flex min-w-0 items-center gap-3">
                            <flux:icon.check-circle class="size-5 shrink-0 text-emerald-600 dark:text-emerald-400" />
                            <span class="truncate font-medium text-zinc-800 dark:text-zinc-100">{{ $entry->title->title }}</span>
                            @if ($entry->episodeLabel())
                                <flux:badge size="sm" color="zinc">{{ $entry->episodeLabel() }}</flux:badge>
                            @endif
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
            {{-- Confirm the selected title --}}
            <form wire:submit="add" class="flex flex-col gap-4 rounded-lg border border-zinc-300 bg-white p-4 dark:border-zinc-600 dark:bg-zinc-900">
                <div class="flex items-center gap-3">
                    @include('livewire.watching.partials.poster', ['url' => $selected->posterUrl(), 'tv' => $selected->isTv()])
                    <div>
                        <div class="font-semibold text-zinc-900 dark:text-white">{{ $selected->title }}</div>
                        @if ($selected->year)
                            <div class="text-sm text-zinc-500">{{ $selected->year }}</div>
                        @endif
                    </div>
                </div>

                @if ($selected->isTv())
                    <div class="grid grid-cols-2 gap-3">
                        <flux:input type="number" min="0" inputmode="numeric" wire:model.live.debounce.300ms="season" :label="__('Season')" />
                        <flux:input type="number" min="0" inputmode="numeric" wire:model="episode" :label="__('Episode')" />
                    </div>
                    @if (is_numeric($season) && $selected->episodeCount((int) $season))
                        <flux:text class="-mt-2 text-sm">
                            {{ __('Season :season has :count episodes.', ['season' => (int) $season, 'count' => $selected->episodeCount((int) $season)]) }}
                        </flux:text>
                    @endif
                @else
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
                @endif

                <div class="flex gap-2">
                    <flux:button type="submit" variant="primary" class="flex-1">{{ __('Add') }}</flux:button>
                    <flux:button type="button" variant="ghost" wire:click="cancelSelection">{{ __('Cancel') }}</flux:button>
                </div>
            </form>
        @else
            {{-- One-tap picks from recent history --}}
            @if ($this->quickPicks !== [])
                <div class="flex flex-col gap-2">
                    <flux:text class="text-xs font-medium uppercase tracking-wide">
                        {{ $isTv ? __('Carry on watching') : __('Watched recently') }}
                    </flux:text>

                    @foreach ($this->quickPicks as $pick)
                        <button
                            type="button"
                            wire:key="pick-{{ $pick['title']->id }}"
                            wire:click="chooseTitle({{ $pick['title']->id }}, {{ $pick['season'] ?? 'null' }}, {{ $pick['episode'] ?? 'null' }})"
                            class="flex w-full items-center gap-3 rounded-lg border border-zinc-200 bg-white px-3 py-2 text-left transition hover:border-zinc-400 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-zinc-500"
                        >
                            @include('livewire.watching.partials.poster', ['url' => $pick['title']->posterUrl(), 'tv' => $isTv])
                            <div class="min-w-0 flex-1">
                                <div class="truncate font-medium text-zinc-800 dark:text-zinc-100">{{ $pick['title']->title }}</div>
                                <div class="text-sm text-zinc-500">
                                    @if ($isTv)
                                        {{ __('Next: S:season E:episode', ['season' => $pick['season'], 'episode' => $pick['episode']]) }} ·
                                    @endif
                                    {{ __('last watched :when', ['when' => $pick['last_watched']->isSameDay($day) ? __('today') : $pick['last_watched']->format('D j M')]) }}
                                </div>
                            </div>
                            <flux:icon.plus class="size-5 text-zinc-400" />
                        </button>
                    @endforeach
                </div>
            @endif

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
        @endif

        <flux:button
            variant="filled"
            icon:trailing="arrow-right"
            wire:click="goTo('{{ $isTv ? 'films' : 'done' }}')"
        >
            @if ($stepEntries->isEmpty())
                {{ $isTv ? __('No TV, on to films') : __('No films, all done') }}
            @else
                {{ $isTv ? __('On to films') : __('All done') }}
            @endif
        </flux:button>
    @else
        {{-- Summary --}}
        <div class="flex flex-col gap-4">
            @if ($this->entries->isEmpty())
                <div class="rounded-lg border border-dashed border-zinc-300 p-6 text-center text-zinc-500 dark:border-zinc-700">
                    {{ __('Nothing logged for this day.') }}
                </div>
            @else
                @foreach ([__('TV shows') => $tvEntries, __('Films') => $filmEntries] as $heading => $group)
                    @if ($group->isNotEmpty())
                        <div class="flex flex-col gap-2">
                            <flux:heading>{{ $heading }}</flux:heading>
                            @foreach ($group as $entry)
                                <div wire:key="summary-{{ $entry->id }}" class="flex items-center gap-3 rounded-lg border border-zinc-200 bg-white px-3 py-2 dark:border-zinc-700 dark:bg-zinc-900">
                                    @include('livewire.watching.partials.poster', ['url' => $entry->title->posterUrl(), 'tv' => $entry->title->isTv()])
                                    <span class="min-w-0 flex-1 truncate font-medium text-zinc-800 dark:text-zinc-100">{{ $entry->title->title }}</span>
                                    @if ($entry->episodeLabel())
                                        <flux:badge size="sm" color="zinc">{{ $entry->episodeLabel() }}</flux:badge>
                                    @endif
                                    @if ($entry->location === \App\Models\WatchEntry::CINEMA)
                                        <flux:badge size="sm" color="amber">{{ __('Cinema') }}</flux:badge>
                                    @elseif ($entry->location === \App\Models\WatchEntry::HOME)
                                        <flux:badge size="sm" color="zinc">{{ __('Home') }}</flux:badge>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                @endforeach
            @endif

            <div class="flex flex-wrap gap-2">
                <flux:button :href="route('watching.history')" wire:navigate icon="magnifying-glass">{{ __('History') }}</flux:button>
                @unless ($this->isBackdating())
                    <flux:button :href="route('watching.backdate')" wire:navigate variant="ghost" icon="clock">{{ __('Log an earlier day') }}</flux:button>
                @else
                    <flux:button wire:click="previousDay" variant="ghost" icon="chevron-left">{{ __('Day before') }}</flux:button>
                @endunless
            </div>
        </div>
    @endif

    @include('livewire.watching.partials.tmdb-credit')
</div>
