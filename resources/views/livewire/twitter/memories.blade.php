<div class="flex h-full w-full flex-1 flex-col gap-8">
    <div>
        <flux:heading size="xl">{{ __('Twitter: Memories') }}</flux:heading>
        <flux:subheading>{{ __('Everything you tweeted on this day, across every year.') }}</flux:subheading>
    </div>

    <div class="flex items-center justify-center gap-4">
        <flux:button icon="chevron-left" wire:click="previousDay" variant="ghost" />

        <div class="flex flex-col items-center gap-1">
            <flux:heading size="lg">{{ \Illuminate\Support\Carbon::parse($date)->format('j F') }}</flux:heading>

            @unless (\Illuminate\Support\Carbon::parse($date)->isToday())
                <flux:button size="sm" variant="ghost" wire:click="today">{{ __('Jump to today') }}</flux:button>
            @endunless
        </div>

        <flux:button icon="chevron-right" wire:click="nextDay" variant="ghost" />
    </div>

    <div class="flex flex-col gap-12">
        @forelse ($this->tweetsByYear as $year => $tweets)
            <div class="flex flex-col gap-4">
                <div class="flex items-center gap-3">
                    <flux:heading size="md">{{ $year }}</flux:heading>
                    <flux:badge size="sm" color="sky">{{ $tweets[0]->posted_at->diffForHumans(['parts' => 1]) }}</flux:badge>
                </div>

                <div class="flex flex-col gap-4">
                    @foreach ($tweets as $tweet)
                        <div wire:key="memory-{{ $tweet->id }}" class="rounded-lg border-l-4 border-sky-400 bg-sky-50/50 p-4 shadow-sm dark:border-sky-500 dark:bg-sky-950/20">
                            <flux:text class="whitespace-pre-line">
                                <span class="float-right ms-3 whitespace-nowrap text-sm text-sky-700 dark:text-sky-400">{{ $tweet->posted_at->format('j F Y, H:i') }}</span>{{ $tweet->body }}
                            </flux:text>
                        </div>
                    @endforeach
                </div>
            </div>
        @empty
            <flux:text class="text-center text-zinc-500">{{ __('Nothing tweeted on this day in any year.') }}</flux:text>
        @endforelse
    </div>
</div>
