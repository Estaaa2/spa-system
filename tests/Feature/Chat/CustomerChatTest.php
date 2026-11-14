<?php

namespace Tests\Feature\Chat;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\BranchProfile;
use App\Models\ChatConversation;
use App\Models\Spa;
use App\Models\User;
use App\Services\Chat\ChatService;
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
 * Customer ↔ spa chat (Nov 2026): one thread per customer per branch (since
 * Nov 15), one spa-wide inbox for staff with "reply to customer messages"
 * (receptionist default), each thread labelled with its branch.
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
        return $this->actingAs($this->customer)->postJson(route('chat.send', $branch ?? $this->branchA), [
            'body' => $body,
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
        $this->assertSame($this->branchA->id, $inbox[0]['branch_id']);

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
            ->assertJsonPath('conversations.0.branch_id', $this->branchA->id)
            ->assertJsonPath('conversations.0.branch_name', $this->branchA->name);

        $thread = $this->actingAs($this->customer)->getJson(route('chat.thread', $this->branchA))->json('messages');
        $this->assertSame($this->spa->name, $thread[1]['from']);
        $this->assertFalse($thread[1]['mine']);
        $this->actingAs($this->customer)->getJson(route('chat.conversations'))->assertJsonPath('unread', 0);

        // Polling with ?after= returns only new messages.
        $this->actingAs($this->customer)->getJson(route('chat.thread', [$this->branchA, 'after' => $thread[1]['id']]))
            ->assertJsonCount(0, 'messages');
    }

    public function test_each_branch_has_its_own_thread_in_one_spa_wide_inbox(): void
    {
        $branchB = Branch::where('spa_id', $this->spa->id)->where('id', '!=', $this->branchA->id)->firstOrFail();
        BranchProfile::updateOrCreate(['branch_id' => $branchB->id], ['is_listed' => true]);

        $this->say('Hello from branch A')->assertCreated();
        $this->say('And from branch B', $branchB)->assertCreated();
        $this->say('Branch A again')->assertCreated();

        // Two threads, one per branch.
        $this->assertSame(2, ChatConversation::where('spa_id', $this->spa->id)->count());
        $a = ChatConversation::where('branch_id', $this->branchA->id)->firstOrFail();
        $this->assertSame(2, $a->messages()->count());

        // The customer's list: one row per branch; each thread only has its own messages.
        $rows = $this->actingAs($this->customer)->getJson(route('chat.conversations'))->json('conversations');
        $this->assertEqualsCanonicalizing([$this->branchA->id, $branchB->id], array_column($rows, 'branch_id'));
        $this->actingAs($this->customer)->getJson(route('chat.thread', $branchB))
            ->assertJsonCount(1, 'messages')
            ->assertJsonPath('messages.0.body', 'And from branch B')
            ->assertJsonPath('branch_id', $branchB->id);

        // A manager at branch B sees both branches' threads (spa-wide inbox, Q1a),
        // each with its branch, plus the chips' branch list and the branch being viewed.
        $managerB = User::where('email', 'manager.b@levictas.test')->firstOrFail();
        $this->actingAs($managerB)->getJson(route('spa-chat.unread'))->assertJsonPath('unread', 3);
        $inbox = $this->actingAs($managerB)->getJson(route('spa-chat.conversations'))->assertOk();
        $this->assertEqualsCanonicalizing([$this->branchA->id, $branchB->id], array_column($inbox->json('conversations'), 'branch_id'));
        $this->assertSame($branchB->id, $inbox->json('current_branch_id'));
        $this->assertContains($this->branchA->id, array_column($inbox->json('branches'), 'id'));

        // Opening branch A's thread from branch B: the thread says which branch it is with.
        $this->actingAs($managerB)->getJson(route('spa-chat.thread', $a->id))
            ->assertJsonPath('branch_id', $this->branchA->id)
            ->assertJsonPath('current_branch_id', $branchB->id);
    }

    public function test_branch_labels_do_not_double_the_word_branch(): void
    {
        $this->assertSame('Imus Branch', ChatService::branchLabel('Imus'));
        $this->assertSame('Imus Branch', ChatService::branchLabel('Imus Branch'));
        $this->assertSame('Main branch', ChatService::branchLabel('Main branch'));
        $this->assertSame('Test Branch A', ChatService::branchLabel('Test Branch A'));
        $this->assertNull(ChatService::branchLabel('  '));
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

        // Removed (soft-deleted) branch.
        $this->branchA->delete();
        $this->say('Hi')->assertStatus(422);
        $this->branchA->restore();

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
