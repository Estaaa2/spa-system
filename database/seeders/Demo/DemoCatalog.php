<?php

namespace Database\Seeders\Demo;

/**
 * Everything the demo seeders need to know about WHICH data is demo data:
 * the spa names, the email domains and the file folder. The Remove seeder
 * uses only this class to find what to delete, so nothing outside these
 * names is ever touched.
 *
 * Emails look real (juan.delacruz@serenityspa.test) but every domain ends in
 * ".test", a reserved name that never receives mail, so no real person can
 * get a message from the demo.
 */
final class DemoCatalog
{
    /** Domain of the demo customers. Remove only deletes the exact customer emails listed below. */
    public const CUSTOMER_DOMAIN = 'mail.test';

    /** Folder (on the local and public disks) that holds every demo image. */
    public const FILE_DIR = 'demo-data';

    /** Shown on demo spas so customers can tell. */
    public const NAME_SUFFIX = ' (Demo)';

    /**
     * key => [spa name, situation, staff email domain, branches]
     * branch = [name, city, street, latitude, longitude]
     */
    public const SPAS = [
        'serenity' => ['Serenity Wellness Spa', 'premium', 'serenityspa.test', [
            ['Imus', 'Imus', 'Aguinaldo Hwy, Anabu II-D', 14.4064, 120.9405],
            ['Bacoor', 'Bacoor', 'Molino Blvd, Molino III', 14.4126, 120.9763],
            ['Dasmariñas', 'Dasmariñas', 'Governor\'s Drive, Salitran II', 14.3270, 120.9530],
        ]],
        'hilot' => ['Hilot Haven Spa', 'basic', 'hilothaven.test', [
            ['General Trias', 'General Trias', 'Arnaldo Hwy, Pasong Camachile I', 14.3869, 120.8817],
            ['Kawit', 'Kawit', 'Tirona Hwy, Binakayan', 14.4446, 120.9020],
        ]],
        'ginhawa' => ['Ginhawa Body & Soul Spa', 'lapsed', 'ginhawaspa.test', [
            ['Trece Martires', 'Trece Martires', 'Capitol Rd, San Agustin', 14.2814, 120.8670],
        ]],
        'kalinaw' => ['Kalinaw Wellness Spa', 'verification_pending', 'kalinawwellness.test', [
            ['GMA', 'General Mariano Alvarez', 'Congressional Rd, Poblacion 1', 14.2930, 121.0060],
        ]],
    ];

    /**
     * Demo data from the first version (12 spas, @levictas-demo.test). Remove also
     * deletes these so a Reset on a server that still has them leaves one clean copy.
     */
    public const LEGACY_SPA_NAMES = [
        'Serenity Wellness Spa', 'Hilot Haven Spa', 'Lakbay Relaxation Spa', 'Tahimik Touch Spa', 'Banayad Massage Spa',
        'Ginhawa Body & Soul Spa', 'Bagong Simula Spa', 'Kalinaw Wellness Spa', 'Payapa Spa & Wellness', 'Unang Hakbang Spa',
        'Luntian Healing Spa', 'Kalma Spa Express',
    ];
    public const LEGACY_EMAIL_DOMAIN = 'levictas-demo.test';

    /** @return list<string> every demo spa name as stored (current + first version) */
    public static function spaNames(): array
    {
        return array_values(array_unique(array_map(fn (string $name) => $name.self::NAME_SUFFIX,
            [...array_column(self::SPAS, 0), ...self::LEGACY_SPA_NAMES])));
    }

    public static function spaName(string $key): string
    {
        return self::SPAS[$key][0].self::NAME_SUFFIX;
    }

    public static function domain(string $spaKey): string
    {
        return self::SPAS[$spaKey][2];
    }

    /** "Juan", "Dela Cruz", "serenityspa.test" → juan.delacruz@serenityspa.test */
    public static function email(string $first, string $last, string $domain): string
    {
        $slug = fn (string $s) => preg_replace('/[^a-z]/', '', strtolower(str_replace(['ñ', 'Ñ'], 'n', $s)));

        return $slug($first).'.'.$slug($last).'@'.$domain;
    }

    /** @return list<string> SQL LIKE patterns of the staff domains (current + first version) */
    public static function staffEmailPatterns(): array
    {
        return [...array_map(fn (array $spa) => '%@'.$spa[2], array_values(self::SPAS)), '%@'.self::LEGACY_EMAIL_DOMAIN];
    }

    /**
     * Limit a users / password_reset_tokens query to demo emails: the staff
     * domains (any address) plus the exact customer list.
     *
     * @template Q of \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder
     * @param Q $query
     * @return Q
     */
    public static function whereDemoEmail($query)
    {
        return $query->where(function ($q) {
            foreach (self::staffEmailPatterns() as $pattern) {
                $q->orWhere('email', 'like', $pattern);
            }
            $q->orWhereIn('email', self::customerEmails());
        });
    }

    /** @return list<array{0: string, 1: string, 2: string}> [first, last, email] of every demo customer */
    public static function customers(): array
    {
        $out = [];
        foreach (self::CUSTOMER_NAMES as [$first, $last]) {
            $out[] = [$first, $last, self::email($first, $last, self::CUSTOMER_DOMAIN)];
        }

        return $out;
    }

    /** @return list<string> */
    public static function customerEmails(): array
    {
        return array_column(self::customers(), 2);
    }

    /**
     * Services offered by the demo spas.
     * key => [name, minutes, price, home service?, demand weight, description]
     */
    public const TREATMENTS = [
        'swedish'  => ['Swedish Massage', 60, 700, true, 24, 'Long, flowing strokes with light to medium pressure to relax the whole body.'],
        'hilot'    => ['Traditional Filipino Hilot', 60, 650, true, 18, 'Filipino healing massage with warm coconut oil and banana leaves.'],
        'shiatsu'  => ['Shiatsu', 60, 750, false, 9, 'Finger and palm pressure along the body\'s pressure points.'],
        'hotstone' => ['Hot Stone Massage', 90, 1200, false, 9, 'Heated basalt stones melt deep muscle tension.'],
        'ventosa'  => ['Ventosa Cupping Therapy', 60, 800, false, 8, 'Cupping with massage to ease back pain and improve circulation.'],
        'foot'     => ['Foot Reflexology', 45, 450, true, 14, 'Pressure on reflex points of the feet to relieve tiredness.'],
        'aroma'    => ['Aromatherapy Massage', 90, 1000, true, 8, 'Full-body massage with lavender or eucalyptus essential oil.'],
        'scrub'    => ['Body Scrub & Glow', 45, 600, false, 5, 'Salt and coconut scrub that leaves the skin smooth.'],
        'back'     => ['Back & Shoulder Massage', 30, 350, true, 12, 'Quick relief for stiff neck, shoulders and upper back.'],
        'prenatal' => ['Prenatal Massage', 60, 850, true, 3, 'Gentle side-lying massage for expecting mothers (2nd trimester onward).'],
    ];

    /** key => [name, treatments, price, demand weight, description] */
    public const PACKAGES = [
        'renew'    => ['Relax & Renew', ['swedish', 'foot'], 1050, 6, 'Swedish massage followed by foot reflexology.'],
        'heritage' => ['Hilot Heritage', ['hilot', 'ventosa'], 1300, 4, 'Traditional hilot paired with ventosa cupping.'],
        'pamper'   => ['Total Pamper', ['scrub', 'aroma'], 1450, 3, 'Body scrub, then a full aromatherapy massage.'],
    ];

    /** Staff names, used in order (one list for all spas, so nobody appears twice). */
    public const STAFF_NAMES = [
        ['Celeste', 'Aguinaldo'], ['Ramon', 'Villanueva'], ['Patricia', 'Mendoza'], ['Mark Anthony', 'Reyes'],
        ['Kristine', 'Bautista'], ['Joel', 'Garcia'], ['Angelica', 'Ramos'], ['Rowena', 'Castillo'],
        ['Jerome', 'Navarro'], ['Liza', 'Fernandez'], ['Arnel', 'Ocampo'], ['Mylene', 'Pascual'],
        ['Ricardo', 'Santiago'], ['Jocelyn', 'Torres'], ['Dennis', 'Aquino'], ['Marites', 'Dizon'],
        ['Christian', 'Lopez'], ['Grace', 'Manalo'], ['Rodel', 'Mercado'], ['Shiela', 'Domingo'],
        ['Noel', 'Gonzales'], ['Jennifer', 'Salazar'], ['Edgar', 'Tolentino'], ['Lorna', 'Valdez'],
        ['Rommel', 'Soriano'], ['Analyn', 'Cabrera'], ['Gilbert', 'Del Rosario'], ['Maricel', 'Ilagan'],
        ['Ronaldo', 'Javier'], ['Cherry', 'Rivera'], ['Teresita', 'Lacson'], ['Alvin', 'De Guzman'],
        ['Rosalie', 'Manansala'], ['Efren', 'Bonifacio'], ['Lourdes', 'Sevilla'], ['Jayson', 'Tamayo'],
        ['Divina', 'Alcantara'], ['Romeo', 'Lualhati'], ['Melody', 'Samson'], ['Armando', 'Reyes'],
    ];

    /** Customer names (exact list: Remove deletes only these emails at mail.test). */
    public const CUSTOMER_NAMES = [
        ['Maria', 'Santos'], ['Juan', 'Dela Cruz'], ['Andrea', 'Lim'], ['Paolo', 'Herrera'], ['Camille', 'Robles'],
        ['Miguel', 'Fajardo'], ['Bea', 'Alonzo'], ['Carlo', 'Estrada'], ['Danica', 'Morales'], ['Francis', 'Bernardo'],
        ['Hannah', 'Concepcion'], ['Jasper', 'Padilla'], ['Kim', 'Velasco'], ['Lea', 'Marquez'], ['Nathan', 'Cortez'],
        ['Nicole', 'Panganiban'], ['Oscar', 'Saldana'], ['Pia', 'Evangelista'], ['Rafael', 'Guevarra'], ['Sophia', 'Ignacio'],
        ['Trisha', 'Mangubat'], ['Vince', 'Arellano'], ['Wendy', 'Caballero'], ['Xavier', 'Lagman'], ['Ysabel', 'Natividad'],
        ['Aaron', 'Briones'], ['Bianca', 'Cuevas'], ['Cedric', 'Dimaculangan'], ['Diane', 'Esguerra'], ['Earl', 'Fuentes'],
        ['Faith', 'Gatchalian'], ['Gian', 'Hidalgo'], ['Ivy', 'Jimenez'], ['Joanna', 'Katigbak'], ['Kevin', 'Laurel'],
        ['Lovely', 'Macaraeg'], ['Marvin', 'Nepomuceno'], ['Nina', 'Olivares'], ['Ralph', 'Pineda'], ['Queenie', 'Quintos'],
        ['Rhea', 'Sarmiento'], ['Sherwin', 'Tan'], ['Tina', 'Umali'], ['Ulysses', 'Vergara'], ['Vina', 'Yap'],
        ['Warren', 'Zamora'], ['Abigail', 'Cruz'], ['Benjie', 'Ramirez'], ['Clarisse', 'Flores'], ['Dominic', 'Gomez'],
        ['Erika', 'Sison'], ['Ferdinand', 'Tiongson'], ['Gela', 'Uy'], ['Harold', 'Vasquez'], ['Iris', 'Yambao'],
        ['Jun', 'Abad'], ['Karla', 'Bustamante'], ['Leo', 'Calderon'], ['Mae', 'Delos Santos'], ['Nestor', 'Espiritu'],
        ['Olive', 'Francisco'], ['Pauline', 'Galang'], ['Ramil', 'Hernandez'], ['Stephanie', 'Ilustre'], ['Tristan', 'Jacinto'],
        ['Ursula', 'Legaspi'], ['Vanessa', 'Magbanua'], ['Wilfredo', 'Nolasco'], ['Yvonne', 'Orosco'], ['Zandro', 'Pangilinan'],
        ['Aileen', 'Quiambao'], ['Brian', 'Roxas'], ['Cristina', 'Samonte'], ['Darwin', 'Tuazon'], ['Elaine', 'Ventura'],
        ['Gerald', 'Yulo'], ['Hazel', 'Agustin'], ['Ian', 'Buenaventura'], ['Jessa', 'Carandang'], ['Kenneth', 'Dacanay'],
        ['Lyka', 'Enriquez'], ['Manny', 'Feliciano'], ['Nikka', 'Gutierrez'], ['Orlando', 'Ho'], ['Precious', 'Ibarra'],
        ['Reggie', 'Joson'], ['Sarah', 'Kalaw'], ['Ted', 'Luna'], ['Uriel', 'Manuel'], ['Veronica', 'Nicolas'],
    ];

    /** First / last names for walk-in guests (no account; name on the booking only). */
    public const FIRST_NAMES = [
        'Andrea', 'Bea', 'Camille', 'Danica', 'Erika', 'Faith', 'Gela', 'Hannah', 'Ivy', 'Joanna',
        'Kristine', 'Lovely', 'Mae', 'Nicole', 'Patricia', 'Rhea', 'Shiela', 'Trisha', 'Vina', 'Ysa',
        'Aldrin', 'Bryan', 'Carlo', 'Dennis', 'Earl', 'Francis', 'Gino', 'Harold', 'Jerome', 'Kevin',
        'Leo', 'Marvin', 'Nestor', 'Paolo', 'Ramil', 'Sherwin', 'Tristan', 'Vince', 'Warren', 'Zandro',
    ];

    public const LAST_NAMES = [
        'Abad', 'Bautista', 'Castillo', 'Dela Cruz', 'Espiritu', 'Fernandez', 'Garcia', 'Hernandez',
        'Ilagan', 'Javier', 'Lacson', 'Manalo', 'Navarro', 'Ocampo', 'Pascual', 'Quiambao', 'Ramos',
        'Santos', 'Tolentino', 'Umali', 'Valdez', 'Villanueva', 'Yap', 'Zamora', 'Aquino', 'Mendoza',
        'Reyes', 'Cruz', 'Gonzales', 'Torres',
    ];
}
