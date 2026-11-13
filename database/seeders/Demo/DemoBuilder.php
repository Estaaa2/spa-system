<?php

namespace Database\Seeders\Demo;

use App\Models\Branch;
use App\Models\BranchProfile;
use App\Models\IdentityVerification;
use App\Models\OperatingHours;
use App\Models\Package;
use App\Models\Spa;
use App\Models\SpaVerificationDocument;
use App\Models\Staff;
use App\Models\Subscription;
use App\Models\Treatment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Small building blocks shared by the demo seeders: accounts, staff, spas,
 * branches, services, documents, identity checks, plan periods and the
 * placeholder images. Every image says SAMPLE / DEMO; no real person or
 * document is used. Rows are saved quietly (no emails, no notifications).
 */
final class DemoBuilder
{
    private int $nameIndex = 0;

    /** @var list<array{0: string, 1: string, 2: string, 3: string}> group, role, email, note */
    private array $accounts = [];

    public function __construct(
        public readonly string $passwordHash,
        public readonly ?User $admin,
    ) {
    }

    // ── People ─────────────────────────────────────────────────────────────

    /** The next invented [first, last] name (deterministic, never repeats a pair). */
    public function nextName(): array
    {
        $i = $this->nameIndex++;
        $firsts = DemoCatalog::FIRST_NAMES;
        $lasts = DemoCatalog::LAST_NAMES;

        // Step through first names 13 at a time (mixes women's and men's names) and
        // shift the last name each lap, so no first+last pair repeats.
        return [$firsts[($i * 13) % count($firsts)], $lasts[($i * 7 + intdiv($i, count($firsts))) % count($lasts)]];
    }

    public function user(string $local, string $role, ?Spa $spa = null, ?Branch $branch = null, array $attributes = [], ?Carbon $createdAt = null): User
    {
        [$first, $last] = isset($attributes['first_name']) ? [$attributes['first_name'], $attributes['last_name']] : $this->nextName();
        $createdAt ??= now();

        $user = new User();
        $user->forceFill([
            'first_name' => $first, 'last_name' => $last, 'email' => DemoCatalog::email($local),
            'password' => $this->passwordHash, 'status' => 'active',
            'email_verified_at' => $createdAt, 'is_owner' => $role === 'owner',
            'spa_id' => $spa?->id, 'branch_id' => $branch?->id,
            'phone' => '0917'.str_pad((string) (1000000 + crc32($local) % 8999999), 7, '0', STR_PAD_LEFT),
            'terms_version' => $this->termsVersion(), 'terms_accepted_at' => $createdAt,
            'created_at' => $createdAt, 'updated_at' => $createdAt,
        ] + array_diff_key($attributes, ['first_name' => 1, 'last_name' => 1]))->saveQuietly();

        $user->syncRoles([$role]);

        return $user;
    }

    public function staff(User $user, Spa $spa, Branch $branch, bool $performsTreatments = false, ?Carbon $hired = null): Staff
    {
        return $this->make(Staff::class, [
            'user_id' => $user->id, 'spa_id' => $spa->id, 'branch_id' => $branch->id,
            'employment_status' => 'active', 'performs_treatments' => $performsTreatments,
            'hire_date' => ($hired ?? now()->subYear())->toDateString(),
            'created_at' => $hired ?? now(), 'updated_at' => $hired ?? now(),
        ]);
    }

    /** Remember a login for the summary table printed at the end. */
    public function account(string $group, string $role, User $user, string $note = ''): void
    {
        $this->accounts[] = [$group, $role, $user->email, $note];
    }

    public function accounts(): array
    {
        return $this->accounts;
    }

    // ── Spa, branches, services ────────────────────────────────────────────

    public function spa(User $owner, string $name, array $attributes): Spa
    {
        $spa = $this->make(Spa::class, ['owner_id' => $owner->id, 'name' => $name] + $attributes);
        $owner->forceFill(['spa_id' => $spa->id])->saveQuietly();

        return $spa;
    }

    /**
     * @param array{0: string, 1: string, 2: string, 3: float, 4: float} $def name, city, street, lat, lng
     */
    public function branch(Spa $spa, string $spaKey, array $def, bool $main, string $status = 'verified', bool $listed = true,
        string $open = '10:00', string $close = '21:00', array $closedDays = [], ?Carbon $createdAt = null, bool $suite = false): Branch
    {
        [$name, $city, $street, $lat, $lng] = $def;
        $createdAt ??= now();

        $branch = $this->make(Branch::class, [
            'spa_id' => $spa->id, 'name' => $name, 'location' => "{$street}, {$city}, Cavite",
            'is_main' => $main, 'verification_status' => $status,
            'verified_at' => $status === 'verified' ? $createdAt->copy()->addDays(2) : null,
            'verified_by' => $status === 'verified' ? $this->admin?->id : null,
            'has_workforce_finance_suite' => $suite,
            'created_at' => $createdAt, 'updated_at' => $createdAt,
        ]);

        if ($main) {
            User::whereKey($spa->owner_id)->first()?->forceFill(['branch_id' => $branch->id])->saveQuietly();
        }

        foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] as $day) {
            OperatingHours::create([
                'branch_id' => $branch->id, 'day_of_week' => $day,
                'opening_time' => $open.':00', 'closing_time' => $close.':00',
                'is_closed' => in_array($day, $closedDays, true),
            ]);
        }

        $profile = new BranchProfile();
        $profile->forceFill([
            'branch_id' => $branch->id, 'is_listed' => $listed,
            'description' => "{$spa->name} — {$name} branch. A calm, air-conditioned space for massage and wellness in {$city}. This is demo data for the Levictas thesis presentation; bookings here are not real.",
            'phone' => '0917'.str_pad((string) (2000000 + $branch->id), 7, '0', STR_PAD_LEFT),
            'address' => "{$street}, {$city}, Cavite", 'city' => $city,
            'latitude' => $lat, 'longitude' => $lng,
            'amenities' => array_slice(['aircon', 'private_rooms', 'shower', 'parking', 'wifi', 'locker'], 0, 3 + $branch->id % 4),
            'cover_image' => $this->cover($spaKey, $branch->id, $spa->name, $name),
        ])->save();

        return $branch;
    }

    /**
     * @param list<string> $treatmentKeys keys of DemoCatalog::TREATMENTS
     * @param list<string> $packageKeys keys of DemoCatalog::PACKAGES (only those whose treatments exist)
     * @return array{treatments: array<string, Treatment>, packages: array<string, Package>}
     */
    public function services(Spa $spa, Branch $branch, array $treatmentKeys, array $packageKeys = [], float $priceFactor = 1.0): array
    {
        $treatments = [];
        foreach ($treatmentKeys as $key) {
            [$name, $minutes, $price, $home, , $description] = DemoCatalog::TREATMENTS[$key];
            $treatments[$key] = $this->make(Treatment::class, [
                'spa_id' => $spa->id, 'branch_id' => $branch->id, 'name' => $name, 'duration' => $minutes,
                'price' => round($price * $priceFactor / 10) * 10, 'description' => $description,
                'service_type' => $home ? 'in_branch_and_home' : 'in_branch_only',
            ]);
        }

        $packages = [];
        foreach ($packageKeys as $key) {
            [$name, $parts, $price, , $description] = DemoCatalog::PACKAGES[$key];
            if (array_diff($parts, $treatmentKeys) !== []) {
                continue;
            }
            $package = $this->make(Package::class, [
                'spa_id' => $spa->id, 'branch_id' => $branch->id, 'name' => $name, 'description' => $description,
                'price' => round($price * $priceFactor / 10) * 10,
                'total_duration' => array_sum(array_map(fn ($p) => DemoCatalog::TREATMENTS[$p][1], $parts)),
            ]);
            foreach ($parts as $part) {
                DB::table('package_treatment')->insert([
                    'package_id' => $package->id, 'treatment_id' => $treatments[$part]->id, 'quantity' => 1,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            $packages[$key] = $package;
        }

        return ['treatments' => $treatments, 'packages' => $packages];
    }

    // ── Plan periods ───────────────────────────────────────────────────────

    /** A paid plan period (fake payment: method "test", no PayMongo, not live). */
    public function paidPeriod(Spa $spa, string $tier, Carbon $start, Carbon $end, string $kind = 'new'): Subscription
    {
        $months = max(1, (int) round($start->diffInDays($end) / 30));

        return $this->make(Subscription::class, [
            'spa_id' => $spa->id, 'business_tier' => $tier, 'kind' => $kind,
            'amount' => (float) config("plans.{$tier}.monthly_price", 0) * $months, 'credit_amount' => 0,
            'billing_cycle' => 'monthly', 'payment_method' => 'test', 'livemode' => false,
            'payment_status' => 'paid', 'status' => $end->isFuture() ? 'active' : 'expired',
            'paid_at' => $start->copy()->subHours(2), 'starts_at' => $start, 'expires_at' => $end,
            'created_at' => $start->copy()->subHours(2), 'updated_at' => $start->copy()->subHours(2),
        ]);
    }

    /** Monthly periods back to back, from $start until one ends after $until. */
    public function paidMonths(Spa $spa, string $tier, Carbon $start, Carbon $until): Carbon
    {
        $kind = 'new';
        while ($start->lt($until)) {
            $end = $start->copy()->addMonthNoOverflow();
            $this->paidPeriod($spa, $tier, $start->copy(), $end->copy(), $kind);
            $kind = 'renewal';
            $start = $end;
        }

        return $start;
    }

    // ── Verification documents ─────────────────────────────────────────────

    /**
     * The four documents an owner uploads: Government ID and DTI/SEC for the spa,
     * BIR and Business Permit for each given branch.
     *
     * @param 'verified'|'pending'|'rejected' $state
     * @param list<Branch> $branches
     */
    public function documents(Spa $spa, array $branches, string $state, Carbon $uploadedAt, array $remarks = [], bool $spaDocuments = true): void
    {
        $slots = $spaDocuments ? [['government_id', null], ['dti_sec', null]] : [];
        foreach ($branches as $branch) {
            $slots[] = ['bir_certificate', $branch];
            $slots[] = ['business_permit', $branch];
        }

        $labels = [
            'government_id' => 'SAMPLE GOVERNMENT ID', 'dti_sec' => 'SAMPLE DTI CERTIFICATE',
            'bir_certificate' => 'SAMPLE BIR FORM 2303', 'business_permit' => 'SAMPLE MAYOR\'S PERMIT',
        ];
        $expiries = [
            'government_id' => $uploadedAt->copy()->addYears(4), 'dti_sec' => $uploadedAt->copy()->addYears(5),
            'bir_certificate' => null, 'business_permit' => Carbon::create($uploadedAt->year + 1, 1, 20),
        ];

        foreach ($slots as [$type, $branch]) {
            $dir = DemoCatalog::FILE_DIR.'/spa-documents/'.$spa->id;
            $path = $this->storeImage('local', $dir, $type.($branch ? '-b'.$branch->id : ''), $this->card(900, 620, [236, 230, 218], [
                $labels[$type].' - NOT A REAL DOCUMENT',
                $spa->name,
                $branch ? 'Branch: '.$branch->name : 'Owner / spa document',
                'Demo data for the Levictas thesis',
            ]));

            $expiry = $expiries[$type];
            $verified = $state === 'verified';
            $note = $remarks[$type] ?? null;

            $doc = new SpaVerificationDocument();
            $doc->forceFill([
                'spa_id' => $spa->id, 'branch_id' => $branch?->id, 'document_type' => $type,
                'file_path' => $path, 'file_name' => Str::slug($labels[$type]).'.jpg',
                'mime_type' => 'image/jpeg', 'file_size' => Storage::disk('local')->size($path),
                'owner_expiry_date' => $expiry?->toDateString(), 'owner_no_expiry' => false,
                'ocr_expiry_date' => null,
                'expiry_date' => $verified ? $expiry?->toDateString() : null,
                'expiry_detection_status' => $type === 'bir_certificate' ? 'not_required' : ($verified ? 'verified' : 'unavailable'),
                'expiry_verified_at' => $verified && $expiry ? $uploadedAt->copy()->addDays(2) : null,
                'expiry_verified_by' => $verified && $expiry ? $this->admin?->id : null,
                'review_remarks' => $note,
                'created_at' => $uploadedAt, 'updated_at' => $uploadedAt,
            ])->save();
        }
    }

    // ── Identity verification (staff) ──────────────────────────────────────

    public function photo(User $user, array $rgb = [120, 98, 70]): string
    {
        $image = imagecreatetruecolor(600, 600);
        imagefill($image, 0, 0, imagecolorallocate($image, ...$rgb));
        $small = imagecreatetruecolor(40, 20);
        imagefill($small, 0, 0, imagecolorallocate($small, ...$rgb));
        imagestring($small, 5, 2, 2, $user->initials(), imagecolorallocate($small, 255, 255, 255));
        imagecopyresized($image, $small, 100, 200, 0, 0, 400, 200, 40, 20);
        imagedestroy($small);
        imagestring($image, 5, 225, 560, 'DEMO PHOTO', imagecolorallocate($image, 255, 255, 255));

        $path = $this->storeImage(config('identity.disk', 'local'), DemoCatalog::FILE_DIR.'/profile-photos/'.$user->id, 'photo', $this->jpeg($image));
        $user->forceFill(['profile_photo_path' => $path, 'profile_photo_updated_at' => $user->created_at ?? now()])->saveQuietly();

        return $path;
    }

    /**
     * @param 'approved'|'pending'|'rejected'|'none' $state "none" = never submitted (no photo either)
     */
    public function identity(User $user, Spa $spa, string $state, Carbon $submittedAt): void
    {
        if ($state === 'none') {
            return;
        }

        $photo = $user->profile_photo_path ?: $this->photo($user, [80 + $user->id % 60, 100, 130 - $user->id % 40]);
        $base = [
            'user_id' => $user->id, 'spa_id' => $spa->id, 'id_type' => 'philid',
            'photo_path_reviewed' => $photo, 'name_reviewed' => $user->legalName(),
            'consent_version' => (string) config('identity.consent_version'),
            'consent_given_at' => $submittedAt, 'consent_channel' => 'web', 'submitted_at' => $submittedAt,
            'created_at' => $submittedAt, 'updated_at' => $submittedAt,
        ];
        $reviewed = [
            'reviewed_by' => $this->admin?->id, 'reviewed_at' => $submittedAt->copy()->addDay(),
            'files_purged_at' => $submittedAt->copy()->addDay(),
        ];

        match ($state) {
            'approved' => $this->make(IdentityVerification::class, $base + $reviewed + ['status' => IdentityVerification::STATUS_APPROVED]),
            'rejected' => $this->make(IdentityVerification::class, $base + $reviewed + [
                'status' => IdentityVerification::STATUS_REJECTED,
                'rejection_reason' => 'The ID photo is blurry, so the name cannot be read. Please retake it in good light.',
            ]),
            'pending' => $this->make(IdentityVerification::class, $base + $this->pendingFiles($user) + ['status' => IdentityVerification::STATUS_PENDING]),
        };

        if ($state === 'approved') {
            $user->forceFill(['identity_verified_at' => $submittedAt->copy()->addDay()])->saveQuietly();
        }
    }

    private function pendingFiles(User $user): array
    {
        $dir = DemoCatalog::FILE_DIR.'/identity/'.$user->id;
        $disk = config('identity.disk', 'local');

        return [
            'id_image_path' => $this->storeImage($disk, $dir, 'id', $this->card(900, 560, [235, 228, 214],
                ['SAMPLE ID - NOT A REAL DOCUMENT', 'Republic of the Philippines (sample)', $user->legalName(), 'ID number covered'])),
            'selfie_path' => $this->storeImage($disk, $dir, 'selfie', $this->card(600, 800, [200, 214, 226],
                ['SAMPLE SELFIE', 'holding the sample ID', $user->legalName()])),
        ];
    }

    // ── Images ─────────────────────────────────────────────────────────────

    private function cover(string $spaKey, int $branchId, string $spaName, string $branchName): string
    {
        $palettes = [[111, 84, 48], [139, 115, 85], [96, 108, 80], [92, 84, 110], [120, 90, 70], [70, 100, 110]];
        [$r, $g, $b] = $palettes[crc32($spaKey) % count($palettes)];

        $image = imagecreatetruecolor(1200, 600);
        for ($y = 0; $y < 600; $y++) {
            $shade = (int) (40 * $y / 600);
            imageline($image, 0, $y, 1199, $y, imagecolorallocate($image, min(255, $r + $shade), min(255, $g + $shade), min(255, $b + $shade)));
        }
        $white = imagecolorallocate($image, 255, 255, 255);
        $title = strtoupper(str_replace(DemoCatalog::NAME_SUFFIX, '', $spaName));
        $small = imagecreatetruecolor(8 * strlen($title) + 8, 20);
        imagecolortransparent($small, imagecolorallocate($small, 0, 0, 0));
        imagestring($small, 4, 4, 2, $title, imagecolorallocate($small, 255, 255, 255));
        imagecopyresized($image, $small, 60, 230, 0, 0, (8 * strlen($title) + 8) * 3, 60, 8 * strlen($title) + 8, 20);
        imagedestroy($small);
        imagestring($image, 5, 64, 310, $branchName.' branch - demo photo', $white);

        return $this->storeImage('public', DemoCatalog::FILE_DIR.'/covers', 'cover-'.$branchId, $this->jpeg($image));
    }

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
        imagejpeg($image, null, 82);
        imagedestroy($image);

        return (string) ob_get_clean();
    }

    private function storeImage(string $disk, string $dir, string $suffix, string $bytes): string
    {
        $path = $dir.'/'.Str::uuid().'-'.$suffix.'.jpg';
        Storage::disk($disk)->put($path, $bytes);

        return $path;
    }

    // ── Misc ───────────────────────────────────────────────────────────────

    private function termsVersion(): ?string
    {
        return \App\Support\Legal\Terms::VERSION;
    }

    /**
     * @template T of Model
     * @param class-string<T> $class
     * @return T
     */
    public function make(string $class, array $attributes): Model
    {
        $model = new $class();
        $model->forceFill($attributes)->save();

        return $model;
    }
}
