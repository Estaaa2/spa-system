<?php

namespace Tests\Feature\MobileApp;

use App\Models\Branch;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Spa;
use App\Models\User;
use App\Services\Chat\ChatService;
use Database\Factories\BranchFactory;
use Database\Factories\SpaFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Fixtures\HomeVisitTestSeeder;
use Tests\Fixtures\IdentityVerificationTestSeeder;
use Tests\Fixtures\PayrollTestAccountsSeeder;
use Tests\TestCase;

/**
 * Owner mobile chat (Nov 2026): /api/owner/chat/* is the web's spa inbox
 * (SpaChatController) inside the owner group; access.chat tells the app
 * whether to show the floating button.
 */
class OwnerMobileChatTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $customer;
    private Spa $spa;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Mail::fake();
        $this->seed(PayrollTestAccountsSeeder::class);
        $this->seed(IdentityVerificationTestSeeder::class);
        $this->seed(HomeVisitTestSeeder::class);

        $this->owner = User::where('email', 'owner@levictas.test')->firstOrFail();
        $this->customer = User::where('email', HomeVisitTestSeeder::CUSTOMER_EMAIL)->firstOrFail();
        $this->spa = Spa::findOrFail($this->owner->spa_id);
    }

    private function customerSays(string $body, ?Spa $spa = null): ChatConversation
    {
        $spa ??= $this->spa;
        $branch = Branch::where('spa_id', $spa->id)->orderByDesc('is_main')->firstOrFail();

        return app(ChatService::class)->send(
            ['spa_id' => $spa->id, 'customer_user_id' => $this->customer->id],
            $this->customer, ChatMessage::SIDE_CUSTOMER, $body, $branch->id,
        )->conversation;
    }

    public function test_branches_reports_chat_access(): void
    {
        Sanctum::actingAs($this->owner);

        $this->getJson('/api/owner/branches')->assertOk()->assertJsonPath('access.chat', true);
    }

    public function test_the_owner_sees_the_badge_inbox_and_thread_and_can_reply(): void
    {
        $id = $this->customerSays('Do you have a slot today?')->id;
        Sanctum::actingAs($this->owner);

        $this->getJson('/api/owner/chat/unread')->assertOk()->assertJsonPath('unread', 1);
        $this->getJson('/api/owner/chat/conversations')->assertOk()
            ->assertJsonPath('conversations.0.id', $id)
            ->assertJsonPath('unread', 1);

        $this->getJson("/api/owner/chat/conversations/{$id}")->assertOk()
            ->assertJsonPath('messages.0.body', 'Do you have a slot today?');
        $this->getJson('/api/owner/chat/unread')->assertJsonPath('unread', 0);

        $this->postJson("/api/owner/chat/conversations/{$id}", ['body' => 'Yes, 3 PM is free.'])
            ->assertCreated()->assertJsonPath('message.mine', true);
        $this->postJson("/api/owner/chat/conversations/{$id}", ['body' => '   '])->assertStatus(422);
    }

    public function test_another_spas_thread_is_not_found(): void
    {
        $other = SpaFactory::new()->create();
        BranchFactory::new()->create(['spa_id' => $other->id]);
        $id = $this->customerSays('Hello', $other)->id;
        Sanctum::actingAs($this->owner);

        $this->getJson("/api/owner/chat/conversations/{$id}")->assertNotFound();
        $this->postJson("/api/owner/chat/conversations/{$id}", ['body' => 'Hi'])->assertNotFound();
    }

    public function test_without_the_permission_the_app_hides_chat_and_the_api_refuses(): void
    {
        $this->owner->roles->each->revokePermissionTo(ChatService::PERMISSION);
        $this->owner->revokePermissionTo(ChatService::PERMISSION);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        Sanctum::actingAs($this->owner->fresh());

        $this->getJson('/api/owner/branches')->assertJsonPath('access.chat', false);
        $this->getJson('/api/owner/chat/unread')->assertForbidden();
    }

    public function test_non_owners_cannot_use_the_owner_chat_routes(): void
    {
        Sanctum::actingAs(User::where('email', 'reception.a@levictas.test')->firstOrFail());

        $this->getJson('/api/owner/chat/unread')->assertForbidden();
    }
}
