@extends('layouts.public')

{{--
    FAQ page (landing footer Phase 3; moved off the landing page on review
    so it isn't one more card section there). Footer → /faq.

    Rules (refunds, cancelling) are not restated: those answers pull the
    exact sentence from App\Support\Legal\Terms / RefundPolicy and link to
    the full page, so the FAQ can never disagree with them. Plan prices and
    limits come from config/plans.php, the trial from config/subscription.php.
    <details>/<summary>: keyboard and screen-reader support built in (Q4-A).
--}}

@section('title', 'Frequently Asked Questions')

@section('content')
    @php
    $rule = fn (string $id, int $block) => \App\Support\Legal\Terms::sections([$id])[0]['blocks'][$block];
    $refundHowTo = \App\Support\Legal\RefundPolicy::sections(['how-to-ask'])[0]['blocks'][0];
    $peso = fn ($n) => '₱' . number_format((int) $n);
    $basic = config('plans.basic');
    $premium = config('plans.premium');
    $trialMonths = (int) config('subscription.trial_months', 1);
    $link = fn (string $href, string $label, bool $newTab = false) =>
        '<a href="' . e($href) . '" class="legal-link"' . ($newTab ? ' target="_blank" rel="noopener"' : '') . '>' . e($label) . '</a>';

    $faqGroups = [
        'For customers' => [
            [
                'q' => 'Do I need an account to book?',
                'a' => ['Yes. You can browse spas without one, but booking needs a free account. Sign up with your name and email address, and we send a code to verify your email.'],
            ],
            [
                'q' => 'How do I pay, and which payment methods can I use?',
                'a' => [
                    $rule('payments', 1),
                    'You pay through PayMongo, our payment provider. The payment methods you can use are shown on the PayMongo checkout page.',
                ],
            ],
            [
                'q' => 'Can I change or cancel my booking?',
                'a' => [
                    'To change the date or time, open My Appointments and send a reschedule request before the appointment starts. The spa approves or declines it.',
                    $rule('cancellation', 0),
                ],
            ],
            [
                'q' => 'Is my payment refundable?',
                'a' => [
                    $rule('cancellation', 1),
                    $rule('cancellation', 2),
                    'The ' . $link(route('legal.refund'), 'Refund Policy') . ' has the details.',
                ],
            ],
            [
                'q' => 'How do I ask for a refund?',
                'a' => [$refundHowTo],
            ],
            [
                'q' => 'How does home service work, and who comes to my home?',
                'a' => ['Some branches offer home service: a therapist comes to the address you give when you book. Only therapists whose identity has been verified by Levictas can take home-service bookings.'],
            ],
            [
                'q' => 'How do I find spas near me?',
                'a' => ['On the ' . $link(url('/'), 'home page') . ', allow your browser to share your location, and the Spas near you section shows the closest spas. You can also search by place, or save your location in your profile.'],
            ],
            [
                'q' => 'Are the spas on Levictas verified?',
                'a' => ['Before a spa is listed, Levictas reviews its business documents. Verified spas show a Business verified badge on their page.'],
            ],
        ],
        'For spa owners and job seekers' => [
            [
                'q' => 'How do I list my spa, and how much does it cost?',
                'a' => [
                    'Register a business account with ' . $link(route('register.business'), 'Join as a Partner') . ' and upload your business documents. After we review them, your spa can be listed.',
                    'You start with a free ' . $trialMonths . '-month trial. After that, choose Basic (' . $peso($basic['monthly_price']) . ' a month or ' . $peso($basic['yearly_price']) . ' a year, up to ' . $basic['max_branches'] . ' branches and ' . $basic['max_staff'] . ' staff) or Premium (' . $peso($premium['monthly_price']) . ' a month or ' . $peso($premium['yearly_price']) . ' a year, up to ' . $premium['max_branches'] . ' branches and ' . $premium['max_staff'] . ' staff). There is no fee on bookings.',
                ],
            ],
            [
                'q' => 'How do I apply for a job at a spa?',
                'a' => ['Spas that are hiring show an Apply Now button on their page. Fill in the form and attach your résumé; it goes only to that spa\'s owner and HR staff.'],
            ],
        ],
        'General' => [
            [
                'q' => 'How is my personal data protected?',
                'a' => ['We collect only what we need to run your account and bookings, keep IDs and documents in private storage, and don\'t sell data, show ads or use tracking tools. The ' . $link(route('legal.privacy'), 'Privacy Policy') . ' explains what we collect, who receives it, and your rights.'],
            ],
        ],
    ];
@endphp

    <div class="px-4 py-12 sm:px-6 sm:py-16">
        <div class="max-w-3xl mx-auto">
            @include('partials.public.back-home')

            <header class="mt-4 text-center">
                <div class="flex items-center justify-center gap-4 sm:gap-6">
                    <span class="hidden sm:block h-px w-16 sm:w-24 bg-gradient-to-r from-transparent to-[#8B7355] dark:to-[#C4A97D]"></span>
                    <h1 class="text-3xl sm:text-4xl font-['Playfair_Display'] text-[#3C2F23] dark:text-white font-semibold">Frequently Asked Questions</h1>
                    <span class="hidden sm:block h-px w-16 sm:w-24 bg-gradient-to-l from-transparent to-[#8B7355] dark:to-[#C4A97D]"></span>
                </div>
                <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">Quick answers about booking, payments and listing your spa.</p>
            </header>

            <div class="mt-10 space-y-10">
                @foreach ($faqGroups as $group => $items)
                    <section aria-labelledby="faq-group-{{ $loop->index }}">
                        <h2 id="faq-group-{{ $loop->index }}" class="mb-3 text-[11px] font-semibold tracking-wider uppercase text-[#8B7355] dark:text-[#C4A97D]">{{ $group }}</h2>
                        <div class="overflow-hidden divide-y shadow-sm bg-white/70 dark:bg-gray-900/40 rounded-3xl ring-1 ring-black/5 dark:ring-white/10 divide-black/5 dark:divide-white/10">
                            @foreach ($items as $item)
                                <details class="group">
                                    <summary
                                        class="flex items-center justify-between gap-4 px-6 py-4 cursor-pointer list-none text-[15px] font-medium text-[#3C2F23] dark:text-white hover:bg-white/60 dark:hover:bg-gray-800/60 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-[#8B7355] [&::-webkit-details-marker]:hidden">
                                        <span>{{ $item['q'] }}</span>
                                        <i class="flex-shrink-0 text-xs transition-transform fa-solid fa-chevron-down text-[#8B7355] dark:text-[#C4A97D] group-open:rotate-180 motion-reduce:transition-none"
                                            aria-hidden="true"></i>
                                    </summary>
                                    <div class="px-6 pb-5 space-y-2 text-sm leading-6 text-gray-600 dark:text-gray-300">
                                        @foreach ($item['a'] as $paragraph)
                                            <p>{!! $paragraph !!}</p>
                                        @endforeach
                                    </div>
                                </details>
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </div>

            <p class="mt-10 text-sm text-center text-gray-600 dark:text-gray-400">
                Still have a question? <a href="{{ route('contact') }}" class="legal-link">Contact us</a>.
            </p>
        </div>
    </div>
@endsection
