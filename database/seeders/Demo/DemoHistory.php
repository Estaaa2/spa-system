<?php

namespace Database\Seeders\Demo;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\Spa;
use App\Models\Staff;
use App\Models\User;
use App\Services\Staff\WorkScheduleWriter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Day-by-day history for one spa: work schedules, attendance and leave
 * (Premium only), bookings with realistic peaks, payments and ratings.
 *
 * The numbers are random but repeatable (fixed seed), and shaped so the
 * Insights / DSS pages have something to say: weekends and paydays are
 * busier, 2–4 PM and 6–8 PM are the peak hours, Swedish and Hilot are the
 * most booked services, and demand grows a little month to month.
 *
 * Branch plan (one per branch):
 *   branch       Branch
 *   services     ['treatments' => [key => Treatment], 'packages' => [key => Package]]
 *   therapists   list<User>   people who do treatments (therapists, receptionists who also treat)
 *   staff        list<array{user: User, staff: Staff, role: string}>  everyone with a staff row
 *   reception    ?User         who records walk-ins
 *   manager      ?User         who marks manual attendance and reviews leave
 *   volume       float         average bookings per day (weekday base)
 *   open, close  int           opening hours (24h)
 *   quality      array<int, float> therapist user id => average star rating
 */
final class DemoHistory
{
    /** Day-of-week demand (Carbon: 0 = Sunday). */
    private const DOW = [0 => 1.35, 1 => 0.8, 2 => 0.75, 3 => 0.85, 4 => 0.9, 5 => 1.15, 6 => 1.45];

    /** Hour-of-day demand; hours outside the branch's opening are ignored. */
    private const HOURS = [9 => 0.4, 10 => 0.5, 11 => 0.6, 12 => 0.6, 13 => 0.8, 14 => 1.1, 15 => 1.2, 16 => 1.0,
        17 => 0.9, 18 => 1.3, 19 => 1.4, 20 => 1.0, 21 => 0.3];

    private const REVIEWS_GOOD = [
        'Very relaxing, my back pain is gone.', 'Magaling po si therapist, babalik ako!', 'Clean room and friendly staff.',
        'Perfect pressure, I almost fell asleep.', 'Sulit sa presyo. Highly recommended.', 'On time and very professional.',
        'Best hilot in Cavite!', 'Will book again next payday.', 'Nice ambiance, malamig at tahimik.', '',
    ];
    private const REVIEWS_OK = ['Okay naman, medyo mahina ang pressure.', 'Good service but we waited 10 minutes.', 'Room was a bit warm.', ''];
    private const SPA_COMMENTS = ['Malinis at mabango ang lugar.', 'Easy to book online.', 'Parking is a bit tight.', 'Friendly front desk.', ''];

    /** @var list<array{id: int, branch_id: int, therapist_id: ?int, customer_user_id: ?int, status: string, date: string, end: string, treatment: string, created_by: ?int, source: string}> */
    private array $bookings = [];

    /** user id => list of [date => true] the person is away (approved leave) */
    private array $leaveDays = [];

    public function __construct(
        private readonly DemoBuilder $b,
        private readonly array $customers,
        int $seed,
    ) {
        mt_srand($seed);
    }

    /**
     * @param list<array<string, mixed>> $plans see class doc
     * @param array{attendance?: bool, schedules?: bool, futureDays?: int, until?: Carbon, leave?: bool, regular?: ?User} $options
     * @return list<array> the bookings made (see $bookings)
     */
    public function run(Spa $spa, array $plans, Carbon $from, array $options = []): array
    {
        $attendance = $options['attendance'] ?? false;
        $futureDays = $options['futureDays'] ?? 7;
        $today = Carbon::today();
        $until = isset($options['until']) ? $options['until']->copy() : $today->copy()->addDays($futureDays);
        $totalDays = max(1, $from->diffInDays($until));

        $restDays = [];
        foreach ($plans as $plan) {
            $restDays += $this->restDays($plan);
            if ($options['schedules'] ?? false) {
                $this->schedules($spa, $plan, $from, $restDays);
            }
        }

        if ($options['leave'] ?? false) {
            $this->leaveRequests($spa, $plans, $from);
        }

        for ($day = $from->copy(); $day->lte($until); $day->addDay()) {
            $date = $day->toDateString();
            $dayIndex = $from->diffInDays($day);

            foreach ($plans as $plan) {
                /** @var Branch $branch */
                $branch = $plan['branch'];
                $present = [];   // user id => [in, out] when they are at work

                foreach ($plan['staff'] as $member) {
                    $user = $member['user'];
                    $works = ! in_array($day->dayOfWeek, $restDays[$user->id] ?? [], true);
                    $onLeave = isset($this->leaveDays[$user->id][$date]);

                    if (! $works) {
                        continue;
                    }
                    if ($day->gt($today)) {
                        if (! $onLeave) {
                            $present[$user->id] = [$plan['open'] * 60, $plan['close'] * 60];
                        }
                        continue;
                    }

                    $window = $attendance
                        ? $this->attendanceRow($spa, $plan, $member, $day, $onLeave)
                        : ($onLeave ? null : [$plan['open'] * 60, $plan['close'] * 60]);

                    if ($window !== null) {
                        $present[$user->id] = $window;
                    }
                }

                $this->bookingsForDay($spa, $plan, $day, $present, $dayIndex / $totalDays, $options['regular'] ?? null);
            }
        }

        $this->ratings($plans);

        return $this->bookings;
    }

    // ── Schedules, rest days, leave ────────────────────────────────────────

    /** Weekdays off: therapists rotate Mon–Thu so weekends are fully staffed; office roles rest on Sunday. */
    private function restDays(array $plan): array
    {
        $out = [];
        $rotation = [1, 2, 3, 4, 1, 3, 2, 4];
        $i = 0;
        foreach ($plan['staff'] as $member) {
            $out[$member['user']->id] = in_array($member['role'], ['therapist', 'receptionist'], true)
                ? [$rotation[($i++ + $plan['branch']->id) % count($rotation)]]
                : [0];
        }

        return $out;
    }

    private function schedules(Spa $spa, array $plan, Carbon $from, array $restDays): void
    {
        $writer = app(WorkScheduleWriter::class);
        $names = [0 => 'Sunday', 1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday'];

        foreach ($plan['staff'] as $member) {
            $days = [];
            foreach ($names as $dow => $name) {
                $days[$name] = in_array($dow, $restDays[$member['user']->id] ?? [], true) ? ['mode' => 'rest'] : ['mode' => 'branch'];
            }
            Carbon::setTestNow($from->copy()->setTime(8, 0));
            $writer->savePattern($member['staff'], $from->toDateString(), $days, $spa->owner_id);
            Carbon::setTestNow();
        }
    }

    /** Two approved leaves in the past, one rejected, one pending in the coming days. */
    private function leaveRequests(Spa $spa, array $plans, Carbon $from): void
    {
        $therapists = [];
        foreach ($plans as $plan) {
            foreach ($plan['staff'] as $member) {
                if ($member['role'] === 'therapist') {
                    $therapists[] = [$member['user'], $plan['branch'], $plan['manager']];
                }
            }
        }
        if (count($therapists) < 4) {
            return;
        }

        $rows = [
            [$therapists[0], 'sick', $from->copy()->addDays(30), 1, 'approved', 'Fever and flu. Medical certificate attached at the front desk.'],
            [$therapists[2], 'vacation', $from->copy()->addDays(58), 2, 'approved', 'Family reunion in Batangas.'],
            [$therapists[3], 'personal', $from->copy()->addDays(70), 1, 'rejected', 'Personal errand.'],
            [$therapists[1], 'vacation', Carbon::today()->addDays(9), 2, 'pending', 'Kids\' school program and a short trip.'],
        ];

        foreach ($rows as [[$user, $branch, $manager], $type, $start, $days, $status, $reason]) {
            $end = $start->copy()->addDays($days - 1);
            $filed = $start->copy()->subDays(5);
            DB::table('leave_requests')->insert([
                'user_id' => $user->id, 'spa_id' => $spa->id, 'branch_id' => $branch->id,
                'leave_type' => $type, 'start_date' => $start->toDateString(), 'end_date' => $end->toDateString(),
                'reason' => $reason, 'status' => $status,
                'rejection_reason' => $status === 'rejected' ? 'Saturday is our busiest day and two therapists are already off. Please choose a weekday.' : null,
                'reviewed_by' => $status === 'pending' ? null : $manager?->id,
                'reviewed_at' => $status === 'pending' ? null : $filed->copy()->addDay(),
                'created_at' => $filed, 'updated_at' => $filed,
            ]);

            if ($status === 'approved') {
                for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
                    $this->leaveDays[$user->id][$d->toDateString()] = true;
                }
            }
        }
    }

    /** @return ?array{0: int, 1: int} minutes in/out when the person worked, null when away */
    private function attendanceRow(Spa $spa, array $plan, array $member, Carbon $day, bool $onLeave): ?array
    {
        $isToday = $day->isToday();
        $open = $plan['open'] * 60;
        $close = $plan['close'] * 60;
        $nowMinutes = now()->hour * 60 + now()->minute;

        if ($isToday && $nowMinutes < $open) {
            return [$open, $close];   // shift not started yet: no row, still bookable
        }

        $status = 'present';
        $in = $open - mt_rand(0, 12);
        $out = $close + mt_rand(0, 20);
        $roll = mt_rand(1, 1000);

        if ($onLeave) {
            $status = 'on_leave';
        } elseif ($roll <= 15) {
            $status = 'absent';
        } elseif ($roll <= 95) {
            $status = 'late';
            $in = $open + mt_rand(8, 40);
        }

        $away = in_array($status, ['on_leave', 'absent'], true);
        $manual = $away || mt_rand(1, 100) <= 12;
        $stillIn = $isToday && $nowMinutes < $close;

        DB::table('staff_attendance')->insert([
            'staff_id' => $member['staff']->id, 'spa_id' => $spa->id, 'branch_id' => $plan['branch']->id,
            'date' => $day->toDateString(), 'status' => $status,
            'time_in' => $away ? null : $this->clock($in),
            'time_out' => $away || $stillIn ? null : $this->clock($out),
            'marked_by' => $manual ? $plan['manager']?->id : null,
            'source' => $manual ? 'manual' : 'self', 'auto_closed' => false,
            'remarks' => $status === 'absent' ? 'No call, no show.' : null,
            'created_at' => $day->copy()->setTime(intdiv(max($in, 0), 60), $in % 60),
            'updated_at' => $day->copy()->setTime(intdiv(min($out, 1439), 60), $out % 60),
        ]);

        return $away ? null : [max($in, $open), $close];
    }

    // ── Bookings ───────────────────────────────────────────────────────────

    private function bookingsForDay(Spa $spa, array $plan, Carbon $day, array $present, float $progress, ?User $regular): void
    {
        $branch = $plan['branch'];
        $payday = in_array($day->day, [1, 15, 16, 30, 31], true) ? 1.2 : 1.0;
        $growth = 0.85 + 0.3 * $progress;
        $isFuture = $day->isAfter(Carbon::today());
        $expected = $plan['volume'] * self::DOW[$day->dayOfWeek] * $payday * $growth * (mt_rand(80, 120) / 100);
        if ($isFuture) {
            // Only online reservations and a few phoned-in bookings exist ahead of time.
            $expected *= max(0.15, 0.65 - 0.08 * Carbon::today()->diffInDays($day));
        }
        $count = (int) round($expected);

        $therapists = array_values(array_filter($plan['therapists'], fn (User $u) => isset($present[$u->id])));
        $busy = [];
        $services = $this->serviceWeights($plan['services']);

        for ($n = 0; $n < $count; $n++) {
            [$code, $label, $minutes, $price, $homeOk] = $this->pick($services);
            $start = $this->startMinute($plan, $day->dayOfWeek, $minutes);
            if ($start === null) {
                continue;
            }
            $end = $start + $minutes;

            $online = mt_rand(1, 100) <= ($isFuture ? 80 : 38);
            $inHome = $online && $homeOk && mt_rand(1, 100) <= 14;

            $free = array_values(array_filter($therapists, function (User $u) use ($present, $busy, $start, $end, $inHome) {
                [$in, $out] = $present[$u->id];
                if ($start < $in || $end > $out || ($inHome && $u->identity_verified_at === null)) {
                    return false;
                }
                foreach ($busy[$u->id] ?? [] as [$s, $e]) {
                    if ($start < $e + 10 && $end + 10 > $s) {
                        return false;
                    }
                }

                return true;
            }));

            $therapist = $free === [] ? null : $free[mt_rand(0, count($free) - 1)];
            if ($therapist === null && ! ($isFuture && $online && mt_rand(1, 4) === 1)) {
                continue;   // fully booked at that time: the customer goes elsewhere
            }
            if ($therapist) {
                $busy[$therapist->id][] = [$start, $end];
            }

            $customer = $online ? $this->customer($regular, $branch) : null;
            $this->insertBooking($spa, $plan, $day, $start, $end, $code, $price, $online, $inHome, $therapist, $customer);
        }
    }

    private function insertBooking(Spa $spa, array $plan, Carbon $day, int $start, int $end, string $code, float $price,
        bool $online, bool $inHome, ?User $therapist, ?User $customer): void
    {
        $startsAt = $day->copy()->setTime(intdiv($start, 60), $start % 60);
        $endsAt = $day->copy()->setTime(intdiv($end, 60), $end % 60);
        $now = now();
        $roll = mt_rand(1, 100);

        if ($endsAt->lte($now)) {
            $status = match (true) {
                $online && $roll <= 7 => 'cancelled',
                $online && $roll <= 11 => Booking::NO_SHOW,
                ! $online && $roll <= 3 => 'cancelled',
                default => 'completed',
            };
        } elseif ($startsAt->lte($now)) {
            $status = 'ongoing';
        } else {
            $status = $online ? 'reserved' : 'pending';
        }

        $deposit = round($price * 0.2, 2);
        $fullUpfront = $online && $roll % 7 === 0;
        $paid = match (true) {
            $status === 'completed' => $price,
            $online => $fullUpfront ? $price : $deposit,
            default => 0.0,
        };
        $paymentStatus = $paid >= $price ? 'paid' : ($paid > 0 ? 'partially_paid' : 'unpaid');

        $createdAt = $online
            ? $startsAt->copy()->subDays(mt_rand(1, 6))->setTime(mt_rand(7, 22), mt_rand(0, 59))
            : $startsAt->copy()->subMinutes(mt_rand(5, 40));
        if ($createdAt->gt($now)) {
            $createdAt = $now->copy()->subMinutes(5);
        }
        $updatedAt = in_array($status, ['completed', 'cancelled', Booking::NO_SHOW], true) ? $endsAt->copy()->addMinutes(mt_rand(1, 20)) : $createdAt;

        [$name, $email, $phone, $address] = $customer
            ? [$customer->first_name.' '.$customer->last_name, $customer->email, $customer->phone, $customer->address]
            : $this->walkInGuest();

        $id = DB::table('bookings')->insertGetId([
            'spa_id' => $spa->id, 'branch_id' => $plan['branch']->id,
            'customer_user_id' => $customer?->id,
            'created_by_user_id' => $online ? null : $plan['reception']?->id,
            'booking_source' => $online ? 'online' : 'walk_in',
            'status' => $status, 'payment_status' => $paymentStatus,
            'amount_paid' => $paid, 'total_amount' => $price, 'balance_amount' => round($price - $paid, 2),
            'service_type' => $inHome ? 'in_home' : 'in_branch', 'treatment' => $code,
            'customer_name' => $name, 'customer_email' => $email, 'customer_phone' => $phone,
            'customer_address' => $inHome ? ($address ?: 'Sample St., '.$plan['branch']->name.', Cavite') : null,
            'appointment_date' => $day->toDateString(),
            'start_time' => $startsAt->format('H:i:s'), 'end_time' => $endsAt->format('H:i:s'),
            'arrival_code' => $online && $inHome ? Crypt::encryptString(Booking::newArrivalCode()) : null,
            'arrival_confirmed_at' => $inHome && in_array($status, ['completed', 'ongoing'], true) ? $startsAt : null,
            'arrival_confirmed_by' => $inHome && in_array($status, ['completed', 'ongoing'], true) ? $therapist?->id : null,
            'arrival_method' => $inHome && in_array($status, ['completed', 'ongoing'], true) ? 'code' : null,
            'therapist_id' => $therapist?->id,
            'created_at' => $createdAt, 'updated_at' => $updatedAt,
        ]);

        if ($online) {
            $this->reservationPayment($spa, $plan, $id, $code, $price, $deposit, $fullUpfront, $inHome, $customer, $name, $email, $phone, $day, $startsAt, $createdAt);
        }

        $this->bookings[] = [
            'id' => $id, 'branch_id' => $plan['branch']->id, 'therapist_id' => $therapist?->id,
            'customer_user_id' => $customer?->id, 'status' => $status, 'date' => $day->toDateString(),
            'start' => $startsAt->toDateTimeString(), 'end' => $endsAt->toDateTimeString(), 'treatment' => $code,
            'source' => $online ? 'online' : 'walk_in', 'price' => $price,
        ];
    }

    private function reservationPayment(Spa $spa, array $plan, int $bookingId, string $code, float $price, float $deposit,
        bool $full, bool $inHome, ?User $customer, string $name, string $email, string $phone, Carbon $day, Carbon $startsAt, Carbon $createdAt): void
    {
        [$type, $itemId] = explode('_', $code, 2);
        $item = $type === 'treatment'
            ? collect($plan['services']['treatments'])->firstWhere('id', (int) $itemId)
            : collect($plan['services']['packages'])->firstWhere('id', (int) $itemId);

        DB::table('online_reservation_payments')->insert([
            'user_id' => $customer?->id, 'spa_id' => $spa->id, 'branch_id' => $plan['branch']->id,
            'customer_name' => $name, 'customer_email' => $email ?: DemoCatalog::email('guest'), 'customer_phone' => $phone,
            'customer_address' => $inHome ? ($customer?->address ?: 'Sample St., Cavite') : null,
            'bookable_id' => (int) $itemId, 'bookable_type' => $type, 'bookable_name' => $item?->name ?? 'Service',
            'full_amount' => $price, 'downpayment_amount' => $deposit,
            'payment_option' => $full ? 'full' : 'downpayment', 'amount_charged' => $full ? $price : $deposit,
            'service_type' => $inHome ? 'in_home' : 'in_branch',
            'appointment_date' => $day->toDateString(), 'start_time' => $startsAt->format('H:i:s'),
            'payment_reference' => 'DEMO-'.str_pad((string) $bookingId, 7, '0', STR_PAD_LEFT),
            'payment_status' => 'paid', 'reservation_status' => 'reserved',
            'paid_at' => $createdAt->copy()->addMinutes(2), 'booking_id' => $bookingId,
            'terms_version' => \App\Support\Legal\Terms::VERSION, 'terms_accepted_at' => $createdAt,
            'created_at' => $createdAt, 'updated_at' => $createdAt->copy()->addMinutes(2),
        ]);
    }

    /** @return list<array{0: string, 1: string, 2: int, 3: float, 4: bool, 5: int}> code, name, minutes, price, home ok, weight */
    private function serviceWeights(array $services): array
    {
        $out = [];
        foreach ($services['treatments'] as $key => $t) {
            $out[] = ['treatment_'.$t->id, $t->name, (int) $t->duration, (float) $t->price, $t->service_type === 'in_branch_and_home', DemoCatalog::TREATMENTS[$key][4]];
        }
        foreach ($services['packages'] as $key => $p) {
            $out[] = ['package_'.$p->id, $p->name, (int) $p->total_duration, (float) $p->price, false, DemoCatalog::PACKAGES[$key][3]];
        }

        return $out;
    }

    private function pick(array $weighted): array
    {
        $total = array_sum(array_column($weighted, 5));
        $roll = mt_rand(1, $total);
        foreach ($weighted as $row) {
            if (($roll -= $row[5]) <= 0) {
                return $row;
            }
        }

        return $weighted[0];
    }

    /** A start time on the hour or half hour, weighted toward the peaks, that ends before closing. */
    private function startMinute(array $plan, int $dow, int $minutes): ?int
    {
        $weights = [];
        for ($h = $plan['open']; $h < $plan['close']; $h++) {
            $w = self::HOURS[$h] ?? 0.3;
            if (in_array($dow, [0, 6], true) && $h >= 11 && $h <= 16) {
                $w *= 1.3;   // weekend afternoons
            }
            $weights[$h] = (int) round($w * 100);
        }

        for ($try = 0; $try < 4; $try++) {
            $roll = mt_rand(1, array_sum($weights));
            foreach ($weights as $h => $w) {
                if (($roll -= $w) <= 0) {
                    $start = $h * 60 + (mt_rand(0, 1) * 30);
                    if ($start + $minutes <= $plan['close'] * 60) {
                        return $start;
                    }
                    break;
                }
            }
        }

        return null;
    }

    /** Online customers: a few loyal regulars, a long tail of occasional ones. */
    private function customer(?User $regular, Branch $branch): User
    {
        if ($regular && $branch->is_main && mt_rand(1, 100) <= 6) {
            return $regular;
        }
        $n = count($this->customers);
        $i = (int) floor($n * (mt_rand(0, 1000) / 1000) ** 2.2);

        return $this->customers[min($i, $n - 1)];
    }

    private function walkInGuest(): array
    {
        $first = DemoCatalog::FIRST_NAMES[mt_rand(0, count(DemoCatalog::FIRST_NAMES) - 1)];
        $last = DemoCatalog::LAST_NAMES[mt_rand(0, count(DemoCatalog::LAST_NAMES) - 1)];

        return ["{$first} {$last}", null, '09'.mt_rand(15, 99).str_pad((string) mt_rand(0, 9999999), 7, '0', STR_PAD_LEFT), null];
    }

    // ── Ratings ────────────────────────────────────────────────────────────

    private function ratings(array $plans): void
    {
        $quality = [];
        foreach ($plans as $plan) {
            $quality += $plan['quality'] ?? [];
        }

        $rows = [];
        foreach ($this->bookings as $booking) {
            if ($booking['status'] !== 'completed' || ! $booking['customer_user_id'] || ! $booking['therapist_id'] || mt_rand(1, 100) > 45) {
                continue;
            }

            $mean = $quality[$booking['therapist_id']] ?? 4.5;
            $stars = max(1, min(5, (int) round($mean + (mt_rand(-100, 100) / 100) * 0.9)));
            $spaStars = max(2, min(5, (int) round(4.4 + (mt_rand(-100, 100) / 100) * 0.8)));
            $at = Carbon::parse($booking['end'])->addHours(mt_rand(1, 30));
            if ($at->isFuture()) {
                continue;
            }
            $comments = $stars >= 4 ? self::REVIEWS_GOOD : self::REVIEWS_OK;

            $rows[] = [
                'booking_id' => $booking['id'], 'therapist_id' => $booking['therapist_id'],
                'customer_id' => $booking['customer_user_id'], 'rating' => $stars, 'spa_rating' => $spaStars,
                'comment' => $comments[mt_rand(0, count($comments) - 1)] ?: null,
                'spa_comment' => self::SPA_COMMENTS[mt_rand(0, count(self::SPA_COMMENTS) - 1)] ?: null,
                'created_at' => $at, 'updated_at' => $at,
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('ratings')->insert($chunk);
        }
    }

    private function clock(int $minutes): string
    {
        $minutes = max(0, min($minutes, 23 * 60 + 59));

        return sprintf('%02d:%02d:00', intdiv($minutes, 60), $minutes % 60);
    }
}
