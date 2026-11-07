import { endedLabel, metaFor, remainingLabel } from '../subscriptionText';

const base = {
  kind: 'subscription' as const,
  status: 'active' as const,
  ends_at: null,
  category: null,
  next_charge_date: '2026-11-15',
  remaining_count: null,
};

describe('metaFor', () => {
  it('shows category and next charge for active subscriptions and savings', () => {
    expect(metaFor(base)).toBe('Ohne Kategorie · nächste 15.11.');
    expect(metaFor({ ...base, kind: 'savings', category: { name: 'Sparen & Vorsorge' } as never })).toBe(
      'Sparen & Vorsorge · nächste 15.11.',
    );
  });

  it('shows the open installments instead of the category', () => {
    expect(metaFor({ ...base, kind: 'installment', remaining_count: 4 })).toBe('noch 4 Raten · nächste 15.11.');
    expect(metaFor({ ...base, kind: 'installment', remaining_count: 1 })).toBe('letzte Rate · nächste 15.11.');
  });

  it('words the end by kind', () => {
    expect(metaFor({ ...base, status: 'cancelled', ends_at: '2026-11-30' })).toBe('Gekündigt zum 30.11.');
    expect(metaFor({ ...base, kind: 'savings', status: 'cancelled', ends_at: '2026-11-30' })).toBe('Beendet zum 30.11.');
    expect(metaFor({ ...base, kind: 'installment', status: 'finished', ends_at: '2026-10-01' })).toBe(
      'Abbezahlt · letzte Rate 01.10.2026',
    );
    expect(metaFor({ ...base, status: 'paused' })).toBe('Pausiert');
  });
});

describe('labels', () => {
  it('calls an ended savings plan "Beendet"', () => {
    expect(endedLabel({ kind: 'savings', status: 'cancelled' })).toBe('Beendet');
    expect(endedLabel({ kind: 'subscription', status: 'cancelled' })).toBeUndefined();
  });

  it('counts installments', () => {
    expect(remainingLabel(1)).toBe('letzte Rate');
    expect(remainingLabel(12)).toBe('noch 12 Raten');
  });
});
