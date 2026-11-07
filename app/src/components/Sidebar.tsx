import { router } from 'expo-router';
import type { BottomTabBarProps } from 'expo-router/js-tabs';
import { Calendar, ChartLine, FileUp, LayoutDashboard, List, Plus, RefreshCw, Settings, type LucideIcon } from 'lucide-react-native';
import { Pressable, StyleSheet, View } from 'react-native';

import { colors, fonts, radius } from '@/theme';

import { AppText } from './AppText';

const TABS: Record<string, { label: string; icon: LucideIcon }> = {
  index: { label: 'Übersicht', icon: LayoutDashboard },
  subscriptions: { label: 'Fixkosten', icon: List },
  calendar: { label: 'Kalender & Fristen', icon: Calendar },
  stats: { label: 'Statistik', icon: ChartLine },
};

/** Desktop navigation (web ≥ 1024 px): same areas as the tab bar, plus import and settings. */
export function Sidebar({ state, navigateToTab }: BottomTabBarProps) {
  return (
    <View style={styles.aside}>
      <View style={styles.brand}>
        <View style={styles.logo}>
          <RefreshCw size={18} strokeWidth={2.2} color={colors.onAccent} />
        </View>
        <AppText variant="rowTitle" style={styles.brandText}>
          Cost-Tracker
        </AppText>
      </View>

      <View accessibilityRole="tablist" accessibilityLabel="Hauptnavigation" style={styles.nav}>
        {state.routes.map((route, index) => {
          const tab = TABS[route.name];
          if (!tab) return null;
          const active = state.index === index;
          return (
            <NavItem
              key={route.key}
              label={tab.label}
              icon={tab.icon}
              active={active}
              role="tab"
              onPress={() => navigateToTab(route.key)}
            />
          );
        })}
        <NavItem label="Import" icon={FileUp} onPress={() => router.push('/import')} />
      </View>

      <View style={styles.bottom}>
        <Pressable
          accessibilityRole="button"
          onPress={() => router.push('/add')}
          style={({ pressed }) => [styles.add, pressed && styles.pressed]}
        >
          <Plus size={18} strokeWidth={2.4} color={colors.onAccent} />
          <AppText variant="body" color="onAccent" style={styles.semi}>
            Hinzufügen
          </AppText>
        </Pressable>
        <NavItem label="Einstellungen" icon={Settings} onPress={() => router.push('/settings')} />
      </View>
    </View>
  );
}

function NavItem({
  label,
  icon: Icon,
  active = false,
  role = 'link',
  onPress,
}: {
  label: string;
  icon: LucideIcon;
  active?: boolean;
  role?: 'tab' | 'link';
  onPress: () => void;
}) {
  return (
    <Pressable
      accessibilityRole={role}
      accessibilityState={role === 'tab' ? { selected: active } : undefined}
      onPress={onPress}
      style={({ pressed, hovered }: { pressed: boolean; hovered?: boolean }) => [
        styles.item,
        active && styles.itemActive,
        !active && hovered && styles.itemHover,
        pressed && styles.pressed,
      ]}
    >
      <Icon size={18} strokeWidth={1.8} color={active ? colors.accent : colors.textMuted} />
      <AppText variant="body" color={active ? 'text' : 'textMuted'} style={active ? styles.semi : styles.medium}>
        {label}
      </AppText>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  aside: {
    width: 240,
    paddingHorizontal: 14,
    paddingVertical: 22,
    backgroundColor: colors.surfaceBar,
    borderRightWidth: 1,
    borderRightColor: colors.border,
  },
  brand: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    paddingHorizontal: 8,
    marginBottom: 26,
  },
  logo: {
    width: 32,
    height: 32,
    borderRadius: 10,
    backgroundColor: colors.accent,
    alignItems: 'center',
    justifyContent: 'center',
  },
  brandText: {
    fontFamily: fonts.display,
    fontSize: 17,
  },
  nav: {
    gap: 4,
  },
  item: {
    minHeight: 44,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    paddingHorizontal: 12,
    borderRadius: radius.input,
  },
  itemActive: {
    backgroundColor: colors.surfaceSelected,
  },
  itemHover: {
    backgroundColor: colors.surface,
  },
  pressed: {
    opacity: 0.85,
  },
  medium: {
    fontFamily: fonts.bodyMedium,
  },
  semi: {
    fontFamily: fonts.bodySemi,
  },
  bottom: {
    marginTop: 'auto',
    gap: 8,
  },
  add: {
    height: 44,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    borderRadius: radius.input,
    backgroundColor: colors.accent,
  },
});
