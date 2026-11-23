<?php

use App\Models\Space;
use App\Models\User;
use App\Models\Widget;
use App\Policies\WidgetPolicy;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    seedPlans();
});

test('an owner is allowed to view their own widget', function () {
    $owner = User::factory()->create();
    $widget = Widget::factory()->for(Space::factory()->for($owner))->create();

    $policy = app(WidgetPolicy::class);

    expect($policy->view($owner, $widget))->toBeTrue();
});

test('an owner is allowed to update their own widget', function () {
    $owner = User::factory()->create();
    $widget = Widget::factory()->for(Space::factory()->for($owner))->create();

    $policy = app(WidgetPolicy::class);

    expect($policy->update($owner, $widget))->toBeTrue();
});

test('an owner is allowed to delete their own widget', function () {
    $owner = User::factory()->create();
    $widget = Widget::factory()->for(Space::factory()->for($owner))->create();

    $policy = app(WidgetPolicy::class);

    expect($policy->delete($owner, $widget))->toBeTrue();
});

test('another user is not allowed to view the widget', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $widget = Widget::factory()->for(Space::factory()->for($owner))->create();

    $policy = app(WidgetPolicy::class);

    expect($policy->view($other, $widget))->toBeFalse();
});

test('another user is not allowed to update the widget', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $widget = Widget::factory()->for(Space::factory()->for($owner))->create();

    $policy = app(WidgetPolicy::class);

    expect($policy->update($other, $widget))->toBeFalse();
});

test('another user is not allowed to delete the widget', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $widget = Widget::factory()->for(Space::factory()->for($owner))->create();

    $policy = app(WidgetPolicy::class);

    expect($policy->delete($other, $widget))->toBeFalse();
});

test('a widget of a soft deleted space belongs to nobody', function () {
    $owner = User::factory()->create();
    $space = Space::factory()->for($owner)->create();
    $widget = Widget::factory()->for($space)->create();

    $space->delete();

    $policy = app(WidgetPolicy::class);

    expect($policy->view($owner, $widget))->toBeFalse()
        ->and($policy->update($owner, $widget))->toBeFalse()
        ->and($policy->delete($owner, $widget))->toBeFalse();
});

test('the widget policy is registered for the widget model', function () {
    $owner = User::factory()->create();
    $widget = Widget::factory()->for(Space::factory()->for($owner))->create();

    expect(Gate::forUser($owner)->allows('view', $widget))->toBeTrue()
        ->and(Gate::forUser($owner)->allows('update', $widget))->toBeTrue()
        ->and(Gate::forUser($owner)->allows('delete', $widget))->toBeTrue();
});

test('the widget policy denies another account', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $widget = Widget::factory()->for(Space::factory()->for($owner))->create();

    expect(Gate::forUser($other)->denies('view', $widget))->toBeTrue()
        ->and(Gate::forUser($other)->denies('update', $widget))->toBeTrue()
        ->and(Gate::forUser($other)->denies('delete', $widget))->toBeTrue();
});
