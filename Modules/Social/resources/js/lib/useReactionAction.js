import { onBeforeUnmount, ref } from 'vue';
import { REACTIONS, reactionByType } from '../constants/reactions.js';
import { playReactionSound } from './reactionSound.js';

export function cloneReactions(src = {}) {
  const next = {};
  for (const reaction of REACTIONS) {
    next[reaction.type] = Number(src[reaction.type] ?? 0);
  }
  next.total = Number(src.total ?? REACTIONS.reduce((sum, reaction) => sum + next[reaction.type], 0));
  return next;
}

export function applyOptimistic(localReactions, localMyReaction, type) {
  const next = cloneReactions(localReactions.value);
  const prev = localMyReaction.value;

  if (prev === type) {
    next[prev] = Math.max(0, next[prev] - 1);
    next.total = Math.max(0, next.total - 1);
    localMyReaction.value = null;
  } else if (prev) {
    next[prev] = Math.max(0, next[prev] - 1);
    next[type] += 1;
    localMyReaction.value = type;
  } else {
    next[type] += 1;
    next.total += 1;
    localMyReaction.value = type;
  }

  localReactions.value = next;
}

function prefersReducedMotion() {
  return typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

export function useReactionAction() {
  const popping = ref(false);
  const bursts = ref([]);
  let reactionGen = 0;
  let burstSeq = 0;
  let popTimer = null;

  function triggerPop() {
    popping.value = false;
    requestAnimationFrame(() => {
      popping.value = true;
    });
    clearTimeout(popTimer);
    popTimer = setTimeout(() => {
      popping.value = false;
    }, 420);
  }

  function spawnBursts(emoji, event) {
    if (!emoji || prefersReducedMotion()) return;

    const source = event?.currentTarget instanceof Element
      ? event.currentTarget
      : event?.target instanceof Element
        ? event.target
        : null;
    const rect = source?.getBoundingClientRect();
    const originX = rect ? rect.left + rect.width / 2 : window.innerWidth / 2;
    const originY = rect ? rect.top + rect.height / 2 : window.innerHeight / 2;
    const created = [];

    for (let i = 0; i < 3; i += 1) {
      const id = ++burstSeq;
      created.push({
        id,
        emoji,
        x: originX + (i - 1) * 18,
        y: originY,
        delay: i * 40,
        drift: (i - 1) * 22,
      });
    }

    bursts.value = [...bursts.value, ...created];
    window.setTimeout(() => {
      const ids = new Set(created.map((item) => item.id));
      bursts.value = bursts.value.filter((item) => !ids.has(item.id));
    }, 780);
  }

  function playFeedback(type, event, removing) {
    playReactionSound(removing ? null : type);
    triggerPop();
    if (!removing) spawnBursts(reactionByType(type)?.emoji, event);
    if (typeof navigator !== 'undefined' && navigator.vibrate) {
      navigator.vibrate(removing ? 8 : 12);
    }
  }

  function nextGen() {
    reactionGen += 1;
    return reactionGen;
  }

  function isLatest(gen) {
    return gen === reactionGen;
  }

  onBeforeUnmount(() => {
    clearTimeout(popTimer);
  });

  return {
    popping,
    bursts,
    playFeedback,
    nextGen,
    isLatest,
  };
}
