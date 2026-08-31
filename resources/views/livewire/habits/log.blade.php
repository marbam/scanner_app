<div class="flex h-full w-full flex-1 flex-col gap-8">
    <div>
        <flux:heading size="xl">{{ __('Log habits') }}</flux:heading>
        <flux:subheading>{{ __('Mark off what you did today.') }}</flux:subheading>
    </div>

    <div class="flex items-center gap-3">
        <flux:button icon="chevron-left" wire:click="previousDay" size="sm" />

        <flux:input type="date" wire:model.live="date" class="max-w-xs" />

        <flux:button icon="chevron-right" wire:click="nextDay" size="sm" />

        @unless ($date === now()->toDateString())
            <flux:button variant="ghost" size="sm" wire:click="$set('date', '{{ now()->toDateString() }}')">
                {{ __('Today') }}
            </flux:button>
        @endunless
    </div>

    <div class="flex flex-col gap-3">
        @forelse ($this->activities as $activity)
            @php $entry = $this->entries->get($activity->id); @endphp

            <button
                type="button"
                wire:click="toggle({{ $activity->id }})"
                wire:key="activity-{{ $activity->id }}"
                class="flex w-full items-center justify-between rounded-lg border border-zinc-200 bg-white px-4 py-3 text-left transition dark:border-zinc-700 dark:bg-zinc-900"
                @style([
                    'border-color: '.$activity->color => $entry?->completed,
                    'background-color: color-mix(in srgb, '.$activity->color.' 12%, transparent)' => $entry?->completed,
                ])
            >
                <div class="flex items-center gap-3">
                    <span class="size-3 shrink-0 rounded-full" style="background-color: {{ $activity->color }}"></span>
                    <span class="font-medium text-zinc-800 dark:text-zinc-100">{{ $activity->name }}</span>
                    <flux:badge size="sm" color="zinc">{{ __('Weight') }} {{ rtrim(rtrim((string) $activity->weight, '0'), '.') }}</flux:badge>
                </div>

                <flux:icon.check-circle
                    class="size-6 {{ $entry?->completed ? 'text-[var(--activity-color)]' : 'text-zinc-300 dark:text-zinc-600' }}"
                    @style(['--activity-color: '.$activity->color => $entry?->completed])
                />
            </button>
        @empty
            <div class="rounded-lg border border-dashed border-zinc-300 p-6 text-center text-zinc-500 dark:border-zinc-700">
                {{ __('No activities yet.') }}
                <flux:link :href="route('habits.activities')" wire:navigate>{{ __('Add one.') }}</flux:link>
            </div>
        @endforelse
    </div>
</div>
