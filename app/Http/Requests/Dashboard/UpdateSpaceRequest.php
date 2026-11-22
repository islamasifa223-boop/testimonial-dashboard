<?php

namespace App\Http\Requests\Dashboard;

use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class UpdateSpaceRequest extends SpaceRequest
{
    /**
     * The name must be unique among the spaces owned by the account.
     *
     * The space being edited is ignored so it does not collide with
     * itself.
     */
    protected function nameRule(User $user): Unique
    {
        return Rule::unique('spaces', 'name_key')
            ->where('user_id', $user->id)
            ->ignore($this->route('space'));
    }
}
