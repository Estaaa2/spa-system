<?php

namespace Tests\Feature\MobileApp;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\Spa;
use App\Models\User;
use App\Services\Chat\ChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Fixtures\HomeVisitTestSeeder;
use Tests\Fixtures\IdentityVerificationTestSeeder;
use Tests\Fixtures\PayrollTestAccountsSeeder;
use Tests\TestCase;

/**
 * Customer chat in the app (customer mobile chat, Nov 15): /api/chat/* is the
 * web's CustomerChatController behind Sanctum, one thread per branch, with the
 * suggested first messages in the thread response.
 */
class CustomerMobileChatTest extends TestCase
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

    public function test_a_new_chat_offers_the_suggested_questions_and_a_tap_sends_one(): void
    {
        Sanctum::actingAs($this->customer);

        $this->getJson('/api/chat/conversations')->assertOk()
            ->assertJsonPath('unread', 0)
            ->assertJsonCount(0, 'conversations');

        $this->getJson("/api/chat/branches/{$this->branch->id}/messages")->assertOk()
            ->assertJsonCount(0, 'messages')
            ->assertJsonPath('branch_id', $this->branch->id)
            ->assertJsonPath('branch_public', true)
            ->assertJsonPath('quick_questions', ChatService::QUICK_QUESTIONS);

        $question = ChatService::QUICK_QUESTIONS[0];
        $this->postJson("/api/chat/branches/{$this->branch->id}/messages", ['body' => $question])
            ->assertCreated()
            ->assertJsonPath('message.mine', true)
            ->assertJsonPath('message.body', $question);

        $this->getJson('/api/chat/conversations')
            ->assertJsonPath('conversations.0.branch_id', $this->branch->id)
            ->assertJsonPath('conversations.0.from_me', true);

        // Polling with ?after= returns only new messages.
        $id = $this->getJson("/api/chat/branches/{$this->branch->id}/messages")->json('messages.0.id');
        $this->getJson("/api/chat/branches/{$this->branch->id}/messages?after={$id}")->assertJsonCount(0, 'messages');
    }

    public function test_only_signed_in_customers_can_use_it(): void
    {
        $this->getJson('/api/chat/conversations')->assertUnauthorized();

        Sanctum::actingAs(User::where('email', 'owner@levictas.test')->firstOrFail());
        $this->getJson('/api/chat/conversations')->assertForbidden();
    }

    public function test_a_branch_the_public_cannot_see_refuses_new_messages(): void
    {
        $this->spa->forceFill(['verification_status' => 'pending'])->saveQuietly();
        Sanctum::actingAs($this->customer);

        $this->getJson("/api/chat/branches/{$this->branch->id}/messages")->assertJsonPath('branch_public', false);
        $this->postJson("/api/chat/branches/{$this->branch->id}/messages", ['body' => 'Hello'])->assertStatus(422);
    }
}
