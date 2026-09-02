<?php

namespace App\Livewire\Squares;

use Illuminate\Support\Collection;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Squares')]
class Board extends Component
{
    private const SIZE = 10;

    private const COLORS = ['red', 'yellow', 'green', 'blue'];

    /** @var array<int, string> */
    public array $grid = [];

    public bool $finished = false;

    public ?int $selectedIndex = null;

    public function mount(): void
    {
        $this->newBoard();
    }

    public function newBoard(): void
    {
        $this->grid = collect(range(0, (self::SIZE ** 2) - 1))
            ->map(fn () => self::COLORS[array_rand(self::COLORS)])
            ->all();

        $this->finished = false;
        $this->selectedIndex = null;
    }

    /**
     * Runs on every poll tick, alternating between two phases so the square
     * about to act is highlighted for one tick before it actually moves:
     * with nothing selected, pick (and highlight) an actionable square;
     * with one already selected, spread its colour into a random
     * differently-coloured neighbour and clear the highlight.
     */
    public function step(): void
    {
        if ($this->finished) {
            return;
        }

        if ($this->selectedIndex === null) {
            $this->selectSquare();

            return;
        }

        $this->commitMove();
    }

    /**
     * Pick an actionable square (one with a differently-coloured neighbour),
     * weighted so colours with fewer squares on the board are more likely to
     * be chosen.
     */
    private function selectSquare(): void
    {
        $actionable = $this->actionableSquares();

        if ($actionable->isEmpty()) {
            $this->finished = true;

            return;
        }

        $byColor = $actionable->groupBy(fn (int $index) => $this->grid[$index]);

        $chosenColor = $this->weightedColor($byColor->keys(), $this->colorCounts());

        $this->selectedIndex = $byColor[$chosenColor]->random();
    }

    private function commitMove(): void
    {
        $selected = $this->selectedIndex;

        $this->selectedIndex = null;

        if ($selected === null) {
            return;
        }

        $target = $this->differentNeighbors($selected)->random();

        $this->grid[$target] = $this->grid[$selected];

        if (count(array_unique($this->grid)) === 1) {
            $this->finished = true;
        }
    }

    public function colorClass(string $color): string
    {
        return match ($color) {
            'red' => 'bg-red-500',
            'yellow' => 'bg-yellow-400',
            'green' => 'bg-green-500',
            'blue' => 'bg-blue-500',
            default => 'bg-zinc-300',
        };
    }

    /**
     * @return Collection<int, int<0, 99>>
     */
    private function actionableSquares(): Collection
    {
        return collect(range(0, (self::SIZE ** 2) - 1))
            ->filter(fn (int $index) => $this->differentNeighbors($index)->isNotEmpty())
            ->values();
    }

    /**
     * @return Collection<int, int<0, max>>
     */
    private function differentNeighbors(int $index): Collection
    {
        return $this->neighbors($index)
            ->filter(fn (int $neighbor) => $this->grid[$neighbor] !== $this->grid[$index])
            ->values();
    }

    /**
     * @return Collection<int, int<0, max>>
     */
    private function neighbors(int $index): Collection
    {
        $row = intdiv($index, self::SIZE);
        $col = $index % self::SIZE;

        $neighbors = [];

        for ($rowOffset = -1; $rowOffset <= 1; $rowOffset++) {
            for ($colOffset = -1; $colOffset <= 1; $colOffset++) {
                if ($rowOffset === 0 && $colOffset === 0) {
                    continue;
                }

                $neighborRow = $row + $rowOffset;
                $neighborCol = $col + $colOffset;

                if ($neighborRow < 0 || $neighborRow >= self::SIZE || $neighborCol < 0 || $neighborCol >= self::SIZE) {
                    continue;
                }

                $neighbors[] = ($neighborRow * self::SIZE) + $neighborCol;
            }
        }

        return collect($neighbors);
    }

    /**
     * @return array<string, int>
     */
    private function colorCounts(): array
    {
        $counts = array_fill_keys(self::COLORS, 0);

        foreach ($this->grid as $color) {
            $counts[$color]++;
        }

        return $counts;
    }

    /**
     * @param  Collection<int, string>  $colors
     * @param  array<string, int>  $counts
     */
    private function weightedColor(Collection $colors, array $counts): string
    {
        $total = self::SIZE ** 2;

        $weights = $colors->mapWithKeys(fn (string $color) => [
            $color => $total - $counts[$color],
        ]);

        $roll = random_int(1, $weights->sum());

        $cursor = 0;

        foreach ($weights as $color => $weight) {
            $cursor += $weight;

            if ($roll <= $cursor) {
                return $color;
            }
        }

        return $weights->keys()->first();
    }
}
