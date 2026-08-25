import { Mark } from '@tiptap/core';

export const MentionMark = Mark.create({
  name: 'mention',

  parseHTML() {
    return [{ tag: 'span.mention' }];
  },

  renderHTML() {
    return ['span', { class: 'mention' }, 0];
  },
});
