import { Voucher } from '../../types/auth.types';

export function mapBackendVoucherList(raw: unknown): Voucher[] {
  if (!Array.isArray(raw)) return [];

  return raw
    .map((item) => {
      try {
        return mapBackendVoucherToFrontend(item);
      } catch (err) {
        console.error('Failed to map alumni item:', item, err);
        return null;
      }
    })
    .filter((a): a is Voucher => a !== null);
}

export function mapBackendVoucherToFrontend(raw: unknown): Voucher {
  const d = raw as Record<string, any>;

  return {
    id: String(d.voucher_id ?? ''),
    fullName: String(d.fullname ?? ''),
    graduationYear: d.graduation_year ?? '',
    chapterId: d.chapter_id || '',
  };
}
