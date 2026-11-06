// Web: sessionStorage – the token is gone when the browser tab is closed.
const KEY = 'abo-tracker.token';

function storage(): Storage | null {
  try {
    return typeof window !== 'undefined' ? window.sessionStorage : null;
  } catch {
    return null;
  }
}

export const tokenStorage = {
  get: async () => storage()?.getItem(KEY) ?? null,
  set: async (token: string) => {
    storage()?.setItem(KEY, token);
  },
  clear: async () => {
    storage()?.removeItem(KEY);
  },
};
