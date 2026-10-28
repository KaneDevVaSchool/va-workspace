const MAX_EDGE = 1600;
const JPEG_QUALITY = 0.82;
const SKIP_UNDER_BYTES = 350 * 1024;

const HEIC = /heic|heif/i;
const MAX_BYTES = 10 * 1024 * 1024;

export class ChatFileError extends Error {}

/**
 * Ảnh chụp từ camera thường vượt 10MB. Thu nhỏ cạnh dài và xuất JPEG
 * trước khi đính kèm. GIF và tệp không phải ảnh giữ nguyên.
 *
 * @param {File} file
 * @returns {Promise<File>}
 */
export async function prepareChatFile(file) {
  if (!(file instanceof File)) return file;
  const heic = HEIC.test(file.type) || HEIC.test(file.name);
  const image = file.type.startsWith('image/') || heic;
  if (!image || file.type === 'image/gif') return file;
  if (!heic && file.size > 0 && file.size < SKIP_UNDER_BYTES) return file;

  let bitmap = null;
  try {
    bitmap = await createImageBitmap(file);
  } catch {
    if (heic || file.size > MAX_BYTES) {
      throw new ChatFileError('Ảnh này không gửi được. Hãy chọn JPG hoặc PNG.');
    }
    return file;
  }

  const longest = Math.max(bitmap.width, bitmap.height);
  const scale = longest > MAX_EDGE ? MAX_EDGE / longest : 1;
  const width = Math.max(1, Math.round(bitmap.width * scale));
  const height = Math.max(1, Math.round(bitmap.height * scale));
  const canvas = document.createElement('canvas');
  canvas.width = width;
  canvas.height = height;
  const context = canvas.getContext('2d');
  if (!context) {
    bitmap.close?.();
    return file;
  }
  context.drawImage(bitmap, 0, 0, width, height);
  bitmap.close?.();

  const blob = await new Promise((resolve) => {
    canvas.toBlob(resolve, 'image/jpeg', JPEG_QUALITY);
  });
  if (!(blob instanceof Blob) || blob.size === 0 || blob.size >= file.size) return file;

  const base = file.name.replace(/\.[^.]+$/, '') || 'anh';
  return new File([blob], `${base}.jpg`, { type: 'image/jpeg', lastModified: Date.now() });
}
