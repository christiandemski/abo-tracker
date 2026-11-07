import type { Subscription, Template } from '@/lib/types';

import { applyTemplate, emptyForm, formFromSubscription, validate, type FormState } from '../model';

const base = (overrides: Partial<FormState> = {}): FormState => ({
  ...emptyForm(new Date(2026, 10, 5)),
  name: 'Netflix',
  amount: '13,99',
  ...overrides,
});

describe('validate', () => {
  it('builds a payload for a simple monthly subscription', () => {
    const { payload, errors } = validate(base());

    expect(errors).toEqual({});
    expect(payload).toMatchObject({
      name: 'Netflix',
      amount_cents: 1399,
      interval_unit: 'month',
      interval_count: 1,
      anchor_date: '2026-11-05',
      min_term_months: null,
      notice_value: null,
      renewal_months: null,
    });
  });

  it('maps rhythm presets and custom rhythms', () => {
    expect(validate(base({ rhythm: 'quarter' })).payload).toMatchObject({ interval_unit: 'month', interval_count: 3 });
    expect(validate(base({ rhythm: 'year' })).payload).toMatchObject({ interval_unit: 'year', interval_count: 1 });
    expect(validate(base({ rhythm: 'custom', customUnit: 'week', customCount: '2' })).payload).toMatchObject({
      interval_unit: 'week',
      interval_count: 2,
    });
    expect(validate(base({ rhythm: 'custom', customCount: '0' })).errors.customCount).toBeDefined();
  });

  it('includes the contract term when a notice period is chosen', () => {
    const { payload } = validate(base({ notice: '1m', minTerm: '24', renewalMonths: 1, startDate: '01.03.2026' }));

    expect(payload).toMatchObject({
      notice_value: 1,
      notice_unit: 'month',
      min_term_months: 24,
      renewal_months: 1,
      start_date: '2026-03-01',
    });
  });

  it('sends no contract term for installments and savings', () => {
    const term = { notice: '1m', minTerm: '24', startDate: '01.03.2026' } as const;

    expect(validate(base({ kind: 'savings', ...term })).payload).toMatchObject({
      kind: 'savings',
      ends_at: null,
      notice_value: null,
      min_term_months: null,
      renewal_months: null,
      start_date: null,
    });
    expect(validate(base({ kind: 'installment', lastPayment: '01.12.2027', ...term })).payload).toMatchObject({
      kind: 'installment',
      ends_at: '2027-12-01',
      notice_value: null,
      min_term_months: null,
    });
  });

  it('needs the last payment of an installment, not before the next one', () => {
    expect(validate(base({ kind: 'installment' })).errors.lastPayment).toBe('Gib das Datum der letzten Rate ein.');
    expect(validate(base({ kind: 'installment', lastPayment: '31.13.2027' })).errors.lastPayment).toBe('Datum als TT.MM.JJJJ.');
    expect(validate(base({ kind: 'installment', lastPayment: '04.11.2026' })).errors.lastPayment).toBe(
      'Die letzte Rate kann nicht vor der nächsten liegen.',
    );
    expect(validate(base({ kind: 'installment', lastPayment: '05.11.2026' })).errors).toEqual({});
    // A subscription ignores the field
    expect(validate(base({ lastPayment: 'x' })).payload).toMatchObject({ kind: 'subscription', ends_at: null });
  });

  it('round-trips an installment with its last payment', () => {
    const s = {
      kind: 'installment',
      name: 'Autokredit',
      amount_cents: 25000,
      interval_unit: 'month',
      interval_count: 1,
      anchor_date: '2026-01-01',
      next_charge_date: '2026-12-01',
      ends_at: '2028-06-01',
      min_term_months: null,
      notice_value: null,
      notice_unit: null,
      renewal_months: null,
      start_date: null,
      remind: true,
    } as unknown as Subscription;

    const form = formFromSubscription(s);
    expect(form).toMatchObject({ kind: 'installment', lastPayment: '01.06.2028' });
    expect(validate(form).payload).toMatchObject({ kind: 'installment', anchor_date: '2026-12-01', ends_at: '2028-06-01' });
  });

  it('reports missing and invalid fields', () => {
    const { payload, errors } = validate(base({ name: ' ', amount: 'abc', anchorDate: '31.02.2026' }));

    expect(payload).toBeNull();
    expect(Object.keys(errors).sort()).toEqual(['amount', 'anchorDate', 'name']);
  });

  it('rejects links without http(s)', () => {
    expect(validate(base({ providerUrl: 'netflix.com' })).errors.providerUrl).toBeDefined();
    expect(validate(base({ providerUrl: 'https://netflix.com/account' })).payload?.provider_url).toBe(
      'https://netflix.com/account',
    );
  });
});

describe('templates and editing', () => {
  it('applies a template without a price', () => {
    const template: Template = {
      key: 'amazon-prime',
      name: 'Amazon Prime',
      category_key: 'streaming',
      category_id: 2,
      interval_unit: 'year',
      interval_count: 1,
      popular: true,
    };

    const form = applyTemplate(base({ amount: '5,00' }), template);

    expect(form).toMatchObject({ name: 'Amazon Prime', amount: '', rhythm: 'year', categoryId: 2, templateKey: 'amazon-prime' });
  });

  it('round-trips an existing subscription', () => {
    const s = {
      kind: 'subscription',
      name: 'Adobe Creative Cloud',
      amount_cents: 14388,
      interval_unit: 'year',
      interval_count: 1,
      anchor_date: '2024-11-12',
      next_charge_date: '2026-11-12',
      category_id: 4,
      start_date: '2023-11-12',
      min_term_months: 12,
      renewal_months: 12,
      notice_value: 2,
      notice_unit: 'day',
      remind: true,
      payment_method: 'Kreditkarte',
      provider_url: null,
      notes: null,
      template_key: 'adobe-cc',
    } as Subscription;

    const form = formFromSubscription(s);
    expect(form).toMatchObject({ amount: '143,88', anchorDate: '12.11.2026', rhythm: 'year', notice: 'custom', customNoticeValue: '2', customNoticeUnit: 'day', minTerm: '12', renewalMonths: 12, startDate: '12.11.2023' });

    expect(validate(form).payload).toMatchObject({
      amount_cents: 14388,
      anchor_date: '2026-11-12',
      min_term_months: 12,
      renewal_months: 12,
      notice_value: 2,
      notice_unit: 'day',
      start_date: '2023-11-12',
    });
  });
});
