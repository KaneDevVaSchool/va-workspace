import { Node, mergeAttributes } from '@tiptap/core';

export const MentionNode = Node.create({
  name: 'mention',
  group: 'inline',
  inline: true,
  atom: true,
  selectable: true,

  addAttributes() {
    return {
      id: {
        default: null,
        parseHTML: (element) => element.getAttribute('data-mention-id'),
        renderHTML: (attributes) => (attributes.id ? { 'data-mention-id': String(attributes.id) } : {}),
      },
      label: {
        default: '',
        parseHTML: (element) => {
          const named = element.getAttribute('data-mention-label');
          if (named) return named;
          return String(element.textContent || '').replace(/^@/, '');
        },
        renderHTML: () => ({}),
      },
    };
  },

  parseHTML() {
    return [{ tag: 'span.mention' }, { tag: 'span[data-mention-id]' }];
  },

  renderHTML({ node, HTMLAttributes }) {
    const label = node.attrs.label || '';
    return ['span', mergeAttributes({ class: 'mention' }, HTMLAttributes), `@${label}`];
  },

  renderText({ node }) {
    return `@${node.attrs.label || ''}`;
  },
});
