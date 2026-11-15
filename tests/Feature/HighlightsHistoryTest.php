<?php

use App\Livewire\Highlights\History;
use App\Models\DayHighlight;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->travelTo(now()->setDate(2026, 11, 15)->setTime(12, 0));
});

test('guests are redirected to the login page', function () {
    auth()->logout();

    $this->get(route('highlights.history'))->assertRedirect(route('login'));
});

test('the page lists every highlight, newest first', function () {
    DayHighlight::create(['date' => '2025-06-01', 'body' => 'Older one']);
    DayHighlight::create(['date' => '2026-11-14', 'body' => 'Newer one']);

    $this->get(route('highlights.history'))->assertOk()->assertSee('Highlights');

    Livewire::test(History::class)
        ->assertSeeInOrder(['Saturday 14 November 2026', 'Newer one', 'Sunday 1 June 2025', 'Older one'])
        ->assertSee('2 highlights');
});

test('highlights can be searched and filtered by year', function () {
    DayHighlight::create(['date' => '2025-06-01', 'body' => 'Swimming in the sea']);
    DayHighlight::create(['date' => '2026-07-01', 'body' => 'Sea views from the train']);
    DayHighlight::create(['date' => '2026-11-14', 'body' => 'A good pub lunch']);

    Livewire::test(History::class)
        ->set('search', 'sea')
        ->assertSee('Swimming in the sea')
        ->assertSee('Sea views from the train')
        ->assertDontSee('A good pub lunch')
        ->set('year', '2026')
        ->assertDontSee('Swimming in the sea')
        ->assertSee('Sea views from the train')
        ->assertSee('1 highlight');
});

test('an invalid year falls back to all time, and an empty page says so', function () {
    Livewire::withQueryParams(['year' => 'nonsense'])
        ->test(History::class)
        ->assertSet('year', '')
        ->assertSee('No highlights written yet.');
});

test('each highlight links to its day on the backdate page', function () {
    DayHighlight::create(['date' => '2026-10-01', 'body' => 'An October day']);

    Livewire::test(History::class)
        ->assertSeeHtml(e(route('day.backdate', ['date' => '2026-10-01', 'step' => 'highlight'])));
});
