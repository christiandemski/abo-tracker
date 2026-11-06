import { router } from 'expo-router';
import { CirclePlus, Search } from 'lucide-react-native';
import { useEffect, useMemo, useState } from 'react';
import { FlatList, RefreshControl, ScrollView, StyleSheet, TextInput, View } from 'react-native';

import {
  AppText,
  Card,
  ErrorState,
  Notice,
  PrimaryButton,
  Screen,
  ScreenHeader,
  Segmented,
  SkeletonList,
  SubscriptionRow,
} from '@/components';
import { Chip } from '@/components/Chip';
import { useAuth } from '@/lib/auth';
import { formatCents, formatShortDate } from '@/lib/format';
import { useCategories, useSubscriptions, type ListParams } from '@/lib/queries';
import type { Subscription } from '@/lib/types';
import { colors, fonts, radius, screenPadding } from '@/theme';

const STATUS = [
  { value: 'active', label: 'Aktiv' },
  { value: 'cancelled', label: 'Gekündigt' },
  { value: 'paused', label: 'Pausiert' },
  { value: 'all', label: 'Alle' },
] as const;

const SORT = [
  { value: 'next_charge', label: 'Nächste' },
  { value: 'price', label: 'Preis' },
  { value: 'name', label: 'Name' },
] as const;

type Status = (typeof STATUS)[number]['value'];
type Sort = (typeof SORT)[number]['value'];

function useDebounced<T>(value: T, delay = 250): T {
  const [debounced, setDebounced] = useState(value);
  useEffect(() => {
    const t = setTimeout(() => setDebounced(value), delay);
    return () => clearTimeout(t);
  }, [value, delay]);
  return debounced;
}

function metaFor(s: Subscription): string {
  if (s.status === 'cancelled') {
    return s.ends_at ? `Gekündigt zum ${formatShortDate(s.ends_at)}` : 'Gekündigt';
  }
  if (s.status === 'paused') return 'Pausiert';
  const parts = [s.category?.name ?? 'Ohne Kategorie'];
  if (s.next_charge_date) parts.push(`nächste ${formatShortDate(s.next_charge_date)}`);
  return parts.join(' · ');
}

export default function SubscriptionsScreen() {
  const { user } = useAuth();
  const [query, setQuery] = useState('');
  const [status, setStatus] = useState<Status>('active');
  const [category, setCategory] = useState<number | undefined>(undefined);
  const [sort, setSort] = useState<Sort>('next_charge');
  const q = useDebounced(query.trim());

  const params: ListParams = useMemo(() => ({ status, category, q: q || undefined, sort }), [status, category, q, sort]);
  const list = useSubscriptions(params);
  const categories = useCategories();

  const header = (
    <View style={styles.header}>
      <ScreenHeader title="Abos" />

      <View style={styles.search}>
        <Search size={18} strokeWidth={2} color={colors.textMuted} />
        <TextInput
          value={query}
          onChangeText={setQuery}
          placeholder="Abos durchsuchen"
          placeholderTextColor={colors.placeholder}
          accessibilityLabel="Abos durchsuchen"
          style={styles.searchInput}
          returnKeyType="search"
          autoCorrect={false}
        />
      </View>

      <Segmented options={STATUS} value={status} onChange={setStatus} accessibilityLabel="Status" stretch />

      {categories.data && categories.data.length > 0 && (
        <ScrollView
          horizontal
          showsHorizontalScrollIndicator={false}
          accessibilityRole="radiogroup"
          accessibilityLabel="Kategorie"
          contentContainerStyle={styles.chips}
          style={styles.chipScroller}
        >
          <Chip label="Alle Kategorien" selected={category === undefined} onPress={() => setCategory(undefined)} />
          {categories.data.map((c) => (
            <Chip
              key={c.id}
              label={c.name}
              dotColor={c.color}
              selected={category === c.id}
              onPress={() => setCategory(category === c.id ? undefined : c.id)}
            />
          ))}
        </ScrollView>
      )}

      <View style={styles.sortRow}>
        <AppText variant="caption" color="textMuted">
          Sortieren
        </AppText>
        <Segmented options={SORT} value={sort} onChange={setSort} accessibilityLabel="Sortierung" />
      </View>
    </View>
  );

  if (!user?.email_verified) {
    return (
      <Screen>
        <ScreenHeader title="Abos" />
        <View style={styles.top16}>
          <Notice tone="info" title="Bitte bestätige zuerst deine E-Mail-Adresse">
            Den Link findest du in deinem Postfach. Danach kannst du hier deine Abos verwalten.
          </Notice>
        </View>
      </Screen>
    );
  }

  const filtered = status !== 'active' || category !== undefined || q !== '';

  return (
    <Screen bare>
      <FlatList
        data={list.data ?? []}
        keyExtractor={(s) => String(s.id)}
        ListHeaderComponent={header}
        contentContainerStyle={styles.listContent}
        keyboardShouldPersistTaps="handled"
        refreshControl={
          <RefreshControl refreshing={list.refreshing && !list.loading} onRefresh={list.refetch} tintColor={colors.accent} />
        }
        renderItem={({ item, index }) => (
          <SubscriptionRow
            name={item.name}
            meta={metaFor(item)}
            amount={formatCents(item.amount_cents)}
            amountMeta={item.rhythm_label}
            categoryColor={item.category?.color}
            status={item.status}
            last={index === (list.data?.length ?? 0) - 1}
            onPress={() => router.push(`/subscription/${item.id}`)}
          />
        )}
        ListEmptyComponent={
          list.loading ? (
            <SkeletonList rows={4} />
          ) : list.error && !list.data ? (
            <ErrorState message={list.error.message} onRetry={list.refetch} retrying={list.refreshing} />
          ) : filtered ? (
            <AppText variant="body" color="textMuted" style={styles.empty}>
              Keine Abos gefunden.
            </AppText>
          ) : (
            <Card>
              <AppText variant="h2">Noch keine Abos</AppText>
              <AppText variant="body" color="textMuted">
                Leg dein erstes Abo an. Vorlagen für bekannte Dienste helfen dir dabei.
              </AppText>
              <PrimaryButton label="Abo anlegen" icon={CirclePlus} onPress={() => router.push('/add')} />
            </Card>
          )
        }
      />
    </Screen>
  );
}

const styles = StyleSheet.create({
  header: {
    gap: 12,
    paddingBottom: 8,
  },
  top16: {
    marginTop: 16,
  },
  search: {
    marginTop: 12,
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
  },
  chipScroller: {
    marginHorizontal: -screenPadding,
  },
  chips: {
    gap: 8,
    paddingHorizontal: screenPadding,
  },
  sortRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 12,
  },
  listContent: {
    paddingHorizontal: screenPadding,
    paddingBottom: 32,
    flexGrow: 1,
  },
  empty: {
    paddingVertical: 24,
    textAlign: 'center',
  },
});
