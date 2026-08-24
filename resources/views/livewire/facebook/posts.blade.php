<div class="flex h-full w-full flex-1 flex-col gap-8">
    <div>
        <flux:heading size="xl">{{ __('Facebook: All posts') }}</flux:heading>
        <flux:subheading>{{ __('Everything imported from your Facebook posts export, newest first.') }}</flux:subheading>
    </div>

    <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search posts…')" clearable />

    <div class="flex flex-col gap-4">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Date') }}</flux:table.column>
                <flux:table.column>{{ __('Title') }}</flux:table.column>
                <flux:table.column>{{ __('Post') }}</flux:table.column>
                <flux:table.column>{{ __('Delete') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->posts as $post)
                    <flux:table.row wire:key="post-{{ $post->id }}">
                        <flux:table.cell class="whitespace-nowrap">{{ $post->posted_at->format('d M Y') }}</flux:table.cell>
                        <flux:table.cell class="max-w-xs align-top break-words whitespace-normal!">{{ $post->title }}</flux:table.cell>
                        <flux:table.cell class="max-w-md align-top break-words whitespace-normal!">{{ Str::limit($post->body, 200) }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:modal.trigger name="confirm-delete-post-{{ $post->id }}">
                                <flux:button size="sm" variant="danger" icon="trash" x-data="" x-on:click.prevent="$dispatch('open-modal', 'confirm-delete-post-{{ $post->id }}')" />
                            </flux:modal.trigger>

                            <flux:modal name="confirm-delete-post-{{ $post->id }}" focusable class="max-w-lg">
                                <div class="space-y-6">
                                    <div>
                                        <flux:heading size="lg">{{ __('Delete this post?') }}</flux:heading>
                                        <flux:subheading>{{ __('It will be hidden from All Posts and Memories, but not permanently deleted.') }}</flux:subheading>
                                    </div>

                                    <div class="flex justify-end space-x-2 rtl:space-x-reverse">
                                        <flux:modal.close>
                                            <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                                        </flux:modal.close>

                                        <flux:button variant="danger" wire:click="delete({{ $post->id }})" x-on:click="$dispatch('close-modal', 'confirm-delete-post-{{ $post->id }}')">
                                            {{ __('Delete') }}
                                        </flux:button>
                                    </div>
                                </div>
                            </flux:modal>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4" class="text-center text-zinc-500">
                            {{ $search !== '' ? __('No posts match your search.') : __('No posts imported yet.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>

        {{ $this->posts->links() }}
    </div>
</div>
