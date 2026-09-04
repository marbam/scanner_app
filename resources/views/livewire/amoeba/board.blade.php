<div class="flex h-full w-full flex-1 flex-col gap-6" wire:poll.600ms="computerMove">
    <div class="flex items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Amoeba') }}</flux:heading>
            <flux:subheading>
                @if ($finished)
                    {{ match ($result) {
                        'red' => __('You win — blue has been eliminated.'),
                        'blue' => __('Blue wins — red has been eliminated.'),
                        default => __('Draw.'),
                    } }}
                @elseif ($calculating)
                    {{ __('Calculating…') }}
                @elseif ($selectedIndex !== null)
                    {{ __('Choose a highlighted square to move to.') }}
                @else
                    {{ __('Your move — pick a red piece.') }}
                @endif
            </flux:subheading>
        </div>

        <div class="flex items-center gap-4">
            <flux:text class="tabular-nums text-zinc-500">
                {{ __('Red') }} {{ $this->counts()['red'] }} &middot; {{ __('Blue') }} {{ $this->counts()['blue'] }}
            </flux:text>

            <flux:button wire:click="newGame" variant="ghost" icon="arrow-path">
                {{ __('New game') }}
            </flux:button>
        </div>
    </div>

    <div class="grid w-fit grid-cols-7 gap-1">
        @foreach ($grid as $index => $color)
            <div
                wire:key="square-{{ $index }}"
                wire:click="handleClick({{ $index }})"
                class="size-10 cursor-pointer rounded-sm {{ $this->colorClass($color) }} {{ $selectedIndex === $index ? 'ring-2 ring-black ring-offset-2 ring-offset-zinc-800' : '' }} {{ in_array($index, $validDestinations, true) ? 'ring-2 ring-emerald-400 ring-offset-2 ring-offset-zinc-800' : '' }}"
            ></div>
        @endforeach
    </div>
</div>
