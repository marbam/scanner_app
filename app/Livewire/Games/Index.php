<?php

namespace App\Livewire\Games;

use App\Models\SteamGame;
use App\Support\GameSuggester;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('What to play')]
class Index extends Component
{
    /** @var array<string, string> question key => chosen option key */
    public array $answers = [];

    /**
     * The current picks, in order. Reasons are the tags that matched.
     *
     * @var list<array{id: int, reasons: list<string>}>
     */
    public array $suggestions = [];

    public bool $asked = false;

    /** Leave out games with every achievement unlocked (rarely worth revisiting). */
    public bool $hideCompleted = true;

    public function mount(): void
    {
        $this->answers = GameSuggester::defaultAnswers();
    }

    public function choose(string $question, string $option): void
    {
        if (isset(GameSuggester::questions()[$question]['options'][$option])) {
            $this->answers[$question] = $option;
        }
    }

    public function suggest(): void
    {
        $this->suggestions = array_map(
            fn (array $pick) => ['id' => $pick['game']->id, 'reasons' => $pick['reasons']],
            (new GameSuggester)->suggest($this->answers, hideCompleted: $this->hideCompleted),
        );

        $this->asked = true;

        unset($this->games);
    }

    public function notTonight(int $gameId): void
    {
        SteamGame::whereKey($gameId)->update([
            'skipped_until' => now()->addDays((int) config('steam_moods.skip_days', 7)),
        ]);

        $this->removeSuggestion($gameId);
    }

    public function neverSuggest(int $gameId): void
    {
        SteamGame::whereKey($gameId)->update(['never_suggest' => true]);

        $this->removeSuggestion($gameId);
        unset($this->hiddenGames);
    }

    public function restore(int $gameId): void
    {
        SteamGame::whereKey($gameId)->update(['never_suggest' => false]);

        unset($this->hiddenGames);
    }

    /**
     * @return Collection<int, SteamGame>
     */
    #[Computed]
    public function games(): Collection
    {
        return SteamGame::findMany(array_column($this->suggestions, 'id'))->keyBy('id');
    }

    /**
     * @return Collection<int, SteamGame>
     */
    #[Computed]
    public function hiddenGames(): Collection
    {
        return SteamGame::where('never_suggest', true)->orderBy('name')->get();
    }

    /**
     * @return array{total: int, pending: int}
     */
    #[Computed]
    public function libraryStats(): array
    {
        return [
            'total' => SteamGame::count(),
            'pending' => SteamGame::whereNull('details_synced_at')->count(),
        ];
    }

    private function removeSuggestion(int $gameId): void
    {
        $this->suggestions = array_values(array_filter(
            $this->suggestions,
            fn (array $suggestion) => $suggestion['id'] !== $gameId,
        ));

        unset($this->games);
    }
}
