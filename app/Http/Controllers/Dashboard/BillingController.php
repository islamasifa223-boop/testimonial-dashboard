<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\PlanName;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Services\PlanEntitlement;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BillingController extends Controller
{
    public function __construct(private readonly PlanEntitlement $entitlement) {}

    /**
     * Show the billing page with the current plan and real usage.
     */
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        return view('dashboard.billing', [
            'currentPlan' => $user->plan,
            'spacesUsed' => $this->entitlement->spacesUsed($user),
            'spacesLimit' => $this->entitlement->spacesLimit($user),
            'plans' => Plan::query()->orderBy('price_monthly_cents')->get(),
            'proFeatures' => Plan::query()
                ->where('name', PlanName::Pro)
                ->first()
                ?->features ?? [],
        ]);
    }
}
