<?php

namespace Tests\Feature\Chat;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\Spa;
use App\Models\User;
use App\Services\Landing\PublicBranches;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Fixtures\HomeVisitTestSeeder;
use Tests\Fixtures\IdentityVerificationTestSeeder;
use Tests\Fixtures\PayrollTestAccountsSeeder;
use Tests\TestCase;

/**
 * Chat Phase 2 (Nov 15, 2026): the customer's branch bar and "View branch"
 * (/web-api/branches/{branch}/card), the read-only state for a branch the
 * public can't see (Q7a), and the card's branch tag rule (Q5a).
 */
class ChatBranchViewTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;
    private Spa $spa;
    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Mail::fake();
        $this->seed(PayrollTestAccountsSeeder::class);
        $this->seed(IdentityVerificationTestSeeder::class);
        $this->seed(HomeVisitTestSeeder::class);

        $this->customer = User::where('email', HomeVisitTestSeeder::CUSTOMER_EMAIL)->firstOrFail();
        $booking = Booking::where('customer_user_id', $this->customer->id)->firstOrFail();
        $this->branch = Branch::findOrFail($booking->branch_id);
        $this->spa = Spa::findOrFail($booking->spa_id);
        $this->spa->forceFill(['verification_status' => 'verified'])->saveQuietly();
    }

    public function test_view_branch_returns_the_spa_window_data_for_a_public_branch(): void
    {
        $this->getJson(route('landing.branch-card', $this->branch))
            ->assertOk()
            ->assertJsonPath('public', true)
            ->assertJsonPath('spa.id', $this->spa->id)
            ->assertJsonPath('spa.branch_id', $this->branch->id)
            ->assertJsonPath('spa.name', $this->spa->name);
    }

    public function test_a_branch_the_public_cannot_see_answers_public_false_with_no_details(): void
    {
        $this->branch->profile->forceFill(['is_listed' => false])->save();

        $res = $this->getJson(route('landing.branch-card', $this->branch))
            ->assertOk()
            ->assertJsonPath('public', false);
        $this->assertArrayNotHasKey('spa', $res->json());

        $this->branch->profile->forceFill(['is_listed' => true])->save();
        $this->branch->delete();
        $this->getJson(route('landing.branch-card', $this->branch->id))->assertJsonPath('public', false);
    }

    public function test_the_thread_says_whether_the_branch_can_take_messages(): void
    {
        $this->actingAs($this->customer)->postJson(route('chat.send', $this->branch), ['body' => 'Hi'])->assertCreated();

        $this->actingAs($this->customer)->getJson(route('chat.thread', $this->branch))
            ->assertJsonPath('branch_public', true)
            ->assertJsonPath('branch_id', $this->branch->id);

        // Hidden from the public: history stays readable, sending is refused (Q7a).
        $this->branch->profile->forceFill(['is_listed' => false])->save();
        $this->actingAs($this->customer)->getJson(route('chat.thread', $this->branch))
            ->assertJsonPath('branch_public', false)
            ->assertJsonCount(1, 'messages');
        $this->actingAs($this->customer)->postJson(route('chat.send', $this->branch), ['body' => 'Still there?'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'This branch isn\'t accepting messages right now.');

        // The list keeps the thread, with the branch photo for the avatar.
        $this->actingAs($this->customer)->getJson(route('chat.conversations'))
            ->assertJsonCount(1, 'conversations')
            ->assertJsonPath('conversations.0.branch_id', $this->branch->id);
        $this->assertNotEmpty($this->actingAs($this->customer)->getJson(route('chat.conversations'))->json('conversations.0.branch_photo'));
    }

    public function test_the_card_branch_tag_shows_only_for_spas_with_two_or_more_public_branches(): void
    {
        $this->assertNull(PublicBranches::cardTag('Imus', 1));
        $this->assertSame('Imus Branch', PublicBranches::cardTag('Imus', 2));
        $this->assertSame('Main Branch', PublicBranches::cardTag('Main Branch', 3));

        $count = app(PublicBranches::class)->count($this->spa);
        $card = $this->getJson(route('landing.branch-card', $this->branch))->json('spa');
        $this->assertSame($count > 1 ? PublicBranches::label($this->branch->name) : null, $card['branch_tag']);
    }
}
