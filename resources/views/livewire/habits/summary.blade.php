<div class="flex h-full w-full flex-1 flex-col gap-8">
    <div>
        <flux:heading size="xl">{{ __('Growth summary') }}</flux:heading>
        <flux:subheading>{{ __('Weighted daily score, with a 7-day rolling average.') }}</flux:subheading>
    </div>

    <div class="flex items-center gap-2">
        @foreach ([30 => '30d', 90 => '90d', 365 => '1y'] as $days => $label)
            <flux:button
                size="sm"
                :variant="$rangeDays === $days ? 'primary' : 'ghost'"
                wire:click="setRange({{ $days }})"
            >
                {{ $label }}
            </flux:button>
        @endforeach
    </div>

    @php
        $points = $this->points;
        $maxScore = max(1.0, collect($points)->max('score'), collect($points)->max('average'));
        $width = 800;
        $height = 220;
        $count = max(1, count($points) - 1);

        $averagePath = collect($points)
            ->map(function ($point, $index) use ($count, $width, $height, $maxScore) {
                $x = $count === 0 ? 0 : ($index / $count) * $width;
                $y = $height - ($point['average'] / $maxScore) * $height;

                return round($x, 2).','.round($y, 2);
            })
            ->implode(' ');
    @endphp

    <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
        @if (count($points) === 0)
            <div class="py-12 text-center text-zinc-500">{{ __('No data yet for this range.') }}</div>
        @else
            <svg viewBox="0 0 {{ $width }} {{ $height }}" class="h-56 w-full" preserveAspectRatio="none">
                @foreach ($points as $index => $point)
                    @php
                        $barWidth = $width / max(1, count($points));
                        $x = $index * $barWidth;
                        $barHeight = ($point['score'] / $maxScore) * $height;
                    @endphp
                    <rect
                        x="{{ round($x, 2) }}"
                        y="{{ round($height - $barHeight, 2) }}"
                        width="{{ max(0, $barWidth - 1) }}"
                        height="{{ round($barHeight, 2) }}"
                        fill="currentColor"
                        class="text-zinc-200 dark:text-zinc-700"
                    >
                        <title>{{ $point['date'] }}: {{ rtrim(rtrim((string) $point['score'], '0'), '.') ?: 0 }}</title>
                    </rect>
                @endforeach

                <polyline
                    points="{{ $averagePath }}"
                    fill="none"
                    stroke="#3b82f6"
                    stroke-width="2.5"
                    stroke-linejoin="round"
                    stroke-linecap="round"
                />
            </svg>

            <div class="mt-2 flex items-center justify-between text-xs text-zinc-500">
                <span>{{ \Illuminate\Support\Carbon::parse($points[0]['date'])->format('j M Y') }}</span>
                <span class="flex items-center gap-1">
                    <span class="inline-block h-0.5 w-4 bg-[#3b82f6]"></span>
                    {{ __('7-day average') }}
                </span>
                <span>{{ \Illuminate\Support\Carbon::parse(end($points)['date'])->format('j M Y') }}</span>
            </div>
        @endif
    </div>
</div>
