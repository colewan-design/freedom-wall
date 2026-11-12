// Labels for JobRadar's stored values. The server keeps the short slugs; these
// are what a reader sees.

export const TYPE_LABELS = {
  'full-time': 'Full-time',
  'part-time': 'Part-time',
  contractual: 'Contractual',
  freelance: 'Freelance',
  internship: 'Internship',
};

export const SECTOR_LABELS = {
  government: 'Government',
  private: 'Private',
};

export const POSTED_OPTIONS = [
  { value: 1, label: 'Last 24 hours' },
  { value: 7, label: 'Last 7 days' },
  { value: 30, label: 'Last 30 days' },
];

export const REPORT_REASONS = [
  { value: 'scam', label: 'Looks like a scam' },
  { value: 'closed', label: 'Already filled or closed' },
  { value: 'wrong-info', label: 'Details are wrong' },
  { value: 'spam', label: 'Spam or not a job' },
];

export function whereLabel(listing) {
  if (listing.location) return listing.location;
  return listing.is_remote ? 'Remote' : 'Location not stated';
}

/** "Closes Nov 30" / "Closes today" / null when no deadline was published. */
export function deadlineLabel(deadline) {
  if (!deadline) return null;

  // A bare date is midnight local time, not UTC, or it shifts a day for us.
  const [y, m, d] = deadline.split('-').map(Number);
  const date = new Date(y, m - 1, d);
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  const days = Math.round((date - today) / 86400000);

  if (days < 0) return 'Deadline passed';
  if (days === 0) return 'Closes today';
  if (days === 1) return 'Closes tomorrow';

  return `Closes ${date.toLocaleDateString([], { month: 'short', day: 'numeric' })}`;
}

export function formatDay(value) {
  if (!value) return '';
  const [y, m, d] = String(value).slice(0, 10).split('-').map(Number);

  return new Date(y, m - 1, d).toLocaleDateString([], { month: 'short', day: 'numeric', year: 'numeric' });
}

export function hostOf(url) {
  try {
    return new URL(url).hostname.replace(/^www\./, '');
  } catch {
    return url;
  }
}
