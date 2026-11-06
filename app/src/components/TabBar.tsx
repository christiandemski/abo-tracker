import { router } from 'expo-router';
import type { BottomTabBarProps } from 'expo-router/js-tabs';
import { Calendar, ChartLine, LayoutDashboard, List, Plus, type LucideIcon } from 'lucide-react-native';
import { Fragment } from 'react';
import { Pressable, StyleSheet, View } from 'react-native';

import { colors, fonts, size } from '@/theme';

import { AppText } from './AppText';

const TABS: Record<string, { label: string; icon: LucideIcon }> = {
  index: { label: 'Übersicht', icon: LayoutDashboard },
  subscriptions: { label: 'Abos', icon: List },
  calendar: { label: 'Kalender', icon: Calendar },
  stats: { label: 'Statistik', icon: ChartLine },
};

/** Index after which the raised plus button sits (between "Abos" and "Kalender"). */
const PLUS_AFTER = 1;

export function TabBar({ state, navigateToTab, insets }: BottomTabBarProps) {
  return (
    <View
      accessibilityRole="tablist"
      style={[styles.bar, { paddingBottom: Math.max(insets.bottom, 8) + 16, height: size.tabBar + insets.bottom }]}
    >
      {state.routes.map((route, index) => {
        const tab = TABS[route.name];
        if (!tab) return null;
        const active = state.index === index;
        const Icon = tab.icon;

        return (
          <Fragment key={route.key}>
            <Pressable
              accessibilityRole="tab"
              accessibilityState={{ selected: active }}
              accessibilityLabel={tab.label}
              onPress={() => navigateToTab(route.key)}
              style={styles.tab}
            >
              <Icon size={22} strokeWidth={1.8} color={active ? colors.accent : colors.textMuted} />
              <AppText variant="tab" color={active ? 'accent' : 'textMuted'} style={active && styles.activeLabel}>
                {tab.label}
              </AppText>
            </Pressable>
            {index === PLUS_AFTER && (
              <Pressable
                accessibilityRole="button"
                accessibilityLabel="Abo hinzufügen"
                onPress={() => router.push('/add')}
                style={({ pressed }) => [styles.plus, pressed && styles.plusPressed]}
              >
                <Plus size={24} strokeWidth={2.2} color={colors.onAccent} />
              </Pressable>
            )}
          </Fragment>
        );
      })}
    </View>
  );
}

const styles = StyleSheet.create({
  bar: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-around',
    paddingTop: 8,
    paddingHorizontal: 12,
    backgroundColor: colors.surfaceBar,
    borderTopWidth: 1,
    borderTopColor: colors.border,
  },
  tab: {
    width: 64,
    height: 48,
    alignItems: 'center',
    justifyContent: 'center',
    gap: 3,
  },
  activeLabel: {
    fontFamily: fonts.bodySemi,
  },
  plus: {
    width: size.fab,
    height: size.fab,
    marginTop: -14,
    borderRadius: 18,
    backgroundColor: colors.accent,
    alignItems: 'center',
    justifyContent: 'center',
  },
  plusPressed: {
    opacity: 0.85,
  },
});
