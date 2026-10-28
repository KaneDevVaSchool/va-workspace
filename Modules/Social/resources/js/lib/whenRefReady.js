import { watch } from 'vue';

/**
 * Chờ một template ref có giá trị (vd. component tải bất đồng bộ bằng defineAsyncComponent mới gắn vào).
 * Trả về `null` nếu quá hạn — thao tác phụ (focus, chèn mention) bị bỏ qua thay vì treo mãi, và watcher
 * luôn được dừng (watcher tạo sau `await` không gắn với component nên không tự dừng khi unmount).
 */
export function whenRefReady(target, timeoutMs = 5000) {
  if (target.value) return Promise.resolve(target.value);
  return new Promise((resolve) => {
    let timer = 0;
    const stop = watch(target, (value) => {
      if (!value) return;
      window.clearTimeout(timer);
      stop();
      resolve(value);
    });
    timer = window.setTimeout(() => {
      stop();
      resolve(null);
    }, timeoutMs);
  });
}
