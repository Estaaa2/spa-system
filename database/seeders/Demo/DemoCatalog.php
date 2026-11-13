<?php

namespace Database\Seeders\Demo;

/**
 * Everything the demo seeders need to know about WHICH data is demo data:
 * the spa names, the email domain and the file folder. The Remove seeder
 * uses only this class to find what to delete, so nothing outside these
 * names is ever touched.
 */
final class DemoCatalog
{
    /** Every demo login ends with this. ".test" is reserved and never delivers mail. */
    public const EMAIL_DOMAIN = 'levictas-demo.test';

    /** Folder (on the local and public disks) that holds every demo image. */
    public const FILE_DIR = 'demo-data';

    /** Shown on demo spas so customers can tell. */
    public const NAME_SUFFIX = ' (Demo)';

    /**
     * key => [spa name, situation, branches]
     * branch = [name, city, street, latitude, longitude]
     */
    public const SPAS = [
        'serenity' => ['Serenity Wellness Spa', 'showcase_premium', [
            ['Imus', 'Imus', 'Aguinaldo Hwy, Anabu II-D', 14.4064, 120.9405],
            ['Bacoor', 'Bacoor', 'Molino Blvd, Molino III', 14.4126, 120.9763],
            ['Dasmariñas', 'Dasmariñas', 'Governor\'s Drive, Salitran II', 14.3270, 120.9530],
            ['Silang', 'Silang', 'J.P. Rizal St, Poblacion', 14.2306, 120.9750],   // new, waiting for verification
        ]],
        'hilot' => ['Hilot Haven Spa', 'showcase_basic', [
            ['General Trias', 'General Trias', 'Arnaldo Hwy, Pasong Camachile I', 14.3869, 120.8817],
            ['Kawit', 'Kawit', 'Tirona Hwy, Binakayan', 14.4446, 120.9020],
        ]],
        'lakbay' => ['Lakbay Relaxation Spa', 'trialing', [
            ['Tagaytay', 'Tagaytay', 'Aguinaldo Hwy, Kaybagal South', 14.1153, 120.9621],
        ]],
        'tahimik' => ['Tahimik Touch Spa', 'trial_ended', [
            ['Tanza', 'Tanza', 'A. Soriano Hwy, Daang Amaya', 14.3944, 120.8531],
        ]],
        'banayad' => ['Banayad Massage Spa', 'grace', [
            ['Rosario', 'Rosario', 'Gen. Trias Drive, Tejeros Convention', 14.4167, 120.8500],
        ]],
        'ginhawa' => ['Ginhawa Body & Soul Spa', 'lapsed', [
            ['Trece Martires', 'Trece Martires', 'Capitol Rd, San Agustin', 14.2814, 120.8670],
        ]],
        'bagong' => ['Bagong Simula Spa', 'no_trial_yet', [
            ['Carmona', 'Carmona', 'Governor\'s Drive, Maduya', 14.3132, 121.0576],
        ]],
        'kalinaw' => ['Kalinaw Wellness Spa', 'verification_pending', [
            ['GMA', 'General Mariano Alvarez', 'Congressional Rd, Poblacion 1', 14.2930, 121.0060],
        ]],
        'payapa' => ['Payapa Spa & Wellness', 'verification_rejected', [
            ['Naic', 'Naic', 'Naic-Indang Rd, Ibayo Silangan', 14.3180, 120.7660],
        ]],
        'unang' => ['Unang Hakbang Spa', 'just_registered', [
            ['Noveleta', 'Noveleta', 'Magdiwang Hwy, Poblacion', 14.4290, 120.8800],
        ]],
        'luntian' => ['Luntian Healing Spa', 'no_verified_therapists', [
            ['Indang', 'Indang', 'San Gregorio St, Poblacion 1', 14.1960, 120.8770],
        ]],
        'kalma' => ['Kalma Spa Express', 'basic_at_limit', [
            ['Cavite City', 'Cavite City', 'P. Burgos Ave, Caridad', 14.4791, 120.8970],
            ['Amadeo', 'Amadeo', 'Amadeo-Tagaytay Rd, Poblacion', 14.1700, 120.9230],
            ['Alfonso', 'Alfonso', 'Mabini St, Poblacion 2', 14.1380, 120.8550],
        ]],
    ];

    /** @return list<string> every demo spa name as stored */
    public static function spaNames(): array
    {
        return array_map(fn (array $spa) => $spa[0].self::NAME_SUFFIX, array_values(self::SPAS));
    }

    public static function spaName(string $key): string
    {
        return self::SPAS[$key][0].self::NAME_SUFFIX;
    }

    public static function email(string $local): string
    {
        return $local.'@'.self::EMAIL_DOMAIN;
    }

    /** SQL LIKE pattern matching every demo email. */
    public static function emailPattern(): string
    {
        return '%@'.self::EMAIL_DOMAIN;
    }

    /**
     * Services offered by the demo spas.
     * key => [name, minutes, price, home service?, demand weight, description]
     */
    public const TREATMENTS = [
        'swedish'  => ['Swedish Massage', 60, 700, true, 24, 'Long, flowing strokes with light to medium pressure to relax the whole body.'],
        'hilot'    => ['Traditional Filipino Hilot', 60, 650, true, 18, 'Filipino healing massage with warm coconut oil and banana leaves.'],
        'shiatsu'  => ['Shiatsu', 60, 750, false, 9, 'Finger and palm pressure along the body\'s pressure points.'],
        'hotstone' => ['Hot Stone Massage', 90, 1200, false, 7, 'Heated basalt stones melt deep muscle tension.'],
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

    /** First names / last names for demo people (invented combinations). */
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
