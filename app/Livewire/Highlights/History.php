<?php

namespace App\Livewire\Highlights;

use App\Models\DayHighlight;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Every highlight of the day written on Your Day, newest first, searchable
 * and filterable by year.
 *
 * @property-read LengthAwarePaginator<int, DayHighlight> $highlights
 * @property-read int $total
 * @property-read list<int> $years
 */
#[Title('Highlights')]
class History extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    /** A year like "2026", or "" for all time. */
    #[Url]
    public string $year = '';

    public function mount(): void
    {
        if ($this->year !== '' && ! preg_match('/^\d{4}$/', $this->year)) {
            $this->year = '';
        }
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'year'], true)) {
            $this->resetPage();
            unset($this->highlights, $this->total);
        }
    }

    /**
     * @return Builder<DayHighlight>
     */
    protected function filtered(): Builder
    {
        return DayHighlight::query()
            ->when($this->year !== '', fn (Builder $q) => $q->whereYear('date', (int) $this->year))
            ->when(trim($this->search) !== '', fn (Builder $q) => $q->where('body', 'like', '%'.trim($this->search).'%'));
    }

    /**
     * @return LengthAwarePaginator<int, DayHighlight>
     */
    #[Computed]
    public function highlights(): LengthAwarePaginator
    {
        return $this->filtered()->orderByDesc('date')->paginate(20);
    }

    /** How many highlights match the current filters. */
    #[Computed]
    public function total(): int
    {
        return $this->filtered()->count();
    }

    /**
     * Every year from the first highlight to this one, newest first.
     *
     * @return list<int>
     */
    #[Computed]
    public function years(): array
    {
        $first = DayHighlight::min('date');

        if ($first === null) {
            return [];
        }

        $from = (int) substr((string) $first, 0, 4);
        $to = max($from, (int) now('Europe/London')->format('Y'));

        return range($to, $from);
    }
}
