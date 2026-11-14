<?php

namespace App\Support\Legal;

/**
 * Refund Policy — /refund-policy (landing footer Phase 2).
 *
 * The booking rules (payment, cancellation, no-show) are NOT written here:
 * they are pulled from Terms so they are defined once (D8). Only the refund
 * process (Q6-A) and the subscription rules (from the code:
 * SubscriptionController, SubscriptionBilling) live here.
 */
class RefundPolicy extends LegalDocument
{
    public const VERSION = '2026-11-14';

    public const UPDATED = 'November 14, 2026';

    private const BOOKINGS = 'Spa bookings';
    private const SUBSCRIPTIONS = 'Partner spa subscriptions';
    private const GENERAL = 'Contact';

    /** The Terms sections this policy reuses, in order. */
    public const TERMS_SECTIONS = ['payments', 'cancellation', 'no-show'];

    public static function title(): string
    {
        return 'Refund Policy';
    }

    protected static function introBlocks(): array
    {
        return [
            'This Refund Policy explains when payments made through Levictas are refunded, and how to ask for a refund. It covers online reservation payments by customers and subscription payments by partner spas. The booking rules below are part of our {terms}.',
        ];
    }

    protected static function allSections(): array
    {
        // Raw Terms sections (tokens unfilled; sections() fills everything once).
        $fromTerms = array_map(
            fn (array $s) => $s + ['group' => self::BOOKINGS, 'from_terms' => true],
            array_values(array_filter(
                Terms::rawSections(),
                fn ($s) => in_array($s['id'], self::TERMS_SECTIONS, true)
            ))
        );

        return [
            ...$fromTerms,
            [
                'id' => 'how-to-ask',
                'group' => self::BOOKINGS,
                'title' => 'How to ask for a refund',
                'blocks' => [
                    // The FAQ shows block 0 alone, so it covers both cases.
                    'If you cancel in the app, your refund is set up for you and you can follow it on the booking in My Appointments. For any other refund, email {email} with your name, the spa and branch, the booking date, and the payment reference from your PayMongo receipt.',
                    'Levictas checks the booking with the spa, then sends approved refunds through PayMongo, back to the payment method you used where PayMongo allows it.',
                    'Some payment methods can\'t be refunded through PayMongo, such as QR Ph payments made with Maya. For these, we return the money another way that we agree on with you, such as a bank or e-wallet transfer.',
                    'How long a refund takes to reach you depends on your payment method and bank.',
                ],
            ],
            [
                'id' => 'subscriptions',
                'group' => self::SUBSCRIPTIONS,
                'title' => 'Subscription payments',
                'blocks' => [
                    ['list' => [
                        'The free trial costs nothing, so there is nothing to refund.',
                        'Subscription payments are not refundable, including when a spa stops using Levictas before the paid period ends.',
                        'Upgrading from Basic to Premium takes effect right away and charges only the difference for the rest of the current period. Downgrading takes effect at the next renewal.',
                        'Plans don\'t renew automatically, so there is nothing to cancel. If the next period isn\'t paid, the spa is locked after the {grace_days}-day grace period, and its data is kept.',
                    ]],
                ],
            ],
            [
                'id' => 'contact',
                'group' => self::GENERAL,
                'title' => 'Questions',
                'blocks' => [
                    'Email {email}, or write to Levictas, {address}.',
                ],
            ],
        ];
    }
}
