import { mergeAttributes, Node } from '@tiptap/core';
import { VueNodeViewRenderer } from '@tiptap/vue-3';
import SocialStickerNodeView from '../components/SocialStickerNodeView.vue';
import { isValidStickerId } from './socialStickers.js';

export const StickerNode = Node.create({
  name: 'sticker',
  group: 'inline',
  inline: true,
  atom: true,
  selectable: true,
  draggable: true,

  addAttributes() {
    return {
      id: {
        default: null,
        parseHTML: (element) => element.getAttribute('data-sticker'),
        renderHTML: (attributes) =>
          attributes.id && isValidStickerId(attributes.id)
            ? { 'data-sticker': attributes.id }
            : {},
      },
      emoji: {
        default: '',
        parseHTML: (element) => {
          const named = element.getAttribute('aria-label');
          if (named) return named;
          return String(element.textContent || '').trim();
        },
        renderHTML: () => ({}),
      },
    };
  },

  parseHTML() {
    return [{ tag: 'span.social-sticker' }, { tag: 'span[data-sticker]' }];
  },

  renderHTML({ node, HTMLAttributes }) {
    const emoji = node.attrs.emoji || '';
    return [
      'span',
      mergeAttributes({ class: 'social-sticker' }, HTMLAttributes),
      emoji,
    ];
  },

  renderText({ node }) {
    return node.attrs.emoji || '';
  },

  addNodeView() {
    return VueNodeViewRenderer(SocialStickerNodeView);
  },

  addCommands() {
    return {
      insertSticker:
        (attrs) =>
        ({ commands }) => {
          if (!attrs?.id || !isValidStickerId(attrs.id)) return false;
          return commands.insertContent({
            type: this.name,
            attrs: { id: attrs.id, emoji: attrs.emoji || '' },
          });
        },
    };
  },
});
