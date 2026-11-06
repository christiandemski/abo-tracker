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
