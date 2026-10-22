<?php

use App\Livewire\Games\Index;
use App\Models\SteamGame;
use App\Models\User;
use App\Support\GameSuggester;
use Livewire\Livewire;
use Random\Engine\Mt19937;
use Random\Randomizer;

function suggestedNames(array $answers, int $count = 3, int $seed = 1): array
{
    $picks = (new GameSuggester(new Randomizer(new Mt19937($seed))))->suggest($answers, $count);

    return array_map(fn (array $pick) => $pick['game']->name, $picks);
}

function singlePlayerGame(string $name, array $tags, array $attributes = []): SteamGame
{
    return SteamGame::factory()->create(array_merge([
        'name' => $name,
        'store_type' => 'game',
        'categories' => ['Single-player', 'Steam Achievements'],
        'tags' => $tags,
        'details_synced_at' => now(),
    ], $attributes));
}

it('keeps single-player games that also have online multiplayer, but drops multiplayer-only ones', function () {
    singlePlayerGame('Ticket to Ride', ['Board Game', 'Strategy'], ['categories' => ['Single-player', 'Multi-player', 'Online PvP']]);
    singlePlayerGame('Online Shooter', ['Action'], ['categories' => ['Multi-player', 'Online PvP']]);

    expect(suggestedNames([], count: 5))->toBe(['Ticket to Ride']);
});

it('keeps older games that list no player-mode category at all', function () {
    singlePlayerGame('Back to the Future: Ep 2', ['Point & Click', 'Adventure'], ['categories' => ['Family Sharing']]);

    expect(suggestedNames([], count: 5))->toBe(['Back to the Future: Ep 2']);
});

it('only suggests games, not demos or tools', function () {
    singlePlayerGame('Real Game', ['Puzzle']);
    singlePlayerGame('A Demo', ['Puzzle'], ['store_type' => 'demo']);

    expect(suggestedNames([], count: 5))->toBe(['Real Game']);
});

it('still includes games whose details have not been fetched yet', function () {
    SteamGame::factory()->create(['name' => 'Pending Game']);

    expect(suggestedNames([], count: 5))->toBe(['Pending Game']);
});

it('ranks chill games above difficult ones when feeling chill', function () {
    singlePlayerGame('Stardew Valley', ['Farming Sim', 'Relaxing', 'Cozy', 'Pixel Graphics']);
    singlePlayerGame('Dark Souls', ['Souls-like', 'Difficult', 'Dark Fantasy']);

    $picks = (new GameSuggester(new Randomizer(new Mt19937(1))))->suggest(['energy' => 'chill'], count: 2);
    $scores = collect($picks)->mapWithKeys(fn (array $pick) => [$pick['game']->name => $pick['score']]);

    expect($scores['Stardew Valley'])->toBeGreaterThan(0)
        ->and($scores['Dark Souls'])->toBeLessThan(0);

    $stardew = collect($picks)->firstWhere(fn (array $pick) => $pick['game']->name === 'Stardew Valley');
    expect($stardew['reasons'])->toBe(['Relaxing', 'Cozy', 'Farming Sim']);
});

it('weights a game\'s top tags more than its minor ones', function () {
    // Same single matching tag, 10th in one list and 1st in the other.
    $unmatched = ['Fantasy', 'Pixel Graphics', '2D', 'Indie', 'Retro', 'Colorful', 'Female Protagonist', 'Funny', 'Great Soundtrack'];
    singlePlayerGame('Minor Puzzle', [...$unmatched, 'Puzzle']);
    singlePlayerGame('Major Puzzle', ['Puzzle', 'Indie']);

    $picks = (new GameSuggester(new Randomizer(new Mt19937(1))))->suggest(['focus' => 'think'], count: 2);
    $scores = collect($picks)->mapWithKeys(fn (array $pick) => [$pick['game']->name => $pick['score']]);

    expect($scores['Major Puzzle'])->toBeGreaterThan($scores['Minor Puzzle']);
});

it('filters by playtime for new, started and favourite games', function () {
    singlePlayerGame('Unplayed', ['Puzzle'], ['playtime_forever' => 0]);
    singlePlayerGame('Dabbled', ['Puzzle'], ['playtime_forever' => 120]);
    singlePlayerGame('Favourite', ['Puzzle'], ['playtime_forever' => 30_000]);

    expect(suggestedNames(['familiarity' => 'new'], count: 5))->toBe(['Unplayed'])
        ->and(suggestedNames(['familiarity' => 'started'], count: 5))->toBe(['Dabbled'])
        ->and(suggestedNames(['familiarity' => 'favourite'], count: 5))->toBe(['Favourite']);
});

it('leaves out never-suggest games and games skipped for tonight', function () {
    singlePlayerGame('Hidden', ['Puzzle'], ['never_suggest' => true]);
    singlePlayerGame('Skipped', ['Puzzle'], ['skipped_until' => now()->addDays(3)]);
    singlePlayerGame('Skip Expired', ['Puzzle'], ['skipped_until' => now()->subDay()]);

    expect(suggestedNames([], count: 5))->toBe(['Skip Expired']);
});

it('ignores unknown answers and falls back to defaults', function () {
    singlePlayerGame('Anything', ['Puzzle']);

    expect(suggestedNames(['energy' => 'nonsense', 'bogus' => 'x']))->toBe(['Anything']);
});

it('draws only from the top of the list', function () {
    config(['steam_moods.pool_size' => 3]);

    foreach (range(1, 3) as $i) {
        singlePlayerGame("Cozy {$i}", ['Relaxing', 'Cozy']);
    }
    foreach (range(1, 5) as $i) {
        singlePlayerGame("Hard {$i}", ['Difficult', 'Souls-like']);
    }

    foreach (range(1, 10) as $seed) {
        expect(suggestedNames(['energy' => 'chill'], count: 3, seed: $seed))
            ->each->toStartWith('Cozy');
    }
});

describe('the What to play page', function () {
    beforeEach(function () {
        $this->actingAs(User::factory()->create());
    });

    it('renders the questions', function () {
        singlePlayerGame('Stardew Valley', ['Relaxing']);

        $this->get(route('games.index'))
            ->assertOk()
            ->assertSee('How are you feeling?')
            ->assertSee('Suggest games');
    });

    it('suggests games for the chosen answers', function () {
        singlePlayerGame('Stardew Valley', ['Relaxing', 'Cozy']);

        Livewire::test(Index::class)
            ->call('choose', 'energy', 'chill')
            ->assertSet('answers.energy', 'chill')
            ->call('suggest')
            ->assertSee('Stardew Valley')
            ->assertSee('Relaxing')
            ->assertSee('steam://run/');
    });

    it('ignores an invalid option', function () {
        Livewire::test(Index::class)
            ->call('choose', 'energy', 'furious')
            ->assertSet('answers.energy', 'engaged');
    });

    it('hides a game for tonight', function () {
        $game = singlePlayerGame('Stardew Valley', ['Relaxing']);

        Livewire::test(Index::class)
            ->call('suggest')
            ->call('notTonight', $game->id)
            ->assertSet('suggestions', []);

        expect($game->refresh()->skipped_until->isFuture())->toBeTrue();
    });

    it('never suggests a game again until restored', function () {
        $game = singlePlayerGame('Rust', ['Survival']);

        $component = Livewire::test(Index::class)
            ->call('suggest')
            ->call('neverSuggest', $game->id)
            ->assertSet('suggestions', [])
            ->assertSee('Never suggested (1)');

        expect($game->refresh()->never_suggest)->toBeTrue();

        $component->call('restore', $game->id);

        expect($game->refresh()->never_suggest)->toBeFalse();
    });
});
