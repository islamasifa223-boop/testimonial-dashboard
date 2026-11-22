<?php

namespace Database\Factories;

use App\Models\Space;
use App\Models\Testimonial;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Testimonial>
 */
class TestimonialFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Testimonial>
     */
    protected $model = Testimonial::class;

    /**
     * Define the model's default state.
     *
     * The moderation flags are not set here: a testimonial is unapproved,
     * unconsented and unhidden until something says otherwise.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'space_id' => Space::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'company_name' => fake()->optional()->company(),
            'social_url' => null,
            'photo_path' => null,
            'body' => Str::random(12).' '.fake()->sentence(12),
            'rating' => fake()->numberBetween(1, 5),
            'consented' => false,
            'approved' => false,
            'hidden' => false,
            'favorite' => false,
            'hearted' => false,
        ];
    }

    /**
     * The customer agreed to be shown publicly.
     */
    public function consented(): static
    {
        return $this->state(fn (array $attributes): array => ['consented' => true]);
    }

    /**
     * The owner approved the testimonial.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes): array => ['approved' => true]);
    }

    /**
     * The owner hid the testimonial.
     */
    public function hidden(): static
    {
        return $this->state(fn (array $attributes): array => ['hidden' => true]);
    }

    /**
     * The testimonial is pinned in the inbox.
     */
    public function favorited(): static
    {
        return $this->state(fn (array $attributes): array => ['favorite' => true]);
    }

    /**
     * The testimonial appears on the Wall of Love.
     */
    public function hearted(): static
    {
        return $this->state(fn (array $attributes): array => ['hearted' => true]);
    }

    /**
     * The testimonial is visible on the Wall of Love.
     */
    public function publiclyVisible(): static
    {
        return $this->consented()->approved();
    }
}
