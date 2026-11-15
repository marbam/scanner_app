@php
    $range = $this->range;
    $fmt = fn (float|int $minutes) => \App\Livewire\Games\Played::formatMinutes((int) round($minutes));
    $dateFmt = fn (string $date) => \Illuminate\Support\Carbon::parse($date)->format('j M Y');
@endphp

<div class="mx-auto flex h-full w-full max-w-3xl flex-1 flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('Year in review') }}</flux:heading>
        <flux:subheading>{{ $dateFmt($range['start']) }} – {{ $dateFmt($range['end']) }} · {{ trans_choice('{1} 1 day|[2,*] :count days', $range['days'], ['count' => $range['days']]) }}</flux:subheading>
    </div>

    <div class="grid grid-cols-2 gap-2 sm:max-w-sm">
        @foreach (['ytd' => __('Year to date'), '365' => __('Last 365 days')] as $key => $label)
            <button
                type="button"
                wire:click="setPeriod('{{ $key }}')"
                wire:key="period-{{ $key }}"
                @class([
                    'rounded-lg border px-3 py-2 text-sm font-medium transition',
                    'border-zinc-800 bg-zinc-800 text-white dark:border-white dark:bg-white dark:text-zinc-900' => $period === $key,
                    'border-zinc-200 bg-white text-zinc-700 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200' => $period !== $key,
                ])
            >{{ $label }}</button>
        @endforeach
    </div>

    {{-- One "data for N of M days" line per section. --}}
    @php
        $coverageLine = function (array $coverage) use ($range, $dateFmt): string {
            if ($coverage['days'] === 0) {
                return __('No data in this period yet.');
            }

            $line = __('Data for :days of :total days', ['days' => number_format($coverage['days']), 'total' => number_format($range['days'])]);

            return $coverage['from'] !== $range['start'] ? $line.' '.__('(since :date)', ['date' => $dateFmt($coverage['from'])]) : $line;
        };
        $stat = 'rounded-lg border border-zinc-200 bg-white px-4 py-3 dark:border-zinc-700 dark:bg-zinc-900';
        $statValue = 'text-2xl font-semibold text-zinc-900 dark:text-white';
        $section = 'flex flex-col gap-4 rounded-xl border border-neutral-200 p-4 dark:border-neutral-700';
        $summary = 'cursor-pointer select-none text-sm font-medium text-zinc-700 dark:text-zinc-200';
        $row = 'flex items-center justify-between gap-3 border-b border-zinc-100 py-2 text-sm last:border-0 dark:border-zinc-800';
    @endphp

    {{-- TV --}}
    @php $watching = $this->watching; @endphp
    <section class="{{ $section }}">
        <div>
            <flux:heading size="lg" class="flex items-center gap-2"><flux:icon name="tv" variant="mini" /> {{ __('TV') }}</flux:heading>
            <flux:text class="text-sm">{{ $coverageLine($watching['coverage']) }}</flux:text>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div class="{{ $stat }}"><div class="{{ $statValue }}">{{ number_format($watching['shows']->count()) }}</div><div class="text-sm text-zinc-500">{{ __('Shows watched') }}</div></div>
            <div class="{{ $stat }}"><div class="{{ $statValue }}">{{ number_format($watching['tv_days']) }}</div><div class="text-sm text-zinc-500">{{ __('Days with TV') }}</div></div>
        </div>

        @if ($watching['shows']->isNotEmpty())
            <details>
                <summary class="{{ $summary }}">{{ __('Show all :count shows', ['count' => $watching['shows']->count()]) }}</summary>
                <div class="mt-2">
                    @foreach ($watching['shows'] as $show)
                        <div wire:key="review-show-{{ $show->id }}" class="{{ $row }}">
                            <span class="truncate text-zinc-800 dark:text-zinc-100">{{ $show->title }}</span>
                            <span class="shrink-0 text-zinc-500">{{ trans_choice('{1} 1 day|[2,*] :count days', $show->entries_count, ['count' => $show->entries_count]) }}</span>
                        </div>
                    @endforeach
                </div>
            </details>
        @endif
    </section>

    {{-- Films --}}
    <section class="{{ $section }}">
        <div>
            <flux:heading size="lg" class="flex items-center gap-2"><flux:icon name="film" variant="mini" /> {{ __('Films') }}</flux:heading>
            <flux:text class="text-sm">{{ $coverageLine($watching['coverage']) }}</flux:text>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div class="{{ $stat }}"><div class="{{ $statValue }}">{{ number_format($watching['films']->count()) }}</div><div class="text-sm text-zinc-500">{{ __('Films watched') }}</div></div>
            <div class="{{ $stat }}"><div class="{{ $statValue }}">{{ number_format($watching['cinema']) }}</div><div class="text-sm text-zinc-500">{{ __('Cinema trips') }}</div></div>
        </div>

        @if ($watching['films']->isNotEmpty())
            <details>
                <summary class="{{ $summary }}">{{ __('Show all :count films', ['count' => $watching['films']->count()]) }}</summary>
                <div class="mt-2">
                    @foreach ($watching['films'] as $film)
                        <div wire:key="review-film-{{ $film->id }}" class="{{ $row }}">
                            <span class="truncate text-zinc-800 dark:text-zinc-100">{{ $film->title }}@if ($film->year) <span class="text-zinc-500">({{ $film->year }})</span>@endif</span>
                            @if ($film->entries_count > 1)
                                <span class="shrink-0 text-zinc-500">{{ trans_choice('[2,*] :count viewings', $film->entries_count, ['count' => $film->entries_count]) }}</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </details>
        @endif
    </section>

    {{-- Games --}}
    @php $games = $this->games; @endphp
    <section class="{{ $section }}">
        <div>
            <flux:heading size="lg" class="flex items-center gap-2"><flux:icon name="puzzle-piece" variant="mini" /> {{ __('Games') }}</flux:heading>
            <flux:text class="text-sm">{{ $coverageLine($games['coverage']) }}</flux:text>
        </div>

        <div class="grid grid-cols-3 gap-3">
            <div class="{{ $stat }}"><div class="{{ $statValue }}">{{ $fmt($games['minutes']) }}</div><div class="text-sm text-zinc-500">{{ __('Total played') }}</div></div>
            <div class="{{ $stat }}"><div class="{{ $statValue }}">{{ $fmt($games['per_day']) }}</div><div class="text-sm text-zinc-500">{{ __('Average a day') }}</div></div>
            <div class="{{ $stat }}"><div class="{{ $statValue }}">{{ number_format(count($games['games'])) }}</div><div class="text-sm text-zinc-500">{{ __('Games played') }}</div></div>
        </div>

        @if ($games['games'] !== [])
            <details>
                <summary class="{{ $summary }}">{{ __('Show all :count games', ['count' => count($games['games'])]) }}</summary>
                <div class="mt-2">
                    <div class="flex justify-end gap-3 pb-1 text-xs uppercase tracking-wide text-zinc-400">
                        <span class="w-20 text-right">{{ __('Total') }}</span>
                        <span class="w-24 text-right">{{ __('Avg / day played') }}</span>
                    </div>
                    @foreach ($games['games'] as $i => $game)
                        <div wire:key="review-game-{{ $i }}" class="{{ $row }}">
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-zinc-800 dark:text-zinc-100">{{ $game['name'] }}</span>
                                <span class="text-xs text-zinc-500">{{ trans_choice('{1} 1 day|[2,*] :count days', $game['days'], ['count' => $game['days']]) }}</span>
                            </span>
                            <span class="w-20 shrink-0 text-right font-medium text-zinc-800 dark:text-zinc-100">{{ $fmt($game['minutes']) }}</span>
                            <span class="w-24 shrink-0 text-right text-zinc-500">{{ $fmt($game['per_day']) }}</span>
                        </div>
                    @endforeach
                </div>
            </details>
        @endif

        <flux:text class="text-xs text-zinc-500">{{ __('Steam play is recorded once a day, so a game played across two days between syncs is counted on the later one.') }}</flux:text>
    </section>

    {{-- Books --}}
    @php $books = $this->books; @endphp
    <section class="{{ $section }}">
        <div>
            <flux:heading size="lg" class="flex items-center gap-2"><flux:icon name="book-open" variant="mini" /> {{ __('Books') }}</flux:heading>
            <flux:text class="text-sm">{{ $coverageLine($books['coverage']) }}</flux:text>
        </div>

        <div class="grid grid-cols-3 gap-3">
            <div class="{{ $stat }}"><div class="{{ $statValue }}">{{ number_format($books['books']->count()) }}</div><div class="text-sm text-zinc-500">{{ __('Books read') }}</div></div>
            <div class="{{ $stat }}"><div class="{{ $statValue }}">{{ number_format($books['finished']->count()) }}</div><div class="text-sm text-zinc-500">{{ __('Finished') }}</div></div>
            <div class="{{ $stat }}"><div class="{{ $statValue }}">{{ number_format($books['days']) }}</div><div class="text-sm text-zinc-500">{{ __('Days reading') }}</div></div>
        </div>

        @if ($books['books']->isNotEmpty())
            <div class="flex flex-col gap-1">
                <flux:heading size="sm">{{ __('Books read') }}</flux:heading>
                @foreach ($books['books'] as $book)
                    <div wire:key="review-book-{{ $book->id }}" class="{{ $row }}">
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-zinc-800 dark:text-zinc-100">{{ $book->title }}</span>
                            @if ($book->author)
                                <span class="block truncate text-xs text-zinc-500">{{ $book->author }}</span>
                            @endif
                        </span>
                        <span class="shrink-0 text-zinc-500">{{ trans_choice('{1} 1 day|[2,*] :count days', $book->entries_count, ['count' => $book->entries_count]) }}</span>
                    </div>
                @endforeach
            </div>
        @endif

        @if ($books['finished']->isNotEmpty())
            <div class="flex flex-col gap-1">
                <flux:heading size="sm">{{ __('Finished') }}</flux:heading>
                @foreach ($books['finished'] as $book)
                    <div wire:key="review-finished-{{ $book->id }}" class="{{ $row }}">
                        <span class="truncate text-zinc-800 dark:text-zinc-100">{{ $book->title }}</span>
                        <flux:badge size="sm" color="amber">{{ $book->finished_on?->format('j M Y') }}</flux:badge>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    {{-- Habits --}}
    @php $habits = $this->habits; @endphp
    <section class="{{ $section }}">
        <div>
            <flux:heading size="lg" class="flex items-center gap-2"><flux:icon name="calendar-days" variant="mini" /> {{ __('Habits') }}</flux:heading>
            <flux:text class="text-sm">
                {{ $coverageLine($habits['coverage']) }}@if ($habits['coverage']['days'] > 0) · {{ trans_choice('{1} 1 day logged|[2,*] :count days logged', $habits['logged_days'], ['count' => number_format($habits['logged_days'])]) }}@endif
            </flux:text>
        </div>

        @forelse ($habits['habits'] as ['activity' => $activity, 'days' => $days])
            @php $share = $habits['coverage']['days'] > 0 ? $days / $habits['coverage']['days'] : 0; @endphp
            <div wire:key="review-habit-{{ $activity->id }}" class="flex flex-col gap-1">
                <div class="flex items-center justify-between gap-3 text-sm">
                    <span class="flex min-w-0 items-center gap-2 font-medium text-zinc-800 dark:text-zinc-100">
                        <span class="size-2.5 shrink-0 rounded-full" style="background-color: {{ $activity->color }}"></span>
                        <span class="truncate">{{ $activity->name }}</span>
                        @if ($activity->archived_at)
                            <flux:badge size="sm" color="zinc">{{ __('Archived') }}</flux:badge>
                        @endif
                    </span>
                    <span class="shrink-0 text-zinc-500">{{ trans_choice('{0} 0 days|{1} 1 day|[2,*] :count days', $days, ['count' => number_format($days)]) }} · {{ round($share * 100) }}%</span>
                </div>
                <div class="h-2 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                    <div class="h-full rounded-full" style="width: {{ min(100, $share * 100) }}%; background-color: {{ $activity->color }}"></div>
                </div>
            </div>
        @empty
            <flux:text class="text-center text-zinc-500">{{ __('No habits set up yet.') }}</flux:text>
        @endforelse

        @if ($habits['habits'] !== [])
            <flux:text class="text-xs text-zinc-500">{{ __('Percentages are of the days with habit data.') }}</flux:text>
        @endif
    </section>
</div>
