<?php

namespace App\Http\Controllers\Dev;

use App\Http\Controllers\Controller;
use App\Services\Vk\VkBotMessageHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VkChatController extends Controller
{
    public function index(): View
    {
        abort_unless(app()->isLocal(), 404);

        return view('dev.vk-chat');
    }

    public function send(Request $request, VkBotMessageHandler $handler): JsonResponse
    {
        abort_unless(app()->isLocal(), 404);

        $text = $request->string('message')->trim()->value();

        if ($text === '') {
            return response()->json(['error' => 'empty'], 422);
        }

        $reply = $handler->handle(vkUserId: 83607447, peerId: 1, text: $text);

        return response()->json(['reply' => $reply]);
    }
}
