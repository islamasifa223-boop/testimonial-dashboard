{{--
    The Kudosly landing page.

    This view is a standalone page. It carries its own document, because
    the guest layout is built around a centred sign-in card and does not
    fit a marketing page.

    The page is not linked from the route table: the root path redirects
    to the sign-in page or to the dashboard, depending on whether the
    visitor is signed in. The file is kept as the reference page for the
    product, and it is rendered directly by anyone who wants to look at
    it.

    Everything here is built from the shared Tailwind tokens in
    resources/css/app.css, so the page follows the same colour scheme as
    the rest of the application.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ config('app.name', 'Kudosly') }} — Collect customer testimonials with style</title>

    <meta name="description" content="Kudosly is a minimal platform for collecting, managing and publishing customer testimonials. Create a branded space, share the link, and embed the good ones on your site.">

    @fonts

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-surface text-ink antialiased">
    <div class="flex min-h-full flex-col">

        {{-- The skip link is the first thing in the tab order, so that a
             keyboard user can jump straight past the navigation. It is
             invisible until it is focused. --}}
        <a
            href="#main"
            class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-brand-600 focus:px-4 focus:py-2 focus:text-sm focus:font-medium focus:text-white"
        >Skip to content</a>

        <header class="border-b border-border-subtle bg-surface">
            <div class="mx-auto flex h-16 max-w-6xl items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
                <a href="{{ url('/') }}" class="flex items-center gap-2.5">
                    <span class="flex size-9 items-center justify-center rounded-lg bg-brand-600 text-base font-bold text-white">K</span>
                    <span class="text-xl font-semibold tracking-tight text-ink">Kudosly</span>
                </a>

                <nav aria-label="Main" class="flex items-center gap-2">
                    @auth
                        <a
                            href="{{ route('dashboard.overview') }}"
                            class="rounded-lg px-3 py-2 text-sm font-medium text-ink-muted hover:bg-surface-muted hover:text-ink focus-ring"
                        >Dashboard</a>
                    @else
                        <a
                            href="{{ route('login') }}"
                            class="rounded-lg px-3 py-2 text-sm font-medium text-ink-muted hover:bg-surface-muted hover:text-ink focus-ring"
                        >Log in</a>

                        <a
                            href="{{ route('register') }}"
                            class="rounded-lg bg-brand-600 px-3.5 py-2 text-sm font-medium text-white shadow-sm hover:bg-brand-700 focus-ring"
                        >Get started</a>
                    @endauth
                </nav>
            </div>
        </header>

        <main id="main" class="flex-1">
            {{-- The opening. The heading is the first thing on the page and
                 says what the product is in one line. --}}
            <section class="mx-auto max-w-6xl px-4 py-16 sm:px-6 sm:py-20 lg:px-8 lg:py-24">
                <div class="grid items-center gap-12 lg:grid-cols-2 lg:gap-16">
                    <div>
                        <p class="inline-flex items-center gap-2 rounded-full bg-brand-50 px-3 py-1 text-xs font-medium text-brand-700">
                            Testimonials, minus the busywork
                        </p>

                        <h1 class="mt-5 text-4xl font-semibold tracking-tight text-ink sm:text-5xl">
                            Collect the kind words your customers actually wrote.
                        </h1>

                        <p class="mt-5 max-w-xl text-lg text-ink-muted">
                            Kudosly gives you a branded page to collect testimonials, a dashboard to
                            curate them, and a widget to publish the best ones on your own site.
                        </p>

                        <div class="mt-8 flex flex-wrap items-center gap-3">
                            <a
                                href="{{ route('register') }}"
                                class="rounded-lg bg-brand-600 px-5 py-3 text-sm font-medium text-white shadow-sm hover:bg-brand-700 focus-ring"
                            >Start collecting free</a>

                            <a
                                href="#how-it-works"
                                class="rounded-lg px-5 py-3 text-sm font-medium text-ink-muted hover:bg-surface-muted hover:text-ink focus-ring"
                            >See how it works</a>
                        </div>

                        <p class="mt-4 text-sm text-ink-subtle">
                            Free plan includes 3 spaces. No credit card required.
                        </p>
                    </div>

                    {{-- The preview is a static illustration of a space page.
                         It is marked as decorative, because it repeats what
                         the text beside it already says. --}}
                    <div aria-hidden="true" class="relative">
                        <div class="rounded-2xl bg-surface p-6 shadow-lg ring-1 ring-inset ring-border-subtle">
                            <div class="flex items-center gap-2">
                                <span class="size-2.5 rounded-full bg-red-400"></span>
                                <span class="size-2.5 rounded-full bg-amber-400"></span>
                                <span class="size-2.5 rounded-full bg-green-400"></span>
                            </div>

                            <div class="mt-5 rounded-xl bg-surface-muted p-6">
                                <div class="h-3 w-28 rounded-full bg-border-subtle"></div>
                                <div class="mt-2 h-5 w-52 rounded-full bg-ink/10"></div>

                                <div class="mt-6 space-y-3">
                                    <div class="rounded-lg bg-surface p-4 shadow-sm ring-1 ring-inset ring-border-subtle">
                                        <div class="flex items-center gap-3">
                                            <span class="size-9 shrink-0 rounded-full bg-brand-100"></span>
                                            <div class="min-w-0 flex-1">
                                                <div class="h-3 w-32 rounded-full bg-ink/10"></div>
                                                <div class="mt-2 h-2.5 w-24 rounded-full bg-border-subtle"></div>
                                            </div>
                                        </div>
                                        <div class="mt-3 space-y-2">
                                            <div class="h-2.5 w-full rounded-full bg-border-subtle"></div>
                                            <div class="h-2.5 w-4/5 rounded-full bg-border-subtle"></div>
                                        </div>
                                    </div>

                                    <div class="rounded-lg bg-surface p-4 shadow-sm ring-1 ring-inset ring-border-subtle">
                                        <div class="flex items-center gap-3">
                                            <span class="size-9 shrink-0 rounded-full bg-brand-100"></span>
                                            <div class="min-w-0 flex-1">
                                                <div class="h-3 w-28 rounded-full bg-ink/10"></div>
                                                <div class="mt-2 h-2.5 w-20 rounded-full bg-border-subtle"></div>
                                            </div>
                                        </div>
                                        <div class="mt-3 space-y-2">
                                            <div class="h-2.5 w-full rounded-full bg-border-subtle"></div>
                                            <div class="h-2.5 w-3/5 rounded-full bg-border-subtle"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            {{-- The three steps. The list is a real list, so a screen reader
                 announces how many steps there are. --}}
            <section id="how-it-works" class="border-t border-border-subtle bg-surface-muted">
                <div class="mx-auto max-w-6xl px-4 py-16 sm:px-6 lg:px-8">
                    <h2 class="text-2xl font-semibold tracking-tight text-ink sm:text-3xl">
                        Three steps to your first testimonial
                    </h2>

                    <ol class="mt-10 grid gap-8 sm:grid-cols-3">
                        <li>
                            <span class="flex size-9 items-center justify-center rounded-full bg-brand-50 text-sm font-semibold text-brand-700">1</span>
                            <h3 class="mt-4 text-base font-semibold text-ink">Create a space</h3>
                            <p class="mt-1.5 text-sm text-ink-muted">
                                Name it, pick a theme, and choose the fields customers fill in. Name and
                                email are always collected.
                            </p>
                        </li>

                        <li>
                            <span class="flex size-9 items-center justify-center rounded-full bg-brand-50 text-sm font-semibold text-brand-700">2</span>
                            <h3 class="mt-4 text-base font-semibold text-ink">Share the link</h3>
                            <p class="mt-1.5 text-sm text-ink-muted">
                                Send your public space URL after a purchase or a launch. Customers write
                                their testimonial without needing an account.
                            </p>
                        </li>

                        <li>
                            <span class="flex size-9 items-center justify-center rounded-full bg-brand-50 text-sm font-semibold text-brand-700">3</span>
                            <h3 class="mt-4 text-base font-semibold text-ink">Publish and embed</h3>
                            <p class="mt-1.5 text-sm text-ink-muted">
                                Approve the best ones, build a Wall of Love, and drop the widget into
                                your site with a snippet of code.
                            </p>
                        </li>
                    </ol>
                </div>
            </section>

            {{-- The features. The heading level sits under the section
                 heading, so the page keeps a flat hierarchy. --}}
            <section class="mx-auto max-w-6xl px-4 py-16 sm:px-6 lg:px-8">
                <h2 class="text-2xl font-semibold tracking-tight text-ink sm:text-3xl">
                    Everything you need, nothing you don't
                </h2>

                <div class="mt-10 grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <h3 class="text-base font-semibold text-ink">Branded to match</h3>
                        <p class="mt-1.5 text-sm text-ink-muted">
                            Three themes, your own colours, and a form that collects exactly what you ask
                            for and nothing else.
                        </p>
                    </div>

                    <div>
                        <h3 class="text-base font-semibold text-ink">Consent you control</h3>
                        <p class="mt-1.5 text-sm text-ink-muted">
                            Customers decide whether their words can be shown publicly. A submission
                            without consent stays private to you.
                        </p>
                    </div>

                    <div>
                        <h3 class="text-base font-semibold text-ink">Moderation queue</h3>
                        <p class="mt-1.5 text-sm text-ink-muted">
                            Every submission lands in your inbox first. Approve, hide, or favourite the
                            ones worth showing.
                        </p>
                    </div>

                    <div>
                        <h3 class="text-base font-semibold text-ink">Ratings</h3>
                        <p class="mt-1.5 text-sm text-ink-muted">
                            Ask for a star rating alongside the words, or leave it off. You choose, per
                            space.
                        </p>
                    </div>

                    <div>
                        <h3 class="text-base font-semibold text-ink">Wall of Love</h3>
                        <p class="mt-1.5 text-sm text-ink-muted">
                            A public page for the testimonials you have picked, ready to link to from
                            your own site.
                        </p>
                    </div>

                    <div>
                        <h3 class="text-base font-semibold text-ink">Analytics</h3>
                        <p class="mt-1.5 text-sm text-ink-muted">
                            See how many testimonials you have collected and how the number moves over
                            time.
                        </p>
                    </div>
                </div>
            </section>

            {{-- The closing call to action. --}}
            <section class="border-t border-border-subtle bg-brand-600">
                <div class="mx-auto max-w-6xl px-4 py-16 sm:px-6 lg:px-8">
                    <div class="max-w-2xl">
                        <h2 class="text-2xl font-semibold tracking-tight text-white sm:text-3xl">
                            Your next testimonial is one link away.
                        </h2>

                        <p class="mt-4 text-brand-100">
                            Create a space, send the link, and start collecting the kind words your
                            customers have already said about you.
                        </p>

                        <div class="mt-8 flex flex-wrap items-center gap-3">
                            <a
                                href="{{ route('register') }}"
                                class="rounded-lg bg-white px-5 py-3 text-sm font-medium text-brand-700 shadow-sm hover:bg-brand-50 focus-ring"
                            >Create your free account</a>

                            <a
                                href="{{ route('login') }}"
                                class="rounded-lg px-5 py-3 text-sm font-medium text-brand-100 hover:bg-brand-700 hover:text-white focus-ring"
                            >Log in</a>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <footer class="border-t border-border-subtle bg-surface">
            <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-4 px-4 py-8 sm:px-6 lg:flex-row lg:px-8">
                <div class="flex items-center gap-2.5">
                    <span class="flex size-8 items-center justify-center rounded-lg bg-brand-600 text-sm font-bold text-white">K</span>
                    <span class="font-semibold tracking-tight text-ink">Kudosly</span>
                </div>

                <p class="text-sm text-ink-subtle">
                    &copy; {{ date('Y') }} {{ config('app.name', 'Kudosly') }}. Collect better testimonials.
                </p>
            </div>
        </footer>
    </div>
</body>
</html>
