{{-- What was watched on the component's date, TV then films. --}}
@php
    $tvEntries = $this->entries->filter(fn ($e) => $e->title->isTv());
    $filmEntries = $this->entries->reject(fn ($e) => $e->title->isTv());
@endphp

@if ($this->entries->isEmpty())
    <div class="rounded-lg border border-dashed border-zinc-300 p-6 text-center text-zinc-500 dark:border-zinc-700">
        {{ $emptyText ?? __('Nothing logged for this day.') }}
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
