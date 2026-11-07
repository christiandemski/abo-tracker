import { Tabs } from 'expo-router/js-tabs';

import { TabBar } from '@/components';
import { Sidebar } from '@/components/Sidebar';
import { useIsDesktop } from '@/lib/useLayout';
import { colors } from '@/theme';

export default function TabsLayout() {
  const desktop = useIsDesktop();

  return (
    <Tabs
      tabBar={(props) => (desktop ? <Sidebar {...props} /> : <TabBar {...props} />)}
      screenOptions={{
        headerShown: false,
        sceneStyle: { backgroundColor: colors.bg },
        tabBarPosition: desktop ? 'left' : 'bottom',
      }}
    >
      <Tabs.Screen name="index" options={{ title: 'Übersicht' }} />
      <Tabs.Screen name="subscriptions" options={{ title: 'Fixkosten' }} />
      <Tabs.Screen name="calendar" options={{ title: 'Kalender' }} />
      <Tabs.Screen name="stats" options={{ title: 'Statistik' }} />
    </Tabs>
  );
}
