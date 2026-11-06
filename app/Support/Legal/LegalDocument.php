<?php

namespace App\Support\Legal;

/**
 * Base for the three legal documents (Terms, Privacy Policy, Refund Policy).
 *
 * Each document is plain PHP data, so the full page, the registration and
 * booking modals, and the reviewer copy all render the same words
 * (landing footer Phase 2, D8 / Q5-A). Same pattern as
 * App\Support\Identity\ConsentNotice.
 *
 * Section shape:
 *   [
 *     'id'       => 'cancellation',            // anchor and key
 *     'title'    => 'Cancelling a booking',
 *     'group'    => 'Part A — …',              // optional: a heading above a run of sections
 *     'audience' => ['customers', 'bookings'],  // optional (Terms): which modals show it
 *     'blocks'   => [ 'a paragraph', ['list' => ['item', 'item']] ],
 *   ]
 *
 * Text is trusted HTML written in this repository (it may use <strong>).
 * Tokens like {email} or {privacy} are replaced by fill(); contact values
 * are escaped there.
 */
abstract class LegalDocument
{
    /** Bump together with any change to the wording. Recorded on acceptance. */
    public const VERSION = '';

    /** Shown as "Last updated". */
    public const UPDATED = '';

    abstract public static function title(): string;

    /** @return list<string> */
    abstract protected static function introBlocks(): array;

    /** @return list<array> */
    abstract protected static function allSections(): array;

    /** Sections with tokens unfilled — for a document that reuses another's sections. */
    public static function rawSections(): array
    {
        return static::allSections();
    }

    /** @return list<string> */
    public static function intro(): array
    {
        return array_map(fn ($b) => static::fill($b), static::introBlocks());
    }

    /**
     * Sections with tokens filled in.
     *
     * @param  list<string>|null  $ids        only these section ids, in this order
     * @param  list<string>|null  $audiences  only sections tagged with one of these
     */
    public static function sections(?array $ids = null, ?array $audiences = null): array
    {
        $sections = static::allSections();

        if ($ids !== null) {
            $byId = collect($sections)->keyBy('id');
            $sections = array_values(array_filter(array_map(fn ($id) => $byId->get($id), $ids)));
        }

        if ($audiences !== null) {
            $sections = array_values(array_filter(
                $sections,
                fn ($s) => array_intersect($s['audience'] ?? [], $audiences) !== []
            ));
        }

        return array_map(function (array $s) {
            $s['title'] = static::fill($s['title']);
            $s['blocks'] = array_map(
                fn ($b) => is_array($b)
                    ? ['list' => array_map(fn ($i) => static::fill($i), $b['list'])]
                    : static::fill($b),
                $s['blocks']
            );

            return $s;
        }, $sections);
    }

    /** Everything the page view needs. */
    public static function document(): array
    {
        return [
            'title'    => static::title(),
            'version'  => static::VERSION,
            'updated'  => static::UPDATED,
            'intro'    => static::intro(),
            'sections' => static::sections(),
        ];
    }

    /** Replaces {tokens} with links and contact details. */
    public static function fill(string $text): string
    {
        $email = Contact::email();
        $emailHtml = Contact::isPlaceholder($email)
            ? e($email)
            : '<a href="mailto:' . e($email) . '" class="legal-link">' . e($email) . '</a>';

        $link = fn (string $route, string $label) =>
            '<a href="' . route($route) . '" class="legal-link">' . $label . '</a>';

        return strtr($text, [
            '{email}'           => $emailHtml,
            '{address}'         => e(Contact::address()),
            '{team}'            => e(Contact::team()),
            '{school}'          => e(Contact::school()),
            '{terms}'           => $link('legal.terms', 'Terms and Conditions'),
            '{privacy}'         => $link('legal.privacy', 'Privacy Policy'),
            '{refund}'          => $link('legal.refund', 'Refund Policy'),
            '{npc}'             => '<a href="https://privacy.gov.ph" class="legal-link" target="_blank" rel="noopener noreferrer">privacy.gov.ph</a>',
            '{grace_days}'      => (string) (int) config('subscription.grace_days', 3),
            '{trial_months}'    => (string) (int) config('subscription.trial_months', 1),
            '{id_days}'         => (string) (int) config('identity.pending_max_days', 30),
            '{session_minutes}' => (string) (int) config('session.lifetime', 120),
        ]);
    }
}
