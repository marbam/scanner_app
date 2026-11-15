<?php

namespace App\Livewire\Day;

use App\Livewire\Concerns\LogsBooks;
use App\Livewire\Concerns\LogsHabits;
use App\Livewire\Watching\Today;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

/**
 * "Your day": one daily screen that walks through habits, TV, films and
 * books, then a summary of the lot. The TV/films steps are the watching flow
 * from Watching\Today; habits and books come from their own traits.
 *
 * New daily trackers slot in as another entry in STEPS plus a branch in the
 * view and the summary.
 */
#[Title('Your day')]
class Index extends Today
{
    use LogsBooks;
    use LogsHabits;

    /** @var list<string> */
    public const STEPS = ['habits', 'tv', 'films', 'books', 'done'];

    #[Url]
    public string $step = 'habits';

    public function updatedSearch(): void
    {
        parent::updatedSearch();
        unset($this->bookHistoryResults, $this->openLibraryResults);
    }

    protected function clearComputed(): void
    {
        parent::clearComputed();
        $this->clearBookComputed();
        unset($this->habitEntries);
    }

    public function render(): View
    {
        return view('livewire.day');
    }
}
