<div class="flex h-full w-full flex-1 flex-col gap-8">
    <div>
        <flux:heading size="xl">{{ __('Dashboard') }}</flux:heading>
        <flux:subheading>{{ __('What happened on this day, and what\'s new in the last 24 hours.') }}</flux:subheading>
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div class="flex flex-col gap-4 rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
            <div class="flex items-center justify-between gap-4">
                <flux:heading size="lg">{{ __('Facebook memories') }}</flux:heading>
                <flux:button href="{{ route('facebook.memories.index') }}" size="sm" variant="ghost" icon-trailing="chevron-right" wire:navigate>
                    {{ __('View all') }}
                </flux:button>
            </div>

            <div class="flex flex-col gap-6">
                @forelse ($this->facebookMemoriesByYear as $year => $posts)
                    <div class="flex flex-col gap-3">
                        <div class="flex items-center gap-3">
                            <flux:heading size="sm">{{ $year }}</flux:heading>
                            <flux:badge size="sm" color="sky">{{ $posts[0]->posted_at->diffForHumans(['parts' => 1]) }}</flux:badge>
                        </div>

                        <div class="flex flex-col gap-3">
                            @foreach ($posts as $post)
                                @php
                                    $hasTitle = $post->title && ! Illuminate\Support\Str::endsWith($post->title, 'updated his status.');
                                @endphp

                                <div wire:key="dashboard-fb-{{ $post->id }}" class="rounded-lg border-l-4 border-sky-400 bg-sky-50/50 p-3 shadow-sm dark:border-sky-500 dark:bg-sky-950/20">
                                    @if ($hasTitle)
                                        <flux:text class="font-medium">{{ $post->title }}</flux:text>
                                    @endif

                                    @if ($post->body)
                                        <flux:text class="line-clamp-3 whitespace-pre-line">{{ $post->body }}</flux:text>
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

        <div class="flex flex-col gap-4 rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
            <div class="flex items-center justify-between gap-4">
                <flux:heading size="lg">{{ __('Twitter memories') }}</flux:heading>
                <flux:button href="{{ route('twitter.memories.index') }}" size="sm" variant="ghost" icon-trailing="chevron-right" wire:navigate>
                    {{ __('View all') }}
                </flux:button>
            </div>

            <div class="flex flex-col gap-6">
                @forelse ($this->twitterMemoriesByYear as $year => $tweets)
                    <div class="flex flex-col gap-3">
                        <div class="flex items-center gap-3">
                            <flux:heading size="sm">{{ $year }}</flux:heading>
                            <flux:badge size="sm" color="sky">{{ $tweets[0]->posted_at->diffForHumans(['parts' => 1]) }}</flux:badge>
                        </div>

                        <div class="flex flex-col gap-3">
                            @foreach ($tweets as $tweet)
                                <div wire:key="dashboard-tw-{{ $tweet->id }}" class="rounded-lg border-l-4 border-sky-400 bg-sky-50/50 p-3 shadow-sm dark:border-sky-500 dark:bg-sky-950/20">
                                    <flux:text class="line-clamp-3 whitespace-pre-line">{{ $tweet->body }}</flux:text>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <flux:text class="text-center text-zinc-500">{{ __('Nothing posted on this day in any year.') }}</flux:text>
                @endforelse
            </div>
        </div>
    </div>

    <div class="flex flex-col gap-4 rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
        <div class="flex items-center justify-between gap-4">
            <flux:heading size="lg">{{ __('Scans gone live') }}</flux:heading>
            <flux:button href="{{ route('scans.index') }}" size="sm" variant="ghost" icon-trailing="chevron-right" wire:navigate>
                {{ __('View all') }}
            </flux:button>
        </div>

        <div class="flex flex-col gap-3">
            @forelse ($this->recentAlerts as $alert)
                <div wire:key="dashboard-alert-{{ $alert->id }}" class="flex flex-col gap-1 rounded-lg border-l-4 border-emerald-400 bg-emerald-50/50 p-3 shadow-sm dark:border-emerald-500 dark:bg-emerald-950/20">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <flux:text class="font-medium">{{ $alert->title }}</flux:text>
                        <flux:text size="sm" class="shrink-0 whitespace-nowrap text-emerald-700 dark:text-emerald-400">
                            {{ $alert->detected_at->diffForHumans() }}
                        </flux:text>
                    </div>

                    @if ($alert->url)
                        <flux:link href="{{ $alert->url }}" target="_blank">{{ $alert->url }}</flux:link>
                    @endif
                </div>
            @empty
                <flux:text class="text-center text-zinc-500">{{ __('Nothing new in the last 24 hours.') }}</flux:text>
            @endforelse
        </div>
    </div>

    <div class="flex flex-col gap-4 rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
        <div class="flex items-center justify-between gap-4">
            <flux:heading size="lg">{{ __('New Bristol adverts') }}</flux:heading>
            <flux:button href="{{ route('planning-applications.index') }}" size="sm" variant="ghost" icon-trailing="chevron-right" wire:navigate>
                {{ __('View all') }}
            </flux:button>
        </div>

        <div class="flex flex-col gap-3">
            @forelse ($this->recentAdverts as $application)
                <div wire:key="dashboard-advert-{{ $application->id }}" class="flex flex-col gap-1 rounded-lg border border-neutral-200 p-3 dark:border-neutral-700">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <flux:text class="font-medium">{{ $application->reference }} &middot; {{ $application->address }}</flux:text>
                        <flux:badge size="sm" color="zinc">{{ $application->status }}</flux:badge>
                    </div>

                    @if ($application->proposal)
                        <flux:text class="line-clamp-2 text-zinc-500">{{ $application->proposal }}</flux:text>
                    @endif
                </div>
            @empty
                <flux:text class="text-center text-zinc-500">{{ __('No new advertisement applications in the last 24 hours.') }}</flux:text>
            @endforelse
        </div>
    </div>
</div>
