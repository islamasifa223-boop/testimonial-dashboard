<?php

namespace App\Enums;

/**
 * The visibility of a testimonial.
 *
 * A testimonial is publicly visible only when it is approved, not hidden and
 * explicitly consented to public sharing. These are three independent
 * booleans, so the rule is expressed once, here, rather than being repeated
 * in every query and view that needs it.
 *
 * The public Wall is a stricter surface than public visibility alone: a
 * testimonial only appears there when it is additionally hearted. The four
 * flags remain independent of one another.
 */
enum TestimonialVisibility
{
    /**
     * Whether the three visibility flags combine into public visibility.
     */
    public static function isPubliclyVisible(bool $approved, bool $hidden, bool $consented): bool
    {
        return $approved && ! $hidden && $consented;
    }

    /**
     * Whether the three visibility flags combine into public visibility.
     *
     * This is the rule a widget uses. It is the same rule the Wall uses,
     * without the heart: the heart selects a testimonial for the Wall,
     * it does not decide whether the testimonial may be shown at all.
     *
     * The rule is written here once, in PHP. The query that reads the
     * matching rows writes the same three conditions in SQL, because a
     * query cannot call a PHP method, so the two are kept in step by a
     * test that runs them against each other.
     */
    public static function isVisiblePublicly(bool $approved, bool $hidden, bool $consented): bool
    {
        return self::isPubliclyVisible($approved, $hidden, $consented);
    }

    /**
     * Whether a testimonial qualifies for the public Wall.
     *
     * All four conditions must hold: the testimonial is hearted, approved,
     * not hidden, and explicitly consented to public sharing.
     */
    public static function isOnWall(bool $approved, bool $hidden, bool $consented, bool $hearted): bool
    {
        return $hearted && self::isPubliclyVisible($approved, $hidden, $consented);
    }
}
