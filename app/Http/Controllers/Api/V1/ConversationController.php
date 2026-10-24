<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SendMessageRequest;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\MessageResource;
use App\Models\Booking;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\ChatFilter;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * In-app chat between a customer and a provider:
 *
 * Chat tab (list) -> tap a row -> Chat (single) -> type a message or send a photo.
 * "Chat" on a booking card / Booking Details opens (or starts) the chat with that booking's provider.
 *
 * There is no live socket yet: while the chat screen is open, the app asks for new messages
 * every few seconds with ?after_id={id of the newest message it has}.
 */
class ConversationController extends Controller
{
    /** Messages per page when scrolling up through older messages. */
    private const int PER_PAGE = 30;

    /**
     * Chat tab: threads with at least one message, newest first.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $conversations = Conversation::forUser($user)
            ->whereNotNull('last_message_at')
            ->with(['customer.media', 'provider.media', 'lastMessage'])
            ->latest('last_message_at')
            ->paginate(20);

        return $this->success([
            'conversations' => ConversationResource::collection($conversations)->resolve(),
            // Badge on the Chat tab
            'unread_count' => (int) Conversation::where('customer_id', $user->id)->sum('customer_unread_count')
                + (int) Conversation::where('provider_id', $user->id)->sum('provider_unread_count'),
            'current_page' => $conversations->currentPage(),
            'last_page' => $conversations->lastPage(),
            'total' => $conversations->total(),
        ]);
    }

    /**
     * "Chat" button on a booking: open the thread with the booking's provider (created the first time).
     * Returns the same data as `show`, so the app can open the chat screen straight away.
     */
    public function openFromBooking(Request $request, Booking $booking): JsonResponse
    {
        abort_unless($booking->customer_id === $request->user()->id, 404);

        $conversation = Conversation::firstOrCreate(
            ['customer_id' => $booking->customer_id, 'provider_id' => $booking->provider_id],
            ['service_request_id' => $booking->service_request_id],
        );

        // Remember the latest booking the chat is about
        $conversation->update(['booking_id' => $booking->id]);

        return $this->show($request, $conversation);
    }

    /**
     * Chat (single): the header and the messages, oldest at the top.
     *
     * - No query: the latest 30 messages.
     * - ?before_id=120: 30 older messages (user scrolled to the top, only when `has_more` is true).
     * - ?after_id=150: only messages newer than 150 (polling while the screen is open).
     *
     * Opening the chat marks the other person's messages as read.
     */
    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        $this->ensureIsParticipant($request, $conversation);

        $request->validate([
            'before_id' => ['nullable', 'integer'],
            'after_id' => ['nullable', 'integer'],
        ]);

        $query = $conversation->messages()->with(['sender.media', 'media']);

        if ($request->filled('after_id')) {
            $messages = $query->where('id', '>', $request->integer('after_id'))->orderBy('id')->limit(100)->get();
            $hasMore = false;
        } else {
            // Take one extra message to know if there are older ones
            $messages = $query
                ->when($request->filled('before_id'), fn ($query) => $query->where('id', '<', $request->integer('before_id')))
                ->orderByDesc('id')
                ->limit(self::PER_PAGE + 1)
                ->get();

            $hasMore = $messages->count() > self::PER_PAGE;
            $messages = $messages->take(self::PER_PAGE)->reverse()->values();
        }

        $conversation->markAsReadBy($request->user());
        $conversation->load(['customer.media', 'provider.media', 'lastMessage']);

        return $this->success([
            'conversation' => [
                ...(new ConversationResource($conversation))->resolve(),
                // Orange phone button: only while a booking between the two is active, otherwise hide it
                'call_phone' => $conversation->callPhoneFor($request->user()),
            ],
            'messages' => MessageResource::collection($messages)->resolve(),
            'has_more' => $hasMore,
        ]);
    }

    /**
     * Send a message (text, photo or both).
     *
     * Chat safety: phone numbers in the text are hidden, and when the text has a phone number or words like
     * "cash" / "my number", a warning message is added to the thread right after it.
     * The response has every message that was added (1, or 2 with the warning).
     */
    public function send(SendMessageRequest $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();
        $this->ensureIsParticipant($request, $conversation);

        $body = $request->filled('body') ? trim($request->input('body')) : null;
        $maskedBody = $body !== null ? ChatFilter::maskPhoneNumbers($body) : null;
        $needsWarning = $body !== null && ($maskedBody !== $body || ChatFilter::hasBlockedWords($body));

        $messages = DB::transaction(function () use ($request, $conversation, $user, $maskedBody, $needsWarning) {
            $message = $conversation->messages()->create([
                'sender_id' => $user->id,
                'type' => $request->hasFile('image') ? Message::TYPE_IMAGE : Message::TYPE_TEXT,
                'body' => $maskedBody,
            ]);

            if ($request->hasFile('image')) {
                $message->addMedia($request->file('image'))->toMediaCollection('image');
            }

            $messages = [$message];

            if ($needsWarning) {
                $messages[] = $conversation->messages()->create(['sender_id' => $user->id, 'type' => Message::TYPE_SYSTEM]);
            }

            // The other person gets an unread badge. The list preview shows the user's message, not the warning.
            $unreadColumn = $conversation->customer_id === $user->id ? 'provider_unread_count' : 'customer_unread_count';
            $conversation->increment($unreadColumn, 1, [
                'last_message_id' => $message->id,
                'last_message_at' => now(),
            ]);

            return new Collection($messages);
        });

        $messages->load(['sender.media', 'media']);

        return $this->success(['messages' => MessageResource::collection($messages)->resolve()], null, 201);
    }

    /**
     * Only the customer and the provider of the thread can open it (404 otherwise).
     */
    private function ensureIsParticipant(Request $request, Conversation $conversation): void
    {
        abort_unless($conversation->hasParticipant($request->user()), 404);
    }
}
