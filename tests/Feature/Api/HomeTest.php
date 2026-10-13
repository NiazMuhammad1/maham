<?php

namespace Tests\Feature\Api;

use App\Models\Service;
use App\Models\SubService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_returns_popular_services_and_unread_count(): void
    {
        $user = User::factory()->create();
        Service::factory()->popular()->count(2)->create();
        Service::factory()->create();
        Service::factory()->popular()->inactive()->create();
        $this->createNotification($user, 'work_started');

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/home')
            ->assertOk()
            ->assertJsonCount(2, 'data.popular_services')
            ->assertJsonPath('data.unread_notifications_count', 1)
            ->assertJsonPath('data.show_become_provider', true);
    }

    public function test_home_requires_login(): void
    {
        $this->getJson('/api/v1/home')->assertUnauthorized();
    }

    public function test_all_services_lists_only_active_services(): void
    {
        Service::factory()->count(3)->create();
        Service::factory()->inactive()->create();

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/services')->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_service_details_include_localized_sub_services(): void
    {
        $service = Service::factory()->create(['name_ar' => 'تصليح التكييف']);
        SubService::factory()->for($service)->create(['name_ar' => 'تصليح عام']);
        SubService::factory()->for($service)->create(['is_active' => false]);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/v1/services/{$service->id}", ['Accept-Language' => 'ar'])
            ->assertOk()
            ->assertJsonPath('data.name', 'تصليح التكييف')
            ->assertJsonCount(1, 'data.sub_services')
            ->assertJsonPath('data.sub_services.0.name', 'تصليح عام');
    }

    public function test_inactive_service_details_return_404(): void
    {
        $service = Service::factory()->inactive()->create();

        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/v1/services/{$service->id}")->assertNotFound();
    }

    public function test_notifications_are_translated_and_can_be_marked_as_read(): void
    {
        $user = User::factory()->create();
        $notificationId = $this->createNotification($user, 'work_started');
        $this->createNotification($user, 'work_completed');

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonCount(2, 'data.notifications')
            ->assertJsonPath('data.unread_count', 2)
            ->assertJsonFragment(['body' => 'Service provider Mohammed has started working on your request.']);

        $this->postJson("/api/v1/notifications/{$notificationId}/read")
            ->assertOk()
            ->assertJsonPath('data.is_read', true);

        $this->postJson('/api/v1/notifications/read-all')->assertOk();

        $this->assertSame(0, $user->unreadNotifications()->count());
    }

    public function test_user_cannot_read_another_users_notification(): void
    {
        $notificationId = $this->createNotification(User::factory()->create(), 'work_started');

        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/v1/notifications/{$notificationId}/read")->assertNotFound();
    }

    public function test_user_can_change_language(): void
    {
        $user = User::factory()->create(['language' => 'en']);

        Sanctum::actingAs($user);

        $this->putJson('/api/v1/profile/language', ['language' => 'ar'])
            ->assertOk()
            ->assertJsonPath('message', 'تم تحديث اللغة.');

        $this->assertSame('ar', $user->fresh()->language);

        $this->putJson('/api/v1/profile/language', ['language' => 'fr'])->assertUnprocessable();
    }

    private function createNotification(User $user, string $type): string
    {
        $id = Str::uuid()->toString();

        $user->notifications()->create([
            'id' => $id,
            'type' => $type,
            'data' => ['params' => ['provider' => 'Mohammed']],
        ]);

        return $id;
    }
}
