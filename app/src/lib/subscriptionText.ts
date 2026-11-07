// Display texts that depend on the kind of an entry (Abo, Rate, Sparen).
// Only wording: all numbers (open installments, dates) come from the API.

import { formatDate, formatShortDate } from './format';
import type { Subscription } from './types';

/** "Gekündigt" fits a subscription; a savings plan is ended. */
export function endedLabel(s: Pick<Subscription, 'kind' | 'status'>): string | undefined {
  return s.kind === 'savings' && s.status === 'cancelled' ? 'Beendet' : undefined;
}

/** "noch 4 Raten" / "letzte Rate" */
export function remainingLabel(count: number): string {
  return count === 1 ? 'letzte Rate' : `noch ${count} Raten`;
}

/** Second line in lists: state, category or open installments, next charge. */
export function metaFor(
  s: Pick<Subscription, 'kind' | 'status' | 'ends_at' | 'category' | 'next_charge_date' | 'remaining_count'>,
): string {
  if (s.status === 'cancelled') {
    const word = s.kind === 'savings' ? 'Beendet' : 'Gekündigt';
    return s.ends_at ? `${word} zum ${formatShortDate(s.ends_at)}` : word;
  }
  if (s.status === 'finished') return s.ends_at ? `Abbezahlt · letzte Rate ${formatDate(s.ends_at)}` : 'Abbezahlt';
  if (s.status === 'paused') return 'Pausiert';

  const parts = [
    s.kind === 'installment' && s.remaining_count ? remainingLabel(s.remaining_count) : (s.category?.name ?? 'Ohne Kategorie'),
  ];
  if (s.next_charge_date) parts.push(`nächste ${formatShortDate(s.next_charge_date)}`);
  return parts.join(' · ');
}
