{{--
    About and FAQ sections of the landing page (landing footer Phase 3).
    Linked from the footer as /#about and /#faq.

    Rules (refunds, cancelling) are not restated: those answers pull the
    exact sentence from App\Support\Legal\Terms / RefundPolicy and link to
    the full page, so the FAQ can never disagree with them. Plan prices and
    limits come from config/plans.php, the trial from config/subscription.php.
    The FAQ uses <details>/<summary>: keyboard and screen-reader support are
    built in, no JavaScript (Phase 3, Q4-A).
--}}
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
                'a' => ['Allow your browser to share your location, and the Spas near you section shows the closest spas. You can also search by place at the top of the page, or save your location in your profile.'],
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

    $team = \App\Support\Legal\Contact::team();
    $school = \App\Support\Legal\Contact::school();
@endphp

<!-- ================= ABOUT ================= -->
<section id="about" class="py-20 bg-[#EFE3D6] dark:bg-gray-800" aria-labelledby="aboutTitle">
    <div class="px-6 mx-auto max-w-7xl">
        <div class="text-center">
            <div class="flex items-center justify-center gap-6">
                <span class="hidden sm:block h-px w-24 bg-gradient-to-r from-transparent to-[#8B7355] dark:to-[#C4A97D]"></span>
                <h2 id="aboutTitle" class="text-3xl sm:text-4xl font-['Playfair_Display'] text-[#3C2F23] dark:text-white font-semibold">
                    About Levictas</h2>
                <span class="hidden sm:block h-px w-24 bg-gradient-to-l from-transparent to-[#8B7355] dark:to-[#C4A97D]"></span>
            </div>
            <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">One place to find, book and run spas in Cavite.</p>
        </div>

        <div class="grid gap-6 mt-12 md:grid-cols-3">
            <div class="p-7 shadow-sm bg-white/70 dark:bg-gray-900/40 rounded-3xl ring-1 ring-black/5 dark:ring-white/10">
                <div class="flex items-center justify-center w-12 h-12 rounded-2xl bg-[#F6EFE6] dark:bg-gray-700 ring-1 ring-black/5 dark:ring-white/10">
                    <i class="fa-solid fa-spa text-[#8B7355] dark:text-[#C4A97D]" aria-hidden="true"></i>
                </div>
                <h3 class="mt-5 font-semibold text-[#3C2F23] dark:text-white">For customers</h3>
                <p class="mt-2 text-sm leading-6 text-gray-600 dark:text-gray-400">
                    Browse verified spas, compare services and prices, and book an in-branch or home-service
                    appointment online, with your bookings and schedule in one place.
                </p>
            </div>

            <div class="p-7 shadow-sm bg-white/70 dark:bg-gray-900/40 rounded-3xl ring-1 ring-black/5 dark:ring-white/10">
                <div class="flex items-center justify-center w-12 h-12 rounded-2xl bg-[#F6EFE6] dark:bg-gray-700 ring-1 ring-black/5 dark:ring-white/10">
                    <i class="fa-solid fa-store text-[#8B7355] dark:text-[#C4A97D]" aria-hidden="true"></i>
                </div>
                <h3 class="mt-5 font-semibold text-[#3C2F23] dark:text-white">For spas</h3>
                <p class="mt-2 text-sm leading-6 text-gray-600 dark:text-gray-400">
                    Take online bookings and manage schedules, staff and daily operations across branches,
                    with reports that support everyday decisions.
                </p>
            </div>

            <div class="p-7 shadow-sm bg-white/70 dark:bg-gray-900/40 rounded-3xl ring-1 ring-black/5 dark:ring-white/10">
                <div class="flex items-center justify-center w-12 h-12 rounded-2xl bg-[#F6EFE6] dark:bg-gray-700 ring-1 ring-black/5 dark:ring-white/10">
                    <i class="fa-solid fa-people-group text-[#8B7355] dark:text-[#C4A97D]" aria-hidden="true"></i>
                </div>
                <h3 class="mt-5 font-semibold text-[#3C2F23] dark:text-white">Who we are</h3>
                <p class="mt-2 text-sm leading-6 text-gray-600 dark:text-gray-400">
                    Levictas is a capstone project developed by {{ $team }}, students of {{ $school }}.
                    <a href="{{ route('contact') }}" class="legal-link">Get in touch</a>.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- ================= FAQ ================= -->
<section id="faq" class="py-20" aria-labelledby="faqTitle">
    <div class="max-w-3xl px-6 mx-auto">
        <div class="text-center">
            <div class="flex items-center justify-center gap-6">
                <span class="hidden sm:block h-px w-24 bg-gradient-to-r from-transparent to-[#8B7355] dark:to-[#C4A97D]"></span>
                <h2 id="faqTitle" class="text-3xl sm:text-4xl font-['Playfair_Display'] text-[#3C2F23] dark:text-white font-semibold">
                    Frequently Asked Questions</h2>
                <span class="hidden sm:block h-px w-24 bg-gradient-to-l from-transparent to-[#8B7355] dark:to-[#C4A97D]"></span>
            </div>
            <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">Quick answers about booking, payments and listing your spa.</p>
        </div>

        <div class="mt-12 space-y-10">
            @foreach ($faqGroups as $group => $items)
                <div>
                    <h3 class="mb-3 text-[11px] font-semibold tracking-wider uppercase text-[#8B7355] dark:text-[#C4A97D]">{{ $group }}</h3>
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
                </div>
            @endforeach
        </div>

        <p class="mt-10 text-sm text-center text-gray-600 dark:text-gray-400">
            Still have a question? <a href="{{ route('contact') }}" class="legal-link">Contact us</a>.
        </p>
    </div>
</section>
