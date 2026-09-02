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

test('a step changes exactly one square to match one of its neighbours', function () {
    $component = Livewire::test(Board::class);
    $before = $component->get('grid');

    $component->call('step');
    $after = $component->get('grid');

    $changed = collect($before)->keys()->filter(fn ($index) => $before[$index] !== $after[$index]);

    expect($changed)->toHaveCount(1);

    $changedIndex = $changed->first();
    $row = intdiv($changedIndex, 10);
    $col = $changedIndex % 10;

    $matchesANeighbour = false;

    for ($rowOffset = -1; $rowOffset <= 1; $rowOffset++) {
        for ($colOffset = -1; $colOffset <= 1; $colOffset++) {
            $neighborRow = $row + $rowOffset;
            $neighborCol = $col + $colOffset;

            if ($neighborRow < 0 || $neighborRow >= 10 || $neighborCol < 0 || $neighborCol >= 10) {
                continue;
            }

            $neighborIndex = ($neighborRow * 10) + $neighborCol;

            if ($neighborIndex !== $changedIndex && $before[$neighborIndex] === $after[$changedIndex]) {
                $matchesANeighbour = true;
            }
        }
    }

    expect($matchesANeighbour)->toBeTrue();
});

test('stops taking steps once the whole board is a single colour', function () {
    $component = Livewire::test(Board::class);
    $component->set('grid', array_fill(0, 100, 'red'));
    $component->set('finished', false);

    $component->call('step');

    expect($component->get('grid'))->toEqual(array_fill(0, 100, 'red'));
    expect($component->get('finished'))->toBeTrue();
});

test('new board resets to an unfinished random grid', function () {
    $component = Livewire::test(Board::class);
    $component->set('grid', array_fill(0, 100, 'red'));
    $component->set('finished', true);

    $component->call('newBoard');

    expect($component->get('finished'))->toBeFalse();
    expect(collect($component->get('grid'))->unique()->count())->toBeGreaterThan(1);
});
