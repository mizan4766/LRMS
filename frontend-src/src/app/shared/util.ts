export const STATUS: Record<string, string> = { draft: 'খসড়া', submitted: 'দাখিলকৃত', processing: 'প্রক্রিয়াধীন', needs_refinement: 'সংশোধন প্রয়োজন', under_verification: 'পুনঃযাচাইয়ে', completed: 'সম্পন্ন', rejected: 'নামঞ্জুর' };
export const KIND: Record<string, string> = { notice_4_1: '৪(১) ধারার নোটিশ', notice_7_1: '৭(১) ধারার নোটিশ', notice_8_3: '৮(৩) ধারার নোটিশ', roydad: 'রোয়েদাদ বহি', deposit_demand: 'ক্ষতিপূরণ জমার চিঠি',
  field_book: 'ফিল্ডবহি', feasibility_report: 'সম্ভাব্যতা প্রতিবেদন', committee_feasibility: 'সম্ভাব্যতা কমিটি', committee_joint: 'যৌথ তদন্ত কমিটি', meeting: 'অনুমোদন সভা', meeting_minutes: 'সভার কার্যবিবরণী',
  media: 'ভিডিও/স্থিরচিত্র', class_change_case: 'শ্রেণি পরিবর্তন মিসকেস', class_change_report: 'শ্রেণি পরিবর্তন রিপোর্ট', price_letter: 'মূল্যতালিকার চিঠি', valuation: 'মূল্য নির্ধারণ তালিকা', form16: 'ফর্ম ১৬ (দখল প্রদান)',
  handover_letter: 'দখল হস্তান্তর চিঠি', declaration: 'ঘোষণা ফর্ম', gazette: 'গেজেট', transfer_deed: 'হস্তান্তর দলিল (ফর্ম ১৯)', claim: 'মালিকানা দাবি', comp_app: 'ক্ষতিপূরণ আবেদন', objection: 'আপত্তি' };
export const ROLE_SHORT: Record<string, string> = { rb: 'প্রত্যাশী সংস্থা', dc: 'ডিসি', adc: 'এডিসি', lao: 'এলএও', ac_land: 'এসি ল্যান্ড', ulao: 'ইউএলএও', kanungo: 'কানুনগো', surveyor: 'সার্ভেয়ার', commissioner: 'কমিশনার', ministry: 'মন্ত্রণালয়', citizen: 'নাগরিক', admin: 'অ্যাডমিন', accounts: 'হিসাব কর্মকর্তা' };

/** Opens a printable window with trusted, server-generated notice HTML. */
export function printHtml(title: string, html: string) {
  const w = window.open('', '_blank');
  if (!w) return;
  w.document.write(`<html><head><meta charset="utf-8"><title>${title}</title><style>body{font-family:'Noto Sans Bengali','SolaimanLipi',Arial,sans-serif;margin:40px;line-height:1.7}table{border-collapse:collapse;width:100%}td,th{border:1px solid #444;padding:4px}</style></head><body>${html}</body></html>`);
  w.document.close(); w.focus(); setTimeout(() => w.print(), 300);
}

export function saveBlob(blob: Blob, name: string) {
  const url = URL.createObjectURL(blob); const a = document.createElement('a');
  a.href = url; a.download = name; a.click(); setTimeout(() => URL.revokeObjectURL(url), 2000);
}

export function money(v: any): string { return (Number(v) || 0).toLocaleString('bn-BD', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
