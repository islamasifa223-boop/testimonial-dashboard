<?php

namespace App\Services;

use App\Enums\SpaceField;

/**
 * Translates between the submitted form values and the columns that
 * record which fields a space collects.
 */
class SpaceFieldConfiguration
{
    /**
     * The enabled/required state for every configurable field.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, array{enabled: bool, required: bool}>
     */
    public function fromInput(array $input): array
    {
        $configuration = [];

        foreach (SpaceField::configurableValues() as $field) {
            $configuration[$field] = [
                'enabled' => filter_var(
                    $input[$field.'_enabled'] ?? false,
                    FILTER_VALIDATE_BOOLEAN
                ),
                'required' => filter_var(
                    $input[$field.'_required'] ?? false,
                    FILTER_VALIDATE_BOOLEAN
                ),
            ];
        }

        return $configuration;
    }

    /**
     * The database columns for a configuration.
     *
     * A field that is not enabled can never be required, so the required
     * flag is cleared whenever the field is switched off.
     *
     * @param  array<string, array{enabled: bool, required: bool}>  $configuration
     * @return array<string, bool>
     */
    public function toColumns(array $configuration): array
    {
        $columns = [];

        foreach (SpaceField::configurableValues() as $field) {
            $enabled = (bool) ($configuration[$field]['enabled'] ?? false);
            $required = $enabled && (bool) ($configuration[$field]['required'] ?? false);

            $columns[$field.'_enabled'] = $enabled;
            $columns[$field.'_required'] = $required;
        }

        return $columns;
    }

    /**
     * The configuration as the edit form needs it.
     *
     * @param  array<string, bool>  $columns
     * @return array<string, array{enabled: bool, required: bool}>
     */
    public function toInput(array $columns): array
    {
        $input = [];

        foreach (SpaceField::configurableValues() as $field) {
            $input[$field] = [
                'enabled' => (bool) ($columns[$field.'_enabled'] ?? false),
                'required' => (bool) ($columns[$field.'_required'] ?? false),
            ];
        }

        return $input;
    }
}
