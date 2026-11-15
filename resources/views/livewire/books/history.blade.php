<div class="mx-auto flex h-full w-full max-w-3xl flex-1 flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('Reading history') }}</flux:heading>
        <flux:subheading>{{ __('Every book you\'ve logged, most recently read first.') }}</flux:subheading>
    </div>

    <div class="flex flex-wrap gap-2">
        <flux:button :href="route('day', ['step' => 'books'])" wire:navigate size="sm" icon="book-open">{{ __('Log today\'s reading') }}</flux:button>
        <flux:button :href="route('day.backdate', ['step' => 'books'])" wire:navigate size="sm" variant="ghost" icon="clock">{{ __('Log an earlier day') }}</flux:button>
    </div>

    <div class="grid grid-cols-3 gap-3">
        @foreach ([
            __('Books') => $this->stats['books'],
            __('Finished') => $this->stats['finished'],
            __('Days reading') => $this->stats['days'],
        ] as $label => $value)
            <div class="rounded-lg border border-zinc-200 bg-white px-4 py-3 dark:border-zinc-700 dark:bg-zinc-900">
                <div class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ number_format($value) }}</div>
                <div class="text-sm text-zinc-500">{{ $label }}{{ $year !== '' ? ' · '.$year : '' }}</div>
            </div>
        @endforeach
    </div>

    <div class="flex flex-col gap-3 sm:flex-row">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search by title or author…')" clearable class="flex-1" />

        <flux:select wire:model.live="status" class="sm:max-w-36">
            <flux:select.option value="all">{{ __('All books') }}</flux:select.option>
            <flux:select.option value="reading">{{ __('Reading') }}</flux:select.option>
            <flux:select.option value="finished">{{ __('Finished') }}</flux:select.option>
        </flux:select>

        <flux:select wire:model.live="year" class="sm:max-w-32">
            <flux:select.option value="">{{ __('All time') }}</flux:select.option>
            @foreach ($this->years as $y)
                <flux:select.option value="{{ $y }}">{{ $y }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <div class="flex flex-col gap-3">
        @forelse ($this->books as $book)
            @php
                $first = \Illuminate\Support\Carbon::parse($book->getAttribute('entries_min_read_on'));
                $last = \Illuminate\Support\Carbon::parse($book->getAttribute('entries_max_read_on'));
                $finished = $book->isFinished($book->getAttribute('last_read_on'));
            @endphp

            <div wire:key="book-{{ $book->id }}" class="flex gap-3 rounded-lg border border-zinc-200 bg-white p-3 dark:border-zinc-700 dark:bg-zinc-900">
                @include('livewire.books.partials.cover', ['url' => $book->coverUrl()])

                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-semibold text-zinc-900 dark:text-white">{{ $book->title }}</span>
                        @if ($book->year)
                            <span class="text-sm text-zinc-500">({{ $book->year }})</span>
                        @endif
                        @if ($finished)
                            <flux:badge size="sm" color="amber">{{ __('Finished :date', ['date' => $book->finished_on?->format('j M Y')]) }}</flux:badge>
                        @else
                            <flux:badge size="sm" color="emerald">{{ __('Reading') }}</flux:badge>
                        @endif
                    </div>

                    @if ($book->author)
                        <div class="truncate text-sm text-zinc-600 dark:text-zinc-300">{{ $book->author }}</div>
                    @endif

                    <div class="mt-1 text-sm text-zinc-500">
                        {{ trans_choice('{1} Read on 1 day|[2,*] Read on :count days', $book->entries_count, ['count' => $book->entries_count]) }}
                        ·
                        @if ($first->isSameDay($last))
                            {{ $first->format('D j M Y') }}
                        @else
                            {{ $first->format($first->year === $last->year ? 'j M' : 'j M Y') }} – {{ $last->format('j M Y') }}
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="rounded-lg border border-dashed border-zinc-300 p-6 text-center text-zinc-500 dark:border-zinc-700">
                {{ trim($search) !== '' || $year !== '' || $status !== 'all' ? __('Nothing matches.') : __('No books logged yet.') }}
                <flux:link :href="route('day', ['step' => 'books'])" wire:navigate>{{ __('Log today.') }}</flux:link>
            </div>
        @endforelse
    </div>

    {{ $this->books->links() }}

    <flux:text class="text-xs text-zinc-500">
        {{ __('Book details and covers from') }} <flux:link href="https://openlibrary.org" target="_blank" rel="noopener">Open Library</flux:link>.
    </flux:text>
</div>
