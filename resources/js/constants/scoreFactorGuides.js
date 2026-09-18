export const SCORE_FACTOR_GUIDES = {
  kitOverview: {
    title: 'Khung chấm điểm là gì?',
    kicker: 'Giải thích cho người dùng hàng ngày',
    summary:
      'Đây là “thước đo” của phòng ban. Khi chấm điểm nhân sự cuối kỳ, máy không đoán cảm tính — máy lấy số liệu từ từng công việc rồi nhân với 3 thước: độ khó, tiến độ, chất lượng.',
    steps: [
      {
        title: 'Chọn cách tính',
        body: 'Có 2 cách. “Đếm số việc” = việc nào cũng tính giống nhau, chỉ cần đếm việc xong / chưa xong. “Hiệu suất việc” = việc khó được tính nặng hơn việc dễ, xong sớm hơn hạn được cộng, làm ẩu bị trừ.',
      },
      {
        title: 'Chọn 3 thang (chỉ khi dùng Hiệu suất việc)',
        body: 'Độ khó = việc này nặng cỡ nào. Tiến độ = xong sớm, đúng hạn hay trễ. Chất lượng = kết quả có đạt không. Mỗi thang là một danh sách mức, mỗi mức có hệ số (ví dụ ×1.2).',
      },
      {
        title: 'Gán việc cho người',
        body: 'Khi tạo công việc, chọn độ khó ngay lúc giao. Trong lúc làm, cập nhật % tiến độ và ngày hoàn thành thật. Khi nghiệm thu, chọn mức chất lượng. Cuối kỳ, báo cáo đánh giá tự lấy 3 số này.',
      },
    ],
    example: {
      title: 'Ví dụ dễ nhớ',
      body: 'Việc “Soạn đề thi” chọn Độ khó = Khó (×1.2), nộp đúng hạn (×1.0), nghiệm thu Đạt (×1.0). Điểm việc = 100 × 1.2 × 1.0 × 1.0 = 120. Việc dễ hơn cùng hạn cùng Đạt chỉ được 85. Không cần nhớ công thức — nhớ: khó hơn / đúng hạn / làm tốt thì điểm cao hơn.',
    },
    notes: [
      'Không cần biết Excel hay công thức. Chỉ cần chọn đúng mức khi giao việc, ghi ngày xong thật, và chấm chất lượng khi nghiệm thu.',
      'Thang trên trang này phải trùng với mức đang chọn trên công việc. Đổi tên mức ở đây thì lần chấm sau sẽ dùng tên mới.',
    ],
  },
  kitCount: {
    title: 'Cách 1 — Đếm số việc',
    kicker: 'Mọi việc tính như nhau',
    summary:
      'Giống đếm điểm danh: việc xong cộng một số điểm, việc chưa xong cộng hoặc trừ một số điểm khác. Không phân biệt việc khó hay dễ.',
    steps: [
      {
        title: 'Khi nào dùng',
        body: 'Phòng làm nhiều việc nhỏ, gần giống nhau (ví dụ xử lý phiếu, trả lời yêu cầu). Không cần phân biệt “việc này khó hơn việc kia”.',
      },
      {
        title: 'Cần làm gì trên công việc',
        body: 'Chỉ cần giao việc và đánh dấu hoàn thành đúng. Không bắt buộc chọn độ khó hay chấm chất lượng theo thang.',
      },
    ],
    example: {
      title: 'Ví dụ',
      body: 'Điểm khởi đầu 100. Mỗi việc xong +5, mỗi việc chưa xong −2. Người A xong 8 việc, còn 2 việc → 100 + 40 − 4 = 136.',
    },
    notes: [
      'Nếu phòng muốn việc khó được tính nặng hơn, hãy chuyển sang “Hiệu suất việc”.',
    ],
  },
  kitWeight: {
    title: 'Cách 2 — Hiệu suất việc',
    kicker: 'Việc khó, đúng hạn, làm tốt thì điểm cao',
    summary:
      'Mỗi việc có “điểm chuẩn” theo độ khó, rồi nhân tiếp với tiến độ và chất lượng. Việc chưa xong, hoặc thiếu ngày hạn / chưa chấm chất lượng, có thể ra 0 điểm thực.',
    steps: [
      {
        title: 'Độ khó — chọn lúc giao việc',
        body: 'Người giao việc bấm một mức (Rất khó, Khó, Trung bình, Dễ…). Máy lấy hệ số của mức đó. Việc khó hơn = điểm chuẩn cao hơn.',
      },
      {
        title: 'Tiến độ — máy tự xếp, không chọn tay',
        body: 'Không có ô “chọn sớm hạn / trễ hạn” trên công việc. Máy so Ngày hạn với Ngày hoàn thành thực tế. Xong sớm hơn hạn → hệ số tốt. Trễ → hệ số kém.',
      },
      {
        title: 'Chất lượng — chọn lúc nghiệm thu',
        body: 'Mở “Đánh giá kết quả” trên công việc, chọn Đạt / Không đạt và mức (Xuất sắc, Đạt, Cần sửa…). Đây là bước người quản lý làm sau khi việc xong.',
      },
    ],
    example: {
      title: 'Ví dụ một việc',
      body: 'Điểm cơ bản 100. Khó ×1.2, đúng hạn ×1.0, Đạt ×1.0 → 120 điểm. Nếu trễ 4 ngày (×0.75) và phải sửa (×0.8) → 100 × 1.2 × 0.75 × 0.8 = 72.',
    },
    notes: [
      'Muốn điểm tiến độ đúng: phải có Ngày kết thúc (hạn) và khi xong phải ghi Ngày kết thúc thực tế.',
      'Muốn điểm chất lượng đúng: phải chấm “Đánh giá kết quả”, đừng để trống.',
    ],
  },
  difficulty: {
    title: 'Độ khó — chọn khi giao việc',
    kicker: 'Một việc = một mức, chọn một lần',
    summary:
      'Độ khó trả lời câu hỏi: “Việc này nặng cỡ nào so với việc thường?”. Chọn ngay khi tạo việc. Hệ số này nhân vào điểm chuẩn.',
    steps: [
      {
        title: 'Chọn mức trên form công việc',
        body: 'Ô tên “Độ khó” (hoặc tên tiêu chí phòng ban đặt). Bấm một dòng: Rất khó / Khó / Trung bình / Dễ, hoặc mức do phòng tự đặt. Chỉ chọn một.',
      },
      {
        title: 'Chọn theo việc, không theo người',
        body: 'Cùng một loại việc thì cùng mức, dù giao cho ai. Đừng chọn “Rất khó” chỉ vì muốn người đó được điểm cao.',
      },
      {
        title: 'Khóa sau khi giao (nếu bật)',
        body: 'Nếu khung điểm bật “Khóa độ khó sau khi giao việc”, khi đã có người thực hiện thì không đổi mức nữa — tránh chỉnh điểm lúc gần chấm kỳ.',
      },
    ],
    example: {
      title: 'Ví dụ chọn mức',
      body: 'Soạn đề thi học kỳ = Khó. In bản điểm danh = Dễ. Cùng hạn, cùng Đạt, việc Khó vẫn được điểm chuẩn cao hơn việc Dễ.',
    },
    notes: [
      'Không thấy ô Độ khó? Kiểm tra phòng ban đã chọn cách “Hiệu suất việc” trên Khung điểm, và tiêu chí nguồn thang độ khó đã gắn.',
      'Mức trên công việc phải trùng mã/tên với thang ở Khung điểm, nếu không báo cáo sẽ báo “thiếu độ khó”.',
    ],
  },
  progress: {
    title: 'Tiến độ — máy tự xếp từ ngày',
    kicker: 'Không chọn tay “sớm hạn / trễ hạn”',
    summary:
      'Thang tiến độ trên khung điểm (Sớm ≥20%, Đúng hạn, Trễ 1–2 ngày…) không hiện thành danh sách để bấm trên công việc. Máy tự xếp sau khi có ngày hạn và ngày xong thật.',
    steps: [
      {
        title: 'Nhập Ngày kết thúc = hạn phải xong',
        body: 'Đây là mốc “đúng hạn”. Thiếu ngày này máy không biết việc sớm hay trễ.',
      },
      {
        title: 'Cập nhật % (hoặc khối lượng) trong lúc làm',
        body: 'Ô “Cập nhật tiến độ” chỉ để mọi người thấy việc đã làm được bao nhiêu phần trăm. Số % này không phải mức thang chấm điểm.',
      },
      {
        title: 'Khi xong: ghi Ngày kết thúc thực tế',
        body: 'Máy lấy ngày này trừ ngày hạn. Xong trước hạn = sớm. Đúng ngày hạn = đúng hạn. Sau hạn = trễ, càng trễ hệ số càng thấp.',
      },
    ],
    example: {
      title: 'Ví dụ',
      body: 'Hạn 20/09. Xong ngày 18/09 → sớm. Xong đúng 20/09 → đúng hạn. Xong 24/09 → trễ 4 ngày, rơi vào mức “Trễ 3–5 ngày” nếu thang đang dùng mức đó.',
    },
    notes: [
      'Việc chưa hoàn thành: chưa tới hạn thì máy tạm coi như đúng hạn; quá hạn mà chưa xong thì lấy mức trễ nặng nhất.',
      'Muốn điểm tiến độ đúng, đừng quên bấm hoàn thành và điền ngày xong thật — đừng chỉ kéo % lên 100.',
    ],
  },
  quality: {
    title: 'Chất lượng — chọn khi nghiệm thu',
    kicker: 'Bước của người duyệt, không phải lúc giao việc',
    summary:
      'Chất lượng trả lời: “Việc xong rồi, kết quả có đạt không?”. Chọn trong “Đánh giá kết quả”. Danh sách mức lấy đúng thang chất lượng của khung điểm phòng ban người được giao.',
    steps: [
      {
        title: 'Mở Đánh giá kết quả',
        body: 'Trên chi tiết việc, hoặc chuột phải trên danh sách → Đánh giá kết quả.',
      },
      {
        title: 'Chọn Đạt hoặc Không đạt',
        body: 'Không đạt = 0 điểm chất lượng, dù ô mô tả viết gì. Đạt rồi mới chọn mức chi tiết (Xuất sắc, Đạt, Cần sửa…).',
      },
      {
        title: 'Chọn đúng mức trong danh sách',
        body: 'Đừng gõ tự do nếu đã có danh sách. Gõ lệch chữ (ví dụ “dat” thay vì “Đạt”) máy có thể không khớp mức, báo cáo sẽ thiếu chất lượng.',
      },
    ],
    example: {
      title: 'Ví dụ',
      body: 'Việc đã xong, đúng hạn, độ khó Trung bình. Chấm Xuất sắc → giữ nguyên điểm và có thể cộng bonus. Chấm Cần sửa (×0.8) → điểm thực giảm 20%.',
    },
    notes: [
      'Chưa chấm chất lượng thì báo cáo “Hiệu suất việc” có thể tính 0 điểm thực cho việc đó.',
      'Danh sách mức theo phòng ban của người được giao, không phải phòng của người đang xem màn hình.',
    ],
  },
  lockDifficulty: {
    title: 'Khóa độ khó sau khi giao việc',
    kicker: 'Tránh sửa điểm lúc gần chấm kỳ',
    summary:
      'Khi bật, độ khó đóng băng ngay sau khi việc đã có người thực hiện. Người giao không đổi “Dễ” thành “Rất khó” để tăng điểm lúc cuối kỳ.',
    steps: [
      {
        title: 'Chọn độ khó trước khi gán người',
        body: 'Tạo việc → chọn độ khó → rồi mới chọn người thực hiện. Hoặc chọn cả hai lúc tạo, rồi không sửa độ khó nữa.',
      },
      {
        title: 'Muốn sửa khi đã giao',
        body: 'Người có quyền cấu hình khung điểm của phòng vẫn đổi được khi thật sự giao nhầm. Nhân viên thường không đổi được.',
      },
    ],
    example: {
      title: 'Ví dụ',
      body: 'Giao “Soạn đề” cho A, độ khó Khó. Sang tháng muốn đổi thành Rất khó — máy từ chối. Đúng quy tắc: độ khó gắn với việc, không gắn với mong muốn điểm.',
    },
    notes: [
      'Tắt khóa nếu phòng hay điều chỉnh phạm vi việc sau khi giao. Bật khóa nếu muốn số liệu chấm kỳ ổn định.',
    ],
  },
  taskList: {
    title: 'Độ khó, tiến độ, chất lượng trên danh sách việc',
    kicker: 'Nhìn nhanh việc đã đủ số liệu chấm điểm chưa',
    summary:
      'Danh sách việc vừa để làm việc hàng ngày, vừa là nơi “nhập liệu” cho khung điểm. Ba cột/thẻ giúp thấy việc nào còn thiếu.',
    steps: [
      {
        title: 'Cột mức độ / độ khó',
        body: 'Mức đã chọn lúc giao. Bấm vào việc để đổi (nếu chưa khóa). Kéo thẻ Kanban theo cột độ khó cũng đổi được mức.',
      },
      {
        title: 'Cột tiến độ %',
        body: 'Phần trăm đang làm. Muốn điểm tiến độ lúc chấm kỳ đúng, còn cần Ngày hạn và Ngày xong thật — chuột phải → Cập nhật thời gian.',
      },
      {
        title: 'Cột chất lượng',
        body: 'Kết quả nghiệm thu. Trống = chưa chấm. Chuột phải → Đánh giá kết quả để chọn mức từ khung điểm.',
      },
    ],
    example: {
      title: 'Việc “đủ số liệu” trông như thế nào',
      body: 'Độ khó = Khó, tiến độ 100%, hạn 20/09, xong thực tế 20/09, chất lượng = Đạt. Việc này sẵn sàng vào báo cáo đánh giá.',
    },
    notes: [
      'Thiếu một trong ba thứ (độ khó / ngày hạn-ngày xong / chất lượng) thì điểm hiệu suất việc có thể bị 0 hoặc bị đếm vào “thiếu dữ liệu”.',
    ],
  },
};

export function previewProgressLevel(task, levels) {
  const rows = Array.isArray(levels) ? levels.filter((item) => item && (item.label || item.code)) : [];
  if (!rows.length || !task) return null;

  const labels = rows.map((item) => item.label || item.code);
  const best = labels[0];
  const worst = labels[labels.length - 1];
  const middle = labels[Math.floor((labels.length - 1) / 2)];

  if (!task.end_date) {
    return {
      label: middle,
      tone: 'neutral',
      hint: 'Chưa có ngày hạn nên chưa xếp được sớm / đúng hạn / trễ.',
    };
  }

  const end = startOfDay(task.end_date);
  if (!end) {
    return { label: middle, tone: 'neutral', hint: 'Ngày hạn chưa đọc được.' };
  }

  if (task.status !== 'completed') {
    const today = startOfDay(new Date());
    if (today && today > end) {
      return {
        label: worst,
        tone: 'danger',
        hint: 'Việc chưa xong mà đã quá hạn — máy lấy mức trễ nặng nhất.',
      };
    }
    return {
      label: middle,
      tone: 'info',
      hint: 'Việc chưa xong, chưa tới hạn — tạm xếp như đúng hạn.',
    };
  }

  if (!task.actual_end_date) {
    return {
      label: middle,
      tone: 'gold',
      hint: 'Đã hoàn thành nhưng chưa ghi ngày xong thật — hãy bổ sung ngày kết thúc thực tế.',
    };
  }

  const actual = startOfDay(task.actual_end_date);
  if (!actual) {
    return { label: middle, tone: 'gold', hint: 'Ngày xong thật chưa đọc được.' };
  }

  const varianceDays = Math.round((actual - end) / 86400000);
  if (varianceDays <= -1) {
    return { label: best, tone: 'success', hint: `Xong sớm ${Math.abs(varianceDays)} ngày so với hạn.` };
  }
  if (varianceDays <= 0) {
    return { label: middle, tone: 'info', hint: 'Xong đúng hạn.' };
  }

  const lateLabels = labels.slice(Math.ceil(labels.length / 2));
  const index = Math.min(varianceDays - 1, Math.max(0, lateLabels.length - 1));
  return {
    label: lateLabels[index] || worst,
    tone: 'warning',
    hint: `Xong trễ ${varianceDays} ngày so với hạn.`,
  };
}

function startOfDay(value) {
  const date = value instanceof Date ? value : new Date(value);
  if (Number.isNaN(date.getTime())) return null;
  date.setHours(0, 0, 0, 0);
  return date.getTime();
}
