@if ($url)
    <img src="{{ $url }}" alt="" loading="lazy" class="h-15 w-10 shrink-0 rounded object-cover" />
@else
    <div class="flex h-15 w-10 shrink-0 items-center justify-center rounded bg-zinc-100 dark:bg-zinc-800">
        <flux:icon :name="$tv ? 'tv' : 'film'" class="size-5 text-zinc-400" />
    </div>
@endif
