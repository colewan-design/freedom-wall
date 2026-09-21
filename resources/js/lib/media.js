// Mirrors App\Rules\MediaAttachment. These checks only exist so someone picking
// a two-minute clip finds out before a 100MB upload; the server re-validates
// everything and is the authority.

export const MAX_ATTACHMENTS = 4;
export const MAX_VIDEOS = 1;
export const MAX_VIDEO_SECONDS = 60;
export const MAX_IMAGE_MEGABYTES = 5;
export const MAX_VIDEO_MEGABYTES = 100;

export const ACCEPTED_UPLOAD_TYPES =
  'image/jpeg,image/png,image/webp,video/mp4,video/quicktime,video/webm';

const VIDEO_EXTENSIONS = ['mp4', 'mov', 'webm'];

// Recorders overshoot a one-minute stop by a few frames; matching the server's
// tolerance keeps the two from disagreeing on the same file.
const DURATION_TOLERANCE_SECONDS = 0.5;

const METADATA_TIMEOUT_MS = 8000;

export function isVideoUrl(url) {
  if (typeof url !== 'string') return false;

  const path = url.split(/[?#]/)[0];
  const dot = path.lastIndexOf('.');

  return dot !== -1 && VIDEO_EXTENSIONS.includes(path.slice(dot + 1).toLowerCase());
}

export function isVideoFile(file) {
  return Boolean(file?.type?.startsWith('video/'));
}

/** "2 photos", "1 video", "1 photo and 1 video" — for the composer's label. */
export function describeAttachments(files) {
  const videos = files.filter(isVideoFile).length;
  const photos = files.length - videos;

  const parts = [];
  if (photos) parts.push(`${photos} photo${photos > 1 ? 's' : ''}`);
  if (videos) parts.push(`${videos} video${videos > 1 ? 's' : ''}`);

  return parts.join(' and ');
}

/** Resolves to the error to show, or null when the selection looks fine. */
export async function validateAttachments(files) {
  if (files.length > MAX_ATTACHMENTS) {
    return `You can attach up to ${MAX_ATTACHMENTS} files to a post.`;
  }

  const videos = files.filter(isVideoFile);

  if (videos.length > MAX_VIDEOS) {
    return 'You can attach one video per post.';
  }

  for (const file of files) {
    const limit = isVideoFile(file) ? MAX_VIDEO_MEGABYTES : MAX_IMAGE_MEGABYTES;

    if (file.size > limit * 1024 * 1024) {
      return isVideoFile(file)
        ? `Videos must be ${MAX_VIDEO_MEGABYTES}MB or smaller.`
        : `Photos must be ${MAX_IMAGE_MEGABYTES}MB or smaller.`;
    }
  }

  for (const video of videos) {
    const seconds = await readVideoDuration(video);

    // A browser that cannot decode the codec (HEVC .mov in Chrome, for one)
    // reports nothing. The server reads the container itself, so leave the
    // verdict to it rather than blocking a file that may well be fine.
    if (seconds !== null && seconds > MAX_VIDEO_SECONDS + DURATION_TOLERANCE_SECONDS) {
      return `Videos can be at most ${MAX_VIDEO_SECONDS} seconds long. "${video.name}" runs ${formatDuration(seconds)}.`;
    }
  }

  return null;
}

/** Resolves to the clip's length in seconds, or null if it cannot be read. */
export function readVideoDuration(file) {
  return new Promise((resolve) => {
    const objectUrl = URL.createObjectURL(file);
    const video = document.createElement('video');
    let settled = false;

    const finish = (seconds) => {
      if (settled) return;
      settled = true;
      clearTimeout(timer);
      video.removeAttribute('src');
      video.load();
      URL.revokeObjectURL(objectUrl);
      resolve(seconds);
    };

    const timer = setTimeout(() => finish(null), METADATA_TIMEOUT_MS);

    video.preload = 'metadata';
    video.muted = true;
    video.onloadedmetadata = () => finish(Number.isFinite(video.duration) ? video.duration : null);
    video.onerror = () => finish(null);
    video.src = objectUrl;
  });
}

export function formatDuration(seconds) {
  const whole = Math.round(seconds);

  return whole < 60
    ? `${whole} seconds`
    : `${Math.floor(whole / 60)}:${String(whole % 60).padStart(2, '0')}`;
}
