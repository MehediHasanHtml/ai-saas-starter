<?php

namespace App\Http\Controllers\Api;

use App\AI\Data\ChatResult;
use App\Http\Controllers\Controller;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Services\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class MessageController extends Controller
{
    /**
     * POST /api/conversations/{id}/messages
     *
     * With "stream": true (default) responds with Server-Sent Events:
     *   event: start      data: {"user_message_id": 1}
     *   event: citations  data: [{index, document_id, document_title, page, score, snippet}]
     *   event: delta      data: {"text": "..."}         (many)
     *   event: done       data: {message}               (persisted assistant message)
     *   event: error      data: {"message": "..."}
     */
    public function store(Request $request, Conversation $conversation, ChatService $chat): JsonResponse|StreamedResponse
    {
        abort_unless((int) $conversation->user_id === (int) $request->user()->id, 404);

        $data = $request->validate([
            'content' => ['required', 'string', 'max:20000'],
            'stream' => ['sometimes', 'boolean'],
        ]);

        $conversation->loadMissing('organization');
        $user = $request->user();

        // Quota / plan / provider checks throw here, before any streaming starts,
        // so the client receives a normal 402/422 JSON error.
        $turn = $chat->prepare($conversation, $user, $data['content']);
        $provider = $turn['provider'];
        $started = hrtime(true);

        if (! ($data['stream'] ?? true)) {
            $result = $provider->chat($turn['request']);
            $message = $chat->complete($conversation, $user, $provider, $result, $turn['citations'], $this->ms($started));

            return response()->json([
                'user_message' => new MessageResource($turn['user_message']),
                'message' => new MessageResource($message),
            ]);
        }

        return response()->stream(function () use ($chat, $conversation, $user, $provider, $turn, $started) {
            $send = function (string $event, mixed $payload) {
                echo "event: {$event}\n";
                echo 'data: '.json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n\n";
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            };

            $send('start', ['user_message_id' => $turn['user_message']->id]);
            $send('citations', $turn['citations']);

            $text = '';
            $input = 0;
            $output = 0;

            try {
                foreach ($provider->stream($turn['request']) as $event) {
                    if (connection_aborted()) {
                        break;
                    }

                    if ($event->isDelta()) {
                        $text .= $event->text;
                        $send('delta', ['text' => $event->text]);
                    } else {
                        $input = $event->inputTokens;
                        $output = $event->outputTokens;
                    }
                }

                $result = new ChatResult(
                    content: $text,
                    model: $conversation->model,
                    inputTokens: $input,
                    outputTokens: $output ?: (int) ceil(mb_strlen($text) / 4),
                );

                $message = $chat->complete($conversation, $user, $provider, $result, $turn['citations'], $this->ms($started));
                $send('done', (new MessageResource($message))->resolve());
            } catch (Throwable $e) {
                report($e);
                $send('error', ['message' => app()->hasDebugModeEnabled() ? $e->getMessage() : 'The AI provider returned an error. Please try again.']);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no', // disable nginx buffering
        ]);
    }

    private function ms(int $startedNs): int
    {
        return (int) ((hrtime(true) - $startedNs) / 1_000_000);
    }
}
