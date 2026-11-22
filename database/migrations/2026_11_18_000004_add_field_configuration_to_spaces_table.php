<?php

use App\Enums\SpaceField;
use App\Services\SpaceNameNormalizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // ------------------------------------------------------------------
        // Step 1 - PREFLIGHT. Pure reads. If this throws, nothing has changed.
        // ------------------------------------------------------------------
        $conflicts = $this->detectNameConflicts();

        if ($conflicts !== []) {
            $details = [];

            foreach ($conflicts as $conflict) {
                $details[] = sprintf(
                    '  user_id=%d, name_key="%s": spaces #%s (names: %s)',
                    $conflict['user_id'],
                    $conflict['name_key'],
                    implode(', #', $conflict['space_ids']),
                    '"'.implode('", "', $conflict['names']).'"',
                );
            }

            throw new RuntimeException(
                'Cannot add the unique index on (user_id, name_key): the existing spaces '
                ."contain case-insensitively duplicate names within the same account.\n"
                .implode("\n", $details)."\n"
                ."No columns were added and no records were changed.\n"
                .'To resolve each group, rename one of the conflicting spaces so that no two '
                .'spaces in the same account share a name. Deleting a space is not enough: '
                ."space names stay reserved after a soft delete, so the conflict remains.\n"
                .'Only permanently deleting a space - which physically removes its row from '
                .'the database - clears a conflict, and that is destructive and irreversible. '
                .'Renaming is the recommended fix.'
            );
        }

        // ------------------------------------------------------------------
        // Step 2 - SCHEMA. Only reached once the preflight has passed.
        // ------------------------------------------------------------------
        Schema::table('spaces', function (Blueprint $table): void {
            $table->string('name_key')->nullable()->after('name');
            $table->boolean('company_name_enabled')->default(true)->after('rating_enabled');
            $table->boolean('company_name_required')->default(false);
            $table->boolean('social_url_enabled')->default(false);
            $table->boolean('social_url_required')->default(false);
            $table->boolean('photo_enabled')->default(false);
            $table->boolean('photo_required')->default(false);
            $table->json('field_order')->nullable();
        });

        // ------------------------------------------------------------------
        // Step 3 - BACKFILL. Derived columns only; `name` is never touched.
        // ------------------------------------------------------------------
        DB::table('spaces')
            ->orderBy('id')
            ->each(function ($space): void {
                DB::table('spaces')
                    ->where('id', $space->id)
                    ->update([
                        'name_key' => SpaceNameNormalizer::key((string) $space->name),
                        'field_order' => json_encode(SpaceField::defaultOrder()),
                    ]);
            });

        // ------------------------------------------------------------------
        // Step 4 - UNIQUE INDEX. The database-level guarantee.
        // ------------------------------------------------------------------
        Schema::table('spaces', function (Blueprint $table): void {
            $table->unique(['user_id', 'name_key'], 'spaces_user_id_name_key_unique');
        });

        // ------------------------------------------------------------------
        // Step 5 - NOT NULL.
        // ------------------------------------------------------------------
        Schema::table('spaces', function (Blueprint $table): void {
            $table->string('name_key')->nullable(false)->change();
        });
    }

    /**
     * Detect case-insensitively duplicate space names belonging to the same
     * account, using the exact same key the application will use.
     *
     * This runs BEFORE any schema or data change so that a conflict leaves
     * the database completely untouched.
     *
     * @return array<int, array{user_id: int, name_key: string, space_ids: array<int, int>, names: array<int, string>}>
     */
    private function detectNameConflicts(): array
    {
        $groups = [];

        // Read every space, including soft-deleted ones: a soft-deleted name
        // stays reserved, so it still participates in the conflict check.
        DB::table('spaces')
            ->orderBy('id')
            ->select('id', 'user_id', 'name')
            ->each(function ($space) use (&$groups): void {
                $key = SpaceNameNormalizer::key((string) $space->name);

                $groups[$space->user_id.'|'.$key][] = [
                    'id' => (int) $space->id,
                    'name' => (string) $space->name,
                ];
            });

        $conflicts = [];

        foreach ($groups as $composite => $spaces) {
            if (count($spaces) < 2) {
                continue;
            }

            [$userId, $nameKey] = explode('|', $composite, 2);

            $conflicts[] = [
                'user_id' => (int) $userId,
                'name_key' => $nameKey,
                'space_ids' => array_column($spaces, 'id'),
                'names' => array_column($spaces, 'name'),
            ];
        }

        return $conflicts;
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('spaces', function (Blueprint $table): void {
            $table->dropUnique('spaces_user_id_name_key_unique');
            $table->dropColumn([
                'name_key',
                'company_name_enabled',
                'company_name_required',
                'social_url_enabled',
                'social_url_required',
                'photo_enabled',
                'photo_required',
                'field_order',
            ]);
        });
    }
};
