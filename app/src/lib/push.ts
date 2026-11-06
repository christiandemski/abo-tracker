// Push notifications (Android). The web version gets reminders by mail only.
import Constants from 'expo-constants';
import * as Device from 'expo-device';
import * as Notifications from 'expo-notifications';
import { AndroidImportance } from 'expo-notifications';
import * as SecureStore from 'expo-secure-store';
import { Platform } from 'react-native';

import type { ApiClient } from './api';
import { deviceName } from './config';

const TOKEN_KEY = 'abo-tracker.push-token';
export const CHANNEL_ID = 'reminders';

export const pushSupported = Platform.OS === 'android';

if (pushSupported) {
  Notifications.setNotificationHandler({
    handleNotification: async () => ({
      shouldShowBanner: true,
      shouldShowList: true,
      shouldPlaySound: true,
      shouldSetBadge: false,
    }),
  });
}

export type PushStatus = 'unsupported' | 'denied' | 'unconfigured' | 'registered' | 'error';

function projectId(): string | undefined {
  return (
    (Constants.expoConfig?.extra?.eas?.projectId as string | undefined) ??
    (Constants.easConfig?.projectId as string | undefined)
  );
}

/**
 * Asks for permission (once) and registers this device with the API.
 * Safe to call on every app start; the server deduplicates tokens.
 */
export async function registerForPush(api: ApiClient, { ask = true } = {}): Promise<PushStatus> {
  if (!pushSupported || !Device.isDevice) return 'unsupported';

  try {
    await Notifications.setNotificationChannelAsync(CHANNEL_ID, {
      name: 'Erinnerungen',
      description: 'Kündigungsfristen und anstehende Abbuchungen',
      importance: AndroidImportance.HIGH,
    });

    let { status } = await Notifications.getPermissionsAsync();
    if (status !== 'granted' && ask) {
      status = (await Notifications.requestPermissionsAsync()).status;
    }
    if (status !== 'granted') return 'denied';

    const id = projectId();
    if (!id) return 'unconfigured';

    const { data: token } = await Notifications.getExpoPushTokenAsync({ projectId: id });
    await api.post('push-tokens', { token, device_name: deviceName });
    await SecureStore.setItemAsync(TOKEN_KEY, token);
    return 'registered';
  } catch {
    return 'error';
  }
}

/** On logout: this device should no longer receive reminders for the account. */
export async function unregisterPush(api: ApiClient): Promise<void> {
  if (!pushSupported) return;
  try {
    const token = await SecureStore.getItemAsync(TOKEN_KEY);
    if (!token) return;
    await api.delete(`push-tokens/${encodeURIComponent(token)}`);
    await SecureStore.deleteItemAsync(TOKEN_KEY);
  } catch {
    // Logging out must never fail because of push.
  }
}

/** Route from a tapped notification, e.g. "/subscription/5". */
export function urlFromNotification(response: Notifications.NotificationResponse | null | undefined): string | null {
  const url = response?.notification.request.content.data?.url;
  return typeof url === 'string' && url.startsWith('/') ? url : null;
}
