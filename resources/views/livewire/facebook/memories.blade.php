<div class="flex h-full w-full flex-1 flex-col gap-8">
    <div>
        <flux:heading size="xl">{{ __('Facebook: Memories') }}</flux:heading>
        <flux:subheading>{{ __('Everything you posted on this day, across every year.') }}</flux:subheading>
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
        @forelse ($this->postsByYear as $year => $posts)
            <div class="flex flex-col gap-4">
                <div class="flex items-center gap-3">
                    <flux:heading size="md">{{ $year }}</flux:heading>
                    <flux:badge size="sm" color="sky">{{ $posts[0]->posted_at->diffForHumans(['parts' => 1]) }}</flux:badge>
                </div>

                <div class="flex flex-col gap-4">
                    @foreach ($posts as $post)
                        @php
                            $hasTitle = $post->title && ! Illuminate\Support\Str::endsWith($post->title, 'updated his status.');
                        @endphp

                        <div wire:key="memory-{{ $post->id }}" class="rounded-lg border-l-4 border-sky-400 bg-sky-50/50 p-4 shadow-sm dark:border-sky-500 dark:bg-sky-950/20">
                            @if ($hasTitle)
                                <div class="mb-1 flex items-center justify-between gap-4">
                                    <flux:text class="font-medium">{{ $post->title }}</flux:text>

                                    <flux:text size="sm" class="shrink-0 whitespace-nowrap text-sky-700 dark:text-sky-400">
                                        {{ $post->posted_at->format('j F Y, H:i') }}
                                    </flux:text>
                                </div>

                                @if ($post->body)
                                    <flux:text class="whitespace-pre-line">{{ $post->body }}</flux:text>
                                @endif
                            @else
                                <flux:text class="whitespace-pre-line">
                                    <span class="float-right ms-3 whitespace-nowrap text-sm text-sky-700 dark:text-sky-400">{{ $post->posted_at->format('j F Y, H:i') }}</span>{{ $post->body }}
                                </flux:text>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @empty
            <flux:text class="text-center text-zinc-500">{{ __('Nothing posted on this day in any year.') }}</flux:text>
        @endforelse
    </div>
</div>
