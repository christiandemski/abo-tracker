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
