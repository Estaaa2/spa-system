<?php

namespace App\Support\Identity;

/**
 * The identity-verification consent notice (RA 10173; NPC Circular 2023-04).
 *
 * One source for the web profile page and the mobile API, so both show exactly
 * the notice whose version (config identity.consent_version) is recorded with
 * the consent. Change the wording here and bump the version together.
 */
class ConsentNotice
{
    public const HEADING = 'Before you submit: how we handle your ID';

    public const INTRO = 'Data Privacy Act of 2012 (RA 10173). Your ID is sensitive personal information, so we need your consent first.';

    public const AGREEMENT = 'I have read this notice and I agree to Levictas processing my ID and selfie to verify my identity.';

    /** @return list<array{title: string, body: string}> */
    public static function sections(): array
    {
        $contact = config('identity.privacy_contact');

        return [
            ['title' => 'What we collect', 'body' => 'One photo of a government ID, one selfie holding it, the ID type, and your name as shown on your profile.'],
            ['title' => 'Why', 'body' => 'To confirm that the person in your profile photo is you, so customers who book home service can check who arrives at their door.'],
            ['title' => 'Who sees it', 'body' => 'Only the Levictas admins who review it. Your spa owner and co-workers cannot see your ID or selfie. Every time an admin opens them, it is recorded.'],
            ['title' => 'How long we keep it', 'body' => 'Your ID and selfie are deleted as soon as the admin approves or rejects your request, or when you cancel it. We keep only the result, the ID type, who reviewed it and the dates, for as long as your account exists.'],
            ['title' => "It's your choice", 'body' => "Verification is optional. If you don't verify, nothing else changes: you can still work in-branch and take walk-in and staff-made bookings. You just won't be assigned online home-service bookings."],
            ['title' => 'Changing your mind', 'body' => 'You can withdraw consent at any time on this page. A pending request and its files are deleted right away; an approval ends, and you stop receiving new online home-service bookings.'],
            ['title' => 'Your rights', 'body' => 'You can ask to see, correct or delete your data '
                .($contact ? "by writing to {$contact}." : 'by contacting the Levictas team.')
                .' You may also file a complaint with the National Privacy Commission (privacy.gov.ph).'],
        ];
    }

    /** The whole notice for the API. */
    public static function toArray(): array
    {
        return [
            'version'         => config('identity.consent_version'),
            'heading'         => self::HEADING,
            'intro'           => self::INTRO,
            'sections'        => self::sections(),
            'agreement'       => self::AGREEMENT,
            'privacy_contact' => config('identity.privacy_contact'),
        ];
    }
}
