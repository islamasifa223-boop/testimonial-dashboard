<li class="klw-card">
    @if ($testimonial->photo_path)
        <img
            class="klw-photo"
            src="{{ route('widgets.photos.show', [$widget->public_id, $testimonial->id]) }}"
            alt="{{ $testimonial->name }}"
            width="48"
            height="48"
            loading="lazy"
            decoding="async"
        >
    @endif

    <div class="klw-card-body">
        <p class="klw-name">{{ $testimonial->name }}</p>

        @if ($testimonial->company_name)
            <p class="klw-company">{{ $testimonial->company_name }}</p>
        @endif

        @if ($testimonial->rating)
            <span class="klw-stars" role="img" aria-label="{{ $testimonial->rating }} out of 5 stars">
                @for ($star = 1; $star <= 5; $star++)
                    <span class="klw-star @if ($star > $testimonial->rating) klw-star--empty @endif">
                        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.562.562 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.562.562 0 00.475-.345L11.48 3.5z" />
                        </svg>
                    </span>
                @endfor
            </span>
        @endif

        <p class="klw-body-text">{{ $testimonial->body }}</p>
    </div>
</li>
