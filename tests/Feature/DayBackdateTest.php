<?php

use App\Livewire\Day\Backdate;
use App\Livewire\Day\Index as Day;
use App\Models\Book;
use App\Models\BookEntry;
use App\Models\HabitActivity;
use App\Models\HabitEntry;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->travelTo(now()->setDate(2026, 11, 7)->setTime(12, 0));
});

test('the page loads, defaults to yesterday and can open straight on books', function () {
    $this->get(route('day.backdate'))->assertOk()->assertSee('Backdate your day');

    Livewire::withQueryParams(['step' => 'books'])
        ->test(Backdate::class)
        ->assertSet('date', '2026-11-06')
        ->assertSet('step', 'books')
        ->assertSee('Any reading?');
});

test('books typed, picked and ticked are logged against the picked date', function () {
    $current = Book::factory()->create(['title' => 'Piranesi']);
    BookEntry::factory()->for($current)->create(['read_on' => '2026-10-30']);

    $component = Livewire::test(Backdate::class)
        ->call('goTo', 'books')
        ->set('date', '2026-11-01')
        ->assertSee('Piranesi')
        ->assertSee('Last read Fri 30 Oct')
        ->call('toggleBook', $current->id)
        ->set('search', 'My Zine')
        ->call('addTypedBook');

    expect(BookEntry::whereDate('read_on', '2026-11-01')->count())->toBe(2)
        ->and(BookEntry::whereDate('read_on', '2026-11-07')->count())->toBe(0);

    $component->call('goTo', 'done')->assertSee('Piranesi')->assertSee('My Zine');
});

test('the recent list only looks back from the picked date', function () {
    $later = Book::factory()->create(['title' => 'Started later']);
    BookEntry::factory()->for($later)->create(['read_on' => '2026-11-05']);

    Livewire::test(Backdate::class)
        ->call('goTo', 'books')
        ->set('date', '2026-11-01')
        ->assertDontSee('Started later');
});

test('a book can be marked finished on an earlier day', function () {
    $book = Book::factory()->create(['title' => 'Piranesi']);
    BookEntry::factory()->for($book)->create(['read_on' => '2026-11-01']);

    Livewire::test(Backdate::class)
        ->call('goTo', 'books')
        ->set('date', '2026-11-03')
        ->call('toggleFinished', $book->id);

    expect($book->fresh()->finished_on->toDateString())->toBe('2026-11-03')
        ->and($book->fresh()->isFinished())->toBeTrue();

    // Gone from today's list, since it was finished before today.
    Livewire::test(Day::class)->call('goTo', 'books')->assertDontSee('Piranesi');
});

test('habits are logged against the picked date too', function () {
    $habit = HabitActivity::factory()->create();

    Livewire::test(Backdate::class)
        ->set('date', '2026-11-02')
        ->call('toggleHabit', $habit->id);

    expect(HabitEntry::sole()->date->toDateString())->toBe('2026-11-02');
});

test('future dates are refused', function () {
    Livewire::test(Backdate::class)
        ->set('date', '2027-01-01')
        ->assertSet('date', '2026-11-07')
        ->call('nextDay')
        ->assertSet('date', '2026-11-07')
        ->call('previousDay')
        ->assertSet('date', '2026-11-06');
});
