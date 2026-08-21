<div class="flex h-full w-full flex-1 flex-col gap-8">
    <div>
        <flux:heading size="xl">{{ __('Scans') }}</flux:heading>
        <flux:subheading>{{ __('What\'s being watched, and the most recent check attempts.') }}</flux:subheading>
    </div>

    <div class="flex flex-col gap-4">
        <flux:heading>{{ __('Interests') }}</flux:heading>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Provider') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column>{{ __('Last checked') }}</flux:table.column>
                <flux:table.column>{{ __('Checks') }}</flux:table.column>
                <flux:table.column>{{ __('Enabled') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->interests as $interest)
                    <flux:table.row wire:key="interest-{{ $interest->id }}">
                        <flux:table.cell class="font-medium">{{ $interest->name }}</flux:table.cell>
                        <flux:table.cell>{{ $interest->provider }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="match ($interest->status) {
                                'released' => 'green',
                                'error' => 'red',
                                default => 'zinc',
                            }">
                                {{ $interest->status }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            {{ $interest->last_checked_at?->diffForHumans() ?? __('Never') }}
                        </flux:table.cell>
                        <flux:table.cell>{{ $interest->checks_count }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:switch
                                :checked="$interest->enabled"
                                wire:click="toggleEnabled({{ $interest->id }})"
                            />
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="text-center text-zinc-500">
                            {{ __('No interests configured yet.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <div class="flex flex-col gap-4">
        <flux:heading>{{ __('Recent scans') }}</flux:heading>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Interest') }}</flux:table.column>
                <flux:table.column>{{ __('Outcome') }}</flux:table.column>
                <flux:table.column>{{ __('HTTP status') }}</flux:table.column>
                <flux:table.column>{{ __('When') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->recentChecks as $check)
                    <flux:table.row wire:key="check-{{ $check->id }}">
                        <flux:table.cell class="font-medium">{{ $check->interest->name }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="match ($check->outcome) {
                                'released' => 'green',
                                'error' => 'red',
                                'unchanged' => 'zinc',
                                default => 'blue',
                            }">
                                {{ $check->outcome }}
                            </flux:badge>

                            @if ($check->error_message)
                                <flux:tooltip content="{{ $check->error_message }}">
                                    <flux:icon.information-circle class="inline size-4 text-zinc-400" />
                                </flux:tooltip>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $check->http_status ?? __('—') }}</flux:table.cell>
                        <flux:table.cell>{{ $check->created_at->diffForHumans() }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4" class="text-center text-zinc-500">
                            {{ __('No scans have run yet.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</div>
