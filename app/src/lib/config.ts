import Constants from 'expo-constants';
import { Platform } from 'react-native';

const FALLBACK_API_URL = 'https://kosten.codekorb.de/api/v1';

function resolveApiUrl(): string {
  // Explicit override (e.g. Expo dev server on :8081 talking to `php artisan serve` on :8000)
  const override = process.env.EXPO_PUBLIC_API_URL;
  if (override) return override;

  // The web export is served by Laravel itself, so the API lives on the same origin.
  if (Platform.OS === 'web' && typeof window !== 'undefined' && window.location?.origin) {
    return `${window.location.origin}/api/v1`;
  }

  return (Constants.expoConfig?.extra?.apiUrl as string | undefined) ?? FALLBACK_API_URL;
}

export const apiUrl: string = resolveApiUrl();

/** Shown in the token list on the server, so devices can be told apart. */
export const deviceName: string =
  Platform.OS === 'web' ? 'Browser' : (Constants.deviceName ?? 'Android-Gerät');
