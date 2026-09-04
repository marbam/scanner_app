<?php

use App\Livewire\Amoeba\Board;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('mounts a 7x7 grid with two red and two blue corners and nothing else placed', function () {
    $component = Livewire::test(Board::class);
    $grid = $component->get('grid');

    expect($grid)->toHaveCount(49);

    $counts = collect($grid)->countBy();

    expect($counts->get('red'))->toBe(2);
    expect($counts->get('blue'))->toBe(2);
    expect($counts->get(null))->toBe(45);

    foreach ([0, 6, 42, 48] as $corner) {
        expect($grid[$corner])->not->toBeNull();
    }
});

test('clicking a red piece with legal moves selects it and highlights its destinations', function () {
    $grid = array_fill(0, 49, null);
    $grid[0] = 'red';

    $component = Livewire::test(Board::class)
        ->set('grid', $grid)
        ->set('turn', 'red')
        ->set('calculating', false)
        ->call('handleClick', 0);

    expect($component->get('selectedIndex'))->toBe(0);
    expect($component->get('validDestinations'))->toContain(1, 7, 8, 2, 14, 16);
});

test('a copy move adds a new red piece and converts adjacent blue pieces without clearing the origin', function () {
    $grid = array_fill(0, 49, null);
    $grid[0] = 'red';
    $grid[1] = 'blue';
    $grid[48] = 'blue';

    $component = Livewire::test(Board::class)
        ->set('grid', $grid)
        ->set('turn', 'red')
        ->set('calculating', false)
        ->call('handleClick', 0)
        ->call('handleClick', 8);

    $after = $component->get('grid');

    expect($after[0])->toBe('red');
    expect($after[8])->toBe('red');
    expect($after[1])->toBe('red');
    expect($component->get('selectedIndex'))->toBeNull();
    expect($component->get('turn'))->toBe('blue');
    expect($component->get('calculating'))->toBeTrue();
});

test('a jump move clears the origin square', function () {
    $grid = array_fill(0, 49, null);
    $grid[0] = 'red';
    $grid[6] = 'blue';

    $component = Livewire::test(Board::class)
        ->set('grid', $grid)
        ->set('turn', 'red')
        ->set('calculating', false)
        ->call('handleClick', 0)
        ->call('handleClick', 14);

    $after = $component->get('grid');

    expect($after[0])->toBeNull();
    expect($after[14])->toBe('red');
});

test('eliminating the opponent ends the game', function () {
    $grid = array_fill(0, 49, null);
    $grid[0] = 'red';
    $grid[1] = 'blue';

    $component = Livewire::test(Board::class)
        ->set('grid', $grid)
        ->set('turn', 'red')
        ->set('calculating', false)
        ->call('handleClick', 0)
        ->call('handleClick', 8);

    expect($component->get('finished'))->toBeTrue();
    expect($component->get('result'))->toBe('red');
});

test('filling the last empty square ends the game in a draw even though colours differ in count', function () {
    $grid = array_fill(0, 49, 'blue');
    $grid[0] = 'red';
    $grid[8] = null;

    $component = Livewire::test(Board::class)
        ->set('grid', $grid)
        ->set('turn', 'red')
        ->set('calculating', false)
        ->call('handleClick', 0)
        ->call('handleClick', 8);

    $after = $component->get('grid');

    expect($after)->not->toContain(null);
    expect(collect($after)->countBy()->get('red'))->toBeGreaterThan(0);
    expect(collect($after)->countBy()->get('blue'))->toBeGreaterThan(0);
    expect($component->get('finished'))->toBeTrue();
    expect($component->get('result'))->toBe('draw');
});

test('the computer only moves when it is calculating and it is blues turn', function () {
    $grid = array_fill(0, 49, null);
    $grid[0] = 'blue';
    $grid[1] = 'red';
    $grid[24] = 'red';

    $component = Livewire::test(Board::class)
        ->set('grid', $grid)
        ->set('turn', 'blue')
        ->set('calculating', false)
        ->call('computerMove');

    expect($component->get('grid'))->toEqual($grid);

    $component->set('calculating', true)->call('computerMove');

    $after = $component->get('grid');

    expect($after)->not->toEqual($grid);
    expect($component->get('calculating'))->toBeFalse();
    expect($component->get('turn'))->toBe('red');
});

test('blue picks the move that converts the most red pieces available to it', function () {
    $grid = array_fill(0, 49, null);
    $grid[24] = 'blue';
    $grid[16] = 'red';
    $grid[17] = 'red';
    $grid[18] = 'red';

    $component = Livewire::test(Board::class)
        ->set('grid', $grid)
        ->set('turn', 'blue')
        ->set('calculating', true)
        ->call('computerMove');

    $after = $component->get('grid');

    expect($after[16])->toBe('blue');
    expect($after[17])->toBe('blue');
    expect($after[18])->toBe('blue');
});

test('new game resets to a fresh, unfinished, randomised board', function () {
    $grid = array_fill(0, 49, 'red');

    $component = Livewire::test(Board::class)
        ->set('grid', $grid)
        ->set('finished', true)
        ->set('result', 'red')
        ->call('newGame');

    $after = $component->get('grid');

    expect($component->get('finished'))->toBeFalse();
    expect($component->get('result'))->toBeNull();
    expect($component->get('selectedIndex'))->toBeNull();
    expect(collect($after)->countBy()->get('red'))->toBe(2);
    expect(collect($after)->countBy()->get('blue'))->toBe(2);
});
