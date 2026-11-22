<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Space;
use Illuminate\View\View;

class SpaceController extends Controller
{
    /**
     * Show the public page for a space.
     *
     * The space is looked up by slug rather than route-model bound, so
     * the model's global route key is left untouched for the dashboard
     * routes. A slug that matches no active space - including one that
     * belongs to a soft-deleted space - results in a 404 rather than
     * an error.
     */
    public function show(string $slug): View
    {
        $space = Space::where('slug', $slug)->firstOrFail();

        return view('public.spaces.show', ['space' => $space]);
    }
}
