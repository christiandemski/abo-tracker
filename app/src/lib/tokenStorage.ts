// Android: token in the Keystore via expo-secure-store. Web: see tokenStorage.web.ts.
import * as SecureStore from 'expo-secure-store';

const KEY = 'abo-tracker.token';

export const tokenStorage = {
  get: () => SecureStore.getItemAsync(KEY),
  set: (token: string) => SecureStore.setItemAsync(KEY, token),
  clear: () => SecureStore.deleteItemAsync(KEY),
};
