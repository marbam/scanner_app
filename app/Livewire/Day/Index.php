<?php

namespace App\Livewire\Day;

use App\Livewire\Concerns\LogsHabits;
use App\Livewire\Watching\Today;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

/**
 * "Your day": one daily screen that walks through habits, then TV, then
 * films, then a summary of the lot. The TV/films steps are the watching
 * flow from Watching\Today; the habits step is the habit log's list.
 *
 * New daily trackers (e.g. books) slot in as another entry in STEPS plus a
 * branch in the view.
 */
#[Title('Your day')]
class Index extends Today
{
    use LogsHabits;

    /** @var list<string> */
    public const STEPS = ['habits', 'tv', 'films', 'done'];

    #[Url]
    public string $step = 'habits';

    protected function clearComputed(): void
    {
        parent::clearComputed();
        unset($this->habitEntries);
    }

    public function render(): View
    {
        return view('livewire.day');
    }
}
