<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\IdentityVerification;
use App\Models\Spa;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * LOCAL TESTING ONLY — adds staff to the fake test spa in every identity-verification
 * state, so each screen can be checked (decision Q18/18b).
 *
 *   php artisan db:seed --class=PayrollTestAccountsSeeder         (first, once)
 *   php artisan db:seed --class=IdentityVerificationTestSeeder
 *
 * All accounts are in "Test Branch A", use the same password as the other test
 * accounts and the reserved @levictas.test domain. Photos, IDs and selfies are
 * generated placeholder images that say SAMPLE; no real person or document.
 *
 * PayrollTestAccountsRemoveSeeder removes these accounts with the rest of the
 * test spa (their verification rows go with them); `php artisan identity:purge`
 * then deletes the leftover image files.
 */
class IdentityVerificationTestSeeder extends Seeder
{
    /** key => [role, first, last, state, performs_treatments, has_photo] */
    private const PEOPLE = [
        'vera'  => ['therapist',    'Vera',  'Verified',            'approved',             false, true],
        'paolo' => ['therapist',    'Paolo', 'Photo Only',          'not_started',          false, true],
        'pia'   => ['therapist',    'Pia',   'Pending',             'pending',              false, true],
        'rey'   => ['therapist',    'Rey',   'Rejected',            'rejected',             false, true],
        'carlo' => ['therapist',    'Carlo', 'Changed Photo',       'needs_reverification', false, true],
        'wendy' => ['therapist',    'Wendy', 'Withdrew',            'withdrawn',            false, true],
        'rhea'  => ['receptionist', 'Rhea',  'Reception Therapist', 'approved',             true,  true],
        'nico'  => ['therapist',    'Nico',  'No Photo',            'not_started',          false, false],
    ];

    /** @var list<array{0: string, 1: string, 2: string}> */
    private array $accountTable = [];

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->error('IdentityVerificationTestSeeder refuses to run in production.');

            return;
        }

        $spa = Spa::withoutGlobalScopes()->where('name', PayrollTestAccountsSeeder::SPA_NAME)->first();

        if (! $spa) {
            $this->command?->error('Run PayrollTestAccountsSeeder first: these accounts are added to its test spa.');

            return;
        }

        if (User::where('email', 'like', 'idv.%@levictas.test')->exists()) {
            $this->command?->warn('The identity test accounts already exist. Run PayrollTestAccountsResetSeeder, then this seeder, for a clean copy.');

            return;
        }

        $branch = Branch::withoutGlobalScopes()->where('spa_id', $spa->id)->orderByDesc('is_main')->orderBy('id')->firstOrFail();
        $reviewer = User::whereHas('roles', fn ($q) => $q->where('name', 'admin'))->orderBy('id')->first();

        DB::transaction(function () use ($spa, $branch, $reviewer) {
            foreach (self::PEOPLE as $key => [$role, $first, $last, $state, $performs, $hasPhoto]) {
                $this->seedPerson($spa, $branch, $reviewer, $key, $role, $first, $last.' (test)', $state, $performs, $hasPhoto);
            }
        });

        $this->command?->info('Identity test accounts created. Password: '.PayrollTestAccountsSeeder::PASSWORD);
        $this->command?->table(['Email', 'Role', 'Verification state'], $this->accountTable);

        if (! $reviewer) {
            $this->command?->warn('No admin account found, so approved/rejected rows have no reviewer. Run AdminSeeder to add one.');
        }
    }

    private function seedPerson(Spa $spa, Branch $branch, ?User $reviewer, string $key, string $role, string $first, string $last, string $state, bool $performs, bool $hasPhoto): void
    {
        $email = "idv.{$key}@levictas.test";
        $now = Carbon::now();

        $user = $this->make(User::class, [
            'first_name' => $first, 'last_name' => $last, 'email' => $email,
            'password' => Hash::make(PayrollTestAccountsSeeder::PASSWORD), 'status' => 'active',
            'email_verified_at' => $now, 'spa_id' => $spa->id, 'branch_id' => $branch->id,
        ]);
        $user->assignRole($role);

        $this->make(Staff::class, [
            'user_id' => $user->id, 'spa_id' => $spa->id, 'branch_id' => $branch->id,
            'employment_status' => 'active', 'performs_treatments' => $performs,
            'hire_date' => $now->copy()->subMonths(3)->toDateString(),
        ]);

        $photo = $hasPhoto ? $this->photo($user, [90, 115, 140]) : null;
        $user->forceFill([
            'profile_photo_path' => $photo,
            'profile_photo_updated_at' => $photo ? $now : null,
        ])->saveQuietly();

        $submittedAt = $now->copy()->subDays(3);
        $base = [
            'user_id' => $user->id, 'spa_id' => $spa->id, 'id_type' => 'philid',
            'photo_path_reviewed' => $photo, 'name_reviewed' => $user->legalName(),
            'consent_version' => (string) config('identity.consent_version'),
            'consent_given_at' => $submittedAt, 'consent_channel' => 'web',
            'submitted_at' => $submittedAt,
        ];
        $decided = [
            'reviewed_by' => $reviewer?->id, 'reviewed_at' => $submittedAt->copy()->addDay(),
            'files_purged_at' => $submittedAt->copy()->addDay(),
        ];

        switch ($state) {
            case 'approved':
                $this->make(IdentityVerification::class, $base + $decided + ['status' => IdentityVerification::STATUS_APPROVED]);
                $user->forceFill(['identity_verified_at' => $submittedAt->copy()->addDay()])->saveQuietly();
                break;

            case 'pending':
                $this->make(IdentityVerification::class, $base + $this->pendingFiles($user) + ['status' => IdentityVerification::STATUS_PENDING]);
                break;

            case 'rejected':
                $this->make(IdentityVerification::class, $base + $decided + [
                    'status' => IdentityVerification::STATUS_REJECTED,
                    'rejection_reason' => 'The ID photo is blurry, so the name cannot be read. Please retake it in good light.',
                ]);
                break;

            case 'needs_reverification':
                // Was approved with an older photo, then uploaded a new one and submitted again.
                $this->make(IdentityVerification::class, [
                    ...$base, ...$decided,
                    'photo_path_reviewed' => 'profile-photos/'.$user->id.'/replaced-photo.jpg',
                    'submitted_at' => $now->copy()->subDays(20), 'consent_given_at' => $now->copy()->subDays(20),
                    'reviewed_at' => $now->copy()->subDays(19),
                    'status' => IdentityVerification::STATUS_SUPERSEDED,
                    'superseded_reason' => IdentityVerification::SUPERSEDED_PHOTO_CHANGED,
                ]);
                $this->make(IdentityVerification::class, [
                    ...$base, ...$this->pendingFiles($user),
                    'status' => IdentityVerification::STATUS_PENDING,
                    'submitted_at' => $now->copy()->subHours(5), 'consent_given_at' => $now->copy()->subHours(5),
                ]);
                break;

            case 'withdrawn':
                $this->make(IdentityVerification::class, $base + $decided + [
                    'status' => IdentityVerification::STATUS_WITHDRAWN,
                    'consent_withdrawn_at' => $now->copy()->subDay(),
                ]);
                break;
        }

        $label = $state === 'needs_reverification' ? 'pending (re-review after a photo change)' : $state;
        $this->accountTable[] = [$email, $role.($performs ? ' + performs treatments' : ''), $label];
    }

    /** @return array{id_image_path: string, selfie_path: string} */
    private function pendingFiles(User $user): array
    {
        $dir = config('identity.directories.verifications', 'identity-verifications').'/'.$user->id;

        return [
            'id_image_path' => $this->store($dir, 'id', $this->card(900, 560, [235, 228, 214], ['SAMPLE ID - NOT A REAL DOCUMENT', 'Republic of the Philippines (sample)', $user->legalName(), 'ID number covered'])),
            'selfie_path' => $this->store($dir, 'selfie', $this->card(600, 800, [200, 214, 226], ['SAMPLE SELFIE', 'holding the sample ID', $user->legalName()])),
        ];
    }

    private function photo(User $user, array $rgb): string
    {
        $dir = config('identity.directories.profile_photos', 'profile-photos').'/'.$user->id;
        $image = imagecreatetruecolor(600, 600);
        imagefill($image, 0, 0, imagecolorallocate($image, ...$rgb));

        // GD's built-in font is tiny: draw the initials small, then scale them up.
        $small = imagecreatetruecolor(40, 20);
        imagefill($small, 0, 0, imagecolorallocate($small, ...$rgb));
        imagestring($small, 5, 2, 2, $user->initials(), imagecolorallocate($small, 255, 255, 255));
        imagecopyresized($image, $small, 100, 200, 0, 0, 400, 200, 40, 20);
        imagestring($image, 5, 220, 560, 'SAMPLE PHOTO', imagecolorallocate($image, 255, 255, 255));
        imagedestroy($small);

        return $this->store($dir, '', $this->jpeg($image));
    }

    /** A plain placeholder "document" with a few lines of text. */
    private function card(int $width, int $height, array $rgb, array $lines): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, ...$rgb));
        $ink = imagecolorallocate($image, 60, 47, 35);
        imagerectangle($image, 10, 10, $width - 11, $height - 11, $ink);

        foreach ($lines as $i => $line) {
            imagestring($image, 5, 40, 60 + $i * 40, $line, $ink);
        }

        return $this->jpeg($image);
    }

    private function jpeg(\GdImage $image): string
    {
        ob_start();
        imagejpeg($image, null, 85);
        imagedestroy($image);

        return (string) ob_get_clean();
    }

    private function store(string $dir, string $suffix, string $bytes): string
    {
        $path = $dir.'/'.Str::uuid().($suffix !== '' ? '-'.$suffix : '').'.jpg';
        Storage::disk(config('identity.disk', 'local'))->put($path, $bytes);

        return $path;
    }

    /**
     * Same helper as PayrollTestAccountsSeeder: explicit values, bypassing
     * $fillable and global scopes.
     *
     * @template T of Model
     * @param  class-string<T>  $class
     * @return T
     */
    private function make(string $class, array $attributes): Model
    {
        $model = new $class();
        $model->forceFill($attributes)->save();

        return $model;
    }
}
