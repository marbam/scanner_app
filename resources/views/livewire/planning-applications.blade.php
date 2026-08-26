<div class="flex h-full w-full flex-1 flex-col gap-8">
    <div class="flex items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Bristol adverts') }}</flux:heading>
            <flux:subheading>{{ __('Advertisement consent applications found on the Bristol planning register (reference ending "/A"), scanned daily.') }}</flux:subheading>
        </div>

        <div class="flex shrink-0 items-center gap-2">
            <flux:label>{{ __('Daily scan') }}</flux:label>
            <flux:switch
                :checked="auth()->user()->bristol_adverts_scan_enabled"
                wire:click="toggleScanEnabled"
            />
        </div>
    </div>

    <flux:callout icon="information-circle" :heading="__('The planning portal blocks direct deep links, so Search opens the search page — paste the reference in to find the application.')" />

    <div class="flex flex-col gap-4">
        <flux:table>
            <flux:table.columns>
                <flux:table.column class="w-28">{{ __('Reference') }}</flux:table.column>
                <flux:table.column class="w-56">{{ __('Address') }}</flux:table.column>
                <flux:table.column>{{ __('Proposal') }}</flux:table.column>
                <flux:table.column class="w-28">{{ __('Status') }}</flux:table.column>
                <flux:table.column class="w-20">{{ __('Search') }}</flux:table.column>
                <flux:table.column class="w-20">{{ __('Viewed') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->applications as $application)
                    <flux:table.row wire:key="application-{{ $application->id }}">
                        <flux:table.cell class="font-medium whitespace-nowrap">{{ $application->reference }}</flux:table.cell>
                        <flux:table.cell class="whitespace-normal break-words">{{ $application->address }}</flux:table.cell>
                        <flux:table.cell class="whitespace-normal break-words">{{ $application->proposal }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" color="zinc">{{ $application->status }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:button
                                href="https://pa.bristol.gov.uk/online-applications/search.do?action=simple"
                                target="_blank"
                                size="sm"
                                variant="ghost"
                                icon="arrow-top-right-on-square"
                            >
                                {{ __('Open') }}
                            </flux:button>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:switch
                                :checked="$application->viewed"
                                wire:click="toggleViewed({{ $application->id }})"
                            />
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="text-center text-zinc-500">
                            {{ __('No advertisement applications found yet.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</div>
