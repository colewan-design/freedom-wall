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
const FRAME = { x: 248, y: 448, w: 532, h: 590, r: 4 };

const THEMES = {
  olive: {
    label: 'Classic',
    band: ['#4f6440', '#91a36f'],
    accent: '#335228',
    paper: ['#e4e7ba', '#c9d48d'],
    panel: '#edf0ce',
    ink: '#21301d',
    sub: '#536141',
    story: ['#5c7050', '#283123'],
    banner: ['#c8b087', '#5f4f34'],
    stripe: ['#ff8a68', '#d83c4e'],
    line: '#35452b',
    chip: '#f7f3ea',
  },
  maroon: {
    label: 'Maroon',
    band: ['#4e3b39', '#9f7a64'],
    accent: '#612f32',
    paper: ['#ece2c3', '#d7c291'],
    panel: '#f3ead1',
    ink: '#301f1f',
    sub: '#71574b',
    story: ['#5b463d', '#271a17'],
    banner: ['#c29f6d', '#5c4330'],
    stripe: ['#ff9467', '#b92f43'],
    line: '#5e453c',
    chip: '#f9f3ea',
  },
  night: {
    label: 'Night',
    band: ['#2e453f', '#748d7f'],
    accent: '#20352e',
    paper: ['#dfe4c2', '#bcc792'],
    panel: '#e8edd0',
    ink: '#172621',
    sub: '#4d6257',
    story: ['#40574e', '#17201c'],
    banner: ['#b5a07b', '#4f4331'],
    stripe: ['#ff8964', '#cc3a4b'],
    line: '#284038',
    chip: '#f7f4ec',
  },
};

const YEAR_LEVELS = ['1st Year', '2nd Year', '3rd Year', '4th Year', '5th Year', 'Irregular', 'Graduating', 'Alumni'];

const now = new Date();
const syStart = now.getMonth() >= 5 ? now.getFullYear() : now.getFullYear() - 1;
const schoolYear = `S.Y. ${syStart}-${syStart + 1}`;
const validUntil = `JUN ${syStart + 1}`;

const form = reactive({
  name: props.defaults.name ?? '',
  course: '',
  year: '1st Year',
  campus: props.campuses[0] ?? '',
  tagline: '',
  handle: props.defaults.handle ?? '',
});

const themeKey = ref('olive');
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

function drawCard(ctx) {
  const t = theme.value;

  ctx.save();
  rr(ctx, 0, 0, CARD_W, CARD_H, 18);
  ctx.clip();

  const paper = ctx.createLinearGradient(0, 0, 0, CARD_H);
  paper.addColorStop(0, t.paper[0]);
  paper.addColorStop(1, t.paper[1]);
  ctx.fillStyle = paper;
  ctx.fillRect(0, 0, CARD_W, CARD_H);

  ctx.save();
  ctx.globalAlpha = 0.18;
  ctx.fillStyle = '#ffffff';
  ctx.beginPath();
  ctx.arc(146, 252, 220, 0, Math.PI * 2);
  ctx.arc(224, 930, 170, 0, Math.PI * 2);
  ctx.arc(742, 1356, 240, 0, Math.PI * 2);
  ctx.fill();
  ctx.restore();

  drawPosterWatermark(ctx);
  drawSchoolBanner(ctx, t);
  drawDecorativeStrip(ctx, 856, 0, 76, CARD_H, t.stripe);

  ctx.save();
  ctx.fillStyle = 'rgba(255,255,255,0.22)';
  ctx.beginPath();
  ctx.moveTo(0, 216);
  ctx.lineTo(632, 0);
  ctx.lineTo(726, 0);
  ctx.lineTo(0, 288);
  ctx.closePath();
  ctx.fill();
  ctx.restore();

  ctx.save();
  ctx.fillStyle = '#111315';
  ctx.fillRect(FRAME.x - 12, FRAME.y - 12, FRAME.w + 24, FRAME.h + 24);
  rr(ctx, FRAME.x, FRAME.y, FRAME.w, FRAME.h, FRAME.r);
  ctx.fillStyle = '#1d1e22';
  ctx.fill();
  ctx.save();
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
  ctx.strokeStyle = '#f5f2e6';
  ctx.lineWidth = 3;
  ctx.strokeRect(FRAME.x - 8, FRAME.y - 8, FRAME.w + 16, FRAME.h + 16);
  ctx.restore();

  const nameText = displayName.value.trim();
  const courseText = form.course.trim() || 'YOUR PROGRAM HERE';
  const handleText = form.handle.trim();
  const taglineText = form.tagline.trim() || 'Awan ag klase no awan students';

  ctx.textAlign = 'center';
  const nameSize = fitFont(ctx, nameText, 610, 700, 54, 28);
  setFont(ctx, 700, nameSize);
  ctx.fillStyle = '#f3f1ea';
  ctx.shadowColor = 'rgba(53,59,34,0.28)';
  ctx.shadowBlur = 10;
  ctx.fillText(nameText, CARD_W / 2, 1120);
  ctx.shadowBlur = 0;

  ctx.strokeStyle = t.line;
  ctx.lineWidth = 3;
  ctx.beginPath();
  ctx.moveTo(154, 1142);
  ctx.lineTo(786, 1142);
  ctx.stroke();
  setFont(ctx, 500, 17);
  ctx.fillStyle = t.sub;
  ctx.fillText('Name', CARD_W / 2, 1168);

  const courseSize = fitFont(ctx, courseText.toUpperCase(), 610, 700, 38, 21);
  setFont(ctx, 700, courseSize);
  ctx.fillStyle = '#efe6d8';
  ctx.fillText(courseText.toUpperCase(), CARD_W / 2, 1238);
  ctx.beginPath();
  ctx.moveTo(146, 1262);
  ctx.lineTo(794, 1262);
  ctx.stroke();
  setFont(ctx, 500, 18);
  ctx.fillStyle = t.ink;
  ctx.fillText('Program & Course', CARD_W / 2, 1292);

  ctx.textAlign = 'left';
  setFont(ctx, 600, 22);
  ctx.fillStyle = t.ink;
  ctx.fillText(`ID ${idNumber.value}`, 122, 1388);
  ctx.fillText(form.year.toUpperCase(), 122, 1424);
  ctx.fillText((form.campus.trim() || 'MAIN CAMPUS').toUpperCase(), 122, 1460);

  setFont(ctx, 700, 15);
  ctx.fillStyle = t.sub;
  ctx.fillText(schoolYear, 122, 1494);
  ctx.fillText(`VALID ${validUntil}`, 122, 1518);
  if (handleText) {
    ctx.fillText(handleText, 122, 1542);
  }

  setFont(ctx, 600, 26);
  ctx.fillStyle = '#f4f3ed';
  ctx.fillText(taglineText, 116, 1460);
  setFont(ctx, 700, 16);
  ctx.fillStyle = '#283720';
  ctx.fillText('MOST SAID LINE', 116, 1492);

  ctx.save();
  rr(ctx, 632, 1496, 466, 108, 26);
  ctx.fillStyle = t.chip;
  ctx.shadowColor = 'rgba(0,0,0,0.14)';
  ctx.shadowBlur = 16;
  ctx.shadowOffsetY = 6;
  ctx.fill();
  ctx.restore();
  ctx.shadowBlur = 0;
  ctx.textAlign = 'center';
  setFont(ctx, 800, 28);
  ctx.fillStyle = '#181d18';
  ctx.fillText('BSUkol ID CHECK', 862, 1562);

  ctx.restore();
}

function drawStoryBackdrop(ctx) {
  const t = theme.value;
  const gradient = ctx.createLinearGradient(0, 0, 0, STORY_H);
  gradient.addColorStop(0, t.story[0]);
  gradient.addColorStop(1, t.story[1]);
  ctx.fillStyle = gradient;
  ctx.fillRect(0, 0, STORY_W, STORY_H);

  const glow = ctx.createRadialGradient(STORY_W * 0.52, 260, 70, STORY_W * 0.52, 260, 980);
  glow.addColorStop(0, 'rgba(244,236,188,0.28)');
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
  if (logoReady.value) {
    ctx.drawImage(logo, 22, 108, 270, 270);
  }

  ctx.textAlign = 'center';
  ctx.font = '900 106px Inter, system-ui, sans-serif';
  drawOutlinedText(ctx, 'ID CHECK', 672, 198, '#f3f6d3', '#18392a', 18);

  setFont(ctx, 800, 34);
  ctx.fillStyle = '#f3f6d3';
  ctx.fillText('BSUkol', 352, 112);

  setFont(ctx, 600, 32);
  ctx.fillStyle = 'rgba(243,246,211,0.84)';
  ctx.fillText('Drop your photo, match the batch-pic vibe, then post it.', STORY_W / 2, 1818);

  setFont(ctx, 600, 24);
  ctx.fillStyle = 'rgba(243,246,211,0.68)';
  ctx.fillText(siteLabel.value.toUpperCase(), STORY_W / 2, 1868);
  ctx.textAlign = 'left';
}

function render() {
  const canvas = canvasEl.value;
  if (!canvas) return;

  const story = format.value === 'story';
  const width = story ? STORY_W : CARD_W;
  const height = story ? STORY_H : CARD_H;

  if (canvas.width !== width) canvas.width = width;
  if (canvas.height !== height) canvas.height = height;

  const ctx = canvas.getContext('2d');
  if (!ctx) return;

  ctx.clearRect(0, 0, width, height);
  ctx.textBaseline = 'alphabetic';
  ctx.textAlign = 'left';

  if (!story) {
    cardTransform.x = 0;
    cardTransform.y = 0;
    cardTransform.s = 1;
    drawCard(ctx);
    return;
  }

  drawStoryBackdrop(ctx);

  const scale = (STORY_W * 0.84) / CARD_W;
  const x = (STORY_W - CARD_W * scale) / 2;
  const y = 292;
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

watch([() => ({ ...form }), themeKey, format, seed], scheduleRender, { deep: true });

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
      <span class="idc-kicker">Poster Mode</span>
      <h1>BSUkol ID CHECK</h1>
      <p>
        Use your portrait, course, and one iconic line. The card now leans into the BSU batch-pic poster vibe from your
        reference while still exporting cleanly for stories.
      </p>
    </header>

    <div class="idc-grid">
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

        <p v-if="busy" class="idc-stage-note">Reading your photo...</p>
        <p v-else-if="cropHint" class="idc-stage-note">{{ cropHint }}</p>
        <p v-else class="idc-stage-note">Drop a portrait here, paste one, or use the upload button.</p>
      </section>

      <section class="idc-panel-stack">
        <div class="idc-panel">
          <h2>1 - Portrait</h2>
          <input ref="fileInput" type="file" accept="image/*" class="idc-file" @change="onPick" />
          <div class="idc-row">
            <button type="button" class="idc-btn primary" @click="fileInput?.click()">
              {{ photo ? 'Change photo' : 'Upload portrait' }}
            </button>
            <button v-if="photo" type="button" class="idc-btn" @click="removePhoto">Remove</button>
          </div>
          <p v-if="photoError" class="idc-error">{{ photoError }}</p>

          <template v-if="photo">
            <label class="idc-field">
              <span class="idc-label">Zoom</span>
              <input v-model.number="zoom" type="range" min="1" max="4" step="0.01" class="idc-range" />
            </label>
            <div class="idc-row">
              <button type="button" class="idc-btn" @click="recrop">Re-center photo</button>
            </div>
          </template>

          <p class="idc-hint">The image stays in your browser. Nothing gets uploaded from this tool.</p>
        </div>

        <div class="idc-panel">
          <h2>2 - Details</h2>

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

          <label class="idc-field">
            <span class="idc-label">Handle <span class="idc-optional">optional</span></span>
            <input v-model="form.handle" type="text" maxlength="26" placeholder="@yourhandle" class="idc-input" />
          </label>

          <div class="idc-row spread">
            <span class="idc-hint">ID No. {{ idNumber }}</span>
            <button type="button" class="idc-btn small" @click="shuffleId">Shuffle</button>
          </div>
        </div>

        <div class="idc-panel">
          <h2>3 - Tone & Size</h2>

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

          <div class="idc-toggle">
            <button type="button" :class="{ active: format === 'story' }" @click="format = 'story'">
              Story poster
            </button>
            <button type="button" :class="{ active: format === 'card' }" @click="format = 'card'">Card only</button>
          </div>
        </div>

        <div class="idc-panel">
          <h2>4 - Export</h2>
          <div class="idc-row">
            <button type="button" class="idc-btn primary" @click="download">Download PNG</button>
            <button type="button" class="idc-btn" @click="share">Share</button>
          </div>
          <div class="idc-row">
            <button type="button" class="idc-btn small" @click="copy('caption')">
              {{ copied === 'caption' ? 'Caption copied' : 'Copy caption' }}
            </button>
            <button type="button" class="idc-btn small" @click="copy('link')">
              {{ copied === 'link' ? 'Link copied' : 'Copy link' }}
            </button>
          </div>
          <ol class="idc-steps">
            <li>Download the poster or share it straight from your phone.</li>
            <li>Open the BSUkol ID CHECK Add Yours story.</li>
            <li>Post it, tag friends, and let them make theirs too.</li>
          </ol>
          <p class="idc-hint">
            This is a fan-made novelty poster for the wall. It is not an official school ID and should not be used as one.
          </p>
          <Link href="/wall" class="idc-back">Back to the wall</Link>
        </div>
      </section>
    </div>
  </div>
</template>

<style scoped>
.idc {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

.idc-hero {
  background: var(--nf-hero-grad);
  border: 1px solid var(--nf-line);
  border-radius: var(--b-r-card);
  padding: 1.5rem 1.75rem;
}

.idc-kicker {
  display: inline-block;
  font-size: 0.75rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--nf-accent);
  margin-bottom: 0.4rem;
}

.idc-hero h1 {
  margin: 0 0 0.5rem;
  font-size: 1.9rem;
  font-weight: 800;
  color: var(--nf-ink);
}

.idc-hero p {
  margin: 0;
  max-width: 62ch;
  font-size: 0.92rem;
  line-height: 1.6;
  color: var(--nf-muted);
}

.idc-grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 360px;
  gap: 1.25rem;
  align-items: start;
}

.idc-stage {
  position: sticky;
  top: 1rem;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.75rem;
  background: var(--nf-panel);
  border: 1px solid var(--nf-line);
  border-radius: var(--b-r-card);
  padding: 1.25rem;
}

.idc-stage.over {
  border-color: var(--nf-accent);
  box-shadow: none;
}

.idc-canvas {
  max-width: 100%;
  max-height: 78vh;
  width: auto;
  height: auto;
  border-radius: var(--b-r-card);
  background: var(--nf-surface-2);
  touch-action: none;
}

.idc-canvas.grab {
  cursor: grab;
}

.idc-canvas.grab:active {
  cursor: grabbing;
}

.idc-stage-note {
  margin: 0;
  font-size: 0.8rem;
  color: var(--nf-muted);
  text-align: center;
}

.idc-panel-stack {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.idc-panel {
  background: var(--nf-panel);
  border: 1px solid var(--nf-line);
  border-radius: var(--b-r-card);
  padding: 1.1rem 1.15rem;
  display: flex;
  flex-direction: column;
  gap: 0.7rem;
}

.idc-panel h2 {
  margin: 0;
  font-size: 0.78rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--nf-muted);
}

.idc-file {
  display: none;
}

.idc-row {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.idc-row.spread {
  justify-content: space-between;
  align-items: center;
}

.idc-btn {
  flex: 1;
  min-width: 7rem;
  padding: 0.55rem 0.85rem;
  border-radius: var(--b-r-thumb);
  border: 1px solid var(--nf-line);
  background: var(--nf-surface-2);
  color: var(--nf-ink);
  font-size: 0.85rem;
  font-weight: 600;
  cursor: pointer;
  transition: border-color 0.15s ease, background 0.15s ease, color 0.15s ease;
}

.idc-btn:hover {
  border-color: var(--nf-accent);
  color: var(--nf-accent);
}

.idc-btn.primary {
  background: var(--nf-accent);
  border-color: var(--nf-accent);
  color: var(--nf-accent-contrast);
}

.idc-btn.primary:hover {
  filter: brightness(1.05);
  color: var(--nf-accent-contrast);
}

.idc-btn.small {
  flex: 0 1 auto;
  min-width: 0;
  padding: 0.4rem 0.7rem;
  font-size: 0.78rem;
}

.idc-field {
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
}

.idc-field-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.6rem;
}

.idc-label {
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--nf-muted);
}

.idc-optional {
  font-weight: 500;
  opacity: 0.7;
}

.idc-input {
  width: 100%;
  padding: 0.5rem 0.65rem;
  border-radius: var(--b-r-thumb);
  border: 1px solid var(--nf-line);
  background: var(--nf-surface-2);
  color: var(--nf-ink);
  font-family: inherit;
  font-size: 0.88rem;
}

.idc-input:focus {
  outline: none;
  border-color: var(--nf-accent);
}

.idc-range {
  width: 100%;
  accent-color: var(--nf-accent);
}

.idc-swatches {
  display: flex;
  gap: 0.5rem;
}

.idc-swatch {
  width: 2.4rem;
  height: 2.4rem;
  border-radius: var(--b-r-thumb);
  border: 2px solid transparent;
  cursor: pointer;
  padding: 0;
}

.idc-swatch.active {
  border-color: var(--nf-ink);
  box-shadow: none;
}

.idc-toggle {
  display: flex;
  gap: 0.35rem;
  background: var(--nf-surface-2);
  border: 1px solid var(--nf-line);
  border-radius: var(--b-r-thumb);
  padding: 0.25rem;
}

.idc-toggle button {
  flex: 1;
  padding: 0.4rem 0.5rem;
  border: none;
  border-radius: var(--b-r-sm);
  background: transparent;
  color: var(--nf-muted);
  font-family: inherit;
  font-size: 0.82rem;
  font-weight: 600;
  cursor: pointer;
}

.idc-toggle button.active {
  background: var(--nf-accent);
  color: var(--nf-accent-contrast);
}

.idc-steps {
  margin: 0;
  padding-left: 1.1rem;
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  font-size: 0.82rem;
  color: var(--nf-muted);
}

.idc-hint {
  margin: 0;
  font-size: 0.76rem;
  line-height: 1.5;
  color: var(--nf-muted);
}

.idc-error {
  margin: 0;
  font-size: 0.8rem;
  color: #dc2626;
}

.idc-back {
  font-size: 0.82rem;
  font-weight: 600;
  color: var(--nf-accent);
  text-decoration: none;
}

@media (max-width: 1000px) {
  .idc-grid {
    grid-template-columns: minmax(0, 1fr);
  }

  .idc-stage {
    position: static;
  }

  .idc-canvas {
    max-height: 62vh;
  }
}
</style>
