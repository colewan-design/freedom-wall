export function timeAgo(value) {
  const diffMs = Date.now() - new Date(value).getTime();
  const min = Math.floor(diffMs / 60000);
  if (min < 1) return 'Just now';
  if (min < 60) return `${min}m ago`;
  const hr = Math.floor(min / 60);
  if (hr < 24) return `${hr}h ago`;
  const day = Math.floor(hr / 24);
  if (day < 7) return `${day}d ago`;
  return new Date(value).toLocaleDateString();
}

export function formatDateTime(value) {
  return new Date(value).toLocaleString();
}

// Chat shows wall-clock times on each message instead of relative ages, so a
// long-running room stays readable as a transcript.
export function timeOfDay(value) {
  return new Date(value).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
}

// The local calendar day a timestamp falls on, for grouping a transcript.
export function dayKey(value) {
  const date = new Date(value);

  return `${date.getFullYear()}-${date.getMonth()}-${date.getDate()}`;
}

// Heading for the marker that opens each day of the transcript. Recent days
// read better by name than by date, and the year only earns its place once the
// conversation is old enough to have crossed into another one.
export function dayLabel(value) {
  const date = new Date(value);
  const today = new Date();

  if (dayKey(date) === dayKey(today)) return 'Today';

  const yesterday = new Date(today);
  yesterday.setDate(yesterday.getDate() - 1);
  if (dayKey(date) === dayKey(yesterday)) return 'Yesterday';

  return date.toLocaleDateString([], {
    weekday: 'long',
    month: 'long',
    day: 'numeric',
    ...(date.getFullYear() === today.getFullYear() ? {} : { year: 'numeric' }),
  });
}

// Compact presence label for the active-members list: "now", "4m", "2h".
export function shortTimeAgo(value) {
  const min = Math.floor((Date.now() - new Date(value).getTime()) / 60000);
  if (min < 1) return 'now';
  if (min < 60) return `${min}m`;
  const hr = Math.floor(min / 60);
  if (hr < 24) return `${hr}h`;
  return `${Math.floor(hr / 24)}d`;
}
