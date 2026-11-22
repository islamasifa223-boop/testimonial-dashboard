<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\StoreSpaceRequest;
use App\Http\Requests\Dashboard\UpdateSpaceRequest;
use App\Models\Space;
use App\Services\PlanEntitlement;
use App\Services\SpaceFieldConfiguration;
use App\Services\SpaceSlugGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SpaceController extends Controller
{
    public function __construct(
        private readonly PlanEntitlement $entitlement,
        private readonly SpaceSlugGenerator $slugs,
        private readonly SpaceFieldConfiguration $fields,
    ) {}

    /**
     * List the spaces owned by the account.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        return view('dashboard.spaces.index', [
            'spaces' => $user->spaces()->latest()->get(),
            'spacesUsed' => $this->entitlement->spacesUsed($user),
            'spacesLimit' => $this->entitlement->spacesLimit($user),
            'spacesRemaining' => $this->entitlement->spacesRemaining($user),
            'canCreateSpace' => $this->entitlement->canCreateSpace($user),
        ]);
    }

    /**
     * Show the form for creating a space.
     */
    public function create(Request $request): View
    {
        $this->entitlement->assertCanCreateSpace($request->user());

        return view('dashboard.spaces.create');
    }

    /**
     * Store a newly created space.
     */
    public function store(StoreSpaceRequest $request): RedirectResponse
    {
        $user = $request->user();

        $this->entitlement->assertCanCreateSpace($user);

        $attributes = $request->spaceAttributes();

        // The field columns are derived from the configuration, so a field
        // that is switched off can never stay marked as required.
        $attributes = array_merge(
            $attributes,
            $this->fields->toColumns($this->fields->fromInput($request->validated())),
        );

        $space = new Space;

        // The owner is set explicitly on the relation, never through the
        // attributes: user_id is not fillable, so it can never be taken from
        // the submitted form.
        $space->user()->associate($user);

        $space = $this->slugs->createFor($space, $attributes);

        $space->load('user');

        return redirect()
            ->route('dashboard.spaces.created', $space)
            ->with('status', 'Your space is ready to share.');
    }

    /**
     * Show the space that was just created.
     */
    public function created(Request $request, Space $space): View
    {
        $this->authorizeSpace($request, $space);

        return view('dashboard.spaces.created', ['space' => $space]);
    }

    /**
     * Show a single space.
     */
    public function show(Request $request, Space $space): View
    {
        $this->authorizeSpace($request, $space);

        return view('dashboard.spaces.show', ['space' => $space]);
    }

    /**
     * Show the form for editing a space.
     */
    public function edit(Request $request, Space $space): View
    {
        $this->authorizeSpace($request, $space);

        return view('dashboard.spaces.edit', ['space' => $space]);
    }

    /**
     * Update a space.
     */
    public function update(UpdateSpaceRequest $request, Space $space): RedirectResponse
    {
        $this->authorizeSpace($request, $space);

        $attributes = $request->spaceAttributes();

        $attributes = array_merge(
            $attributes,
            $this->fields->toColumns($this->fields->fromInput($request->validated())),
        );

        $space->fill($attributes);
        $space->save();

        return redirect()
            ->route('dashboard.spaces.show', $space)
            ->with('status', 'Your space has been updated.');
    }

    /**
     * Delete a space.
     */
    public function destroy(Request $request, Space $space): RedirectResponse
    {
        $this->authorizeSpace($request, $space);

        $space->delete();

        return redirect()
            ->route('dashboard.spaces.index')
            ->with('status', 'Your space has been deleted.');
    }

    /**
     * Make sure the space belongs to the signed-in account.
     *
     * The space is resolved through the user's own relation so a space
     * owned by another account is never found, and the request is
     * rejected with a 404 rather than a 403.
     */
    private function authorizeSpace(Request $request, Space $space): void
    {
        abort_unless($request->user()->spaces()->whereKey($space->getKey())->exists(), 404);
    }
}
