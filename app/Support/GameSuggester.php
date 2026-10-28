<?php

namespace App\Support;

use App\Models\SteamGame;
use Illuminate\Database\Eloquent\Builder;
use Random\Randomizer;

/**
 * Rule-based "what should I play?" picker. No AI involved: every answer maps
 * to tag weights in config/steam_moods.php, each eligible game is scored by
 * how strongly it carries those tags, and a few games are drawn at random
 * from the top of the list (higher scores are more likely to be drawn).
 */
class GameSuggester
{
    private Randomizer $randomizer;

    public function __construct(?Randomizer $randomizer = null)
    {
        $this->randomizer = $randomizer ?? new Randomizer;
    }

    /**
     * @return array<string, string> question key => default option key
     */
    public static function defaultAnswers(): array
    {
        $answers = [];

        foreach (self::questions() as $key => $question) {
            $answers[$key] = $question['default'];
        }

        return $answers;
    }

    /**
     * @return array<string, array{label: string, default: string, options: array<string, array<string, mixed>>}>
     */
    public static function questions(): array
    {
        /** @var array<string, array{label: string, default: string, options: array<string, array<string, mixed>>}> */
        return config('steam_moods.questions', []);
    }

    /**
     * Single-player rule: a game is only dropped when Steam marks it as
     * multiplayer and NOT single-player (Dota 2, Among Us...). Anything that
     * also offers single-player stays in, even with online multiplayer
     * (e.g. Ticket to Ride vs computer players), as do games with no
     * player-mode category at all (some older games list none) and games
     * whose details haven't been fetched yet.
     *
     * Store type 'advertising' is how Steam labels some delisted games.
     */
    public static function isEligible(SteamGame $game): bool
    {
        if (! in_array($game->store_type, [null, 'game', 'unavailable', 'advertising'], true)) {
            return false;
        }

        $categories = $game->categories ?? [];

        if (in_array('Single-player', $categories, true) || in_array('Singleplayer', $game->tags ?? [], true)) {
            return true;
        }

        return array_intersect($categories, ['Multi-player', 'MMO', 'Online PvP', 'Online Co-op']) === [];
    }

    /**
     * @param  array<string, string>  $answers
     * @param  bool  $hideCompleted  leave out games with every achievement unlocked
     *                               (ignored when the achievements answer asks for them)
     * @return list<array{game: SteamGame, score: float, reasons: list<string>}>
     */
    public function suggest(array $answers, int $count = 3, bool $hideCompleted = true): array
    {
        $answers = $this->normaliseAnswers($answers);
        $weights = $this->tagWeights($answers);

        /** @var string|null $achievementFilter */
        $achievementFilter = self::questions()['achievements']['options'][$answers['achievements'] ?? 'any']['achievements'] ?? null;

        $scored = [];

        foreach ($this->candidates($answers)->get() as $game) {
            if (! self::isEligible($game) || ! $this->passesAchievementFilter($game, $achievementFilter, $hideCompleted)) {
                continue;
            }

            $scored[] = $this->score($game, $weights);
        }

        if ($scored === []) {
            return [];
        }

        // Shuffle first so equal scores don't always come out in the same order.
        $scored = $this->randomizer->shuffleArray($scored);
        usort($scored, fn (array $a, array $b) => $b['score'] <=> $a['score']);

        $pool = array_slice($scored, 0, max($count, (int) config('steam_moods.pool_size', 15)));

        return $this->weightedPick($pool, $count);
    }

    private function passesAchievementFilter(SteamGame $game, ?string $filter, bool $hideCompleted): bool
    {
        if ($filter === 'complete') {
            return $game->isFullyAchieved();
        }

        if ($hideCompleted && $game->isFullyAchieved()) {
            return false;
        }

        $percent = $game->achievementPercent();

        return match ($filter) {
            'incomplete' => $percent !== null && $percent < 100,
            'nearly' => $percent !== null && $percent < 100 && $percent >= (float) config('steam_moods.nearly_there_percent', 75),
            default => true,
        };
    }

    /**
     * @param  array<string, string>  $answers
     * @return array<string, string>
     */
    private function normaliseAnswers(array $answers): array
    {
        $normalised = [];

        foreach (self::questions() as $key => $question) {
            $answer = $answers[$key] ?? null;
            $normalised[$key] = is_string($answer) && isset($question['options'][$answer]) ? $answer : $question['default'];
        }

        return $normalised;
    }

    /**
     * @param  array<string, string>  $answers
     * @return array<string, float> tag => combined weight across all answers
     */
    private function tagWeights(array $answers): array
    {
        $weights = [];

        foreach ($answers as $key => $answer) {
            /** @var array<string, int|float> $tags */
            $tags = self::questions()[$key]['options'][$answer]['tags'] ?? [];

            foreach ($tags as $tag => $weight) {
                $weights[$tag] = ($weights[$tag] ?? 0) + $weight;
            }
        }

        return $weights;
    }

    /**
     * @param  array<string, string>  $answers
     * @return Builder<SteamGame>
     */
    private function candidates(array $answers): Builder
    {
        $familiarity = self::questions()['familiarity']['options'][$answers['familiarity'] ?? 'any'] ?? [];

        return SteamGame::query()
            ->where('never_suggest', false)
            ->where(fn (Builder $query) => $query->whereNull('skipped_until')->orWhere('skipped_until', '<=', now()))
            ->when(isset($familiarity['min_minutes']), fn (Builder $query) => $query->where('playtime_forever', '>=', $familiarity['min_minutes']))
            ->when(isset($familiarity['max_minutes']), fn (Builder $query) => $query->where('playtime_forever', '<=', $familiarity['max_minutes']));
    }

    /**
     * @param  array<string, float>  $weights
     * @return array{game: SteamGame, score: float, reasons: list<string>}
     */
    private function score(SteamGame $game, array $weights): array
    {
        $score = 0.0;
        $contributions = [];

        foreach (array_values($game->tags ?? []) as $index => $tag) {
            $weight = $weights[$tag] ?? 0;

            if ($weight == 0) {
                continue;
            }

            $points = $weight * $this->tagStrength($index + 1);
            $score += $points;

            if ($points > 0) {
                $contributions[$tag] = $points;
            }
        }

        arsort($contributions);

        return [
            'game' => $game,
            'score' => round($score, 2),
            'reasons' => array_slice(array_keys($contributions), 0, 3),
        ];
    }

    private function tagStrength(int $position): float
    {
        /** @var array<int, float> $bands */
        $bands = config('steam_moods.tag_strength', []);
        ksort($bands);

        foreach ($bands as $upTo => $strength) {
            if ($position <= $upTo) {
                return (float) $strength;
            }
        }

        return 0.0;
    }

    /**
     * Draw $count games without replacement; a higher score means a better
     * chance, but anything in the pool can come up.
     *
     * @param  list<array{game: SteamGame, score: float, reasons: list<string>}>  $pool
     * @return list<array{game: SteamGame, score: float, reasons: list<string>}>
     */
    private function weightedPick(array $pool, int $count): array
    {
        if ($pool === []) {
            return [];
        }

        $lowest = min(array_column($pool, 'score'));
        $picked = [];

        while ($pool !== [] && count($picked) < $count) {
            $tickets = array_map(fn (array $entry) => (int) round(($entry['score'] - $lowest + 1) * 100), $pool);
            $draw = $this->randomizer->getInt(1, array_sum($tickets));

            foreach ($tickets as $index => $ticketCount) {
                $draw -= $ticketCount;

                if ($draw <= 0) {
                    $picked[] = $pool[$index];
                    array_splice($pool, $index, 1);

                    break;
                }
            }
        }

        return $picked;
    }
}
