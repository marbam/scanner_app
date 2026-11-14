@php
    $tvCount = $this->entries->filter(fn ($e) => $e->title->isTv())->count();
    $filmCount = $this->entries->count() - $tvCount;
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

    @include('livewire.watching.partials.step-tabs', ['tabs' => [
        'tv' => [__('TV shows'), 'tv', $tvCount],
        'films' => [__('Films'), 'film', $filmCount],
        'done' => [__('Done'), 'check-circle', null],
    ]])

    @if ($step !== 'done')
        @include('livewire.watching.partials.step')
    @else
        <div class="flex flex-col gap-4">
            @include('livewire.watching.partials.summary')

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
