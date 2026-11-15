<?php

use App\Livewire\Books\History;
use App\Livewire\Day\Index as Day;
use App\Models\Book;
use App\Models\BookEntry;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->travelTo(now()->setDate(2026, 11, 7)->setTime(12, 0));
});

test('finishing a book logs it as read today, can be undone, and drops it from the list from tomorrow', function () {
    $book = Book::factory()->create(['title' => 'Piranesi']);
    BookEntry::factory()->for($book)->create(['read_on' => '2026-11-05']);

    $component = Livewire::test(Day::class)->call('goTo', 'books');
    $item = fn () => $component->get('recentBooks')[0] ?? null;

    $component->call('toggleFinished', $book->id);
    expect($book->fresh()->finished_on->toDateString())->toBe('2026-11-07')
        ->and($item())->toMatchArray(['logged' => true, 'finished' => true]); // still listed today, to allow undo

    $component->call('toggleFinished', $book->id);
    expect($book->fresh()->finished_on)->toBeNull()
        ->and($item())->toMatchArray(['logged' => true, 'finished' => false]);

    $component->call('toggleFinished', $book->id);
    $this->travelTo(now()->addDay());

    Livewire::test(Day::class)->call('goTo', 'books')->assertDontSee('Piranesi');
});

test('reading a finished book again brings it back as a re-read', function () {
    $book = Book::factory()->create(['title' => 'Piranesi', 'finished_on' => '2026-10-20']);
    BookEntry::factory()->for($book)->create(['read_on' => '2026-10-20']);

    $component = Livewire::test(Day::class)->call('goTo', 'books')->assertDontSee('Piranesi');
    expect($book->isFinished())->toBeTrue();

    $component->call('chooseBook', $book->id);

    expect($component->get('recentBooks')[0]['book']->id)->toBe($book->id)
        ->and($book->fresh()->isFinished())->toBeFalse()
        ->and(Book::reading()->pluck('id')->all())->toBe([$book->id])
        ->and(Book::finished()->count())->toBe(0);
});

test('the summary marks books finished today', function () {
    $book = Book::factory()->create(['title' => 'Piranesi']);

    Livewire::test(Day::class)
        ->call('toggleFinished', $book->id)
        ->call('goTo', 'done')
        ->assertSeeInOrder(['Piranesi', 'Finished']);
});

test('the history page lists books with their status, days read and span', function () {
    $reading = Book::factory()->create(['title' => 'Middlemarch', 'author' => 'George Eliot']);
    BookEntry::factory()->for($reading)->create(['read_on' => '2026-11-01']);
    BookEntry::factory()->for($reading)->create(['read_on' => '2026-11-06']);

    $finished = Book::factory()->create(['title' => 'Piranesi', 'finished_on' => '2025-12-30']);
    BookEntry::factory()->for($finished)->create(['read_on' => '2025-12-28']);
    BookEntry::factory()->for($finished)->create(['read_on' => '2025-12-30']);

    $this->get(route('books.history'))->assertOk()->assertSee('Reading history');

    Livewire::test(History::class)
        ->assertSeeInOrder(['Middlemarch', 'George Eliot', 'Read on 2 days', '1 Nov – 6 Nov 2026', 'Piranesi', 'Finished 30 Dec 2025'])
        ->assertSet('stats', ['books' => 2, 'finished' => 1, 'days' => 4])
        ->set('status', 'finished')
        ->assertDontSee('Middlemarch')
        ->assertSee('Piranesi')
        ->set('status', 'reading')
        ->assertSee('Middlemarch')
        ->assertDontSee('Piranesi')
        ->set('status', 'all')
        ->set('year', '2025')
        ->assertDontSee('Middlemarch')
        ->assertSet('stats', ['books' => 1, 'finished' => 1, 'days' => 2])
        ->set('year', '')
        ->set('search', 'eliot')
        ->assertSee('Middlemarch')
        ->assertDontSee('Piranesi');
});

test('the history page ignores unknown filters', function () {
    Livewire::withQueryParams(['status' => 'nonsense', 'year' => 'abc'])
        ->test(History::class)
        ->assertSet('status', 'all')
        ->assertSet('year', '')
        ->assertSee('No books logged yet.');
});
