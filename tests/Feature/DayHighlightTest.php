<?php

use App\Livewire\Day\Backdate;
use App\Livewire\Day\Index as Day;
use App\Models\DayHighlight;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->travelTo(now()->setDate(2026, 11, 7)->setTime(12, 0));
});

test('the books step moves on to the highlight step', function () {
    Livewire::test(Day::class)
        ->call('goTo', 'books')
        ->assertSee('No reading, on to the highlight')
        ->call('goTo', 'highlight')
        ->assertSee('What was the highlight of today?');
});

test('the highlight is saved for today and shown in the summary', function () {
    Livewire::test(Day::class)
        ->call('goTo', 'highlight')
        ->set('highlight', "  Saw a kingfisher by the river.\n")
        ->call('saveHighlight')
        ->call('goTo', 'done')
        ->assertSet('highlight', 'Saw a kingfisher by the river.')
        ->assertSee('Saw a kingfisher by the river.');

    $highlight = DayHighlight::sole();

    expect($highlight->date->toDateString())->toBe('2026-11-07')
        ->and($highlight->body)->toBe('Saw a kingfisher by the river.');
});

test('editing updates the one row for the day, and clearing it deletes it', function () {
    DayHighlight::create(['date' => '2026-11-07', 'body' => 'First go']);

    $component = Livewire::test(Day::class)
        ->assertSet('highlight', 'First go')
        ->set('highlight', 'Second go');

    expect(DayHighlight::sole()->body)->toBe('Second go');

    $component->set('highlight', '   ');

    expect(DayHighlight::count())->toBe(0);
});

test('a highlight that is too long is refused', function () {
    Livewire::test(Day::class)
        ->set('highlight', str_repeat('a', Day::HIGHLIGHT_MAX_LENGTH + 1))
        ->assertHasErrors('highlight');

    expect(DayHighlight::count())->toBe(0);
});

test('backdating loads and saves the highlight for the chosen date', function () {
    DayHighlight::create(['date' => '2026-11-06', 'body' => 'Yesterday was good']);
    DayHighlight::create(['date' => '2026-11-05', 'body' => 'Two days ago']);

    Livewire::test(Backdate::class)
        ->assertSet('date', '2026-11-06')
        ->assertSet('highlight', 'Yesterday was good')
        ->call('previousDay')
        ->assertSet('highlight', 'Two days ago')
        ->call('previousDay')
        ->assertSet('highlight', '')
        ->set('highlight', 'Three days ago');

    expect(DayHighlight::whereDate('date', '2026-11-04')->sole()->body)->toBe('Three days ago')
        ->and(DayHighlight::count())->toBe(3);
});

test('backdate opens on the date in the URL, and refuses future or invalid ones', function () {
    DayHighlight::create(['date' => '2026-10-01', 'body' => 'An October day']);

    Livewire::withQueryParams(['date' => '2026-10-01', 'step' => 'highlight'])
        ->test(Backdate::class)
        ->assertSet('date', '2026-10-01')
        ->assertSet('step', 'highlight')
        ->assertSet('highlight', 'An October day')
        ->assertSee('What was the highlight of that day?');

    Livewire::withQueryParams(['date' => '2027-01-01'])->test(Backdate::class)->assertSet('date', '2026-11-07');
    Livewire::withQueryParams(['date' => 'nonsense'])->test(Backdate::class)->assertSet('date', '2026-11-07');
});
