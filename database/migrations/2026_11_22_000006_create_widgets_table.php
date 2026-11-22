<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('widgets', function (Blueprint $table) {
            $table->id();

            // The space is the only owner of a widget. Cascading on delete
            // removes the widgets with their space.
            $table->foreignId('space_id')->constrained()->cascadeOnDelete();

            // The identifier the embed URL is built from. It is a random
            // string rather than the primary key, so a widget cannot be
            // found by counting up from one and one account's widget
            // cannot be enumerated from another's.
            $table->string('public_id', 32)->unique();

            // The layout the testimonials are shown in: masonry or
            // carousel. The theme is the colour mode: light or dark.
            $table->string('layout')->default('masonry');
            $table->string('theme')->default('light');

            // The background colour of the widget. A null background
            // means the theme's own default is used, so a widget that
            // was never given a colour does not carry one.
            $table->string('background', 7)->nullable();

            // Whether the carousel moves on its own.
            $table->boolean('animation')->default(true);

            // How many testimonials the widget shows at most.
            $table->unsignedSmallInteger('limit')->default(6);

            $table->timestamps();

            // The list of the widgets of one space.
            $table->index(['space_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('widgets');
    }
};
