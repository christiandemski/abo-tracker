// Form state <-> API payload for "Abo hinzufügen" / "Bearbeiten".
// Pure mapping of user input; all amounts/dates are converted, never calculated.

import { centsToInput, formatDate, parseEuroInput, parseGermanDate, toIsoDate } from '@/lib/format';
import type { IntervalUnit, NoticeUnit, Subscription, SubscriptionInput, SubscriptionKind, Template } from '@/lib/types';

export type RhythmPreset = 'month' | 'quarter' | 'year' | 'custom';
export type NoticePreset = 'anytime' | '14d' | '1m' | '3m' | 'custom';
export type TermPreset = '1' | '12' | '24' | 'custom';

export type FormState = {
  kind: SubscriptionKind;
  templateKey: string | null;
  name: string;
  amount: string;
  anchorDate: string;
  /** installments: last payment, TT.MM.JJJJ */
  lastPayment: string;
  rhythm: RhythmPreset;
  customUnit: IntervalUnit;
  customCount: string;
  categoryId: number | null;
  notice: NoticePreset;
  customNoticeValue: string;
  customNoticeUnit: NoticeUnit;
  minTerm: TermPreset;
  customMinTerm: string;
  renewalMonths: 1 | 12;
  startDate: string;
  remind: boolean;
  paymentMethod: string;
  providerUrl: string;
  notes: string;
  customerNumber: string;
  providerEmail: string;
};

export const RHYTHM_PRESETS: Record<Exclude<RhythmPreset, 'custom'>, { unit: IntervalUnit; count: number }> = {
  month: { unit: 'month', count: 1 },
  quarter: { unit: 'month', count: 3 },
  year: { unit: 'year', count: 1 },
};

export const NOTICE_PRESETS: Record<'14d' | '1m' | '3m', { value: number; unit: NoticeUnit }> = {
  '14d': { value: 14, unit: 'day' },
  '1m': { value: 1, unit: 'month' },
  '3m': { value: 3, unit: 'month' },
};

export function emptyForm(today = new Date()): FormState {
  return {
    kind: 'subscription',
    templateKey: null,
    name: '',
    amount: '',
    anchorDate: formatDate(today),
    lastPayment: '',
    rhythm: 'month',
    customUnit: 'month',
    customCount: '2',
    categoryId: null,
    notice: 'anytime',
    customNoticeValue: '',
    customNoticeUnit: 'month',
    minTerm: '12',
    customMinTerm: '',
    renewalMonths: 1,
    startDate: '',
    remind: true,
    paymentMethod: '',
    providerUrl: '',
    notes: '',
    customerNumber: '',
    providerEmail: '',
  };
}

function rhythmOf(unit: IntervalUnit, count: number): RhythmPreset {
  for (const [preset, r] of Object.entries(RHYTHM_PRESETS)) {
    if (r.unit === unit && r.count === count) return preset as RhythmPreset;
  }
  return 'custom';
}

export function applyTemplate(form: FormState, template: Template): FormState {
  const rhythm = rhythmOf(template.interval_unit, template.interval_count);
  return {
    ...form,
    templateKey: template.key,
    name: template.name,
    amount: '',
    rhythm,
    customUnit: template.interval_unit,
    customCount: String(template.interval_count),
    categoryId: template.category_id,
  };
}

export function formFromSubscription(s: Subscription): FormState {
  let notice: NoticePreset = 'anytime';
  if (s.min_term_months !== null) {
    notice = 'custom';
    for (const [preset, n] of Object.entries(NOTICE_PRESETS)) {
      if (n.value === s.notice_value && n.unit === s.notice_unit) notice = preset as NoticePreset;
    }
  }
  const termPreset: TermPreset =
    s.min_term_months === 1 || s.min_term_months === 12 || s.min_term_months === 24
      ? (String(s.min_term_months) as TermPreset)
      : 'custom';

  return {
    kind: s.kind,
    templateKey: s.template_key,
    name: s.name,
    amount: centsToInput(s.amount_cents),
    anchorDate: formatDate(s.next_charge_date ?? s.anchor_date),
    lastPayment: s.kind === 'installment' && s.ends_at ? formatDate(s.ends_at) : '',
    rhythm: rhythmOf(s.interval_unit, s.interval_count),
    customUnit: s.interval_unit,
    customCount: String(s.interval_count),
    categoryId: s.category_id,
    notice,
    customNoticeValue: s.notice_value !== null ? String(s.notice_value) : '',
    customNoticeUnit: s.notice_unit ?? 'month',
    minTerm: s.min_term_months === null ? '12' : termPreset,
    customMinTerm: s.min_term_months !== null && termPreset === 'custom' ? String(s.min_term_months) : '',
    renewalMonths: s.renewal_months === 12 ? 12 : 1,
    startDate: s.start_date ? formatDate(s.start_date) : '',
    remind: s.remind,
    paymentMethod: s.payment_method ?? '',
    providerUrl: s.provider_url ?? '',
    notes: s.notes ?? '',
    customerNumber: s.customer_number ?? '',
    providerEmail: s.provider_email ?? '',
  };
}

export function intervalOf(form: FormState): { unit: IntervalUnit; count: number | null } {
  if (form.rhythm !== 'custom') return RHYTHM_PRESETS[form.rhythm];
  const count = Number(form.customCount);
  return { unit: form.customUnit, count: Number.isInteger(count) && count >= 1 && count <= 52 ? count : null };
}

export type FieldErrors = Partial<Record<keyof FormState, string>>;

/** Client-side checks for instant feedback; the server validates again. */
export function validate(form: FormState): { payload: SubscriptionInput | null; errors: FieldErrors } {
  const errors: FieldErrors = {};

  const name = form.name.trim();
  if (!name) errors.name = 'Gib einen Namen ein.';

  const amount = parseEuroInput(form.amount);
  if (amount === null || amount <= 0) errors.amount = 'Gib einen Betrag ein, z. B. 9,99.';

  const anchor = parseGermanDate(form.anchorDate);
  if (!anchor) errors.anchorDate = 'Datum als TT.MM.JJJJ.';

  const interval = intervalOf(form);
  if (interval.count === null) errors.customCount = 'Zahl zwischen 1 und 52.';

  let noticeValue: number | null = null;
  let noticeUnit: NoticeUnit | null = null;
  let minTerm: number | null = null;
  let startDate: string | null = null;
  let endsAt: string | null = null;

  // Only subscriptions have a term and a notice period
  const hasTerm = form.kind === 'subscription' && form.notice !== 'anytime';

  if (form.kind === 'installment') {
    endsAt = parseGermanDate(form.lastPayment);
    if (!endsAt) errors.lastPayment = form.lastPayment.trim() ? 'Datum als TT.MM.JJJJ.' : 'Gib das Datum der letzten Rate ein.';
    else if (anchor && endsAt < anchor) errors.lastPayment = 'Die letzte Rate kann nicht vor der nächsten liegen.';
  }

  if (form.kind === 'subscription' && form.notice !== 'anytime') {
    if (form.notice === 'custom') {
      const v = Number(form.customNoticeValue);
      if (!Number.isInteger(v) || v < 0 || v > 365) errors.customNoticeValue = 'Zahl zwischen 0 und 365.';
      noticeValue = v;
      noticeUnit = form.customNoticeUnit;
    } else {
      noticeValue = NOTICE_PRESETS[form.notice].value;
      noticeUnit = NOTICE_PRESETS[form.notice].unit;
    }

    minTerm = form.minTerm === 'custom' ? Number(form.customMinTerm) : Number(form.minTerm);
    if (!Number.isInteger(minTerm) || minTerm < 1 || minTerm > 120) errors.customMinTerm = 'Zahl zwischen 1 und 120.';

    if (form.startDate.trim()) {
      startDate = parseGermanDate(form.startDate);
      if (!startDate) errors.startDate = 'Datum als TT.MM.JJJJ.';
    }
  }

  const url = form.providerUrl.trim();
  if (url && !/^https?:\/\/\S+\.\S+/.test(url)) errors.providerUrl = 'Link mit https:// beginnen.';

  const providerEmail = form.providerEmail.trim();
  if (providerEmail && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(providerEmail)) {
    errors.providerEmail = 'Gib eine gültige E-Mail-Adresse ein.';
  }

  if (Object.keys(errors).length > 0 || amount === null || !anchor || interval.count === null) {
    return { payload: null, errors };
  }

  return {
    errors,
    payload: {
      kind: form.kind,
      name,
      amount_cents: amount,
      interval_unit: interval.unit,
      interval_count: interval.count,
      anchor_date: anchor,
      ends_at: endsAt,
      category_id: form.categoryId,
      start_date: startDate,
      min_term_months: minTerm,
      renewal_months: hasTerm ? form.renewalMonths : null,
      notice_value: noticeValue,
      notice_unit: noticeUnit,
      remind: form.remind,
      payment_method: form.paymentMethod.trim() || null,
      provider_url: url || null,
      notes: form.notes.trim() || null,
      template_key: form.templateKey,
      customer_number: form.customerNumber.trim() || null,
      provider_email: providerEmail || null,
    },
  };
}

/** Maps Laravel field names back to form fields for inline errors. */
export const SERVER_FIELD: Partial<Record<keyof SubscriptionInput, keyof FormState>> = {
  name: 'name',
  amount_cents: 'amount',
  anchor_date: 'anchorDate',
  ends_at: 'lastPayment',
  interval_count: 'customCount',
  category_id: 'categoryId',
  start_date: 'startDate',
  min_term_months: 'customMinTerm',
  notice_value: 'customNoticeValue',
  provider_url: 'providerUrl',
  payment_method: 'paymentMethod',
  notes: 'notes',
  customer_number: 'customerNumber',
  provider_email: 'providerEmail',
};

export { toIsoDate };
