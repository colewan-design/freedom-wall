// Sorting a conversation, shared by the thread page and each reply node so a
// nested branch reorders the same way the top level does.

export const REPLY_SORTS = [
  ['oldest', 'Oldest first'],
  ['newest', 'Newest first'],
  ['top', 'Top voted'],
];

/** Sorts a level of the tree without touching the array it was handed. */
export function sortReplies(replies, mode) {
  const list = [...(replies ?? [])];

  if (mode === 'newest') return list.sort((a, b) => b.id - a.id);

  // Ties on score fall back to oldest-first, so the reply that earned the votes
  // first sits above the one that merely matched it.
  if (mode === 'top') return list.sort((a, b) => b.score - a.score || a.id - b.id);

  return list.sort((a, b) => a.id - b.id);
}

/** Every reply in the tree, not just the top level. */
export function countReplies(replies) {
  return (replies ?? []).reduce((total, reply) => total + 1 + countReplies(reply.children), 0);
}

/**
 * Drops a new reply in under its parent, or at the end of the top level.
 * Returns whether a home was found — a false means the tree moved on and the
 * caller should reload rather than silently lose the reply.
 */
export function insertReply(replies, reply) {
  if (!reply.parent_id) {
    replies.push(reply);
    return true;
  }

  for (const node of replies) {
    if (node.id === reply.parent_id) {
      node.children.push(reply);
      return true;
    }

    if (insertReply(node.children, reply)) return true;
  }

  return false;
}

/** Finds one reply anywhere in the tree, for updating a score in place. */
export function findReply(replies, id) {
  for (const node of replies) {
    if (node.id === id) return node;

    const found = findReply(node.children, id);
    if (found) return found;
  }

  return null;
}
