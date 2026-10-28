import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

let instance = null;

function createEcho() {
  return new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
    // Dùng window.axios (đã có CSRF + session cookie từ bootstrap.js) thay vì
    // fetch mặc định của Echo, để /broadcasting/auth xác thực đúng qua session.
    authorizer: (channel) => ({
      authorize: (socketId, callback) => {
        window.axios
          .post('/broadcasting/auth', { socket_id: socketId, channel_name: channel.name })
          .then((response) => callback(false, response.data))
          .catch((error) => callback(true, error));
      },
    }),
  });
}

// Khởi tạo lười: `new Echo()` mở websocket ngay, nên chỉ tạo ở lần đầu code chạm tới
// `window.Echo` (chat / story — luôn sau khi đăng nhập). Trang login và khách chưa
// đăng nhập không còn mở kết nối realtime.
Object.defineProperty(window, 'Echo', {
  configurable: true,
  get() {
    if (!instance) instance = createEcho();
    return instance;
  },
  set(value) {
    instance = value;
  },
});

/**
 * Rời mọi kênh private (không tạo kết nối mới nếu chưa từng có) — dùng khi đăng xuất,
 * để máy dùng chung không nhận tiếp tin của người trước.
 */
export function leaveAllEchoChannels() {
  try {
    instance?.leaveAllChannels();
  } catch {
    // kết nối đã đóng — không còn kênh nào để rời
  }
}
