import AsyncStorage from '@react-native-async-storage/async-storage';

// Last API responses, so the overview shows instantly (and offline) on the next start.
// Only display data lives here; tokens stay in SecureStore.

const PREFIX = 'abo-tracker.cache.';

export async function readCache<T>(key: string): Promise<T | undefined> {
  try {
    const raw = await AsyncStorage.getItem(PREFIX + key);
    return raw ? (JSON.parse(raw) as T) : undefined;
  } catch {
    return undefined;
  }
}

export async function writeCache<T>(key: string, value: T): Promise<void> {
  try {
    await AsyncStorage.setItem(PREFIX + key, JSON.stringify(value));
  } catch {
    // Cache is best effort.
  }
}

/** Called on logout: nothing of the previous user may remain on the device. */
export async function clearCache(): Promise<void> {
  try {
    const keys = await AsyncStorage.getAllKeys();
    await AsyncStorage.multiRemove(keys.filter((k) => k.startsWith(PREFIX)));
  } catch {
    // ignore
  }
}
