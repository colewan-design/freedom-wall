// Mirrors App\Rules\Hashtag. The character set is not cosmetic: the wall reads
// a post's tag back out of its body with /#([\w-]+)\s*$/, so anything outside
// that set would neither render as a hashtag nor get stripped from the text.

export const MIN_HASHTAG_LENGTH = 2;
export const MAX_HASHTAG_LENGTH = 30;

/** Accepts "#FYP", " fyp ", "f y p" and settles them all on "fyp". */
export function normalizeHashtag(value) {
  return String(value ?? '')
    .trim()
    .replace(/^#+/, '')
    .toLowerCase()
    .replace(/[^\w-]/g, '')
    .slice(0, MAX_HASHTAG_LENGTH);
}

/** The message to show for a half-written tag, or null while it is fine. */
export function hashtagError(value) {
  if (!value) return null;

  if (value.length < MIN_HASHTAG_LENGTH) {
    return `A hashtag needs at least ${MIN_HASHTAG_LENGTH} characters.`;
  }

  if (!/[a-z]/.test(value)) {
    return 'A hashtag needs at least one letter.';
  }

  return null;
}
