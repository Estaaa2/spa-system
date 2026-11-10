<?php

namespace App\Support\Legal;

/**
 * Terms and Conditions — the one source for /terms-and-conditions, the
 * customer and business registration modals, and the booking modal
 * (landing footer Phase 2, D8 / Q5-A). The Refund Policy reuses the
 * payment, cancellation and no-show sections from here.
 *
 * Audiences (which modal shows a section):
 *   everyone  — both registration modals
 *   customers — customer registration
 *   bookings  — the booking modal on the landing page
 *   partners  — business registration
 *
 * Every rule here must match what the code does or a decision on record.
 *
 * Change the wording → bump VERSION and UPDATED. The version is recorded
 * when a user registers and when a customer books.
 */
class Terms extends LegalDocument
{
    public const VERSION = '2026-11-07';

    public const UPDATED = 'November 7, 2026';

    public static function title(): string
    {
        return 'Terms and Conditions';
    }

    protected static function introBlocks(): array
    {
        return [
            'These Terms and Conditions are the agreement between you and Levictas, an online platform for booking and managing spas in the Philippines. Levictas is developed and run by {team}, students of {school}, as their capstone project.',
            'By creating an account, booking an appointment, or using Levictas as a spa owner or staff member, you agree to these Terms. Please also read our {privacy}, which explains how we handle your personal data, and our {refund}.',
        ];
    }

    protected static function allSections(): array
    {
        return [
            [
                'id' => 'about',
                'title' => 'What Levictas does',
                'audience' => ['everyone'],
                'blocks' => [
                    'Levictas lists partner spas and lets customers book their services online. Each partner spa sets its own services, prices, schedules and staff, and is responsible for the services it provides.',
                    'Levictas runs the website and mobile app, collects online reservation payments through our payment provider, PayMongo, and gives partner spas tools to manage their bookings, staff and operations.',
                ],
            ],
            [
                'id' => 'account',
                'title' => 'Your account',
                'audience' => ['everyone'],
                'blocks' => [
                    ['list' => [
                        'Give accurate information when you register, and keep it up to date.',
                        'Verify your email address with the code we send before your account is created.',
                        'Keep your password private. You are responsible for what happens under your account.',
                        'Use one account per email address.',
                        'We may suspend or close an account that breaks these Terms or is used to misuse the platform.',
                    ]],
                ],
            ],
            [
                'id' => 'booking',
                'title' => 'Booking an appointment',
                'audience' => ['customers', 'bookings'],
                'blocks' => [
                    'Choose a spa, branch, service, date and time. Your booking is confirmed only after your online payment goes through. If you leave checkout without paying, nothing is booked.',
                    'Give a working mobile number. For home service, give the exact address where the service will take place, and be there at the booked time.',
                    'The spa assigns a therapist to your booking. Please arrive on time.',
                    'To change the date or time, send a reschedule request from My Appointments before the appointment starts. The spa approves or declines it. If it is approved, your payment carries over to the new time.',
                ],
            ],
            [
                'id' => 'payments',
                'title' => 'Prices and payment',
                'audience' => ['customers', 'bookings'],
                'blocks' => [
                    'Prices are in Philippine pesos (₱) and are set by each spa.',
                    'To confirm a booking, you pay online either a 20% downpayment or the full price. With a downpayment, you pay the remaining 80% at the spa on the day of your appointment.',
                    'Online payments are processed by PayMongo, using the payment methods shown at checkout. You enter your card or e-wallet details on PayMongo\'s page; Levictas does not see or store them.',
                ],
            ],
            [
                'id' => 'cancellation',
                'title' => 'Cancelling a booking',
                'audience' => ['customers', 'bookings'],
                'blocks' => [
                    'A paid booking can\'t be cancelled in the app. To cancel, contact the branch at least 24 hours before your appointment. The branch\'s phone number is on its spa page.',
                    'The 20% downpayment is non-refundable, whatever the reason or timing of the cancellation. If you paid in full, the remaining 80% is refunded.',
                    // Spa-side cancellation → full refund (Phase 2 end, Q1-A).
                    'If the spa cancels your booking or can\'t provide the service, you get back everything you paid online, including the downpayment.',
                    'The {refund} explains how to ask for a refund.',
                ],
            ],
            [
                'id' => 'no-show',
                'title' => 'No-shows',
                'audience' => ['customers', 'bookings'],
                'blocks' => [
                    'If you don\'t arrive for your appointment, or aren\'t at the address for a home service, without telling the branch first, the spa marks the booking as a no-show and the 20% downpayment is forfeited. If you paid in full, the remaining 80% is still refunded.',
                ],
            ],
            [
                'id' => 'reviews',
                'title' => 'Ratings and reviews',
                'audience' => ['customers'],
                'blocks' => [
                    'After a completed appointment, you can rate the spa and your therapist and add a comment and photos. Reviews are public and show your first name and last initial.',
                    'Keep reviews honest and about your own visit. Don\'t include other people\'s personal information or offensive content.',
                ],
            ],
            [
                'id' => 'fair-use',
                'title' => 'Fair use',
                'audience' => ['everyone'],
                'blocks' => [
                    ['list' => [
                        'Don\'t make bookings you don\'t intend to keep.',
                        'Don\'t use someone else\'s account or payment method.',
                        'Don\'t try to break, overload or get around the security of Levictas.',
                        'Treat spa staff and customers with respect.',
                    ]],
                ],
            ],
            [
                'id' => 'partners',
                'title' => 'For partner spas',
                'audience' => ['partners'],
                'blocks' => [
                    'Spa owners register a business account, give accurate business details and upload the business documents we ask for. Levictas reviews them before the spa is listed, and may decline or suspend a listing if they are invalid or expire.',
                    'Partner spas use Levictas on a subscription plan. New spas start with a free {trial_months}-month trial. Plans are paid per period through PayMongo and do not renew automatically. If a period ends unpaid, the spa gets a {grace_days}-day grace period. After that, the spa is locked until it renews: only billing stays open, staff can\'t sign in, and the spa is hidden from customers and can\'t take online bookings. The spa\'s data is kept.',
                    'Partner spas pay only the subscription. Levictas charges no fee on bookings.',
                    // Payments reach the spa outside the system (Phase 2 end, Q2-A).
                    'Online reservation payments for your spa are collected by Levictas through PayMongo. Levictas passes them on to your spa, as agreed between your spa and Levictas.',
                    'Keep your services, prices, schedules and branch details accurate, honor the bookings customers make through Levictas, and follow the laws that apply to your business, including permit and labor laws.',
                    'Your spa decides what information it keeps about its staff, job applicants and suppliers, and is responsible for it. Levictas stores and processes that information for your spa, as described in the {privacy}.',
                    'Staff identity verification is optional and has its own consent notice, shown before a staff member uploads anything.',
                ],
            ],
            [
                'id' => 'availability',
                'title' => 'Availability and changes',
                'audience' => ['everyone'],
                'blocks' => [
                    'We work to keep Levictas available, but it may be unavailable at times for maintenance, updates or problems outside our control. Features may change as the platform develops.',
                ],
            ],
            [
                'id' => 'responsibility',
                'title' => 'Responsibility',
                'audience' => ['everyone'],
                'blocks' => [
                    'Each partner spa is responsible for the services it provides, including their quality and safety. Levictas is responsible for running the platform.',
                    'Nothing in these Terms takes away rights you have under Philippine law, including consumer protection laws.',
                ],
            ],
            [
                'id' => 'closing',
                'title' => 'Closing your account',
                'audience' => ['everyone'],
                'blocks' => [
                    'You can ask us to close your account by emailing {email}. A closed account can no longer sign in. The {privacy} explains what happens to your data.',
                ],
            ],
            [
                'id' => 'changes',
                'title' => 'Changes to these Terms',
                'audience' => ['everyone'],
                'blocks' => [
                    'We may update these Terms. The date at the top shows the latest version. We record which version you accepted and when. If you keep using Levictas after an update, the new version applies.',
                ],
            ],
            [
                'id' => 'law',
                'title' => 'Governing law',
                'audience' => ['everyone'],
                'blocks' => [
                    'These Terms are governed by the laws of the Republic of the Philippines.',
                ],
            ],
            [
                'id' => 'contact',
                'title' => 'Contact us',
                'audience' => ['everyone'],
                'blocks' => [
                    'Email {email}, or write to Levictas, {address}.',
                ],
            ],
        ];
    }
}
