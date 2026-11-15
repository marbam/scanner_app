<?php

use App\Livewire\Day\Index as Day;
use App\Models\Book;
use App\Models\BookEntry;
use App\Models\User;
use App\Support\OpenLibrary;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->travelTo(now()->setDate(2026, 11, 7)->setTime(12, 0));
});

function fakeOpenLibrary(): void
{
    Http::fake([
        'openlibrary.org/search.json*' => Http::response(['docs' => [
            ['key' => '/works/OL21745884W', 'title' => 'Project Hail Mary', 'author_name' => ['Andy Weir'], 'first_publish_year' => 2021, 'cover_i' => 11200092],
            ['key' => '/works/OL1W', 'title' => 'Broken', 'author_name' => 'not a list'],
            ['title' => 'No key, skipped'],
        ]]),
    ]);
}

test('books come after films and before done', function () {
    Livewire::test(Day::class)
        ->call('goTo', 'films')
        ->assertSee('No films, on to books')
        ->call('goTo', 'books')
        ->assertSee('Any reading?')
        ->assertSee('No reading, all done');
});

test('picking a book from Open Library logs it for today in one tap', function () {
    fakeOpenLibrary();

    Livewire::test(Day::class)
        ->call('goTo', 'books')
        ->set('search', 'hail mary')
        ->assertSee('Project Hail Mary')
        ->assertSee('Andy Weir')
        ->assertDontSee('No key, skipped')
        ->call('chooseOpenLibraryBook', '/works/OL21745884W')
        ->assertSet('search', '');

    $book = Book::sole();
    expect($book->only(['open_library_key', 'title', 'author', 'year', 'cover_id']))->toBe([
        'open_library_key' => '/works/OL21745884W',
        'title' => 'Project Hail Mary',
        'author' => 'Andy Weir',
        'year' => 2021,
        'cover_id' => 11200092,
    ])->and(BookEntry::sole()->read_on->toDateString())->toBe('2026-11-07');

    Http::assertSent(fn ($request) => $request->hasHeader('User-Agent', OpenLibrary::USER_AGENT));
});

test('a title can be added as typed, and typing it again reuses the same book', function () {
    fakeOpenLibrary();

    Livewire::test(Day::class)
        ->call('goTo', 'books')
        ->set('search', '  My   Zine ')
        ->assertSee('Add “My Zine” as typed')
        ->call('addTypedBook')
        ->set('search', 'my zine')
        ->call('addTypedBook');

    expect(Book::sole()->only(['title', 'open_library_key']))->toBe(['title' => 'My Zine', 'open_library_key' => null])
        ->and(BookEntry::count())->toBe(1); // once per book per day
});

test('recently read books can be ticked and unticked, covering the last 30 days', function () {
    $current = Book::factory()->create(['title' => 'Piranesi']);
    BookEntry::factory()->for($current)->create(['read_on' => '2026-11-05']);
    $abandoned = Book::factory()->create(['title' => 'Ulysses']);
    BookEntry::factory()->for($abandoned)->create(['read_on' => '2026-10-07']); // 31 days ago

    $component = Livewire::test(Day::class)
        ->call('goTo', 'books')
        ->assertSee('Piranesi')
        ->assertSee('Last read Thu 5 Nov')
        ->assertDontSee('Ulysses');

    $logged = fn () => array_column($component->get('recentBooks'), 'logged');

    $component->call('toggleBook', $current->id);
    expect($logged())->toBe([true])
        ->and(BookEntry::whereDate('read_on', '2026-11-07')->count())->toBe(1);

    $component->call('toggleBook', $current->id);
    expect($logged())->toBe([false])
        ->and(BookEntry::count())->toBe(2); // older entries untouched
});

test('history search matches title or author and comes before Open Library', function () {
    fakeOpenLibrary();
    $book = Book::factory()->create(['title' => 'The Martian', 'author' => 'Andy Weir']);
    BookEntry::factory()->for($book)->create(['read_on' => '2026-09-01']);

    Livewire::test(Day::class)
        ->call('goTo', 'books')
        ->set('search', 'weir')
        ->assertSeeInOrder(['From your books', 'The Martian', 'From Open Library', 'Project Hail Mary'])
        ->call('chooseBook', $book->id);

    expect(BookEntry::whereDate('read_on', '2026-11-07')->sole()->book_id)->toBe($book->id);
});

test('books read today show in the summary', function () {
    $book = Book::factory()->create(['title' => 'Piranesi', 'author' => 'Susanna Clarke']);
    BookEntry::factory()->for($book)->create(['read_on' => '2026-11-07']);

    Livewire::test(Day::class)->call('goTo', 'done')->assertSee('Piranesi')->assertSee('Susanna Clarke');
});

test('an Open Library outage still allows adding as typed', function () {
    Http::fake(['openlibrary.org/*' => Http::response([], 503)]);

    Livewire::test(Day::class)
        ->call('goTo', 'books')
        ->set('search', 'anything')
        ->assertSee('Open Library search isn\'t responding right now.')
        ->call('addTypedBook');

    expect(Book::sole()->title)->toBe('anything');
});
