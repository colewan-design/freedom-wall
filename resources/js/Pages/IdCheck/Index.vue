<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import NewsfeedLayout from '../../Layouts/NewsfeedLayout.vue';

defineOptions({ layout: NewsfeedLayout });

const props = defineProps({
  courses: { type: Array, default: () => [] },
  campuses: { type: Array, default: () => [] },
  defaults: { type: Object, default: () => ({}) },
  caption: { type: String, default: '' },
});

const CARD_W = 1050;
const CARD_H = 1650;
const STORY_W = 1080;
const STORY_H = 1920;
const SQUARE_W = 1080;
const SQUARE_H = 1080;
const FRAME = { x: 246, y: 320, w: 558, h: 548, r: 6 };

const THEMES = {
  olive: {
    label: 'Forest',
    band: ['#075c3b', '#183d2e'],
    accent: '#0a5035',
    paper: ['#e4e7ba', '#c9d48d'],
    panel: '#edf0ce',
    ink: '#21301d',
    sub: '#536141',
    story: ['#123e2c', '#07271c'],
    banner: ['#c8b087', '#5f4f34'],
    stripe: ['#ff8a68', '#d83c4e'],
    line: '#35452b',
    chip: '#f7f3ea',
  },
  maroon: {
    label: 'Moss',
    band: ['#89946d', '#4c5d42'],
    accent: '#612f32',
    paper: ['#ece2c3', '#d7c291'],
    panel: '#f3ead1',
    ink: '#301f1f',
    sub: '#71574b',
    story: ['#53634a', '#283529'],
    banner: ['#c29f6d', '#5c4330'],
    stripe: ['#ff9467', '#b92f43'],
    line: '#5e453c',
    chip: '#f9f3ea',
  },
  night: {
    label: 'Earth',
    band: ['#6d4129', '#3b261c'],
    accent: '#20352e',
    paper: ['#dfe4c2', '#bcc792'],
    panel: '#e8edd0',
    ink: '#172621',
    sub: '#4d6257',
    story: ['#563521', '#261a15'],
    banner: ['#b5a07b', '#4f4331'],
    stripe: ['#ff8964', '#cc3a4b'],
    line: '#284038',
    chip: '#f7f4ec',
  },
  slate: {
    label: 'Slate',
    band: ['#173f48', '#10252d'],
    accent: '#173f48',
    paper: ['#e3e6d9', '#c3cbb5'],
    panel: '#eef0e6',
    ink: '#14292c',
    sub: '#516368',
    story: ['#173f48', '#0d242b'],
    banner: ['#b8aa8c', '#4a4a3d'],
    stripe: ['#d96d42', '#a82935'],
    line: '#294a50',
    chip: '#f6f4ed',
  },
};

const TEMPLATES = [
  { key: 'classic', label: 'Classic', tone: 'olive' },
  { key: 'minimal', label: 'Minimal', tone: 'maroon' },
  { key: 'cordillera', label: 'Cordillera', tone: 'slate' },
  { key: 'retro', label: 'Retro', tone: 'night' },
];

const YEAR_LEVELS = ['1st Year', '2nd Year', '3rd Year', '4th Year', '5th Year', 'Irregular', 'Graduating', 'Alumni'];

const now = new Date();
const syStart = now.getMonth() >= 5 ? now.getFullYear() : now.getFullYear() - 1;
const schoolYear = `S.Y. ${syStart}-${syStart + 1}`;
const validUntil = `JUN ${syStart + 1}`;

const form = reactive({
  name: props.defaults.name || 'Rublyn C. Galuludan',
  course: 'BSED — VALUES EDUCATION',
  year: '1st Year',
  campus: props.campuses[0] || 'Alangilan',
  tagline: 'Awan ag klase no awan students.',
  handle: props.defaults.handle || '@yourhandle',
});

const themeKey = ref('olive');
const templateKey = ref('classic');
const format = ref('story');
const seed = ref(1);
const zoom = ref(1);

const photo = ref(null);
const photoError = ref('');
const busy = ref(false);
const cropSource = ref('');
const dragOver = ref(false);
const copied = ref('');
const siteLabel = ref('bsufw');

const canvasEl = ref(null);
const fileInput = ref(null);

const view = reactive({ scale: 1, auto: 1, cx: 0, cy: 0 });
const cardTransform = reactive({ x: 0, y: 0, s: 1 });

let objectUrl = null;
let frameRequest = 0;
let copiedTimer = 0;
let dragging = false;
let lastPoint = null;

const logo = new Image();
const logoReady = ref(false);

const theme = computed(() => THEMES[themeKey.value]);
const displayName = computed(() => form.name.trim() || 'Your Name Here');

const idNumber = computed(() => {
  const h = hash(`${form.name}|${form.course}|${form.campus}|${form.year}|${seed.value}`);
  return `${String(syStart).slice(2)}-${String(h % 100000).padStart(5, '0')}`;
});

const cropHint = computed(() => {
  if (!photo.value) return '';
  if (cropSource.value === 'face') return 'Face detected and centered.';
  if (cropSource.value === 'skin') return 'Auto-framed around your portrait. Drag if you want.';
  return 'Auto-framed. Drag the photo to fine-tune it.';
});

function selectTemplate(template) {
  templateKey.value = template.key;
  themeKey.value = template.tone;
}

function hash(value) {
  let h = 2166136261;
  for (let i = 0; i < value.length; i += 1) {
    h ^= value.charCodeAt(i);
    h = Math.imul(h, 16777619);
  }
  return h >>> 0;
}

function clamp(value, min, max) {
  return Math.min(Math.max(value, min), max);
}

function rr(ctx, x, y, w, h, r) {
  ctx.beginPath();
  if (typeof ctx.roundRect === 'function') {
    ctx.roundRect(x, y, w, h, r);
    return;
  }
  const radius = Math.min(r, w / 2, h / 2);
  ctx.moveTo(x + radius, y);
  ctx.arcTo(x + w, y, x + w, y + h, radius);
  ctx.arcTo(x + w, y + h, x, y + h, radius);
  ctx.arcTo(x, y + h, x, y, radius);
  ctx.arcTo(x, y, x + w, y, radius);
  ctx.closePath();
}

function setFont(ctx, weight, size, italic = false) {
  ctx.font = `${italic ? 'italic ' : ''}${weight} ${size}px Inter, system-ui, "Segoe UI", sans-serif`;
}

function fitFont(ctx, text, maxWidth, weight, startSize, minSize, italic = false) {
  let size = startSize;
  setFont(ctx, weight, size, italic);
  while (size > minSize && ctx.measureText(text).width > maxWidth) {
    size -= 2;
    setFont(ctx, weight, size, italic);
  }
  return size;
}

function measureTracked(ctx, text, spacing) {
  const chars = [...text];
  if (!chars.length) return 0;
  return chars.reduce((w, ch) => w + ctx.measureText(ch).width + spacing, 0) - spacing;
}

function drawTracked(ctx, text, x, y, spacing, align = 'left') {
  const chars = [...text];
  const total = measureTracked(ctx, text, spacing);
  const previous = ctx.textAlign;
  ctx.textAlign = 'left';
  let cursor = align === 'center' ? x - total / 2 : align === 'right' ? x - total : x;
  for (const ch of chars) {
    ctx.fillText(ch, cursor, y);
    cursor += ctx.measureText(ch).width + spacing;
  }
  ctx.textAlign = previous;
}

function drawOutlinedText(ctx, text, x, y, fill, stroke, lineWidth) {
  ctx.fillStyle = fill;
  ctx.strokeStyle = stroke;
  ctx.lineWidth = lineWidth;
  ctx.lineJoin = 'round';
  ctx.miterLimit = 2;
  ctx.strokeText(text, x, y);
  ctx.fillText(text, x, y);
}

async function detectFaceBox(img) {
  if (typeof window === 'undefined' || !('FaceDetector' in window)) return null;
  try {
    const detector = new window.FaceDetector({ maxDetectedFaces: 1, fastMode: true });
    const faces = await detector.detect(img);
    const box = faces?.[0]?.boundingBox;
    if (!box || !box.width || !box.height) return null;
    return { x: box.x, y: box.y, width: box.width, height: box.height, source: 'face' };
  } catch {
    return null;
  }
}

function isSkin(r, g, b) {
  const max = Math.max(r, g, b);
  const min = Math.min(r, g, b);
  return (
    r > 95 && g > 40 && b > 20 &&
    max - min > 15 &&
    Math.abs(r - g) > 15 &&
    r > g && g > b
  );
}

function estimateSkinBox(img) {
  const w = 96;
  const h = Math.max(1, Math.round((img.naturalHeight / img.naturalWidth) * w));
  const scratch = document.createElement('canvas');
  scratch.width = w;
  scratch.height = h;
  const ctx = scratch.getContext('2d', { willReadFrequently: true });
  if (!ctx) return null;

  ctx.drawImage(img, 0, 0, w, h);

  let pixels;
  try {
    pixels = ctx.getImageData(0, 0, w, h).data;
  } catch {
    return null;
  }

  let count = 0;
  let sumX = 0;
  let sumY = 0;
  let sumXX = 0;
  let sumYY = 0;

  for (let j = 0; j < h; j += 1) {
    for (let i = 0; i < w; i += 1) {
      const p = (j * w + i) * 4;
      if (!isSkin(pixels[p], pixels[p + 1], pixels[p + 2])) continue;
      count += 1;
      sumX += i;
      sumY += j;
      sumXX += i * i;
      sumYY += j * j;
    }
  }

  const coverage = count / (w * h);
  if (coverage < 0.02 || coverage > 0.7) return null;

  const meanX = sumX / count;
  const meanY = sumY / count;
  const sdX = Math.sqrt(Math.max(0, sumXX / count - meanX * meanX));
  const sdY = Math.sqrt(Math.max(0, sumYY / count - meanY * meanY));
  const size = Math.max(sdX, sdY) * 2.6;
  if (size < w * 0.12) return null;

  const ratio = img.naturalWidth / w;
  return {
    x: (meanX - size / 2) * ratio,
    y: (meanY - size / 2) * ratio,
    width: size * ratio,
    height: size * ratio,
    source: 'skin',
  };
}

function coverScaleFor(img) {
  return Math.max(FRAME.w / img.naturalWidth, FRAME.h / img.naturalHeight);
}

function clampView() {
  const img = photo.value;
  if (!img) return;
  const cover = coverScaleFor(img);
  view.scale = clamp(view.auto * zoom.value, cover, cover * 12);
  const halfW = FRAME.w / 2 / view.scale;
  const halfH = FRAME.h / 2 / view.scale;
  view.cx = clamp(view.cx, halfW, img.naturalWidth - halfW);
  view.cy = clamp(view.cy, halfH, img.naturalHeight - halfH);
}

async function autoFrame(img) {
  const cover = coverScaleFor(img);
  const box = (await detectFaceBox(img)) ?? estimateSkinBox(img);

  if (box) {
    view.auto = clamp((FRAME.h * 0.56) / box.height, cover, cover * 8);
    view.cx = box.x + box.width / 2;
    view.cy = box.y + box.height / 2 + ((0.5 - 0.43) * FRAME.h) / view.auto;
    cropSource.value = box.source;
  } else {
    view.auto = cover;
    view.cx = img.naturalWidth / 2;
    view.cy = img.naturalHeight * 0.42;
    cropSource.value = 'center';
  }

  zoom.value = 1;
  clampView();
}

async function loadFile(file) {
  if (!file) return;
  if (!file.type.startsWith('image/')) {
    photoError.value = 'That file is not an image.';
    return;
  }

  photoError.value = '';
  busy.value = true;
  const url = URL.createObjectURL(file);

  try {
    const img = new Image();
    img.src = url;
    await img.decode();
    if (objectUrl) URL.revokeObjectURL(objectUrl);
    objectUrl = url;
    photo.value = img;
    await autoFrame(img);
  } catch {
    URL.revokeObjectURL(url);
    photoError.value = 'Could not read that image. Try a JPG or PNG.';
  } finally {
    busy.value = false;
    scheduleRender();
  }
}

function onPick(event) {
  loadFile(event.target.files?.[0]);
  event.target.value = '';
}

function onDrop(event) {
  dragOver.value = false;
  loadFile(event.dataTransfer?.files?.[0]);
}

function onPaste(event) {
  const item = [...(event.clipboardData?.items ?? [])].find((entry) => entry.type.startsWith('image/'));
  if (item) loadFile(item.getAsFile());
}

function removePhoto() {
  if (objectUrl) URL.revokeObjectURL(objectUrl);
  objectUrl = null;
  photo.value = null;
  cropSource.value = '';
  zoom.value = 1;
  scheduleRender();
}

async function recrop() {
  if (!photo.value) return;
  await autoFrame(photo.value);
  scheduleRender();
}

function toCanvasPoint(event) {
  const canvas = canvasEl.value;
  const rect = canvas.getBoundingClientRect();
  return {
    x: ((event.clientX - rect.left) * canvas.width) / rect.width,
    y: ((event.clientY - rect.top) * canvas.height) / rect.height,
  };
}

function overFrame(point) {
  const { x, y, s } = cardTransform;
  return (
    point.x >= x + FRAME.x * s &&
    point.x <= x + (FRAME.x + FRAME.w) * s &&
    point.y >= y + FRAME.y * s &&
    point.y <= y + (FRAME.y + FRAME.h) * s
  );
}

function onPointerDown(event) {
  if (!photo.value) return;
  const point = toCanvasPoint(event);
  if (!overFrame(point)) return;
  dragging = true;
  lastPoint = point;
  canvasEl.value.setPointerCapture(event.pointerId);
}

function onPointerMove(event) {
  if (!dragging || !photo.value) return;
  const point = toCanvasPoint(event);
  const factor = cardTransform.s * view.scale;
  view.cx -= (point.x - lastPoint.x) / factor;
  view.cy -= (point.y - lastPoint.y) / factor;
  lastPoint = point;
  clampView();
  scheduleRender();
}

function onPointerUp(event) {
  if (!dragging) return;
  dragging = false;
  lastPoint = null;
  canvasEl.value?.releasePointerCapture?.(event.pointerId);
}

function onWheel(event) {
  if (!photo.value) return;
  const point = toCanvasPoint(event);
  if (!overFrame(point)) return;
  event.preventDefault();
  zoom.value = clamp(Number((zoom.value * (event.deltaY > 0 ? 0.94 : 1.06)).toFixed(3)), 1, 4);
}

function drawPlaceholder(ctx) {
  ctx.save();
  rr(ctx, FRAME.x, FRAME.y, FRAME.w, FRAME.h, FRAME.r);
  ctx.fillStyle = '#1b1d1f';
  ctx.fill();
  ctx.restore();

  ctx.save();
  ctx.setLineDash([16, 10]);
  ctx.lineWidth = 3;
  ctx.strokeStyle = 'rgba(255,255,255,0.26)';
  rr(ctx, FRAME.x + 28, FRAME.y + 28, FRAME.w - 56, FRAME.h - 56, 2);
  ctx.stroke();
  ctx.restore();

  const cx = FRAME.x + FRAME.w / 2;
  const cy = FRAME.y + FRAME.h / 2;
  ctx.save();
  ctx.globalAlpha = 0.48;
  ctx.fillStyle = '#f5f1e4';
  ctx.beginPath();
  ctx.arc(cx, cy - 34, 60, 0, Math.PI * 2);
  ctx.fill();
  ctx.beginPath();
  ctx.ellipse(cx, cy + 122, 124, 86, 0, Math.PI, 0);
  ctx.fill();
  ctx.restore();

  ctx.fillStyle = '#f5f1e4';
  setFont(ctx, 700, 24);
  drawTracked(ctx, 'ADD YOUR PORTRAIT', cx, FRAME.y + FRAME.h - 42, 1.8, 'center');
}

function drawDecorativeStrip(ctx, x, y, w, h, colors) {
  ctx.save();
  ctx.fillStyle = colors[0];
  ctx.fillRect(x, y, w, h);
  ctx.fillStyle = colors[1];
  ctx.fillRect(x + w * 0.31, y, w * 0.38, h);

  ctx.strokeStyle = 'rgba(255,230,198,0.9)';
  ctx.lineWidth = 3;
  for (let row = y + 12; row < y + h; row += 22) {
    ctx.beginPath();
    ctx.moveTo(x + 8, row);
    ctx.lineTo(x + w * 0.32, row + 10);
    ctx.lineTo(x + 8, row + 20);
    ctx.stroke();

    ctx.beginPath();
    ctx.moveTo(x + w - 8, row);
    ctx.lineTo(x + w * 0.68, row + 10);
    ctx.lineTo(x + w - 8, row + 20);
    ctx.stroke();
  }
  ctx.restore();
}

function drawPosterWatermark(ctx) {
  if (!logoReady.value) return;

  ctx.save();
  ctx.globalAlpha = 0.08;
  ctx.translate(170, 1210);
  ctx.rotate(-0.4);
  ctx.drawImage(logo, -180, -180, 360, 360);
  ctx.restore();

  ctx.save();
  ctx.globalAlpha = 0.05;
  ctx.translate(860, 1500);
  ctx.rotate(0.24);
  ctx.drawImage(logo, -200, -200, 400, 400);
  ctx.restore();
}

function drawSchoolBanner(ctx, t) {
  ctx.save();
  rr(ctx, 154, 122, 746, 210, 8);
  ctx.clip();

  const scenic = ctx.createLinearGradient(154, 122, 900, 332);
  scenic.addColorStop(0, t.banner[0]);
  scenic.addColorStop(0.48, '#d7d5b0');
  scenic.addColorStop(1, t.banner[1]);
  ctx.fillStyle = scenic;
  ctx.fillRect(154, 122, 746, 210);

  const sky = ctx.createLinearGradient(0, 122, 0, 240);
  sky.addColorStop(0, 'rgba(173, 211, 222, 0.95)');
  sky.addColorStop(1, 'rgba(216, 215, 179, 0.35)');
  ctx.fillStyle = sky;
  ctx.fillRect(154, 122, 746, 118);

  ctx.fillStyle = 'rgba(37,53,33,0.2)';
  ctx.beginPath();
  ctx.arc(232, 294, 150, Math.PI, Math.PI * 2);
  ctx.fill();
  ctx.beginPath();
  ctx.arc(852, 314, 198, Math.PI, Math.PI * 2);
  ctx.fill();

  ctx.fillStyle = 'rgba(72, 86, 53, 0.58)';
  for (let i = 0; i < 6; i += 1) {
    const x = 542 + i * 34;
    ctx.beginPath();
    ctx.moveTo(x, 186);
    ctx.lineTo(x + 18, 150);
    ctx.lineTo(x + 28, 186);
    ctx.closePath();
    ctx.fill();
    ctx.fillRect(x + 10, 186, 6, 34);
  }

  ctx.fillStyle = 'rgba(69, 57, 38, 0.86)';
  rr(ctx, 662, 146, 190, 82, 8);
  ctx.fill();
  setFont(ctx, 800, 22);
  ctx.fillStyle = '#f8efcf';
  ctx.textAlign = 'center';
  ctx.fillText('WELCOME', 757, 182);
  setFont(ctx, 600, 12);
  ctx.fillText('BENGUET STATE UNIVERSITY', 757, 202);

  ctx.fillStyle = '#101712';
  ctx.textAlign = 'left';
  ctx.font = '700 64px "Palatino Linotype", Georgia, serif';
  ctx.fillText('Benguet State', 216, 218);
  ctx.font = '700 72px "Palatino Linotype", Georgia, serif';
  ctx.fillText('University', 214, 290);
  ctx.restore();
}

function drawMountainMark(ctx, x, y, scale = 1) {
  ctx.save();
  ctx.translate(x, y);
  ctx.scale(scale, scale);
  ctx.strokeStyle = 'rgba(225, 222, 184, .3)';
  ctx.lineWidth = 7;
  ctx.lineCap = 'round';
  ctx.lineJoin = 'round';
  ctx.beginPath();
  ctx.moveTo(0, 82);
  ctx.lineTo(62, 0);
  ctx.lineTo(118, 82);
  ctx.moveTo(40, 82);
  ctx.lineTo(82, 34);
  ctx.lineTo(138, 82);
  ctx.stroke();
  ctx.restore();
}

function drawQr(ctx, x, y, size) {
  ctx.save();
  ctx.fillStyle = '#f0efe3';
  ctx.fillRect(x, y, size, size);
  const cells = 11;
  const unit = size / (cells + 2);
  ctx.fillStyle = '#0a3023';
  const finder = (cx, cy) => {
    ctx.fillRect(x + (cx + 1) * unit, y + (cy + 1) * unit, unit * 3, unit * 3);
    ctx.fillStyle = '#f0efe3';
    ctx.fillRect(x + (cx + 1.65) * unit, y + (cy + 1.65) * unit, unit * 1.7, unit * 1.7);
    ctx.fillStyle = '#0a3023';
    ctx.fillRect(x + (cx + 2.15) * unit, y + (cy + 2.15) * unit, unit * .7, unit * .7);
  };
  finder(0, 0);
  finder(7, 0);
  finder(0, 7);
  for (let row = 1; row < cells - 1; row += 1) {
    for (let col = 1; col < cells - 1; col += 1) {
      if ((row < 4 && col < 4) || (row < 4 && col > 6) || (row > 6 && col < 4)) continue;
      if ((hash(`${idNumber.value}-${row}-${col}`) + row + col) % 3 === 0) {
        ctx.fillRect(x + (col + 1) * unit, y + (row + 1) * unit, unit, unit);
      }
    }
  }
  ctx.restore();
}

function drawCard(ctx) {
  const t = theme.value;
  const selectedTemplate = templateKey.value;
  const nameText = displayName.value.trim().toUpperCase();
  const courseText = (form.course.trim() || 'YOUR PROGRAM HERE').toUpperCase();
  const handleText = form.handle.trim() || '@yourhandle';
  const taglineText = form.tagline.trim() || 'Awan ag klase no awan students.';

  ctx.save();
  rr(ctx, 0, 0, CARD_W, CARD_H, 26);
  ctx.clip();

  const base = ctx.createLinearGradient(0, 0, CARD_W, CARD_H);
  base.addColorStop(0, t.story[0]);
  base.addColorStop(1, t.story[1]);
  ctx.fillStyle = base;
  ctx.fillRect(0, 0, CARD_W, CARD_H);

  const glow = ctx.createRadialGradient(520, 510, 20, 520, 510, 760);
  glow.addColorStop(0, 'rgba(29, 103, 68, .32)');
  glow.addColorStop(1, 'rgba(4, 24, 17, 0)');
  ctx.fillStyle = glow;
  ctx.fillRect(0, 0, CARD_W, CARD_H);

  ctx.save();
  ctx.globalAlpha = selectedTemplate === 'minimal' ? .05 : .13;
  ctx.fillStyle = '#d7dfbf';
  ctx.beginPath();
  ctx.moveTo(0, 430);
  ctx.lineTo(170, 300);
  ctx.lineTo(278, 404);
  ctx.lineTo(400, 250);
  ctx.lineTo(610, 430);
  ctx.closePath();
  ctx.fill();
  ctx.restore();

  if (selectedTemplate !== 'minimal') drawMountainMark(ctx, 54, 90, 1.05);
  drawDecorativeStrip(ctx, 944, 0, 74, CARD_H, t.stripe);
  ctx.fillStyle = 'rgba(246, 241, 218, .75)';
  ctx.fillRect(922, 0, 4, CARD_H);

  ctx.textAlign = 'left';
  setFont(ctx, 750, 27);
  ctx.fillStyle = '#f4f1df';
  ctx.fillText('BSUkol', 276, 78);
  ctx.font = '900 82px "Arial Narrow", Impact, sans-serif';
  drawTracked(ctx, 'ID CHECK', 276, 174, 3);
  setFont(ctx, 600, 25);
  drawTracked(ctx, 'BENGUET STATE UNIVERSITY', 278, 236, 4);
  ctx.textAlign = 'right';
  setFont(ctx, 800, 28);
  ctx.fillText('2026', 870, 236);
  ctx.strokeStyle = '#e6e0c8';
  ctx.lineWidth = 3;
  ctx.beginPath();
  ctx.moveTo(788, 252);
  ctx.lineTo(872, 252);
  ctx.stroke();

  ctx.textAlign = 'left';
  ctx.fillStyle = '#e7e5d5';
  setFont(ctx, 650, 22);
  drawTracked(ctx, 'SAME\nCAMPUS\nDIFFERENT\nSTORIES'.split('\n')[0], 55, 520, 3);
  ctx.fillText('CAMPUS', 55, 554);
  ctx.fillText('DIFFERENT', 55, 588);
  ctx.fillText('STORIES', 55, 622);
  ctx.fillRect(55, 653, 28, 3);

  ctx.textAlign = 'right';
  ctx.fillText('GOOD', 888, 520);
  ctx.fillText('PEOPLE', 888, 554);
  ctx.fillText('GREAT', 888, 588);
  ctx.fillText('STORIES', 888, 622);
  ctx.fillRect(860, 653, 28, 3);

  ctx.save();
  rr(ctx, FRAME.x - 8, FRAME.y - 8, FRAME.w + 16, FRAME.h + 16, 8);
  ctx.fillStyle = '#eeeadd';
  ctx.fill();
  rr(ctx, FRAME.x, FRAME.y, FRAME.w, FRAME.h, FRAME.r);
  ctx.clip();
  if (photo.value) {
    const img = photo.value;
    const s = view.scale;
    ctx.drawImage(
      img,
      FRAME.x + FRAME.w / 2 - view.cx * s,
      FRAME.y + FRAME.h / 2 - view.cy * s,
      img.naturalWidth * s,
      img.naturalHeight * s,
    );
  } else {
    drawPlaceholder(ctx);
  }
  ctx.restore();

  ctx.save();
  rr(ctx, 54, 842, 846, 268, 10);
  ctx.fillStyle = '#eeeee2';
  ctx.fill();
  ctx.restore();

  ctx.textAlign = 'center';
  const nameSize = fitFont(ctx, nameText, 760, 800, 61, 34);
  setFont(ctx, 800, nameSize);
  ctx.fillStyle = '#0b3325';
  ctx.fillText(nameText, 477, 944);
  ctx.strokeStyle = '#163d2e';
  ctx.lineWidth = 3;
  ctx.beginPath();
  ctx.moveTo(180, 966);
  ctx.lineTo(774, 966);
  ctx.stroke();
  const courseSize = fitFont(ctx, courseText, 680, 650, 29, 19);
  setFont(ctx, 650, courseSize);
  ctx.fillText(courseText, 477, 1010);
  setFont(ctx, 650, 24);
  ctx.fillText(`${form.year.toUpperCase()}   |   ${String(form.campus || 'MAIN CAMPUS').toUpperCase()}`, 477, 1070);

  ctx.save();
  rr(ctx, 54, 1146, 846, 238, 10);
  ctx.strokeStyle = 'rgba(226, 224, 195, .36)';
  ctx.lineWidth = 4;
  ctx.stroke();
  ctx.restore();
  ctx.fillStyle = '#ece9d9';
  setFont(ctx, 600, 21);
  drawTracked(ctx, 'MOST SAID LINE', 477, 1132, 3, 'center');
  const taglineSize = fitFont(ctx, `“ ${taglineText} ”`, 700, 700, 42, 25, true);
  setFont(ctx, 700, taglineSize, true);
  ctx.fillText(`“ ${taglineText} ”`, 477, 1265);

  drawQr(ctx, 54, 1460, 118);
  ctx.textAlign = 'left';
  setFont(ctx, 650, 21);
  ctx.fillText(`ID No. ${idNumber.value}`, 205, 1502);
  ctx.fillText(handleText, 205, 1542);
  ctx.textAlign = 'right';
  ctx.fillText('BSU FREEDOM WALL', 882, 1502);
  setFont(ctx, 600, 17);
  ctx.fillStyle = 'rgba(238, 235, 217, .72)';
  ctx.fillText('BSUKOL ID CHECK', 882, 1540);

  if (selectedTemplate === 'retro') {
    ctx.save();
    ctx.globalAlpha = .08;
    ctx.fillStyle = '#f7efcf';
    for (let y = 0; y < CARD_H; y += 8) ctx.fillRect(0, y, CARD_W, 1);
    ctx.restore();
  }

  ctx.restore();
}

function drawStoryBackdrop(ctx) {
  const t = theme.value;
  const gradient = ctx.createLinearGradient(0, 0, 0, STORY_H);
  gradient.addColorStop(0, t.story[0]);
  gradient.addColorStop(1, t.story[1]);
  ctx.fillStyle = gradient;
  ctx.fillRect(0, 0, STORY_W, STORY_H);

  const glow = ctx.createRadialGradient(STORY_W * 0.52, 360, 70, STORY_W * 0.52, 360, 980);
  glow.addColorStop(0, 'rgba(36, 117, 78, .24)');
  glow.addColorStop(1, 'rgba(244,236,188,0)');
  ctx.fillStyle = glow;
  ctx.fillRect(0, 0, STORY_W, STORY_H);

  ctx.save();
  ctx.globalAlpha = 0.12;
  ctx.fillStyle = '#dce5b7';
  ctx.beginPath();
  ctx.arc(122, 340, 250, 0, Math.PI * 2);
  ctx.arc(962, 1540, 320, 0, Math.PI * 2);
  ctx.fill();
  ctx.restore();

  if (logoReady.value) {
    ctx.save();
    ctx.globalAlpha = 0.08;
    ctx.translate(176, 1510);
    ctx.rotate(-0.28);
    ctx.drawImage(logo, -260, -260, 520, 520);
    ctx.restore();
  }
}

function drawStoryChrome(ctx) {
  ctx.textAlign = 'center';
  setFont(ctx, 600, 26);
  ctx.fillStyle = 'rgba(243,246,211,0.84)';
  ctx.fillText('SAME CAMPUS. DIFFERENT STORIES.', STORY_W / 2, 1816);

  setFont(ctx, 600, 20);
  ctx.fillStyle = 'rgba(243,246,211,0.68)';
  ctx.fillText(siteLabel.value.toUpperCase(), STORY_W / 2, 1868);
  ctx.textAlign = 'left';
}

function render() {
  const canvas = canvasEl.value;
  if (!canvas) return;

  const story = format.value === 'story';
  const width = story ? STORY_W : SQUARE_W;
  const height = story ? STORY_H : SQUARE_H;

  if (canvas.width !== width) canvas.width = width;
  if (canvas.height !== height) canvas.height = height;

  const ctx = canvas.getContext('2d');
  if (!ctx) return;

  ctx.clearRect(0, 0, width, height);
  ctx.textBaseline = 'alphabetic';
  ctx.textAlign = 'left';

  if (!story) {
    const scale = SQUARE_H / CARD_H;
    const x = (SQUARE_W - CARD_W * scale) / 2;
    cardTransform.x = x;
    cardTransform.y = 0;
    cardTransform.s = scale;
    drawStoryBackdrop(ctx);
    ctx.save();
    ctx.translate(x, 0);
    ctx.scale(scale, scale);
    drawCard(ctx);
    ctx.restore();
    return;
  }

  drawStoryBackdrop(ctx);

  const scale = (STORY_W * 0.91) / CARD_W;
  const x = (STORY_W - CARD_W * scale) / 2;
  const y = 90;
  cardTransform.x = x;
  cardTransform.y = y;
  cardTransform.s = scale;

  ctx.save();
  ctx.shadowColor = 'rgba(0,0,0,0.44)';
  ctx.shadowBlur = 64;
  ctx.shadowOffsetY = 28;
  ctx.fillStyle = '#0f110f';
  rr(ctx, x, y, CARD_W * scale, CARD_H * scale, 18 * scale);
  ctx.fill();
  ctx.restore();

  ctx.save();
  ctx.translate(x, y);
  ctx.scale(scale, scale);
  drawCard(ctx);
  ctx.restore();

  drawStoryChrome(ctx);
}

function scheduleRender() {
  if (frameRequest) return;
  frameRequest = requestAnimationFrame(() => {
    frameRequest = 0;
    render();
  });
}

function fileName() {
  const slug = (form.name.trim() || 'student')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-|-$/g, '')
    .slice(0, 40);
  return `bsukol-id-${slug || 'student'}.png`;
}

function toBlob() {
  return new Promise((resolve) => canvasEl.value?.toBlob(resolve, 'image/png'));
}

function saveBlob(blob) {
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = fileName();
  document.body.appendChild(link);
  link.click();
  link.remove();
  setTimeout(() => URL.revokeObjectURL(url), 2000);
}

async function download() {
  const blob = await toBlob();
  if (blob) saveBlob(blob);
}

async function share() {
  const blob = await toBlob();
  if (!blob) return;

  const file = new File([blob], fileName(), { type: 'image/png' });
  if (navigator.canShare?.({ files: [file] })) {
    try {
      await navigator.share({ files: [file], text: props.caption });
      return;
    } catch (error) {
      if (error?.name === 'AbortError') return;
    }
  }

  saveBlob(blob);
}

async function copy(kind) {
  const text = kind === 'caption' ? props.caption : window.location.href;
  try {
    await navigator.clipboard.writeText(text);
    copied.value = kind;
    clearTimeout(copiedTimer);
    copiedTimer = setTimeout(() => {
      copied.value = '';
    }, 2000);
  } catch {
    copied.value = '';
  }
}

function shuffleId() {
  seed.value += 1;
}

watch(zoom, () => {
  clampView();
  scheduleRender();
});

watch([() => ({ ...form }), themeKey, templateKey, format, seed], scheduleRender, { deep: true });

onMounted(() => {
  siteLabel.value = `${window.location.host}/id-check`;

  logo.onload = () => {
    logoReady.value = true;
    scheduleRender();
  };
  logo.src = '/images/branding/bsu-logo-660.png';

  window.addEventListener('paste', onPaste);
  render();
  document.fonts?.ready.then(scheduleRender);
});

onBeforeUnmount(() => {
  window.removeEventListener('paste', onPaste);
  if (frameRequest) cancelAnimationFrame(frameRequest);
  clearTimeout(copiedTimer);
  if (objectUrl) URL.revokeObjectURL(objectUrl);
});
</script>

<template>
  <Head title="BSUkol ID Check" />

  <div class="idc">
    <header class="idc-hero">
      <div class="idc-hero-copy">
        <span class="idc-kicker">03 — Community Tools</span>
        <h1>BSUkol ID CHECK</h1>
        <p>Create your own batch-pic style poster and share your story.</p>
        <small>A fan-made novelty card for the wall. Not an official school ID.</small>
      </div>
      <blockquote>
        “Same campus.<br />Different stories.”
        <cite>— BSU Freedom Wall</cite>
      </blockquote>
    </header>

    <div class="idc-workspace">
      <aside class="idc-template-rail" aria-label="Poster templates">
        <h2>1 — Templates</h2>
        <div class="idc-template-list">
          <button
            v-for="template in TEMPLATES"
            :key="template.key"
            type="button"
            class="idc-template-card"
            :class="[{ active: templateKey === template.key }, `is-${template.key}`]"
            :aria-pressed="templateKey === template.key"
            @click="selectTemplate(template)"
          >
            <span class="idc-template-poster" aria-hidden="true">
              <span class="idc-template-title">ID CHECK</span>
              <span class="idc-template-mountain"></span>
              <span class="idc-template-photo"></span>
            </span>
            <span class="idc-template-name">
              {{ template.label }}
              <svg v-if="templateKey === template.key" viewBox="0 0 20 20" aria-hidden="true">
                <circle cx="10" cy="10" r="9" fill="currentColor" />
                <path d="m6 10 2.4 2.4L14 7" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
              </svg>
            </span>
          </button>
        </div>
      </aside>

      <section
        class="idc-stage"
        :class="{ over: dragOver }"
        @dragover.prevent="dragOver = true"
        @dragleave="dragOver = false"
        @drop.prevent="onDrop"
      >
        <canvas
          ref="canvasEl"
          class="idc-canvas"
          :class="{ grab: !!photo }"
          @pointerdown="onPointerDown"
          @pointermove="onPointerMove"
          @pointerup="onPointerUp"
          @pointercancel="onPointerUp"
          @wheel="onWheel"
        ></canvas>

        <p v-if="busy" class="idc-stage-note">Reading your photo…</p>
        <p v-else-if="cropHint" class="idc-stage-note">{{ cropHint }}</p>
      </section>

      <aside class="idc-panel-stack">
        <div class="idc-panel">
          <h2>2 — Upload Portrait</h2>
          <input ref="fileInput" type="file" accept="image/*" class="idc-file" @change="onPick" />
          <div
            class="idc-dropzone"
            :class="{ over: dragOver }"
            role="button"
            tabindex="0"
            @click="fileInput?.click()"
            @keydown.enter="fileInput?.click()"
            @keydown.space.prevent="fileInput?.click()"
            @dragover.prevent="dragOver = true"
            @dragleave="dragOver = false"
            @drop.prevent="onDrop"
          >
            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <rect x="3" y="4" width="18" height="16" rx="1" stroke="currentColor" stroke-width="1.4" />
              <circle cx="9" cy="10" r="1.7" fill="currentColor" />
              <path d="m4 17 5-5 4 4 3-3 4 4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
            <span>{{ photo ? 'Click to change or drag and drop' : 'Click to upload or drag and drop' }}</span>
            <small>PNG, JPG (Max 5MB)</small>
            <button type="button" class="idc-btn upload" @click.stop="fileInput?.click()">
              {{ photo ? 'Change photo' : 'Choose photo' }}
            </button>
          </div>
          <p v-if="photoError" class="idc-error">{{ photoError }}</p>

          <template v-if="photo">
            <label class="idc-field">
              <span class="idc-label">Zoom</span>
              <input v-model.number="zoom" type="range" min="1" max="4" step="0.01" class="idc-range" />
            </label>
            <div class="idc-row">
              <button type="button" class="idc-btn" @click="recrop">Re-center photo</button>
              <button type="button" class="idc-btn" @click="removePhoto">Remove</button>
            </div>
          </template>
        </div>

        <div class="idc-panel">
          <h2>3 — Details</h2>

          <label class="idc-field">
            <span class="idc-label">Full name</span>
            <input v-model="form.name" type="text" maxlength="40" placeholder="Rublyn C. Galuludan" class="idc-input" />
          </label>

          <label class="idc-field">
            <span class="idc-label">Course / Program</span>
            <input
              v-model="form.course"
              type="text"
              maxlength="48"
              list="idc-courses"
              placeholder="BSED-VALUES ED"
              class="idc-input"
            />
            <datalist id="idc-courses">
              <option v-for="course in courses" :key="course" :value="course" />
            </datalist>
          </label>

          <div class="idc-field-row">
            <label class="idc-field">
              <span class="idc-label">Year level</span>
              <select v-model="form.year" class="idc-input">
                <option v-for="level in YEAR_LEVELS" :key="level" :value="level">{{ level }}</option>
              </select>
            </label>

            <label class="idc-field">
              <span class="idc-label">Campus</span>
              <input v-model="form.campus" type="text" maxlength="24" list="idc-campuses" class="idc-input" />
              <datalist id="idc-campuses">
                <option v-for="campus in campuses" :key="campus" :value="campus" />
              </datalist>
            </label>
          </div>

          <label class="idc-field">
            <span class="idc-label">Most said line <span class="idc-optional">optional</span></span>
            <input
              v-model="form.tagline"
              type="text"
              maxlength="44"
              placeholder="Awan ag klase no awan students"
              class="idc-input"
            />
          </label>

          <div class="idc-field-row idc-meta-row">
            <label class="idc-field">
              <span class="idc-label">Handle <span class="idc-optional">optional</span></span>
              <input v-model="form.handle" type="text" maxlength="26" placeholder="@yourhandle" class="idc-input" />
            </label>
            <div class="idc-field">
              <span class="idc-label">ID No.</span>
              <button type="button" class="idc-input idc-id-number" title="Generate a new ID number" @click="shuffleId">
                {{ idNumber }}
              </button>
            </div>
          </div>
        </div>

        <div class="idc-panel">
          <h2>4 — Style</h2>

          <div class="idc-style-group">
            <span class="idc-label">Color theme</span>
            <div class="idc-swatches">
              <button
                v-for="(value, key) in THEMES"
                :key="key"
                type="button"
                class="idc-swatch"
                :class="{ active: themeKey === key }"
                :style="{ background: `linear-gradient(135deg, ${value.band[0]}, ${value.band[1]})` }"
                :title="value.label"
                :aria-label="value.label"
                @click="themeKey = key"
              ></button>
            </div>
          </div>

          <div class="idc-style-group">
            <span class="idc-label">Card size</span>
            <div class="idc-toggle">
              <button type="button" :class="{ active: format === 'story' }" @click="format = 'story'">
                Story (1080×1920)
              </button>
              <button type="button" :class="{ active: format === 'card' }" @click="format = 'card'">
                Square (1080×1080)
              </button>
            </div>
          </div>
        </div>

        <div class="idc-panel">
          <h2>5 — Export</h2>
          <button type="button" class="idc-btn primary idc-download" @click="download">
            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3v12m0 0 4-4m-4 4-4-4M4 19h16" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" /></svg>
            Download PNG
          </button>
          <div class="idc-row idc-export-row">
            <button type="button" class="idc-btn" @click="copy('link')">
              {{ copied === 'link' ? 'Link copied' : 'Copy link' }}
            </button>
            <button type="button" class="idc-btn" @click="share">Share</button>
          </div>
          <p class="idc-tip">
            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M9 18h6m-5 3h4M8.2 14.5A6 6 0 1 1 16 14c-1 .8-1.3 1.5-1.4 2H9.5c-.1-.5-.4-1-1.3-1.5Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /></svg>
            <span><strong>Tip:</strong> Keep your photo clear and use a portrait shot for the best result. This is a fan-made poster for the wall.</span>
          </p>
        </div>
      </aside>
    </div>

    <div class="idc-bottom">
      <aside class="idc-disclaimer">
        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.5" /><path d="M12 10v6m0-9h.01" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" /></svg>
        <p><strong>Not an official ID</strong><span>This is a fan-made novelty poster for the BSU Freedom Wall and should not be used as an official school ID.</span></p>
      </aside>
      <div class="idc-footer"><Link href="/wall">BSU Freedom Wall</Link><span>•</span><span>Est. 2026</span></div>
    </div>
  </div>
</template>

<style scoped>
.idc {
  --idc-green: #075c3b;
  --idc-green-dark: #063d2a;
  --idc-line: #cbd6cf;
  --idc-surface: #fff;
  --idc-soft: #f6faf7;
  --idc-text: #143526;
  --idc-muted: #566961;
  color: var(--idc-text);
}

.idc-hero {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: space-between;
  min-height: 98px;
  padding: 0 8px 10px;
  overflow: hidden;
  isolation: isolate;
}

.idc-hero::before,
.idc-hero::after {
  content: '';
  position: absolute;
  right: 10px;
  bottom: 0;
  z-index: -1;
  width: 68%;
  height: 94px;
  background: #dfe7e2;
  opacity: .72;
  clip-path: polygon(0 100%, 20% 72%, 34% 48%, 42% 58%, 55% 12%, 64% 42%, 72% 28%, 84% 63%, 94% 47%, 100% 70%, 100% 100%);
}

.idc-hero::after {
  right: 4%;
  width: 54%;
  height: 68px;
  opacity: .5;
  clip-path: polygon(0 100%, 14% 62%, 24% 76%, 36% 30%, 52% 70%, 63% 45%, 76% 78%, 88% 52%, 100% 83%, 100% 100%);
}

.idc-hero-copy {
  position: relative;
  z-index: 1;
}

.idc-kicker {
  display: block;
  margin-bottom: 9px;
  font-family: var(--b-mono);
  font-size: 10px;
  font-weight: 700;
  letter-spacing: .7px;
  text-transform: uppercase;
  color: var(--idc-green);
}

.idc-hero h1 {
  margin: 0 0 4px;
  font-family: var(--b-display);
  font-size: clamp(28px, 3vw, 34px);
  font-weight: 800;
  letter-spacing: -.02em;
  color: var(--idc-green-dark);
}

.idc-hero p {
  margin: 0;
  font-size: 14px;
  color: #29463a;
}

.idc-hero small {
  display: block;
  margin-top: 5px;
  font-size: 11px;
  color: var(--idc-muted);
}

.idc-hero blockquote {
  position: relative;
  z-index: 1;
  width: 315px;
  margin: 0 70px 0 24px;
  padding-left: 27px;
  border-left: 1px solid #8fb6a1;
  font: italic 700 17px/1.45 var(--b-mono);
  color: #173c2b;
}

.idc-hero cite {
  display: block;
  margin-top: 5px;
  font: 700 9px var(--b-mono);
  letter-spacing: 1px;
  text-align: center;
  text-transform: uppercase;
  color: #4a695b;
}

.idc-workspace {
  display: grid;
  grid-template-columns: 188px minmax(420px, 1fr) 370px;
  align-items: stretch;
  background: var(--idc-surface);
  border: 1px solid var(--idc-line);
}

.idc-template-rail {
  padding: 28px 20px;
}

.idc-template-rail h2,
.idc-panel h2 {
  margin: 0;
  font-family: var(--b-mono);
  font-size: 10px;
  font-weight: 800;
  letter-spacing: .75px;
  text-transform: uppercase;
  color: var(--idc-green);
}

.idc-template-list {
  display: flex;
  flex-direction: column;
  gap: 14px;
  margin-top: 20px;
}

.idc-template-card {
  width: 100%;
  padding: 0;
  overflow: hidden;
  border: 1px solid var(--idc-line);
  border-radius: 2px;
  background: #fff;
  color: var(--idc-text);
  text-align: left;
  cursor: pointer;
  box-shadow: 0 2px 8px rgba(15, 48, 34, .05);
}

.idc-template-card.active {
  border: 2px solid #08a451;
}

.idc-template-poster {
  position: relative;
  display: block;
  height: 128px;
  overflow: hidden;
  background: linear-gradient(160deg, #0a412d, #08271d);
}

.idc-template-card.is-minimal .idc-template-poster {
  background: linear-gradient(160deg, #ecece2, #d8d9ca);
}

.idc-template-card.is-cordillera .idc-template-poster {
  background: linear-gradient(160deg, #ddd7ca, #c8c1b4);
  filter: grayscale(.85);
}

.idc-template-card.is-retro .idc-template-poster {
  background: linear-gradient(160deg, #304137, #14251e);
  filter: saturate(.55);
}

.idc-template-title {
  position: absolute;
  top: 9px;
  left: 0;
  right: 0;
  z-index: 2;
  text-align: center;
  font: 800 13px var(--b-mono);
  color: #f2efe0;
}

.is-minimal .idc-template-title,
.is-cordillera .idc-template-title {
  color: #24392f;
}

.idc-template-mountain {
  position: absolute;
  inset: 34px 8px 0;
  opacity: .24;
  background: #b8c7b5;
  clip-path: polygon(0 76%, 22% 44%, 35% 60%, 52% 10%, 70% 61%, 84% 38%, 100% 76%, 100% 100%, 0 100%);
}

.idc-template-photo {
  position: absolute;
  left: 38px;
  right: 38px;
  top: 45px;
  bottom: 9px;
  border: 2px solid rgba(243, 241, 224, .85);
  background: linear-gradient(165deg, transparent 0 48%, rgba(8, 40, 28, .72) 49%), linear-gradient(25deg, #91a89a, #dce5df);
}

.idc-template-name {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 8px 10px;
  font: 800 9px var(--b-mono);
  text-transform: uppercase;
}

.idc-template-name svg {
  width: 14px;
  height: 14px;
  color: #06a451;
}

.idc-stage {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: flex-start;
  min-width: 0;
  padding: 22px 34px;
  background: #fff;
  border-inline: 1px solid var(--idc-line);
}

.idc-stage.over {
  background: #f2faf5;
}

.idc-canvas {
  max-width: 100%;
  max-height: 758px;
  width: auto;
  height: auto;
  border-radius: 18px;
  background: #092c20;
  box-shadow: 0 14px 32px rgba(15, 51, 35, .16);
  touch-action: none;
}

.idc-canvas.grab {
  cursor: grab;
}

.idc-canvas.grab:active {
  cursor: grabbing;
}

.idc-stage-note {
  margin: 8px 0 0;
  font-size: 11px;
  color: var(--idc-muted);
  text-align: center;
}

.idc-panel-stack {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 12px 16px;
}

.idc-panel {
  display: flex;
  flex-direction: column;
  gap: 7px;
  padding: 11px 14px;
  background: #fff;
  border: 1px solid var(--idc-line);
}

.idc-file {
  display: none;
}

.idc-dropzone {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 104px;
  gap: 5px;
  border: 1px dashed #b8c8bf;
  background: #fbfdfb;
  color: #3f5c4e;
  cursor: pointer;
}

.idc-dropzone.over {
  border-color: #06a451;
  background: #f0faf4;
}

.idc-dropzone > svg {
  width: 29px;
  color: #078e49;
}

.idc-dropzone > span {
  font-size: 12px;
}

.idc-dropzone > small {
  font-size: 9px;
  color: #72847b;
}

.idc-row {
  display: flex;
  gap: 8px;
}

.idc-row.spread {
  justify-content: space-between;
  align-items: center;
}

.idc-btn {
  flex: 1;
  min-width: 0;
  min-height: 31px;
  padding: 6px 12px;
  border-radius: 2px;
  border: 1px solid var(--idc-line);
  background: #fff;
  color: var(--idc-text);
  font-family: var(--b-sans);
  font-size: 11px;
  font-weight: 700;
  cursor: pointer;
  transition: border-color 0.15s ease, background 0.15s ease, color 0.15s ease;
}

.idc-btn:hover {
  border-color: #059d4e;
  color: #05743e;
}

.idc-btn.primary {
  background: #06a451;
  border-color: #06a451;
  color: #fff;
}

.idc-btn.primary:hover {
  filter: brightness(1.05);
  color: var(--nf-accent-contrast);
}

.idc-btn.upload {
  flex: 0 0 auto;
  min-height: 29px;
  margin-top: 5px;
  padding-inline: 20px;
  border-color: #08a451;
  color: #078446;
}

.idc-field {
  display: flex;
  flex-direction: column;
  min-width: 0;
  gap: 4px;
}

.idc-field-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.6rem;
}

.idc-label {
  font-size: 10px;
  font-weight: 650;
  color: #3d5549;
}

.idc-optional {
  font-weight: 500;
  opacity: 0.7;
}

.idc-input {
  width: 100%;
  height: 31px;
  padding: 6px 10px;
  border-radius: 2px;
  border: 1px solid var(--idc-line);
  background: #f8faf8;
  color: #253e32;
  font-family: inherit;
  font-size: 11px;
}

.idc-input:focus {
  outline: none;
  border-color: #06a451;
  box-shadow: 0 0 0 2px rgba(6, 164, 81, .09);
}

.idc-id-number {
  text-align: left;
  cursor: pointer;
}

.idc-range {
  width: 100%;
  accent-color: var(--nf-accent);
}

.idc-swatches {
  display: flex;
  gap: 13px;
}

.idc-swatch {
  width: 24px;
  height: 24px;
  border-radius: 50%;
  border: 2px solid #fff;
  cursor: pointer;
  padding: 0;
  box-shadow: 0 0 0 1px #9caea4;
}

.idc-swatch.active {
  box-shadow: 0 0 0 2px #058f49;
}

.idc-style-group {
  display: flex;
  flex-direction: column;
  gap: 7px;
}

.idc-toggle {
  display: flex;
  border: 1px solid var(--idc-line);
}

.idc-toggle button {
  flex: 1;
  min-height: 30px;
  padding: 5px 8px;
  border: none;
  border-right: 1px solid var(--idc-line);
  background: #fff;
  color: #4d6257;
  font-family: inherit;
  font-size: 10px;
  font-weight: 650;
  cursor: pointer;
}

.idc-toggle button:last-child { border-right: 0; }

.idc-toggle button.active {
  background: #06a451;
  color: #fff;
}

.idc-download {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 7px;
  width: 100%;
}

.idc-download svg {
  width: 15px;
}

.idc-tip {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  margin: 3px 0 0;
  font-size: 9.5px;
  line-height: 1.4;
  color: #5a6e63;
}

.idc-tip svg {
  flex: 0 0 auto;
  width: 18px;
  color: #078e49;
}

.idc-hint {
  margin: 0;
  font-size: 10px;
  line-height: 1.5;
  color: var(--idc-muted);
}

.idc-error {
  margin: 0;
  font-size: 0.8rem;
  color: #dc2626;
}

.idc-bottom {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 20px;
  padding-top: 14px;
}

.idc-disclaimer {
  display: flex;
  gap: 10px;
  width: 335px;
  padding: 12px 14px;
  background: #eef8f2;
  color: #49685a;
}

.idc-disclaimer > svg {
  flex: 0 0 auto;
  width: 17px;
  color: #078e49;
}

.idc-disclaimer p {
  display: flex;
  flex-direction: column;
  gap: 3px;
  margin: 0;
  font-size: 9.5px;
  line-height: 1.45;
}

.idc-disclaimer strong { color: #14603c; }

.idc-footer {
  display: flex;
  gap: 10px;
  padding-bottom: 4px;
  font: 600 9px var(--b-mono);
  letter-spacing: .6px;
  text-transform: uppercase;
  color: #71837a;
}

.idc-footer a {
  color: inherit;
  text-decoration: none;
}

@media (max-width: 1180px) {
  .idc-workspace {
    grid-template-columns: 160px minmax(370px, 1fr) 330px;
  }

  .idc-template-rail { padding-inline: 14px; }
  .idc-stage { padding-inline: 20px; }
  .idc-panel-stack { padding-inline: 12px; }
  .idc-hero blockquote { margin-right: 20px; }
}

@media (max-width: 960px) {
  .idc-workspace {
    grid-template-columns: 150px minmax(0, 1fr);
  }

  .idc-panel-stack {
    grid-column: 1 / -1;
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    border-top: 1px solid var(--idc-line);
  }

  .idc-stage { border-right: 0; }
}

@media (max-width: 700px) {
  .idc-hero {
    min-height: 0;
    padding: 4px 0 18px;
  }

  .idc-hero blockquote { display: none; }

  .idc-workspace {
    display: flex;
    flex-direction: column;
  }

  .idc-template-rail { padding: 18px 14px; }

  .idc-template-list {
    flex-direction: row;
    margin-top: 14px;
    padding-bottom: 4px;
    overflow-x: auto;
  }

  .idc-template-card {
    flex: 0 0 132px;
  }

  .idc-stage {
    order: 2;
    padding: 18px 14px;
    border: 0;
    border-top: 1px solid var(--idc-line);
  }

  .idc-canvas {
    max-height: 72vh;
  }

  .idc-panel-stack {
    order: 3;
    display: flex;
  }

  .idc-bottom {
    align-items: stretch;
    flex-direction: column;
  }

  .idc-disclaimer { width: 100%; }
  .idc-footer { justify-content: flex-end; }
}

/* Keep the reference's true-white editor in light mode while allowing the
   surrounding shell to retain its existing dark theme behavior. */
:global(:root[data-theme='dark'] .idc) {
  --idc-line: #34433b;
  --idc-surface: #0d1411;
  --idc-soft: #121c17;
  --idc-text: #e7eee9;
  --idc-muted: #9eaca4;
}

:global(:root[data-theme='dark'] .idc .idc-hero h1),
:global(:root[data-theme='dark'] .idc .idc-hero p),
:global(:root[data-theme='dark'] .idc .idc-hero blockquote) {
  color: #e7eee9;
}

:global(:root[data-theme='dark'] .idc .idc-workspace),
:global(:root[data-theme='dark'] .idc .idc-stage),
:global(:root[data-theme='dark'] .idc .idc-panel-stack),
:global(:root[data-theme='dark'] .idc .idc-panel),
:global(:root[data-theme='dark'] .idc .idc-template-card),
:global(:root[data-theme='dark'] .idc .idc-dropzone),
:global(:root[data-theme='dark'] .idc .idc-btn),
:global(:root[data-theme='dark'] .idc .idc-toggle button) {
  background: var(--idc-surface);
  color: var(--idc-text);
}

:global(:root[data-theme='dark'] .idc .idc-input) {
  background: var(--idc-soft);
  color: var(--idc-text);
}

@media (prefers-reduced-motion: reduce) {
  .idc-btn,
  .idc-template-card {
    transition: none;
  }
}
</style>
