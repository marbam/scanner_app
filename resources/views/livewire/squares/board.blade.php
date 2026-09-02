<div
    class="flex h-full w-full flex-1 flex-col gap-6"
    wire:poll.10s="step"
    x-data="{ remaining: 10 }"
    x-init="setInterval(() => { remaining = remaining > 1 ? remaining - 1 : 10 }, 1000)"
>
    <div class="flex items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Squares') }}</flux:heading>
            <flux:subheading>
                {{ $finished
                    ? __('The board has settled — every square is the same colour.')
                    : __('Every 10 seconds, one square spreads its colour into a neighbour.') }}
            </flux:subheading>
        </div>

        <div class="flex items-center gap-4">
            @unless ($finished)
                <flux:text class="tabular-nums text-zinc-500">
                    {{ __('Next move in') }} <span x-text="remaining"></span>{{ __('s') }}
                </flux:text>
            @endunless

            <flux:button wire:click="newBoard" variant="ghost" icon="arrow-path">
                {{ __('New board') }}
            </flux:button>
        </div>
    </div>

    <div class="grid w-fit grid-cols-10 gap-1">
        @foreach ($grid as $index => $color)
            <div wire:key="square-{{ $index }}" class="size-8 rounded-sm {{ $this->colorClass($color) }}"></div>
        @endforeach
    </div>
</div>
