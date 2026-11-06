// Display formatting only. Money is always integer cents; amounts are never computed here.

const euro = new Intl.NumberFormat('de-DE', {
  style: 'currency',
  currency: 'EUR',
  minimumFractionDigits: 2,
  maximumFractionDigits: 2,
});

/**
 * 123456 → "1.234,56 €" (de-DE, non-breaking space before €).
 */
export function formatCents(cents: number): string {
  if (!Number.isFinite(cents)) {
    throw new TypeError(`formatCents expects a finite number, got ${cents}`);
  }
  // Intl output differs between engines (e.g. "-0,00 €", narrow vs. normal NBSP); normalize it.
  const value = Object.is(Math.round(cents), -0) ? 0 : Math.round(cents);
  return euro.format(value / 100).replace(/ /g, ' ');
}

const weekday = ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'];
const month = [
  'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni',
  'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember',
];

/**
 * Parses an API DATE ("2026-11-05") as a local calendar date (no timezone shift).
 */
export function parseDate(isoDate: string): Date {
  const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(isoDate);
  if (!match) {
    throw new TypeError(`Expected YYYY-MM-DD, got "${isoDate}"`);
  }
  return new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]));
}

/** "05.11.2026" */
export function formatDate(date: Date | string): string {
  const d = typeof date === 'string' ? parseDate(date) : date;
  const dd = String(d.getDate()).padStart(2, '0');
  const mm = String(d.getMonth() + 1).padStart(2, '0');
  return `${dd}.${mm}.${d.getFullYear()}`;
}

/** "Donnerstag, 5. November" – header line on the overview */
export function formatLongDate(date: Date | string): string {
  const d = typeof date === 'string' ? parseDate(date) : date;
  return `${weekday[d.getDay()]}, ${d.getDate()}. ${month[d.getMonth()]}`;
}

const weekdayShort = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'];

/** "10.11." */
export function formatShortDate(date: Date | string): string {
  const d = typeof date === 'string' ? parseDate(date) : date;
  return `${String(d.getDate()).padStart(2, '0')}.${String(d.getMonth() + 1).padStart(2, '0')}.`;
}

/** "Sa, 15.11." */
export function formatWeekdayDate(date: Date | string): string {
  const d = typeof date === 'string' ? parseDate(date) : date;
  return `${weekdayShort[d.getDay()]}, ${formatShortDate(d)}`;
}

/** "Mo, 10.11.2026" */
export function formatWeekdayLongDate(date: Date | string): string {
  const d = typeof date === 'string' ? parseDate(date) : date;
  return `${weekdayShort[d.getDay()]}, ${formatDate(d)}`;
}

/** Days as sent by the API → "heute", "morgen", "in 5 Tagen" */
export function formatDaysUntil(days: number): string {
  if (days <= 0) return 'heute';
  if (days === 1) return 'morgen';
  return `in ${days} Tagen`;
}

/** 20 → "+20 %", -5.5 → "−5,5 %" */
export function formatPercent(percent: number): string {
  const sign = percent > 0 ? '+' : percent < 0 ? '−' : '';
  const value = Math.abs(percent).toLocaleString('de-DE', { maximumFractionDigits: 1 });
  return `${sign}${value} %`;
}

/**
 * Parses a German amount from a text field into cents: "9,99" → 999, "1.234,5" → 123450.
 * Returns null for empty or invalid input. Input parsing only – no calculations.
 */
export function parseEuroInput(input: string): number | null {
  const cleaned = input.replace(/\s|€/g, '');
  if (!/^\d{1,3}(\.\d{3})*(,\d{0,2})?$|^\d+(,\d{0,2})?$/.test(cleaned)) return null;
  const [euros, cents = ''] = cleaned.replace(/\./g, '').split(',');
  const value = Number(euros) * 100 + Number(cents.padEnd(2, '0') || '0');
  return Number.isSafeInteger(value) ? value : null;
}

/** 999 → "9,99" (for prefilling the amount field when editing) */
export function centsToInput(cents: number): string {
  return (cents / 100).toLocaleString('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2, useGrouping: false });
}

/** Date → "2026-11-05" in local time (API DATE format) */
export function toIsoDate(date: Date): string {
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

/** "05.11.2026" or "5.11.26" → "2026-11-05"; null if invalid */
export function parseGermanDate(input: string): string | null {
  const m = /^(\d{1,2})\.(\d{1,2})\.(\d{2}|\d{4})$/.exec(input.trim());
  if (!m) return null;
  const year = m[3].length === 2 ? 2000 + Number(m[3]) : Number(m[3]);
  const d = new Date(year, Number(m[2]) - 1, Number(m[1]));
  if (d.getFullYear() !== year || d.getMonth() !== Number(m[2]) - 1 || d.getDate() !== Number(m[1])) return null;
  return toIsoDate(d);
}

const monthShort = ['Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'];

/** "2026-11" → "November 2026" */
export function formatMonthYear(ym: string): string {
  const [y, m] = ym.split('-').map(Number);
  return `${month[m - 1]} ${y}`;
}

/** "2026-11" → "Nov" (chart labels); withYear → "Nov 2026" */
export function formatMonthShort(ym: string, withYear = false): string {
  const [y, m] = ym.split('-').map(Number);
  return withYear ? `${monthShort[m - 1]} ${y}` : monthShort[m - 1];
}

/** "2026-11" → "November" */
export function formatMonthName(ym: string): string {
  return month[Number(ym.split('-')[1]) - 1];
}

/** Month navigation: ("2026-12", 1) → "2027-01" */
export function shiftMonth(ym: string, delta: number): string {
  const [y, m] = ym.split('-').map(Number);
  const d = new Date(y, m - 1 + delta, 1);
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
}

/** Current month as "2026-11" (local time) */
export function currentMonth(date = new Date()): string {
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
}

/** "Donnerstag, 12.11." */
export function formatWeekdayDayMonth(date: Date | string): string {
  const d = typeof date === 'string' ? parseDate(date) : date;
  return `${weekday[d.getDay()]}, ${formatShortDate(d)}`;
}

/** Position in a Monday-first week: Mo = 0 … So = 6 */
export function mondayIndex(date: Date | string): number {
  const d = typeof date === 'string' ? parseDate(date) : date;
  return (d.getDay() + 6) % 7;
}

/** Whole euros for tight spaces (month grid): 14388 → "144 €" */
export function formatEuroRounded(cents: number): string {
  return `${Math.round(cents / 100).toLocaleString('de-DE')} €`;
}
