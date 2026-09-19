<div class="flex h-full w-full flex-1 flex-col gap-8">
    <div>
        <flux:heading size="xl">{{ __('Reminders') }}</flux:heading>
        <flux:subheading>{{ __('Get a push notification at a set time on the days you choose.') }}</flux:subheading>
    </div>

    <form wire:submit="addReminder" class="flex flex-col gap-3 rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex flex-wrap items-end gap-3">
            <flux:field class="min-w-48 flex-1">
                <flux:label>{{ __('Name') }}</flux:label>
                <flux:input wire:model="name" placeholder="e.g. Drink water" />
                <flux:error name="name" />
            </flux:field>

            <flux:field class="min-w-48 flex-1">
                <flux:label>{{ __('Description') }}</flux:label>
                <flux:input wire:model="description" placeholder="{{ __('Optional') }}" />
                <flux:error name="description" />
            </flux:field>

            <flux:field class="w-32">
                <flux:label>{{ __('Time') }}</flux:label>
                <flux:input type="time" wire:model="time" />
                <flux:error name="time" />
            </flux:field>
        </div>

        <flux:field>
            <flux:label>{{ __('Days') }}</flux:label>
            <div class="flex flex-wrap gap-3">
                @foreach ($this->dayOptions() as $value => $label)
                    <label class="flex items-center gap-2 text-sm text-zinc-600 dark:text-zinc-300">
                        <input type="checkbox" value="{{ $value }}" wire:model="days" class="h-5 w-5 rounded border-zinc-300 dark:border-zinc-600" />
                        {{ __($label) }}
                    </label>
                @endforeach
            </div>
            <flux:error name="days" />
        </flux:field>

        <div>
            <flux:button type="submit" variant="primary">{{ __('Add reminder') }}</flux:button>
        </div>
    </form>

    <div class="flex flex-col gap-3">
        <flux:heading size="lg">{{ __('Existing Reminders') }}</flux:heading>

        @forelse ($this->reminders as $reminder)
            <div
                wire:key="reminder-{{ $reminder->id }}"
                class="flex flex-col gap-3 rounded-lg border p-3 {{ $reminder->is_active ? 'border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900' : 'border-zinc-200 bg-zinc-50 opacity-75 dark:border-zinc-700 dark:bg-zinc-800/60' }}"
            >
                <form wire:submit="updateReminder({{ $reminder->id }})" class="flex flex-col gap-3">
                    <div class="flex flex-wrap items-end gap-3">
                        <flux:input wire:model="edits.{{ $reminder->id }}.name" class="min-w-48 flex-1" />
                        <flux:input wire:model="edits.{{ $reminder->id }}.description" class="min-w-48 flex-1" placeholder="{{ __('Optional') }}" />
                        <flux:input type="time" wire:model="edits.{{ $reminder->id }}.time" class="w-32" />
                    </div>

                    <div class="flex flex-wrap gap-3">
                        @foreach ($this->dayOptions() as $value => $label)
                            <label class="flex items-center gap-2 text-sm text-zinc-600 dark:text-zinc-300">
                                <input type="checkbox" value="{{ $value }}" wire:model="edits.{{ $reminder->id }}.days" class="h-5 w-5 rounded border-zinc-300 dark:border-zinc-600" />
                                {{ __($label) }}
                            </label>
                        @endforeach
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <flux:button type="submit" size="sm" variant="ghost">{{ __('Save') }}</flux:button>

                        <flux:badge size="sm" :color="$reminder->is_active ? 'green' : 'zinc'">
                            {{ $reminder->is_active ? __('Active') : __('Inactive') }}
                        </flux:badge>

                        <flux:button size="sm" variant="ghost" wire:click="toggleActive({{ $reminder->id }})">
                            {{ $reminder->is_active ? __('Deactivate') : __('Activate') }}
                        </flux:button>

                        <flux:button size="sm" variant="ghost" wire:click="deleteReminder({{ $reminder->id }})" wire:confirm="{{ __('Delete this reminder?') }}">
                            {{ __('Delete') }}
                        </flux:button>
                    </div>
                </form>
            </div>
        @empty
            <div class="rounded-lg border border-dashed border-zinc-300 p-6 text-center text-zinc-500 dark:border-zinc-700">
                {{ __('No reminders yet — add your first one above.') }}
            </div>
        @endforelse
    </div>
</div>
