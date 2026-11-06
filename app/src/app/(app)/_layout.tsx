import { Stack } from 'expo-router';

import { colors } from '@/theme';

export const unstable_settings = {
  initialRouteName: '(tabs)',
};

export default function AppLayout() {
  return (
    <Stack screenOptions={{ headerShown: false, contentStyle: { backgroundColor: colors.bg } }}>
      <Stack.Screen name="(tabs)" />
      <Stack.Screen name="add" options={{ presentation: 'modal' }} />
      <Stack.Screen name="import" options={{ presentation: 'modal' }} />
      <Stack.Screen name="settings" />
      <Stack.Screen name="subscription/[id]/index" />
      <Stack.Screen name="subscription/[id]/edit" options={{ presentation: 'modal' }} />
    </Stack>
  );
}
