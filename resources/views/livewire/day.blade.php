@php
    $tvCount = $this->entries->filter(fn ($e) => $e->title->isTv())->count();
    $filmCount = $this->entries->count() - $tvCount;
    $habitsDone = $this->habitsCompletedCount();
    $habitsTotal = $this->habitActivities->count();
@endphp

<div class="mx-auto flex h-full w-full max-w-2xl flex-1 flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('Your day') }}</flux:heading>
        <flux:subheading>{{ __('Today') }} {{ \Illuminate\Support\Carbon::parse($date)->format('l j F Y') }}</flux:subheading>
    </div>

    @include('livewire.watching.partials.step-tabs', ['tabs' => [
        'habits' => [__('Habits'), 'calendar-days', $habitsTotal > 0 ? $habitsDone.'/'.$habitsTotal : null],
        'tv' => [__('TV'), 'tv', $tvCount],
        'films' => [__('Films'), 'film', $filmCount],
        'done' => [__('Done'), 'check-circle', null],
    ]])

    @if ($step === 'habits')
        <div>
            <flux:heading size="lg">{{ __('Which habits did you do?') }}</flux:heading>
            <flux:text class="text-sm">{{ __('Tap the ones you did. Tap again to untick.') }}</flux:text>
        </div>

        @include('livewire.habits.partials.list')

        <flux:button variant="filled" icon:trailing="arrow-right" wire:click="goTo('tv')">
            {{ __('On to TV') }}
        </flux:button>
    @elseif ($step !== 'done')
        @include('livewire.watching.partials.step')
    @else
        <div class="flex flex-col gap-6">
            <div class="flex flex-col gap-2">
                <div class="flex items-center justify-between gap-3">
                    <flux:heading>{{ __('Habits') }}</flux:heading>
                    @if ($habitsTotal > 0)
                        <flux:badge size="sm" :color="$habitsDone === $habitsTotal ? 'emerald' : 'zinc'">{{ $habitsDone }} / {{ $habitsTotal }}</flux:badge>
                    @endif
                </div>

                @php $doneHabits = $this->habitActivities->filter(fn ($a) => $this->habitEntries->get($a->id)?->completed); @endphp

                @if ($doneHabits->isEmpty())
                    <div class="rounded-lg border border-dashed border-zinc-300 p-4 text-center text-zinc-500 dark:border-zinc-700">
                        {{ __('No habits ticked off yet.') }}
                    </div>
                @else
                    <div class="flex flex-wrap gap-2">
                        @foreach ($doneHabits as $activity)
                            <span wire:key="summary-habit-{{ $activity->id }}" class="flex items-center gap-2 rounded-full border px-3 py-1 text-sm font-medium text-zinc-800 dark:text-zinc-100" style="border-color: {{ $activity->color }}; background-color: color-mix(in srgb, {{ $activity->color }} 12%, transparent)">
                                <span class="size-2.5 rounded-full" style="background-color: {{ $activity->color }}"></span>
                                {{ $activity->name }}
                            </span>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="flex flex-col gap-4">
                @include('livewire.watching.partials.summary', ['emptyText' => __('Nothing watched today.')])
            </div>

            <div class="flex flex-wrap gap-2">
                <flux:button :href="route('habits.tracker')" wire:navigate icon="squares-2x2">{{ __('Habit tracker') }}</flux:button>
                <flux:button :href="route('watching.history')" wire:navigate icon="film">{{ __('Watch history') }}</flux:button>
            </div>
        </div>
    @endif

    @if (in_array($step, ['tv', 'films'], true))
        @include('livewire.watching.partials.tmdb-credit')
    @endif
</div>
