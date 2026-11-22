<?php

namespace Database\Factories;

use App\Enums\SpaceField;
use App\Enums\SpaceTheme;
use App\Models\Space;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Space>
 */
class SpaceFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Space>
     */
    protected $model = Space::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'user_id' => User::factory(),
            'name' => Str::title($name),
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)),
            'title' => Str::title($name).' Testimonials',
            'subtitle' => 'What our customers say about us.',
            'request' => 'Share your experience with us.',
            'theme' => SpaceTheme::MinimalLight,
            'rating_enabled' => false,
            'company_name_enabled' => true,
            'company_name_required' => false,
            'social_url_enabled' => false,
            'social_url_required' => false,
            'photo_enabled' => false,
            'photo_required' => false,
            'field_order' => SpaceField::defaultOrder(),
        ];
    }
}
