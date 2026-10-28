const FILES = [
  ['01', '01_Hi.png', 'Xin chào'],
  ['02', '02_OK.png', 'OK'],
  ['03', '03_Vui_mung.png', 'Vui mừng'],
  ['04', '04_Trao_tim.png', 'Trao tim'],
  ['05', '05_Ha_ha_ha.png', 'Ha ha ha'],
  ['06', '06_De_thuong.png', 'Dễ thương'],
  ['07', '07_Suy_nghi.png', 'Suy nghĩ'],
  ['08', '08_Y_tuong.png', 'Ý tưởng'],
  ['09', '09_Lam_viec.png', 'Làm việc'],
  ['10', '10_Tra_sua.png', 'Trà sữa'],
  ['11', '11_Cam_on.png', 'Cảm ơn'],
  ['12', '12_Xin_loi.png', 'Xin lỗi'],
  ['13', '13_Co_len.png', 'Cố lên'],
  ['14', '14_Tuyet_voi.png', 'Tuyệt vời'],
  ['15', '15_Bye.png', 'Tạm biệt'],
  ['16', '16_Yeu_thuong.png', 'Yêu thương'],
  ['17', '17_Bat_ngo.png', 'Bất ngờ'],
  ['18', '18_Met_moi.png', 'Mệt mỏi'],
  ['19', '19_Cool.png', 'Cool'],
  ['20', '20_Ngu_ngon.png', 'Ngủ ngon'],
];

export const VA_STICKERS = FILES.map(([code, file, label]) => ({
  id: `va_${code}`,
  label,
  src: `/stickers/va/${file}`,
}));

const BY_ID = new Map(VA_STICKERS.map((sticker) => [sticker.id, sticker]));

export function vaStickerById(id) {
  return BY_ID.get(id) ?? null;
}

export const VA_STICKER_PACK = {
  key: 'va',
  label: 'Sticker VA',
  icon: 'VA',
  iconSrc: VA_STICKERS[0].src,
  stickers: VA_STICKERS.map((sticker) => ({
    id: sticker.id,
    emoji: sticker.label,
    src: sticker.src,
  })),
};
