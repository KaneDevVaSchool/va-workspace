<?php

use Illuminate\Support\Facades\Broadcast;
use Modules\Chat\App\Repositories\Contracts\ConversationRepositoryInterface;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('chat.conversation.{conversationId}', function ($user, $conversationId) {
    return app(ConversationRepositoryInterface::class)->isMember((int) $conversationId, (int) $user->id);
});
