<?php

namespace App\Livewire\PlanningApplications;

use App\Models\PlanningApplication;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Bristol adverts')]
class Index extends Component
{
    /**
     * @return Collection<int, PlanningApplication>
     */
    #[Computed]
    public function applications(): Collection
    {
        return PlanningApplication::query()
            ->orderByDesc('reference')
            ->get();
    }

    public function toggleViewed(int $planningApplicationId): void
    {
        $planningApplication = PlanningApplication::findOrFail($planningApplicationId);

        $planningApplication->update(['viewed' => ! $planningApplication->viewed]);

        unset($this->applications);
    }

    public function toggleScanEnabled(): void
    {
        $user = auth()->user();

        $user->forceFill(['bristol_adverts_scan_enabled' => ! $user->bristol_adverts_scan_enabled])->save();
    }
}
