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
        Schema::create('testimonials', function (Blueprint $table) {
            $table->id();

            // The space is the only owner of a testimonial. Cascading on
            // delete removes the testimonials with their space.
            $table->foreignId('space_id')->constrained()->cascadeOnDelete();

            // The customer supplied fields.
            $table->string('name');
            $table->string('email');
            $table->string('company_name')->nullable();
            $table->string('social_url')->nullable();

            // A path on the private local disk, never a public URL.
            $table->string('photo_path')->nullable();

            $table->text('body');

            // The rating is optional; the space decides whether it collects
            // one at all.
            $table->unsignedTinyInteger('rating')->nullable();

            // The three flags that together decide public visibility.
            $table->boolean('consented')->default(false);
            $table->boolean('approved')->default(false);
            $table->boolean('hidden')->default(false);

            // The two separate owner actions. A favourite is pinned in the
            // inbox; a hearted testimonial appears on the Wall of Love.
            $table->boolean('favorite')->default(false);
            $table->boolean('hearted')->default(false);

            $table->timestamps();

            // The inbox listing, newest first.
            $table->index(['space_id', 'created_at']);

            // The Wall of Love listing and the moderation filters.
            $table->index(['space_id', 'approved', 'hidden']);

            // The favourites-first inbox ordering.
            $table->index(['space_id', 'favorite']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('testimonials');
    }
};
