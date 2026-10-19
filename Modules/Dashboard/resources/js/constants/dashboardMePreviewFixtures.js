/** Dữ liệu mẫu cho xem trước /dashboard/me khi cấu hình tiện ích nhanh. */
export const dashboardMePreviewOverview = {
  kpis: {
    total_tasks: 12,
    average_progress_percent: 68.5,
    overdue_tasks_count: 2,
  },
  tasks_status_breakdown: {
    not_started: 2,
    in_progress: 5,
    under_review: 1,
    on_hold: 1,
    completed: 3,
  },
  tasks: [
    {
      id: 1,
      title: 'Hoàn thiện báo cáo tuần Phòng Công nghệ',
      project_name: 'Vận hành workspace',
      status: 'in_progress',
      progress_percent: 45,
      due_date: '2026-10-22',
    },
    {
      id: 2,
      title: 'Rà soát quy trình chấm công tháng 10',
      project_name: 'HRM',
      status: 'under_review',
      progress_percent: 90,
      due_date: '2026-10-20',
    },
    {
      id: 3,
      title: 'Cập nhật tài liệu onboarding nhân sự mới',
      project_name: 'Nhân sự',
      status: 'not_started',
      progress_percent: 0,
      due_date: '2026-10-25',
    },
  ],
};
