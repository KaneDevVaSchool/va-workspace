import assert from 'node:assert/strict';
import {
  mergeMessageLists,
  conversationNeedsMessageSync,
  newestCachedMessageId,
} from '../Modules/Chat/resources/js/lib/chatMessageSync.js';

assert.deepEqual(
  mergeMessageLists(
    [{ id: 1, message: 'a' }, { id: 3, message: 'old' }],
    [{ id: 2, message: 'b' }, { id: 3, message: 'new' }],
  ).map((row) => row.id),
  [1, 2, 3],
);
assert.equal(
  mergeMessageLists([{ id: 3, message: 'old' }], [{ id: 3, message: 'new' }])[0].message,
  'new',
);

assert.equal(newestCachedMessageId([{ id: 5 }, { id: 9 }]), 9);

assert.equal(
  conversationNeedsMessageSync({ last_message: { id: 10 }, unread_count: 0 }, [{ id: 9 }]),
  true,
);
assert.equal(
  conversationNeedsMessageSync({ last_message: { id: 10 }, unread_count: 2 }, [{ id: 10 }]),
  true,
);
assert.equal(
  conversationNeedsMessageSync({ last_message: { id: 10 }, unread_count: 0 }, [{ id: 10 }]),
  false,
);

console.log('chat_message_sync: ok');
