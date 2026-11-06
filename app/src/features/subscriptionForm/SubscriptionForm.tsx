import { Bell, ChevronDown, ChevronUp, Search } from 'lucide-react-native';
import { useEffect, useState } from 'react';
import { Platform, Pressable, ScrollView, StyleSheet, TextInput, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { AppText, CategoryTile, Notice, PrimaryButton, Segmented, Switch, TextField, TextLink } from '@/components';
import { Chip } from '@/components/Chip';
import { ApiError } from '@/lib/api';
import { useAuth } from '@/lib/auth';
import { formatCents, parseEuroInput } from '@/lib/format';
import { useCategories, useTemplates } from '@/lib/queries';
import type { BillingPreview, Resource, SubscriptionInput } from '@/lib/types';
import { colors, fonts, radius, screenPadding } from '@/theme';

import {
  applyTemplate,
  intervalOf,
  SERVER_FIELD,
  validate,
  type FieldErrors,
  type FormState,
  type NoticePreset,
  type RhythmPreset,
  type TermPreset,
} from './model';

const RHYTHMS = [
  { value: 'month', label: 'monatlich' },
  { value: 'quarter', label: 'vierteljährl.' },
  { value: 'year', label: 'jährlich' },
] as const;

const CUSTOM_UNITS = [
  { value: 'week', label: 'Wochen' },
  { value: 'month', label: 'Monate' },
  { value: 'year', label: 'Jahre' },
] as const;

const NOTICES: { value: NoticePreset; label: string }[] = [
  { value: 'anytime', label: 'jederzeit' },
  { value: '14d', label: '14 Tage' },
  { value: '1m', label: '1 Monat' },
  { value: '3m', label: '3 Monate' },
  { value: 'custom', label: 'Andere' },
];

const NOTICE_UNITS = [
  { value: 'day', label: 'Tage' },
  { value: 'week', label: 'Wochen' },
  { value: 'month', label: 'Monate' },
] as const;

const TERMS: { value: TermPreset; label: string }[] = [
  { value: '1', label: '1 Monat' },
  { value: '12', label: '12 Monate' },
  { value: '24', label: '24 Monate' },
  { value: 'custom', label: 'Andere' },
];

const RENEWALS = [
  { value: '1', label: 'um 1 Monat' },
  { value: '12', label: 'um 12 Monate' },
] as const;

type Props = {
  initial: FormState;
  /** Show template search and tiles (only when adding) */
  withTemplates?: boolean;
  submitLabel: string;
  onSubmit: (payload: SubscriptionInput) => Promise<void>;
};

function useDebounced<T>(value: T, delay = 300): T {
  const [debounced, setDebounced] = useState(value);
  useEffect(() => {
    const t = setTimeout(() => setDebounced(value), delay);
    return () => clearTimeout(t);
  }, [value, delay]);
  return debounced;
}

/** Live conversion from the API (the app never calculates amounts itself). */
function usePreview(amountCents: number | null, unit: string, count: number | null) {
  const { api } = useAuth();
  const [preview, setPreview] = useState<BillingPreview | null>(null);
  const debouncedAmount = useDebounced(amountCents);
  const valid = debouncedAmount !== null && debouncedAmount > 0 && count !== null;

  useEffect(() => {
    if (!valid) return;
    let cancelled = false;
    api
      .get<Resource<BillingPreview>>(`billing/preview?amount_cents=${debouncedAmount}&interval_unit=${unit}&interval_count=${count}`)
      .then((res) => !cancelled && setPreview(res.data))
      .catch(() => !cancelled && setPreview(null));
    return () => {
      cancelled = true;
    };
  }, [api, valid, debouncedAmount, unit, count]);

  return valid && amountCents !== null && amountCents > 0 ? preview : null;
}

export function SubscriptionForm({ initial, withTemplates, submitLabel, onSubmit }: Props) {
  const insets = useSafeAreaInsets();
  const [form, setForm] = useState<FormState>(initial);
  const [errors, setErrors] = useState<FieldErrors>({});
  const [formError, setFormError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);
  const [showMore, setShowMore] = useState(
    !!(initial.paymentMethod || initial.providerUrl || initial.notes),
  );
  const [search, setSearch] = useState('');
  const q = useDebounced(search.trim());

  const templates = useTemplates(withTemplates ? q : '__none__');
  const categories = useCategories();

  const set = <K extends keyof FormState>(key: K, value: FormState[K]) => {
    setForm((f) => ({ ...f, [key]: value }));
    setErrors((e) => ({ ...e, [key]: undefined }));
  };

  const amountCents = parseEuroInput(form.amount);
  const interval = intervalOf(form);
  const preview = usePreview(amountCents, interval.unit, interval.count);
  const canSave = form.name.trim() !== '' && amountCents !== null && amountCents > 0;

  let hint = 'Betrag eingeben';
  let monthly = '–';
  if (preview) {
    monthly = formatCents(preview.monthly_cents);
    hint = preview.is_converted
      ? 'umgerechnet pro Monat'
      : interval.unit === 'month' && interval.count === 1
        ? `pro Monat · ${formatCents(preview.yearly_cents)} im Jahr`
        : 'umgerechnet pro Monat';
  }

  async function submit() {
    const { payload, errors: clientErrors } = validate(form);
    setErrors(clientErrors);
    setFormError(null);
    if (!payload) return;

    setSubmitting(true);
    try {
      await onSubmit(payload);
    } catch (error) {
      if (error instanceof ApiError && error.isValidation) {
        const mapped: FieldErrors = {};
        const rest: string[] = [];
        for (const [field, messages] of Object.entries(error.errors)) {
          const target = SERVER_FIELD[field as keyof SubscriptionInput];
          if (target) mapped[target] = messages[0];
          else rest.push(messages[0]);
        }
        setErrors(mapped);
        setFormError(rest.length ? rest.join(' ') : 'Bitte prüf die markierten Felder.');
      } else {
        setFormError(error instanceof Error ? error.message : 'Das hat nicht geklappt.');
      }
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <View style={styles.root}>
      <ScrollView contentContainerStyle={styles.scroll} keyboardShouldPersistTaps="handled">
        {withTemplates && (
          <>
            <View style={styles.search}>
              <Search size={18} strokeWidth={2} color={colors.textMuted} />
              <TextInput
                value={search}
                onChangeText={setSearch}
                placeholder="Dienst suchen, z. B. Netflix"
                placeholderTextColor={colors.placeholder}
                accessibilityLabel="Vorlage suchen"
                style={styles.searchInput}
                autoCorrect={false}
              />
            </View>

            <View style={styles.block}>
              <AppText variant="small" color="textMuted" style={styles.medium}>
                {q ? 'Vorlagen' : 'Beliebte Vorlagen'}
              </AppText>
              <View accessibilityRole="radiogroup" accessibilityLabel="Vorlagen" style={styles.tiles}>
                {/* 7 templates + "Eigenes" fill the 4 × 2 grid */}
                {(templates.data ?? []).slice(0, 7).map((t) => {
                  const selected = form.templateKey === t.key;
                  const color = categories.data?.find((c) => c.id === t.category_id)?.color;
                  return (
                    <Pressable
                      key={t.key}
                      accessibilityRole="radio"
                      accessibilityState={{ checked: selected }}
                      accessibilityLabel={t.name}
                      onPress={() => setForm((f) => applyTemplate(f, t))}
                      style={[styles.tile, selected && styles.tileSelected]}
                    >
                      <CategoryTile name={t.name} color={color} size="small" />
                      <AppText variant="tab" color="textSoft" numberOfLines={1} style={styles.tileLabel}>
                        {t.name}
                      </AppText>
                    </Pressable>
                  );
                })}
                <Pressable
                  accessibilityRole="radio"
                  accessibilityState={{ checked: form.templateKey === null }}
                  accessibilityLabel="Eigenes Abo"
                  onPress={() => setForm((f) => ({ ...f, templateKey: null, name: f.templateKey ? '' : f.name }))}
                  style={[styles.tile, form.templateKey === null && styles.tileSelected]}
                >
                  <View style={styles.ownIcon}>
                    <AppText variant="rowTitle" color="textMuted">
                      +
                    </AppText>
                  </View>
                  <AppText variant="tab" color="textSoft" numberOfLines={1}>
                    Eigenes
                  </AppText>
                </Pressable>
              </View>
              {q !== '' && templates.data?.length === 0 && (
                <AppText variant="small" color="textMuted">
                  Keine Vorlage gefunden. Leg das Abo einfach selbst an.
                </AppText>
              )}
            </View>
          </>
        )}

        <View style={styles.form}>
          {formError && <Notice tone="danger">{formError}</Notice>}

          <TextField
            label="Name"
            value={form.name}
            onChangeText={(v) => set('name', v)}
            error={errors.name}
            placeholder="z. B. Zeitungsabo"
          />

          <View style={styles.twoCols}>
            <View style={styles.col}>
              <TextField
                label="Betrag in €"
                value={form.amount}
                onChangeText={(v) => set('amount', v)}
                error={errors.amount}
                placeholder="0,00"
                keyboardType="decimal-pad"
                inputMode="decimal"
              />
            </View>
            <View style={styles.col}>
              <TextField
                label="Nächste Abbuchung"
                value={form.anchorDate}
                onChangeText={(v) => set('anchorDate', v)}
                error={errors.anchorDate}
                placeholder="TT.MM.JJJJ"
                keyboardType="numbers-and-punctuation"
              />
            </View>
          </View>

          <View style={styles.group}>
            <AppText variant="caption" color="textMuted">
              Rhythmus
            </AppText>
            {form.rhythm === 'custom' ? (
              <View style={styles.customRow}>
                <AppText variant="body">alle</AppText>
                <View style={styles.countField}>
                  <TextField
                    label="Anzahl"
                    value={form.customCount}
                    onChangeText={(v) => set('customCount', v)}
                    error={errors.customCount}
                    keyboardType="number-pad"
                  />
                </View>
                <Segmented
                  options={CUSTOM_UNITS}
                  value={form.customUnit}
                  onChange={(v) => set('customUnit', v)}
                  accessibilityLabel="Einheit"
                />
              </View>
            ) : (
              <Segmented
                options={RHYTHMS}
                value={form.rhythm as Exclude<RhythmPreset, 'custom'>}
                onChange={(v) => set('rhythm', v)}
                accessibilityLabel="Rhythmus"
                stretch
              />
            )}
            <TextLink
              label={form.rhythm === 'custom' ? 'Standard-Rhythmus wählen' : 'Anderer Rhythmus'}
              onPress={() => set('rhythm', form.rhythm === 'custom' ? 'month' : 'custom')}
            />
          </View>

          <View style={styles.group}>
            <AppText variant="caption" color="textMuted">
              Kategorie
            </AppText>
            <View accessibilityRole="radiogroup" accessibilityLabel="Kategorie" style={styles.chips}>
              {(categories.data ?? []).map((c) => (
                <Chip
                  key={c.id}
                  label={c.name}
                  dotColor={c.color}
                  selected={form.categoryId === c.id}
                  onPress={() => set('categoryId', form.categoryId === c.id ? null : c.id)}
                />
              ))}
            </View>
          </View>

          <View style={styles.group}>
            <AppText variant="caption" color="textMuted">
              Kündigungsfrist
            </AppText>
            <View accessibilityRole="radiogroup" accessibilityLabel="Kündigungsfrist" style={styles.chips}>
              {NOTICES.map((n) => (
                <Chip key={n.value} label={n.label} selected={form.notice === n.value} onPress={() => set('notice', n.value)} />
              ))}
            </View>
            {form.notice === 'custom' && (
              <View style={styles.customRow}>
                <View style={styles.countField}>
                  <TextField
                    label="Frist"
                    value={form.customNoticeValue}
                    onChangeText={(v) => set('customNoticeValue', v)}
                    error={errors.customNoticeValue}
                    keyboardType="number-pad"
                  />
                </View>
                <Segmented
                  options={NOTICE_UNITS}
                  value={form.customNoticeUnit}
                  onChange={(v) => set('customNoticeUnit', v)}
                  accessibilityLabel="Einheit der Frist"
                />
              </View>
            )}
          </View>

          {form.notice !== 'anytime' && (
            <View style={[styles.group, styles.termBox]}>
              <AppText variant="rowTitle">Vertragslaufzeit</AppText>
              <AppText variant="small" color="textMuted">
                Daraus berechnen wir, bis wann du kündigen kannst.
              </AppText>

              <AppText variant="caption" color="textMuted">
                Mindestlaufzeit
              </AppText>
              <View accessibilityRole="radiogroup" accessibilityLabel="Mindestlaufzeit" style={styles.chips}>
                {TERMS.map((t) => (
                  <Chip key={t.value} label={t.label} selected={form.minTerm === t.value} onPress={() => set('minTerm', t.value)} />
                ))}
              </View>
              {form.minTerm === 'custom' && (
                <TextField
                  label="Mindestlaufzeit in Monaten"
                  value={form.customMinTerm}
                  onChangeText={(v) => set('customMinTerm', v)}
                  error={errors.customMinTerm}
                  keyboardType="number-pad"
                />
              )}

              <AppText variant="caption" color="textMuted">
                Danach verlängert sich der Vertrag
              </AppText>
              <Segmented
                options={RENEWALS}
                value={String(form.renewalMonths) as '1' | '12'}
                onChange={(v) => set('renewalMonths', v === '12' ? 12 : 1)}
                accessibilityLabel="Verlängerung"
                stretch
              />

              <TextField
                label="Vertragsbeginn"
                value={form.startDate}
                onChangeText={(v) => set('startDate', v)}
                error={errors.startDate}
                placeholder="TT.MM.JJJJ"
                hint="Leer lassen, wenn der Vertrag mit der nächsten Abbuchung beginnt."
                keyboardType="numbers-and-punctuation"
              />
            </View>
          )}

          <View style={styles.remind}>
            <Bell size={18} strokeWidth={1.8} color={colors.textMuted} />
            <View style={styles.remindText}>
              <AppText variant="body" style={styles.medium}>
                Vor Fristende erinnern
              </AppText>
              <AppText variant="caption" color="textMuted">
                14 und 3 Tage vorher
              </AppText>
            </View>
            <Switch value={form.remind} onValueChange={(v) => set('remind', v)} accessibilityLabel="Vor Fristende erinnern" />
          </View>

          <Pressable
            accessibilityRole="button"
            accessibilityState={{ expanded: showMore }}
            onPress={() => setShowMore((v) => !v)}
            style={styles.moreToggle}
          >
            <AppText variant="body" color="accent" style={styles.medium}>
              Weitere Angaben
            </AppText>
            {showMore ? <ChevronUp size={18} color={colors.accent} /> : <ChevronDown size={18} color={colors.accent} />}
          </Pressable>

          {showMore && (
            <>
              <TextField
                label="Zahlungsweg"
                value={form.paymentMethod}
                onChangeText={(v) => set('paymentMethod', v)}
                error={errors.paymentMethod}
                placeholder="z. B. Lastschrift, PayPal, Kreditkarte"
              />
              <TextField
                label="Link zum Anbieter"
                value={form.providerUrl}
                onChangeText={(v) => set('providerUrl', v)}
                error={errors.providerUrl}
                placeholder="https://"
                autoCapitalize="none"
                keyboardType="url"
              />
              <TextField
                label="Notizen"
                value={form.notes}
                onChangeText={(v) => set('notes', v)}
                error={errors.notes}
                placeholder="z. B. Kundennummer, Vertragsnummer"
                multiline
              />
            </>
          )}
        </View>
      </ScrollView>

      <View style={[styles.footer, { paddingBottom: Math.max(insets.bottom, 12) + 16 }]}>
        <View style={styles.footerLine}>
          <AppText variant="small" color="textMuted">
            {hint}
          </AppText>
          <AppText variant="small" amount style={styles.semi}>
            {monthly}
          </AppText>
        </View>
        <PrimaryButton label={submitLabel} onPress={submit} disabled={!canSave} loading={submitting} />
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  root: {
    flex: 1,
  },
  scroll: {
    paddingHorizontal: screenPadding,
    paddingBottom: 24,
  },
  medium: {
    fontFamily: fonts.bodyMedium,
  },
  semi: {
    fontFamily: fonts.bodySemi,
  },
  search: {
    marginTop: 16,
    height: 48,
    paddingHorizontal: 14,
    borderRadius: radius.iconButton,
    borderWidth: 1,
    borderColor: colors.borderStrong,
    backgroundColor: colors.surface,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
  },
  searchInput: {
    flex: 1,
    minHeight: 44,
    color: colors.text,
    fontFamily: fonts.body,
    fontSize: 15,
    ...Platform.select({ web: { outlineStyle: 'none' } as object }),
  },
  block: {
    marginTop: 16,
    gap: 8,
  },
  tiles: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  tile: {
    flexBasis: '22%',
    flexGrow: 1,
    height: 72,
    paddingVertical: 8,
    paddingHorizontal: 4,
    borderRadius: radius.iconButton,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.surface,
    alignItems: 'center',
    justifyContent: 'center',
    gap: 6,
  },
  tileSelected: {
    borderColor: colors.accent,
    borderWidth: 1.5,
    backgroundColor: colors.accentSurface,
  },
  tileLabel: {
    maxWidth: '100%',
  },
  ownIcon: {
    width: 30,
    height: 30,
    borderRadius: 10,
    borderWidth: 1.5,
    borderStyle: 'dashed',
    borderColor: colors.checkboxOff,
    alignItems: 'center',
    justifyContent: 'center',
  },
  form: {
    marginTop: 18,
    gap: 14,
  },
  twoCols: {
    flexDirection: 'row',
    gap: 10,
  },
  col: {
    flex: 1,
  },
  group: {
    gap: 8,
  },
  customRow: {
    flexDirection: 'row',
    alignItems: 'flex-end',
    gap: 10,
    flexWrap: 'wrap',
  },
  countField: {
    width: 88,
  },
  chips: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  termBox: {
    padding: 14,
    borderRadius: 16,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.surface,
    gap: 10,
  },
  remind: {
    minHeight: 56,
    paddingHorizontal: 14,
    borderRadius: radius.input,
    borderWidth: 1,
    borderColor: colors.borderStrong,
    backgroundColor: colors.surface,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  remindText: {
    flex: 1,
    gap: 1,
  },
  moreToggle: {
    minHeight: 44,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
  },
  footer: {
    paddingTop: 12,
    paddingHorizontal: screenPadding,
    gap: 10,
    backgroundColor: colors.surfaceBar,
    borderTopWidth: 1,
    borderTopColor: colors.border,
  },
  footerLine: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'baseline',
  },
});
