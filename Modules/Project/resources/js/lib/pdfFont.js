//
// Nạp font Be Vietnam Pro (Regular + Bold) làm base64 để nhúng vào jsPDF —
// tách khỏi bundle chính bằng ?url, chỉ fetch khi thật sự xuất PDF. jsPDF
// mặc định không có glyph tiếng Việt có dấu nên bắt buộc nhúng font riêng.
//
import regularUrl from '../assets/fonts/BeVietnamPro-Regular.ttf?url';
import boldUrl from '../assets/fonts/BeVietnamPro-Bold.ttf?url';

let cached = null;

function bufferToBase64(buffer) {
  let binary = '';
  const bytes = new Uint8Array(buffer);
  const chunk = 0x8000;
  for (let i = 0; i < bytes.length; i += chunk) {
    binary += String.fromCharCode(...bytes.subarray(i, i + chunk));
  }
  return btoa(binary);
}

async function loadBase64(url) {
  const res = await fetch(url);
  const buffer = await res.arrayBuffer();
  return bufferToBase64(buffer);
}

export async function loadPdfFont() {
  if (cached) return cached;
  const [regular, bold] = await Promise.all([loadBase64(regularUrl), loadBase64(boldUrl)]);
  cached = { regular, bold };
  return cached;
}
