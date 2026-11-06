import Constants from 'expo-constants';
import { Platform } from 'react-native';

const FALLBACK_API_URL = 'https://abo.codekorb.de/api/v1';

export const apiUrl: string =
  (Constants.expoConfig?.extra?.apiUrl as string | undefined) ?? FALLBACK_API_URL;

/** Shown in the token list on the server, so devices can be told apart. */
export const deviceName: string =
  Platform.OS === 'web' ? 'Browser' : (Constants.deviceName ?? 'Android-Gerät');
