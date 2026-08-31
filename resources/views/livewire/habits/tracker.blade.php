<div class="flex h-full w-full flex-1 flex-col gap-16">
    <div>
        <flux:heading size="xl">{{ __('Habit tracker') }}</flux:heading>
        <flux:subheading>{{ __('The last 26 weeks, one square per day.') }}</flux:subheading>
    </div>

    @foreach ($this->activityGrids as $grid)
        <div class="flex flex-col gap-2">
            <div class="flex items-center gap-2">
                <span class="size-3 rounded-sm" style="background-color: {{ $grid['color'] }}"></span>
                <flux:heading size="sm">{{ $grid['name'] }}</flux:heading>
            </div>

            <div class="overflow-x-auto pb-2">
                <div class="flex gap-1">
                    @foreach ($grid['weeks'] as $week)
                        <div class="flex flex-col gap-1">
                            @foreach ($week as $day)
                                <div
                                    wire:key="{{ $grid['name'] }}-{{ $day['date'] }}"
                                    title="{{ \Illuminate\Support\Carbon::parse($day['date'])->format('D j M Y') }}{{ $day['filled'] ? ' — '.$grid['name'] : '' }}"
                                    class="size-3.5 rounded-sm {{ $day['filled'] ? '' : 'bg-zinc-200 dark:bg-zinc-700' }}"
                                    @style(['background-color: '.$grid['color'] => $day['filled']])
                                ></div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endforeach

    @if (count($this->activityGrids) === 0)
        <div class="rounded-lg border border-dashed border-zinc-300 p-6 text-center text-zinc-500 dark:border-zinc-700">
            {{ __('No activities yet.') }}
            <flux:link :href="route('habits.activities')" wire:navigate>{{ __('Add one.') }}</flux:link>
        </div>
    @endif

    <div class="flex flex-col gap-2 border-t border-zinc-200 pt-12 dark:border-zinc-700">
        <flux:heading size="sm">{{ __('Overall') }}</flux:heading>
        <flux:subheading>{{ __('Every activity combined, weighted score per day — lighter green is a lower score, darker is higher.') }}</flux:subheading>

        <div class="overflow-x-auto pb-2">
            <div class="flex gap-1">
                @foreach ($this->summaryGrid as $week)
                    <div class="flex flex-col gap-1">
                        @foreach ($week as $day)
                            @php
                                $shade = match ($day['intensity']) {
                                    1 => '#bbf7d0',
                                    2 => '#86efac',
                                    3 => '#4ade80',
                                    4 => '#16a34a',
                                    default => null,
                                };
                            @endphp
                            <div
                                wire:key="overall-{{ $day['date'] }}"
                                title="{{ \Illuminate\Support\Carbon::parse($day['date'])->format('D j M Y') }}{{ $day['intensity'] > 0 ? ' — score '.rtrim(rtrim((string) $day['score'], '0'), '.') : '' }}"
                                class="size-3.5 rounded-sm {{ $shade ? '' : 'bg-zinc-200 dark:bg-zinc-700' }}"
                                @style(['background-color: '.$shade => $shade])
                            ></div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
