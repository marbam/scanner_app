<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('What to play') }}</flux:heading>
        <flux:subheading>
            {{ __('Answer a few quick questions and get picks from your Steam library.') }}
        </flux:subheading>
    </div>

    @if ($this->libraryStats['total'] === 0)
        <div class="rounded-lg border border-dashed border-zinc-300 p-6 text-center text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
            {{ __('Your Steam library hasn\'t been imported yet. Run php artisan app:sync-steam-library.') }}
        </div>
    @else
        <div class="flex flex-col gap-5 rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
            @foreach (\App\Support\GameSuggester::questions() as $questionKey => $question)
                <div class="flex flex-col gap-2" wire:key="question-{{ $questionKey }}">
                    <div class="text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ __($question['label']) }}</div>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($question['options'] as $optionKey => $option)
                            @php($selected = ($answers[$questionKey] ?? null) === $optionKey)
                            <button
                                type="button"
                                wire:click="choose('{{ $questionKey }}', '{{ $optionKey }}')"
                                aria-pressed="{{ $selected ? 'true' : 'false' }}"
                                class="rounded-full border px-4 py-2 text-sm transition {{ $selected
                                    ? 'border-zinc-800 bg-zinc-800 text-white dark:border-white dark:bg-white dark:text-zinc-900'
                                    : 'border-zinc-300 bg-white text-zinc-700 hover:bg-zinc-100 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-200 dark:hover:bg-zinc-700' }}"
                            >
                                {{ __($option['label']) }}
                            </button>
                        @endforeach
                    </div>
                </div>
            @endforeach

            <div>
                <flux:button variant="primary" wire:click="suggest" icon="sparkles">
                    {{ $asked ? __('Shuffle again') : __('Suggest games') }}
                </flux:button>
            </div>
        </div>

        @if ($asked)
            <div class="flex flex-col gap-3">
                @forelse ($suggestions as $suggestion)
                    @php($game = $this->games[$suggestion['id']] ?? null)
                    @continue($game === null)

                    <div wire:key="suggestion-{{ $game->id }}" class="flex flex-col overflow-hidden rounded-lg border border-zinc-200 bg-white sm:flex-row dark:border-zinc-700 dark:bg-zinc-900">
                        <img
                            src="{{ $game->headerImageUrl() }}"
                            alt=""
                            loading="lazy"
                            onerror="this.remove()"
                            class="aspect-[460/215] w-full bg-zinc-100 object-cover sm:w-64 sm:shrink-0 dark:bg-zinc-800"
                        />

                        <div class="flex flex-1 flex-col gap-2 p-4">
                            <flux:heading size="lg">{{ $game->name }}</flux:heading>

                            <div class="text-sm text-zinc-500 dark:text-zinc-400">
                                @if ($game->playtime_forever === 0)
                                    {{ __('Never played') }}
                                @else
                                    {{ $game->playtime_forever < 60 ? $game->playtime_forever.' min' : round($game->playtime_forever / 60, 1).'h' }} {{ __('played') }}
                                    @if ($game->last_played_at)
                                        · {{ __('last played :when', ['when' => $game->last_played_at->diffForHumans()]) }}
                                    @endif
                                @endif
                            </div>

                            @if ($game->short_description)
                                <p class="line-clamp-3 text-sm text-zinc-700 dark:text-zinc-300">{{ $game->short_description }}</p>
                            @endif

                            @if ($suggestion['reasons'] !== [])
                                <div class="flex flex-wrap items-center gap-1">
                                    <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Matches:') }}</span>
                                    @foreach ($suggestion['reasons'] as $reason)
                                        <flux:badge size="sm" color="green">{{ $reason }}</flux:badge>
                                    @endforeach
                                </div>
                            @endif

                            <div class="mt-auto flex flex-wrap gap-2 pt-2">
                                <flux:button size="sm" variant="primary" icon="play" href="{{ $game->launchUrl() }}">{{ __('Play') }}</flux:button>
                                <flux:button size="sm" variant="ghost" wire:click="notTonight({{ $game->id }})">{{ __('Not tonight') }}</flux:button>
                                <flux:button size="sm" variant="ghost" wire:click="neverSuggest({{ $game->id }})">{{ __('Never suggest') }}</flux:button>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="rounded-lg border border-dashed border-zinc-300 p-6 text-center text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                        {{ __('Nothing left that fits. Try different answers or shuffle again.') }}
                    </div>
                @endforelse
            </div>
        @endif

        <div class="flex flex-col gap-2 text-sm text-zinc-500 dark:text-zinc-400">
            <div>
                {{ __(':total games in your library.', ['total' => $this->libraryStats['total']]) }}
                @if ($this->libraryStats['pending'] > 0)
                    {{ __(':pending are still waiting for their tags, so they won\'t score well yet.', ['pending' => $this->libraryStats['pending']]) }}
                @endif
            </div>

            @if ($this->hiddenGames->isNotEmpty())
                <details class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                    <summary class="cursor-pointer text-zinc-700 dark:text-zinc-300">
                        {{ __('Never suggested (:count)', ['count' => $this->hiddenGames->count()]) }}
                    </summary>
                    <ul class="mt-2 flex flex-col gap-1">
                        @foreach ($this->hiddenGames as $hidden)
                            <li wire:key="hidden-{{ $hidden->id }}" class="flex items-center justify-between gap-2">
                                <span class="text-zinc-700 dark:text-zinc-300">{{ $hidden->name }}</span>
                                <flux:button size="xs" variant="ghost" wire:click="restore({{ $hidden->id }})">{{ __('Restore') }}</flux:button>
                            </li>
                        @endforeach
                    </ul>
                </details>
            @endif
        </div>
    @endif
</div>
