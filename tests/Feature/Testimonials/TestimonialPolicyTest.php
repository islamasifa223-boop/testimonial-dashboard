<?php

use App\Models\Space;
use App\Models\Testimonial;
use App\Models\User;
use App\Policies\TestimonialPolicy;
use Illuminate\Support\Facades\Gate;

test('an owner is allowed to view their own testimonial', function () {
    $owner = User::factory()->create();
    $space = Space::factory()->for($owner)->create();
    $testimonial = Testimonial::factory()->for($space)->create();

    $policy = app(TestimonialPolicy::class);

    expect($policy->view($owner, $testimonial))->toBeTrue();
});

test('an owner is allowed to update their own testimonial', function () {
    $owner = User::factory()->create();
    $space = Space::factory()->for($owner)->create();
    $testimonial = Testimonial::factory()->for($space)->create();

    $policy = app(TestimonialPolicy::class);

    expect($policy->update($owner, $testimonial))->toBeTrue();
});

test('an owner is allowed to delete their own testimonial', function () {
    $owner = User::factory()->create();
    $space = Space::factory()->for($owner)->create();
    $testimonial = Testimonial::factory()->for($space)->create();

    $policy = app(TestimonialPolicy::class);

    expect($policy->delete($owner, $testimonial))->toBeTrue();
});

test('another user is not allowed to view the testimonial', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $space = Space::factory()->for($owner)->create();
    $testimonial = Testimonial::factory()->for($space)->create();

    $policy = app(TestimonialPolicy::class);

    expect($policy->view($other, $testimonial))->toBeFalse();
});

test('another user is not allowed to update the testimonial', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $space = Space::factory()->for($owner)->create();
    $testimonial = Testimonial::factory()->for($space)->create();

    $policy = app(TestimonialPolicy::class);

    expect($policy->update($other, $testimonial))->toBeFalse();
});

test('another user is not allowed to delete the testimonial', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $space = Space::factory()->for($owner)->create();
    $testimonial = Testimonial::factory()->for($space)->create();

    $policy = app(TestimonialPolicy::class);

    expect($policy->delete($other, $testimonial))->toBeFalse();
});

test('the testimonial policy is registered for the testimonial model', function () {
    $owner = User::factory()->create();
    $space = Space::factory()->for($owner)->create();
    $testimonial = Testimonial::factory()->for($space)->create();

    expect(Gate::forUser($owner)->allows('view', $testimonial))->toBeTrue()
        ->and(Gate::forUser($owner)->allows('update', $testimonial))->toBeTrue()
        ->and(Gate::forUser($owner)->allows('delete', $testimonial))->toBeTrue();
});

test('the testimonial policy denies another account', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $space = Space::factory()->for($owner)->create();
    $testimonial = Testimonial::factory()->for($space)->create();

    expect(Gate::forUser($other)->denies('view', $testimonial))->toBeTrue()
        ->and(Gate::forUser($other)->denies('update', $testimonial))->toBeTrue()
        ->and(Gate::forUser($other)->denies('delete', $testimonial))->toBeTrue();
});
