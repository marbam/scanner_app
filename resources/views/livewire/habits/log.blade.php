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

    @include('livewire.habits.partials.list')
</div>
