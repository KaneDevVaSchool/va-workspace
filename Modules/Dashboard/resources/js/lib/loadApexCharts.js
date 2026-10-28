//
// ApexCharts nặng (~270KB gzip, cả với chart chỉ dùng ở vài trang) — chỉ tải khi một biểu đồ thật sự
// cần vẽ, và dùng chung một lần tải cho mọi biểu đồ. Trang chủ nhân viên trên điện thoại
// (EmployeeHome) tái dùng module Dashboard nên trước đây phải tải cả thư viện này dù không vẽ gì.
//
let pending = null;

export function loadApexCharts() {
  if (!pending) {
    pending = import('apexcharts')
      .then((module) => module.default)
      .catch((error) => {
        // Lỗi mạng khi tải chunk: cho lần vẽ sau thử lại thay vì kẹt promise lỗi mãi.
        pending = null;
        throw error;
      });
  }
  return pending;
}
