<div class="flex h-full w-full flex-1 flex-col gap-8">
    <div>
        <flux:heading size="xl">{{ __('Habit activities') }}</flux:heading>
        <flux:subheading>{{ __('Add, edit, or archive the things you track.') }}</flux:subheading>
    </div>

    <form wire:submit="addActivity" class="flex flex-wrap items-end gap-3 rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
        <flux:field class="min-w-48 flex-1">
            <flux:label>{{ __('Name') }}</flux:label>
            <flux:input wire:model="name" placeholder="e.g. Meditation" />
            <flux:error name="name" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Color') }}</flux:label>
            <input type="color" wire:model="color" class="h-10 w-14 cursor-pointer rounded border border-zinc-300 dark:border-zinc-600" />
            <flux:error name="color" />
        </flux:field>

        <flux:field class="w-28">
            <flux:label>{{ __('Weight') }}</flux:label>
            <flux:input type="number" step="0.1" min="0.1" wire:model="weight" />
            <flux:error name="weight" />
        </flux:field>

        <flux:button type="submit" variant="primary">{{ __('Add activity') }}</flux:button>
    </form>

    <div class="flex flex-col gap-3">
        @forelse ($this->activities as $activity)
            <div
                wire:key="activity-{{ $activity->id }}"
                class="flex flex-wrap items-center gap-3 rounded-lg border border-zinc-200 bg-white p-3 dark:border-zinc-700 dark:bg-zinc-900 {{ $activity->archived_at ? 'opacity-50' : '' }}"
            >
                <form wire:submit="updateActivity({{ $activity->id }})" class="flex flex-1 flex-wrap items-center gap-3">
                    <input type="color" wire:model="edits.{{ $activity->id }}.color" class="h-9 w-12 cursor-pointer rounded border border-zinc-300 dark:border-zinc-600" />

                    <flux:input wire:model="edits.{{ $activity->id }}.name" class="max-w-56" />

                    <flux:input wire:model="edits.{{ $activity->id }}.weight" type="number" step="0.1" min="0.1" class="w-24" />

                    <flux:button type="submit" size="sm" variant="ghost">{{ __('Save') }}</flux:button>
                </form>

                <flux:badge size="sm" :color="$activity->archived_at ? 'zinc' : 'green'">
                    {{ $activity->archived_at ? __('Archived') : __('Active') }}
                </flux:badge>

                <flux:button size="sm" variant="ghost" wire:click="toggleArchived({{ $activity->id }})">
                    {{ $activity->archived_at ? __('Unarchive') : __('Archive') }}
                </flux:button>
            </div>
        @empty
            <div class="rounded-lg border border-dashed border-zinc-300 p-6 text-center text-zinc-500 dark:border-zinc-700">
                {{ __('No activities yet — add your first one above.') }}
            </div>
        @endforelse
    </div>
</div>
