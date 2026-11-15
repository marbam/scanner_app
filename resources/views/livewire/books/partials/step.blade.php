{{-- The books step of Your Day. Needs the LogsBooks trait. --}}
@php
    $openLibrary = $this->openLibraryResults;
    $typed = trim(preg_replace('/\s+/', ' ', $search) ?? '');
@endphp

<div>
    <flux:heading size="lg">{{ __('Any reading?') }}</flux:heading>
    <flux:text class="text-sm">{{ __('Tap the books you read. Tap again to untick. Tap Finished when you\'ve finished one.') }}</flux:text>
</div>

{{-- Search --}}
<div class="flex flex-col gap-3">
    <flux:input
        wire:model.live.debounce.400ms="search"
        icon="magnifying-glass"
        :placeholder="__('Search for a book or author…')"
        clearable
    />

    @if ($this->bookHistoryResults->isNotEmpty())
        <flux:text class="text-xs font-medium uppercase tracking-wide">{{ __('From your books') }}</flux:text>
        @foreach ($this->bookHistoryResults as $result)
            <button
                type="button"
                wire:key="book-history-{{ $result->id }}"
                wire:click="chooseBook({{ $result->id }})"
                class="flex w-full items-center gap-3 rounded-lg border border-zinc-200 bg-white px-3 py-2 text-left transition hover:border-zinc-400 dark:border-zinc-700 dark:bg-zinc-900"
            >
                @include('livewire.books.partials.cover', ['url' => $result->coverUrl()])
                <div class="min-w-0 flex-1">
                    <div class="truncate font-medium text-zinc-800 dark:text-zinc-100">{{ $result->title }}</div>
                    <div class="truncate text-sm text-zinc-500">
                        @if ($result->author) {{ $result->author }} · @endif
                        {{ __('last read :date', ['date' => \Illuminate\Support\Carbon::parse($result->getAttribute('entries_max_read_on'))->format('D j M Y')]) }}
                    </div>
                </div>
                <flux:icon.plus class="size-5 text-zinc-400" />
            </button>
        @endforeach
    @endif

    @if ($openLibrary['results'] !== [])
        <flux:text class="text-xs font-medium uppercase tracking-wide">{{ __('From Open Library') }}</flux:text>
        @foreach ($openLibrary['results'] as $result)
            <button
                type="button"
                wire:key="openlibrary-{{ $result['key'] }}"
                wire:click="chooseOpenLibraryBook(@js($result['key']))"
                class="flex w-full items-center gap-3 rounded-lg border border-zinc-200 bg-white px-3 py-2 text-left transition hover:border-zinc-400 dark:border-zinc-700 dark:bg-zinc-900"
            >
                @include('livewire.books.partials.cover', ['url' => \App\Support\OpenLibrary::coverUrl($result['cover_id'])])
                <div class="min-w-0 flex-1">
                    <div class="truncate font-medium text-zinc-800 dark:text-zinc-100">
                        {{ $result['title'] }}
                        @if ($result['year']) <span class="font-normal text-zinc-500">({{ $result['year'] }})</span> @endif
                    </div>
                    @if ($result['author'])
                        <div class="truncate text-sm text-zinc-500">{{ $result['author'] }}</div>
                    @endif
                </div>
                <flux:icon.plus class="size-5 text-zinc-400" />
            </button>
        @endforeach
    @elseif ($openLibrary['error'])
        <flux:text class="text-sm text-red-600">{{ __('Open Library search isn\'t responding right now. You can still add the title as typed.') }}</flux:text>
    @endif

    @if (mb_strlen($typed) >= 2)
        <button
            type="button"
            wire:click="addTypedBook"
            class="flex w-full items-center gap-3 rounded-lg border border-dashed border-zinc-300 px-3 py-2 text-left text-zinc-600 transition hover:border-zinc-500 dark:border-zinc-600 dark:text-zinc-300"
        >
            <flux:icon.pencil-square class="size-5 shrink-0 text-zinc-400" />
            <span class="min-w-0 flex-1 truncate">{{ __('Add “:title” as typed', ['title' => $typed]) }}</span>
        </button>
    @endif
</div>

{{-- Books from the last month, ticked if read on this date --}}
@if ($this->recentBooks !== [])
    <div class="flex flex-col gap-2">
        <flux:text class="text-xs font-medium uppercase tracking-wide">{{ __('Recently read') }}</flux:text>

        @foreach ($this->recentBooks as $item)
            <div wire:key="book-{{ $item['book']->id }}" class="flex items-stretch gap-2">
                <button
                    type="button"
                    wire:click="toggleBook({{ $item['book']->id }})"
                    aria-pressed="{{ $item['logged'] ? 'true' : 'false' }}"
                    @class([
                        'flex min-w-0 flex-1 items-center gap-3 rounded-lg border px-3 py-2 text-left transition',
                        'border-emerald-400 bg-emerald-50 dark:border-emerald-700 dark:bg-emerald-950/40' => $item['logged'],
                        'border-zinc-200 bg-white hover:border-zinc-400 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-zinc-500' => ! $item['logged'],
                    ])
                >
                    @include('livewire.books.partials.cover', ['url' => $item['book']->coverUrl()])
                    <div class="min-w-0 flex-1">
                        <div class="truncate font-medium text-zinc-800 dark:text-zinc-100">{{ $item['book']->title }}</div>
                        <div class="truncate text-sm text-zinc-500">
                            @if ($item['book']->author) {{ $item['book']->author }} · @endif
                            {{ $item['last_read'] ? __('Last read :when', ['when' => $item['last_read']->format('D j M')]) : __('First time') }}
                        </div>
                    </div>
                    @if ($item['logged'])
                        <flux:icon.check-circle variant="solid" class="size-7 shrink-0 text-emerald-600 dark:text-emerald-400" />
                    @else
                        <span class="size-6 shrink-0 rounded-full border-2 border-zinc-300 dark:border-zinc-600"></span>
                    @endif
                </button>

                <button
                    type="button"
                    wire:click="toggleFinished({{ $item['book']->id }})"
                    aria-pressed="{{ $item['finished'] ? 'true' : 'false' }}"
                    title="{{ $item['finished'] ? __('Finished today. Tap to undo.') : __('Mark as finished today') }}"
                    @class([
                        'flex w-18 shrink-0 flex-col items-center justify-center gap-1 rounded-lg border px-2 text-xs font-medium transition',
                        'border-amber-400 bg-amber-500 text-zinc-950' => $item['finished'],
                        'border-zinc-200 bg-white text-zinc-500 hover:border-zinc-400 hover:text-zinc-700 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-400 dark:hover:border-zinc-500 dark:hover:text-zinc-200' => ! $item['finished'],
                    ])
                >
                    <flux:icon :name="$item['finished'] ? 'check-badge' : 'flag'" variant="mini" />
                    {{ __('Finished') }}
                </button>
            </div>
        @endforeach
    </div>
@endif

<flux:button variant="filled" icon:trailing="arrow-right" wire:click="goTo('done')">
    {{ $this->bookEntries->isEmpty() ? __('No reading, all done') : __('All done') }}
</flux:button>

<flux:text class="text-xs text-zinc-500">
    <flux:link :href="route('books.history')" wire:navigate>{{ __('Reading history') }}</flux:link> ·
    {{ __('Book search by') }} <flux:link href="https://openlibrary.org" target="_blank" rel="noopener">Open Library</flux:link>.
</flux:text>
