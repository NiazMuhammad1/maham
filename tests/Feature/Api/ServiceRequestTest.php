<?php

namespace Tests\Feature\Api;

use App\Models\City;
use App\Models\District;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ServiceRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_customer_can_submit_a_request_with_photos(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->post('/api/v1/service-requests', [
            ...$this->validPayload(),
            'images' => [UploadedFile::fake()->image('ac-1.jpg'), UploadedFile::fake()->image('ac-2.jpg')],
        ], ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonPath('data.status', ServiceRequest::STATUS_OPEN)
            ->assertJsonPath('data.budget', 250)
            ->assertJsonPath('data.preferred_time', '09:00')
            ->assertJsonPath('data.flexibility_label', 'Flexible')
            ->assertJsonCount(2, 'data.images');

        $serviceRequest = $user->serviceRequests()->sole();
        $this->assertStringStartsWith('REQ-', $serviceRequest->reference);
        $this->assertCount(2, $serviceRequest->getMedia('images'));
        Storage::disk('public')->assertExists($serviceRequest->getFirstMedia('images')->getPathRelativeToRoot());
    }

    public function test_labels_are_returned_in_arabic(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/service-requests', $this->validPayload(), ['Accept-Language' => 'ar'])
            ->assertCreated()
            ->assertJsonPath('message', 'تم تقديم الطلب بنجاح.')
            ->assertJsonPath('data.flexibility_label', 'مرن')
            ->assertJsonPath('data.status_label', 'مفتوح');
    }

    public function test_request_is_validated(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/service-requests', [
            ...$this->validPayload(),
            'preferred_date' => now()->subDay()->toDateString(),
            'flexibility' => 'sometimes',
            'description' => '',
        ])->assertUnprocessable()->assertJsonValidationErrors(['preferred_date', 'flexibility', 'description']);
    }

    public function test_no_more_than_six_photos_are_allowed(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $images = collect(range(1, 7))->map(fn (int $number) => UploadedFile::fake()->image("photo-{$number}.jpg"))->all();

        $this->post('/api/v1/service-requests', [...$this->validPayload(), 'images' => $images], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('images');
    }

    public function test_guest_cannot_submit_a_request(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_guest' => true]));

        $this->postJson('/api/v1/service-requests', $this->validPayload())->assertForbidden();
    }

    public function test_my_requests_lists_open_and_closed_tabs(): void
    {
        $user = User::factory()->create();
        ServiceRequest::factory()->for($user, 'customer')->count(2)->create();
        ServiceRequest::factory()->for($user, 'customer')->cancelled()->create();
        ServiceRequest::factory()->create();

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/service-requests')->assertOk()->assertJsonCount(2, 'data.requests');
        $this->getJson('/api/v1/service-requests?tab=closed')->assertOk()->assertJsonCount(1, 'data.requests');
    }

    public function test_customer_cannot_see_someone_elses_request(): void
    {
        $serviceRequest = ServiceRequest::factory()->create();

        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/v1/service-requests/{$serviceRequest->id}")->assertNotFound();
    }

    public function test_options_return_translated_flexibilities(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/service-requests/options', ['Accept-Language' => 'ar'])
            ->assertOk()
            ->assertJsonPath('data.max_images', 6)
            ->assertJsonPath('data.flexibilities.2.label', 'مرن جداً');
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(): array
    {
        $city = City::factory()->create();
        $district = District::factory()->for($city)->create();

        return [
            'service_id' => Service::factory()->create()->id,
            'city_id' => $city->id,
            'district_id' => $district->id,
            'street_address' => '675 King Jamil Rd.',
            'postal_code' => '23142',
            'preferred_date' => now()->addDays(2)->toDateString(),
            'preferred_time' => '09:00',
            'flexibility' => 'flexible',
            'description' => 'AC is not cooling and makes a loud noise.',
            'budget' => 250,
        ];
    }
}
