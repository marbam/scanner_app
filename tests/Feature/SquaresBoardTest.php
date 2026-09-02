<?php

use App\Livewire\Squares\Board;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('mounts a 10x10 grid using only the four allowed colours', function () {
    $grid = Livewire::test(Board::class)->get('grid');

    expect($grid)->toHaveCount(100);
    expect(collect($grid)->diff(['red', 'yellow', 'green', 'blue']))->toBeEmpty();
});

test('the first step selects an actionable square and highlights it without changing the grid', function () {
    $component = Livewire::test(Board::class);
    $before = $component->get('grid');

    $component->call('step');

    expect($component->get('grid'))->toEqual($before);

    $selected = $component->get('selectedIndex');

    expect($selected)->not->toBeNull();
    expect(differentColouredNeighbours($before, $selected))->not->toBeEmpty();
});

test('the second step commits the move into a differently-coloured neighbour of the selected square', function () {
    $component = Livewire::test(Board::class);

    $component->call('step');
    $selected = $component->get('selectedIndex');
    $before = $component->get('grid');

    $component->call('step');
    $after = $component->get('grid');

    expect($component->get('selectedIndex'))->toBeNull();

    $changed = collect($before)->keys()->filter(fn ($index) => $before[$index] !== $after[$index]);

    expect($changed)->toHaveCount(1);

    $changedIndex = $changed->first();

    expect(differentColouredNeighbours($before, $selected))->toContain($changedIndex);
    expect($after[$changedIndex])->toBe($before[$selected]);
});

/**
 * @param  array<int, string>  $grid
 * @return array<int, int>
 */
function differentColouredNeighbours(array $grid, int $index): array
{
    $row = intdiv($index, 10);
    $col = $index % 10;

    $neighbours = [];

    for ($rowOffset = -1; $rowOffset <= 1; $rowOffset++) {
        for ($colOffset = -1; $colOffset <= 1; $colOffset++) {
            $neighborRow = $row + $rowOffset;
            $neighborCol = $col + $colOffset;

            if ($neighborRow < 0 || $neighborRow >= 10 || $neighborCol < 0 || $neighborCol >= 10) {
                continue;
            }

            $neighborIndex = ($neighborRow * 10) + $neighborCol;

            if ($neighborIndex !== $index && $grid[$neighborIndex] !== $grid[$index]) {
                $neighbours[] = $neighborIndex;
            }
        }
    }

    return $neighbours;
}

test('stops taking steps once the whole board is a single colour', function () {
    $component = Livewire::test(Board::class);
    $component->set('grid', array_fill(0, 100, 'red'));
    $component->set('finished', false);

    $component->call('step');

    expect($component->get('grid'))->toEqual(array_fill(0, 100, 'red'));
    expect($component->get('finished'))->toBeTrue();
});

test('new board resets to an unfinished random grid with no square selected', function () {
    $component = Livewire::test(Board::class);
    $component->set('grid', array_fill(0, 100, 'red'));
    $component->set('finished', true);

    $component->call('newBoard');

    expect($component->get('finished'))->toBeFalse();
    expect($component->get('selectedIndex'))->toBeNull();
    expect(collect($component->get('grid'))->unique()->count())->toBeGreaterThan(1);
});
