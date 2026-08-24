<?php

namespace App\Livewire\Facebook\Posts;

use App\Models\FacebookPost;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Facebook: All posts')]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, FacebookPost>
     */
    #[Computed]
    public function posts(): LengthAwarePaginator
    {
        return FacebookPost::query()
            ->when($this->search !== '', function ($query) {
                $query->where(function ($query) {
                    $query->where('title', 'like', "%{$this->search}%")
                        ->orWhere('body', 'like', "%{$this->search}%");
                });
            })
            ->orderByDesc('posted_at')
            ->paginate(25);
    }

    public function delete(int $postId): void
    {
        FacebookPost::findOrFail($postId)->delete();

        unset($this->posts);
    }
}
