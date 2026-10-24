<?php

namespace Tests\Feature\Api;

use App\Models\Booking;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\ChatFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_chat_button_on_a_booking_opens_one_thread_with_the_provider(): void
    {
        $booking = Booking::factory()->create();

        Sanctum::actingAs($booking->customer);

        $response = $this->postJson("/api/v1/bookings/{$booking->id}/conversation")
            ->assertOk()
            ->assertJsonPath('data.conversation.participant.id', $booking->provider_id)
            ->assertJsonPath('data.conversation.booking_id', $booking->id)
            ->assertJsonPath('data.conversation.call_phone', $booking->provider->fullPhone())
            ->assertJsonCount(0, 'data.messages');

        // Tapping "Chat" again opens the same thread
        $this->postJson("/api/v1/bookings/{$booking->id}/conversation")
            ->assertJsonPath('data.conversation.id', $response->json('data.conversation.id'));

        $this->assertSame(1, Conversation::count());
    }

    public function test_customer_sends_a_message_and_sees_it_in_the_chat_list(): void
    {
        $conversation = Conversation::factory()->create();

        Sanctum::actingAs($conversation->customer);

        // Empty threads are not listed
        $this->getJson('/api/v1/conversations')->assertOk()->assertJsonCount(0, 'data.conversations');

        $this->postJson("/api/v1/conversations/{$conversation->id}/messages", ['body' => 'Hi, my AC isn\'t cooling properly.'])
            ->assertCreated()
            ->assertJsonCount(1, 'data.messages')
            ->assertJsonPath('data.messages.0.is_mine', true);

        $this->getJson('/api/v1/conversations')
            ->assertOk()
            ->assertJsonPath('data.conversations.0.participant.id', $conversation->provider_id)
            ->assertJsonPath('data.conversations.0.last_message.body', 'Hi, my AC isn\'t cooling properly.')
            ->assertJsonPath('data.conversations.0.unread_count', 0);

        $this->assertSame(1, $conversation->fresh()->provider_unread_count);
    }

    public function test_opening_the_chat_marks_messages_as_read(): void
    {
        $conversation = Conversation::factory()->create(['customer_unread_count' => 2]);
        $first = Message::factory()->for($conversation)->create(['sender_id' => $conversation->provider_id]);
        $second = Message::factory()->for($conversation)->create(['sender_id' => $conversation->provider_id]);

        Sanctum::actingAs($conversation->customer);

        $this->getJson("/api/v1/conversations/{$conversation->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data.messages')
            ->assertJsonPath('data.messages.0.id', $first->id)
            ->assertJsonPath('data.messages.1.is_mine', false)
            ->assertJsonPath('data.conversation.call_phone', null)
            ->assertJsonPath('data.has_more', false);

        // Polling for new messages
        $this->getJson("/api/v1/conversations/{$conversation->id}?after_id={$first->id}")
            ->assertJsonCount(1, 'data.messages')
            ->assertJsonPath('data.messages.0.id', $second->id);

        $this->assertSame(0, $conversation->fresh()->customer_unread_count);
        $this->assertNotNull($first->fresh()->read_at);
    }

    public function test_phone_numbers_are_hidden_and_a_warning_is_added(): void
    {
        $conversation = Conversation::factory()->create();

        Sanctum::actingAs($conversation->customer);

        $this->postJson("/api/v1/conversations/{$conversation->id}/messages", ['body' => 'Call me on 050 123 4567 and I pay cash'])
            ->assertCreated()
            ->assertJsonCount(2, 'data.messages')
            ->assertJsonPath('data.messages.0.body', 'Call me on '.ChatFilter::MASK.' and I pay cash')
            ->assertJsonPath('data.messages.1.type', Message::TYPE_SYSTEM)
            ->assertJsonPath('data.messages.1.sender', null);

        // Prices and normal text stay as they are
        $this->postJson("/api/v1/conversations/{$conversation->id}/messages", ['body' => 'Is 250 SAR fine?'])
            ->assertJsonCount(1, 'data.messages')
            ->assertJsonPath('data.messages.0.body', 'Is 250 SAR fine?');
    }

    public function test_arabic_digits_are_masked_too(): void
    {
        $this->assertSame('رقمي '.ChatFilter::MASK, ChatFilter::maskPhoneNumbers('رقمي ٠٥٠١٢٣٤٥٦٧'));
        $this->assertTrue(ChatFilter::hasBlockedWords('سأدفع كاش'));
    }

    public function test_customer_sends_a_photo(): void
    {
        Storage::fake('public');
        $conversation = Conversation::factory()->create();

        Sanctum::actingAs($conversation->customer);

        $this->post("/api/v1/conversations/{$conversation->id}/messages", [
            'image' => UploadedFile::fake()->image('ac.jpg'),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.messages.0.type', Message::TYPE_IMAGE)
            ->assertJsonPath('data.messages.0.body', null);

        $this->assertNotNull(Message::first()->imageUrl());
        $this->postJson("/api/v1/conversations/{$conversation->id}/messages", [])->assertJsonValidationErrors('body');
    }

    public function test_other_users_cannot_open_the_chat(): void
    {
        $conversation = Conversation::factory()->create();
        $booking = Booking::factory()->create();

        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/v1/conversations/{$conversation->id}")->assertNotFound();
        $this->postJson("/api/v1/conversations/{$conversation->id}/messages", ['body' => 'Hi'])->assertNotFound();
        $this->postJson("/api/v1/bookings/{$booking->id}/conversation")->assertNotFound();
    }
}
