export function normalizePhoneForUrl(phone?: string | null): string | null {
  if (!phone) return null;
  const trimmed = phone.trim();
  if (!trimmed) return null;
  const startsWithPlus = trimmed.startsWith('+');
  const digits = trimmed.replace(/[^0-9]/g, '');
  if (!digits) return null;
  return startsWithPlus ? `+${digits}` : digits;
}

export function buildTelUrl(phone?: string | null): string | null {
  const normalized = normalizePhoneForUrl(phone);
  return normalized ? `tel:${normalized}` : null;
}

export function buildWhatsAppUrl(phone?: string | null): string | null {
  const normalized = normalizePhoneForUrl(phone);
  if (!normalized) return null;
  return `https://wa.me/${normalized.replace('+', '')}`;
}