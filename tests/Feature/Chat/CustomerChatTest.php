<?php

namespace Tests\Feature\Chat;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\BranchProfile;
use App\Models\ChatConversation;
use App\Models\Spa;
use App\Models\User;
use Database\Factories\BranchFactory;
use Database\Factories\PayrollUserFactory;
use Database\Factories\SpaFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Fixtures\HomeVisitTestSeeder;
use Tests\Fixtures\IdentityVerificationTestSeeder;
use Tests\Fixtures\PayrollTestAccountsSeeder;
use Tests\TestCase;

/**
 * Customer ↔ spa chat (Nov 2026): one spa-wide thread per customer, a shared
 * inbox for staff with "reply to customer messages" (receptionist default).
 */
class CustomerChatTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;
    private User $reception;
    private Spa $spa;
    private Branch $branchA;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Mail::fake();
        $this->withoutVite();
        $this->seed(PayrollTestAccountsSeeder::class);
        $this->seed(IdentityVerificationTestSeeder::class);
        $this->seed(HomeVisitTestSeeder::class);

        $this->customer  = User::where('email', HomeVisitTestSeeder::CUSTOMER_EMAIL)->firstOrFail();
        $this->reception = User::where('email', 'reception.a@levictas.test')->firstOrFail();
        $booking = Booking::where('customer_user_id', $this->customer->id)->firstOrFail();
        $this->branchA = Branch::findOrFail($booking->branch_id);
        $this->spa = Spa::findOrFail($booking->spa_id);
        $this->spa->forceFill(['verification_status' => 'verified'])->saveQuietly();
    }

    private function say(string $body, ?Branch $branch = null)
    {
        return $this->actingAs($this->customer)->postJson(route('chat.send', $this->spa), [
            'body' => $body, 'branch_id' => ($branch ?? $this->branchA)->id,
        ]);
    }

    public function test_a_customer_message_reaches_the_receptionist_and_the_reply_comes_back(): void
    {
        $this->say('Do you have a slot today?')->assertCreated()->assertJsonPath('message.mine', true);

        // Spa side: badge, inbox, thread.
        $this->actingAs($this->reception)->getJson(route('spa-chat.unread'))->assertJsonPath('unread', 1);
        $inbox = $this->actingAs($this->reception)->getJson(route('spa-chat.conversations'))->assertOk()->json('conversations');
        $this->assertCount(1, $inbox);
        $this->assertSame('Cara Customer (test)', $inbox[0]['customer']);
        $this->assertSame($this->branchA->name, $inbox[0]['branch']);

        $id = $inbox[0]['id'];
        $this->actingAs($this->reception)->getJson(route('spa-chat.thread', $id))
            ->assertJsonPath('messages.0.body', 'Do you have a slot today?')
            ->assertJsonPath('messages.0.branch', $this->branchA->name);
        $this->actingAs($this->reception)->getJson(route('spa-chat.unread'))->assertJsonPath('unread', 0);

        $this->actingAs($this->reception)->postJson(route('spa-chat.send', $id), ['body' => 'Yes, 3 PM is free.'])
            ->assertCreated()->assertJsonPath('message.mine', true);

        // Customer side: the floating button's list, then the thread shows the spa's name.
        $this->actingAs($this->customer)->getJson(route('chat.conversations'))
            ->assertJsonPath('unread', 1)
            ->assertJsonPath('conversations.0.spa_name', $this->spa->name)
            ->assertJsonPath('conversations.0.branch_id', $this->branchA->id);

        $thread = $this->actingAs($this->customer)->getJson(route('chat.thread', $this->spa))->json('messages');
        $this->assertSame($this->spa->name, $thread[1]['from']);
        $this->assertFalse($thread[1]['mine']);
        $this->actingAs($this->customer)->getJson(route('chat.conversations'))->assertJsonPath('unread', 0);

        // Polling with ?after= returns only new messages.
        $this->actingAs($this->customer)->getJson(route('chat.thread', [$this->spa, 'after' => $thread[1]['id']]))
            ->assertJsonCount(0, 'messages');
    }

    public function test_every_branch_of_the_spa_shares_one_thread_and_one_inbox(): void
    {
        $branchB = Branch::where('spa_id', $this->spa->id)->where('id', '!=', $this->branchA->id)->firstOrFail();
        BranchProfile::updateOrCreate(['branch_id' => $branchB->id], ['is_listed' => true]);

        $this->say('Hello from branch A');
        $this->say('And from branch B', $branchB)->assertCreated();

        $this->assertSame(1, ChatConversation::where('spa_id', $this->spa->id)->count());

        // A manager at branch B sees the same thread (spa-wide inbox).
        $managerB = User::where('email', 'manager.b@levictas.test')->firstOrFail();
        $this->actingAs($managerB)->getJson(route('spa-chat.unread'))->assertJsonPath('unread', 2);
    }

    public function test_who_may_use_each_side(): void
    {
        $this->say('Hi')->assertCreated();
        $id = ChatConversation::firstOrFail()->id;

        // No permission: no inbox.
        $hr = User::where('email', 'hr.a@levictas.test')->firstOrFail();
        $this->actingAs($hr)->getJson(route('spa-chat.unread'))->assertForbidden();

        // Another spa's receptionist can't open this thread.
        $otherSpa = SpaFactory::new()->create();
        $otherBranch = BranchFactory::new()->create(['spa_id' => $otherSpa->id]);
        $stranger = PayrollUserFactory::new()->create(['spa_id' => $otherSpa->id, 'branch_id' => $otherBranch->id]);
        $stranger->assignRole('receptionist');
        $this->actingAs($stranger)->getJson(route('spa-chat.thread', $id))->assertNotFound();
        $this->actingAs($stranger)->getJson(route('spa-chat.unread'))->assertJsonPath('unread', 0);

        // Staff can't use the customer side.
        $this->actingAs($this->reception)->getJson(route('chat.conversations'))->assertForbidden();
    }

    public function test_a_spa_that_cannot_receive_messages_refuses_them(): void
    {
        // Branch of another spa.
        $otherBranch = BranchFactory::new()->create(['spa_id' => SpaFactory::new()->create()->id]);
        $this->say('Hi', $otherBranch)->assertStatus(422);

        // Unlisted branch.
        $this->branchA->profile->forceFill(['is_listed' => false])->save();
        $this->say('Hi')->assertStatus(422);

        // Empty or too long.
        $this->branchA->profile->forceFill(['is_listed' => true])->save();
        $this->say('   ')->assertStatus(422);
        $this->say(str_repeat('a', 1001))->assertStatus(422);
        $this->assertSame(0, ChatConversation::count());
    }

    public function test_the_widgets_render_for_the_right_people(): void
    {
        // Staff: the floating inbox only for those who may answer.
        $this->actingAs($this->reception)->get(route('appointments.index'))
            ->assertOk()->assertSee('data-chat-widget data-mode="spa"', false);
        $hr = User::where('email', 'hr.a@levictas.test')->firstOrFail();
        $this->actingAs($hr)->get(route('dashboard'))->assertDontSee('data-chat-widget', false);

        // Customer: the Message button in the spa window and the widget.
        $this->actingAs($this->customer)->get('/')
            ->assertOk()
            ->assertSee('data-open-spa-chat', false)
            ->assertSee('data-chat-widget data-mode="customer"', false);
    }
}
