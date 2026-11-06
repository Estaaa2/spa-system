<?php

namespace App\Support\Legal;

/**
 * Privacy Policy — /privacy-policy (landing footer Phase 2).
 *
 * Written from the data inventory (claude/legal-data-inventory.md); each
 * section notes the inventory rows it covers. Part A is data Levictas is
 * responsible for; Part B is data it stores for partner spas (Q6-A).
 *
 * Never state a deletion or retention rule the code doesn't enforce (Q11-A).
 * Change the wording → bump VERSION and UPDATED. The version is recorded
 * with a job applicant's consent.
 */
class PrivacyPolicy extends LegalDocument
{
    public const VERSION = '2026-11-07';

    public const UPDATED = 'November 7, 2026';

    private const PART_A = 'Part A — Data Levictas is responsible for';
    private const PART_B = 'Part B — Data we handle for partner spas';
    private const GENERAL = 'Updates and contact';

    public static function title(): string
    {
        return 'Privacy Policy';
    }

    protected static function introBlocks(): array
    {
        return [
            'This Privacy Policy explains what personal data Levictas collects, why, who receives it, how long we keep it, and your rights under the Data Privacy Act of 2012 (Republic Act No. 10173).',
            'Levictas is developed and run by {team}, students of {school}. Part A covers the data Levictas is responsible for. Part B covers data we store for partner spas about their own staff, job applicants and suppliers.',
        ];
    }

    protected static function allSections(): array
    {
        return [
            // ── Part A ───────────────────────────────────────────────────
            [
                'id' => 'who-we-are',
                'group' => self::PART_A,
                'title' => 'Who we are',
                'blocks' => [
                    'Levictas, {address}. For anything about your personal data, contact the Levictas team, our data protection contact, at {email}.',
                ],
            ],
            [
                'id' => 'what-we-collect', // inventory A1–A11, C1
                'group' => self::PART_A,
                'title' => 'What we collect',
                'blocks' => [
                    ['list' => [
                        '<strong>Account:</strong> your name, email address, mobile number, password (stored only in hashed form, never as plain text) and profile photo.',
                        '<strong>Location:</strong> an address and map location you save in your profile, and the location you search from when looking for nearby spas. The search location is not saved.',
                        '<strong>Bookings:</strong> the spa, branch, service, date and time, your contact details and home-service address, the assigned therapist, the booking status and any reschedule requests.',
                        '<strong>Payments:</strong> the amount, the payment status and PayMongo\'s record of the payment, with your name, email address and mobile number. We never receive your card or e-wallet details.',
                        '<strong>Reviews:</strong> your ratings, comments and photos.',
                        '<strong>Partner spas:</strong> the owner\'s business details and business documents, and the spa\'s subscription and billing records.',
                        '<strong>Staff identity verification (optional):</strong> a photo of a government ID, a selfie holding it, the ID type and the review result. A record is kept each time an admin opens these files.',
                        '<strong>Sign-in and technical data:</strong> your IP address and browser type while you are signed in, the record of the version of our Terms you accepted, and error logs.',
                    ]],
                ],
            ],
            [
                'id' => 'why-we-use-it',
                'group' => self::PART_A,
                'title' => 'Why we use it',
                'blocks' => [
                    ['list' => [
                        'To create and run your account, take your bookings and payments, and let the spa serve you. We need this to provide the service you asked for (RA 10173, Sec. 12(b)).',
                        'To review spas before they are listed, keep the platform secure and prevent misuse. These are our legitimate interests (Sec. 12(f)).',
                        'To show the reviews you choose to post.',
                        'To verify a staff member\'s identity, only with their consent, which is given and recorded before anything is uploaded (Sec. 13(a)). A government ID is sensitive personal information.',
                        'To send account, booking and billing emails.',
                    ]],
                    'We don\'t sell personal data, show ads, or use tracking or analytics tools.',
                ],
            ],
            [
                'id' => 'who-receives-it', // inventory A5–A7, C3–C5
                'group' => self::PART_A,
                'title' => 'Who receives it',
                'blocks' => [
                    ['list' => [
                        '<strong>The spa you book</strong> receives your name, mobile number, email address, home-service address and booking details, so it can serve you. Spa staff see only what their role allows.',
                        '<strong>PayMongo</strong>, our payment provider, receives your name, email address, mobile number and the amount, to process your payment under its own privacy policy.',
                        '<strong>Google (Gmail)</strong> delivers our emails, so it handles your email address and the content of the message.',
                        '<strong>OpenStreetMap</strong> receives the address or map point you search, sent directly from your browser, to look up locations. Map images also load from OpenStreetMap.',
                        '<strong>Kamatera</strong> hosts our servers and database, in Singapore.',
                        'Your browser also loads fonts and scripts from Google Fonts, Bunny Fonts, cdnjs, jsDelivr and unpkg. These services see your IP address.',
                        '<strong>The public</strong> sees reviews with your first name and last initial.',
                    ]],
                    'We may also disclose personal data when the law requires it.',
                ],
            ],
            [
                'id' => 'outside-the-philippines', // inventory C4
                'group' => self::PART_A,
                'title' => 'Data stored outside the Philippines',
                'blocks' => [
                    'Our servers are in Singapore, and some of the services above process data in other countries. We remain responsible for the personal data we transfer to them (RA 10173, Sec. 21).',
                ],
            ],
            [
                'id' => 'how-long-we-keep-it', // inventory A1–A11 "Deleted?"
                'group' => self::PART_A,
                'title' => 'How long we keep it',
                'blocks' => [
                    ['list' => [
                        '<strong>Accounts, bookings, payments and reviews:</strong> kept while your account is open. We don\'t delete this data automatically on a schedule yet.',
                        '<strong>Closed accounts:</strong> a closed account can no longer sign in, but its data stays in our records. You can ask us to delete it (see Your rights).',
                        '<strong>Unfinished sign-ups:</strong> a sign-up you don\'t complete stops working after one day.',
                        '<strong>Staff ID photos and selfies:</strong> deleted as soon as the review is decided, the request is cancelled or the account is deleted, or after {id_days} days without a review. Only the result is kept.',
                        '<strong>Sign-in sessions</strong> end after {session_minutes} minutes without activity. <strong>Error logs</strong> are deleted after 14 days.',
                    ]],
                ],
            ],
            [
                'id' => 'how-we-protect-it',
                'group' => self::PART_A,
                'title' => 'How we protect it',
                'blocks' => [
                    ['list' => [
                        'Passwords are hashed, never stored as plain text.',
                        'Government IDs, selfies, business documents and résumés are stored privately, with no public web address. Only authorized users can open them, and every admin view of a staff ID is recorded.',
                        'Spa staff see only what their role allows, branch by branch.',
                        'The site is served over an encrypted (HTTPS) connection.',
                    ]],
                ],
            ],
            [
                'id' => 'cookies', // inventory C1–C2
                'group' => self::PART_A,
                'title' => 'Cookies and local storage',
                'blocks' => [
                    'We use only the cookies needed to run the site: one that keeps you signed in, one that protects forms against forgery, and a "remember me" cookie if you tick that box. The staff schedule remembers your chosen view in your browser\'s local storage.',
                    'We don\'t use advertising or analytics cookies.',
                ],
            ],
            [
                'id' => 'your-rights',
                'group' => self::PART_A,
                'title' => 'Your rights',
                'blocks' => [
                    'Under RA 10173, you have the right to:',
                    ['list' => [
                        'be informed about how your data is processed;',
                        'access your data;',
                        'have inaccurate data corrected;',
                        'object to processing, and withdraw your consent where we rely on it;',
                        'have your data deleted or blocked when it is no longer needed or was processed unlawfully;',
                        'get a copy of your data in a usable electronic format (data portability);',
                        'claim damages for harm caused by unlawful processing; and',
                        'file a complaint with the National Privacy Commission ({npc}).',
                    ]],
                    'To use these rights, email {email}. We may ask you to confirm your identity first. Staff can also withdraw their identity-verification consent at any time on their profile page.',
                ],
            ],

            // ── Part B ───────────────────────────────────────────────────
            [
                'id' => 'our-role-for-spas',
                'group' => self::PART_B,
                'title' => 'Our role',
                'blocks' => [
                    'Partner spas use Levictas to manage their staff, payroll, hiring and suppliers. For this data, the spa decides what is collected and why, and is responsible for it. Levictas stores and processes it only to provide the system to the spa, and does not use it for its own purposes.',
                ],
            ],
            [
                'id' => 'spa-data', // inventory B1–B4
                'group' => self::PART_B,
                'title' => 'What it includes',
                'blocks' => [
                    ['list' => [
                        '<strong>Staff and employee records:</strong> names, contact details, address, pay rates, payroll and payslips, attendance, leave, and government numbers (TIN, SSS, PhilHealth and Pag-IBIG), which are sensitive personal information.',
                        '<strong>Job applicants:</strong> what is sent with the Apply Now form (name, contact details, gender, date of birth, civil status, education, address, availability, an emergency contact and a résumé) and interview notes.',
                        '<strong>Supplier contacts:</strong> names and contact details.',
                    ]],
                ],
            ],
            [
                'id' => 'spa-data-access',
                'group' => self::PART_B,
                'title' => 'Who can see it',
                'blocks' => [
                    'The spa owner, and the staff roles the owner gives access to, limited to their branches. It is stored on the same servers as Part A, and it is not shared with other spas or used for advertising.',
                ],
            ],
            [
                'id' => 'job-applicants',
                'group' => self::PART_B,
                'title' => 'Job applicants',
                'blocks' => [
                    'The Apply Now form asks for your consent before you send an application. Your résumé is stored privately. Only the owner and HR staff of the spa you applied to can see your application.',
                ],
            ],
            [
                'id' => 'spa-data-retention',
                'group' => self::PART_B,
                'title' => 'How long it is kept',
                'blocks' => [
                    'It is kept for as long as the spa uses Levictas. Levictas does not delete it automatically; the spa can ask us to delete it.',
                ],
            ],
            [
                'id' => 'spa-data-requests',
                'group' => self::PART_B,
                'title' => 'Requests about this data',
                'blocks' => [
                    'Contact the spa first, since it decides about this data. You can also email us at {email}, and we will help or pass your request to the spa.',
                ],
            ],

            // ── General ──────────────────────────────────────────────────
            [
                'id' => 'changes',
                'group' => self::GENERAL,
                'title' => 'Changes to this policy',
                'blocks' => [
                    'We may update this Privacy Policy. The date at the top shows the latest version.',
                ],
            ],
            [
                'id' => 'contact',
                'group' => self::GENERAL,
                'title' => 'Contact us',
                'blocks' => [
                    'Email {email}, or write to Levictas, {address}. See also our {terms} and {refund}.',
                ],
            ],
        ];
    }
}
