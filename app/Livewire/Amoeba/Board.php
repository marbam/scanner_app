<?php

namespace App\Livewire\Amoeba;

use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Amoeba')]
class Board extends Component
{
    private const SIZE = 7;

    private const RED = 'red';

    private const BLUE = 'blue';

    private const CORNERS = [0, 6, 42, 48];

    private const DIRECTIONS = [
        [-1, -1], [-1, 0], [-1, 1],
        [0, -1], [0, 1],
        [1, -1], [1, 0], [1, 1],
    ];

    private const AI_DEPTH = 3;

    /** @var array<int, ?string> */
    public array $grid = [];

    public string $turn = self::RED;

    public ?string $result = null;

    public bool $finished = false;

    public ?int $selectedIndex = null;

    /** @var array<int, int> */
    public array $validDestinations = [];

    public bool $calculating = false;

    public function mount(): void
    {
        $this->resetBoard();
    }

    public function newGame(): void
    {
        $this->resetBoard();
    }

    /**
     * Clicking a red piece with available moves selects it (highlighting its
     * legal destinations); clicking a highlighted destination moves it there;
     * clicking anything else clears the current selection.
     */
    public function handleClick(int $index): void
    {
        if ($this->finished || $this->calculating || $this->turn !== self::RED) {
            return;
        }

        if ($this->selectedIndex !== null && in_array($index, $this->validDestinations, true)) {
            $this->applyHumanMove($index);

            return;
        }

        if ($this->grid[$index] === self::RED) {
            $moves = self::movesFrom($this->grid, $index);

            $this->selectedIndex = $moves === [] ? null : $index;
            $this->validDestinations = array_column($moves, 'to');

            return;
        }

        $this->selectedIndex = null;
        $this->validDestinations = [];
    }

    /**
     * Runs on every poll tick, but only acts once a move has left the board
     * waiting on blue — this is what lets the "Calculating…" state render on
     * the tick before the (potentially slow) search actually runs.
     */
    public function computerMove(): void
    {
        if (! $this->calculating || $this->finished || $this->turn !== self::BLUE) {
            return;
        }

        $move = self::bestMove($this->grid, self::AI_DEPTH);

        if ($move === null) {
            $this->finished = true;
            $this->result = 'draw';
            $this->calculating = false;

            return;
        }

        $this->grid = self::applyMove($this->grid, $move, self::BLUE);
        $this->calculating = false;

        $this->afterMove(self::RED);
    }

    public function colorClass(?string $color): string
    {
        return match ($color) {
            self::RED => 'bg-red-500',
            self::BLUE => 'bg-blue-500',
            default => 'bg-zinc-200 dark:bg-zinc-700',
        };
    }

    /**
     * @return array<string, int>
     */
    public function counts(): array
    {
        return self::countColors($this->grid);
    }

    private function applyHumanMove(int $target): void
    {
        $move = collect(self::movesFrom($this->grid, $this->selectedIndex))
            ->first(fn (array $move) => $move['to'] === $target);

        $this->selectedIndex = null;
        $this->validDestinations = [];

        if ($move === null) {
            return;
        }

        $this->grid = self::applyMove($this->grid, $move, self::RED);

        $this->afterMove(self::BLUE);
    }

    private function afterMove(string $next): void
    {
        if ($this->concludeIfGameOver()) {
            return;
        }

        if (self::movesFor($this->grid, $next) === []) {
            $this->finished = true;
            $this->result = 'draw';

            return;
        }

        $this->turn = $next;
        $this->calculating = $next === self::BLUE;
    }

    private function concludeIfGameOver(): bool
    {
        $counts = self::countColors($this->grid);

        if ($counts[self::RED] === 0) {
            $this->finished = true;
            $this->result = self::BLUE;

            return true;
        }

        if ($counts[self::BLUE] === 0) {
            $this->finished = true;
            $this->result = self::RED;

            return true;
        }

        if (! in_array(null, $this->grid, true)) {
            $this->finished = true;
            $this->result = 'draw';

            return true;
        }

        return false;
    }

    private function resetBoard(): void
    {
        $this->grid = array_fill(0, self::SIZE ** 2, null);

        $corners = self::CORNERS;
        shuffle($corners);

        $this->grid[$corners[0]] = self::RED;
        $this->grid[$corners[1]] = self::RED;
        $this->grid[$corners[2]] = self::BLUE;
        $this->grid[$corners[3]] = self::BLUE;

        $this->turn = random_int(0, 1) === 0 ? self::RED : self::BLUE;
        $this->finished = false;
        $this->result = null;
        $this->selectedIndex = null;
        $this->validDestinations = [];
        $this->calculating = $this->turn === self::BLUE;
    }

    /**
     * @param  array<int, ?string>  $grid
     * @return array<int, array{from: int, to: int, type: string}>
     */
    private static function movesFor(array $grid, string $color): array
    {
        $moves = [];

        foreach ($grid as $index => $value) {
            if ($value === $color) {
                array_push($moves, ...self::movesFrom($grid, $index));
            }
        }

        return $moves;
    }

    /**
     * @param  array<int, ?string>  $grid
     * @return array<int, array{from: int, to: int, type: string}>
     */
    private static function movesFrom(array $grid, int $index): array
    {
        [$row, $col] = self::rowCol($index);

        $moves = [];

        foreach (self::DIRECTIONS as [$rowOffset, $colOffset]) {
            $copyTo = self::indexAt($row + $rowOffset, $col + $colOffset);

            if ($copyTo !== null && $grid[$copyTo] === null) {
                $moves[] = ['from' => $index, 'to' => $copyTo, 'type' => 'copy'];
            }

            $jumpTo = self::indexAt($row + (2 * $rowOffset), $col + (2 * $colOffset));

            if ($jumpTo !== null && $grid[$jumpTo] === null) {
                $moves[] = ['from' => $index, 'to' => $jumpTo, 'type' => 'jump'];
            }
        }

        return $moves;
    }

    private static function indexAt(int $row, int $col): ?int
    {
        if ($row < 0 || $row >= self::SIZE || $col < 0 || $col >= self::SIZE) {
            return null;
        }

        return ($row * self::SIZE) + $col;
    }

    /**
     * @return array{0: int, 1: int}
     */
    private static function rowCol(int $index): array
    {
        return [intdiv($index, self::SIZE), $index % self::SIZE];
    }

    /**
     * @param  array<int, ?string>  $grid
     * @param  array{from: int, to: int, type: string}  $move
     * @return array<int, ?string>
     */
    private static function applyMove(array $grid, array $move, string $color): array
    {
        $grid[$move['to']] = $color;

        if ($move['type'] === 'jump') {
            $grid[$move['from']] = null;
        }

        [$row, $col] = self::rowCol($move['to']);

        $opponent = $color === self::RED ? self::BLUE : self::RED;

        foreach (self::DIRECTIONS as [$rowOffset, $colOffset]) {
            $neighbor = self::indexAt($row + $rowOffset, $col + $colOffset);

            if ($neighbor !== null && $grid[$neighbor] === $opponent) {
                $grid[$neighbor] = $color;
            }
        }

        return $grid;
    }

    /**
     * @param  array<int, ?string>  $grid
     * @return array<string, int>
     */
    private static function countColors(array $grid): array
    {
        $counts = [self::RED => 0, self::BLUE => 0];

        foreach ($grid as $value) {
            if ($value !== null) {
                $counts[$value]++;
            }
        }

        return $counts;
    }

    /**
     * @param  array<int, ?string>  $grid
     * @return array{from: int, to: int, type: string}|null
     */
    private static function bestMove(array $grid, int $depth): ?array
    {
        return self::minimax($grid, self::BLUE, $depth, -INF, INF)['move'];
    }

    /**
     * Recursively scores every move blue could make by cloning the board,
     * applying it, then letting red pick its best reply on the resulting
     * board, and so on — alternating maximise-for-blue / minimise-for-red
     * down to a fixed depth, so the move chosen accounts for red's best
     * counters rather than just blue's immediate gain.
     *
     * @param  array<int, ?string>  $grid
     * @return array{score: int, move: array{from: int, to: int, type: string}|null}
     */
    private static function minimax(array $grid, string $color, int $depth, float $alpha, float $beta): array
    {
        $moves = self::movesFor($grid, $color);
        $counts = self::countColors($grid);

        $gameOver = $counts[self::RED] === 0
            || $counts[self::BLUE] === 0
            || $moves === []
            || ! in_array(null, $grid, true);

        if ($depth === 0 || $gameOver) {
            return ['score' => $counts[self::BLUE] - $counts[self::RED], 'move' => null];
        }

        $maximizing = $color === self::BLUE;
        $opponent = $maximizing ? self::RED : self::BLUE;

        $bestScore = $maximizing ? -INF : INF;
        $bestMove = null;

        foreach ($moves as $move) {
            $child = self::applyMove($grid, $move, $color);

            $score = self::minimax($child, $opponent, $depth - 1, $alpha, $beta)['score'];

            if ($maximizing ? $score > $bestScore : $score < $bestScore) {
                $bestScore = $score;
                $bestMove = $move;
            }

            if ($maximizing) {
                $alpha = max($alpha, $bestScore);
            } else {
                $beta = min($beta, $bestScore);
            }

            if ($alpha >= $beta) {
                break;
            }
        }

        return ['score' => (int) $bestScore, 'move' => $bestMove];
    }
}
