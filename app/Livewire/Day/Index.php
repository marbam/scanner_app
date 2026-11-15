<?php

namespace App\Livewire\Day;

use App\Livewire\Concerns\LogsBooks;
use App\Livewire\Concerns\LogsHabits;
use App\Livewire\Watching\Today;
use App\Models\DayHighlight;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

/**
 * "Your day": one daily screen that walks through habits, TV, films, books
 * and a highlight of the day, then a summary of the lot. The TV/films steps
 * are the watching flow from Watching\Today; habits and books come from their
 * own traits.
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
    public const STEPS = ['habits', 'tv', 'films', 'books', 'highlight', 'done'];

    public const HIGHLIGHT_MAX_LENGTH = 2000;

    #[Url]
    public string $step = 'habits';

    /** The highlight of the day, saved on blur and when moving on. */
    public string $highlight = '';

    public function mount(): void
    {
        parent::mount();
        $this->loadHighlight();
    }

    public function updatedHighlight(): void
    {
        $this->saveHighlight();
    }

    /** Save the highlight for this date; clearing the text deletes it. */
    public function saveHighlight(): void
    {
        $this->validate(['highlight' => ['nullable', 'string', 'max:'.self::HIGHLIGHT_MAX_LENGTH]]);

        $body = trim($this->highlight);
        $existing = DayHighlight::query()->whereDate('date', $this->date)->first();

        if ($body === '') {
            $existing?->delete();
        } elseif ($existing !== null) {
            $existing->update(['body' => $body]);
        } else {
            DayHighlight::create(['date' => $this->date, 'body' => $body]);
        }

        $this->highlight = $body;
    }

    protected function loadHighlight(): void
    {
        $this->highlight = DayHighlight::query()->whereDate('date', $this->date)->value('body') ?? '';
        $this->resetValidation('highlight');
    }

    protected function setDate(string $date): void
    {
        parent::setDate($date);
        $this->loadHighlight();
    }

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
