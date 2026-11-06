import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from 'react';

import { ApiError, createApiClient, type ApiClient } from './api';
import { clearCache } from './cache';
import { unregisterPush } from './push';
import { apiUrl, deviceName } from './config';
import { tokenStorage } from './tokenStorage';
import type { AuthResponse, Invitation, MessageResponse, Resource, User } from './types';

type Status = 'loading' | 'signedOut' | 'signedIn';

export type RegisterInput = {
  token: string;
  name: string;
  email: string;
  password: string;
  password_confirmation: string;
};

type AuthContextValue = {
  status: Status;
  user: User | null;
  api: ApiClient;
  signIn: (email: string, password: string) => Promise<void>;
  register: (input: RegisterInput) => Promise<void>;
  signOut: () => Promise<void>;
  refreshUser: () => Promise<void>;
  checkInvitation: (token: string) => Promise<Invitation>;
  requestPasswordReset: (email: string) => Promise<string>;
  resetPassword: (input: { token: string; email: string; password: string; password_confirmation: string }) => Promise<string>;
};

const AuthContext = createContext<AuthContextValue | null>(null);

// In-memory copy of the stored token for the current session.
const session: { token: string | null } = { token: null };

export function AuthProvider({ children }: { children: ReactNode }) {
  const [status, setStatus] = useState<Status>('loading');
  const [user, setUser] = useState<User | null>(null);

  const clearSession = useCallback(async () => {
    session.token = null;
    await tokenStorage.clear();
    await clearCache();
    setUser(null);
    setStatus('signedOut');
  }, []);

  const api = useMemo(
    () =>
      createApiClient({
        baseUrl: apiUrl,
        getToken: async () => session.token,
        // Token expired or revoked elsewhere → back to login.
        onUnauthorized: () => {
          void clearSession();
        },
      }),
    [clearSession],
  );

  const startSession = useCallback(async ({ token, user: me }: AuthResponse) => {
    session.token = token;
    await tokenStorage.set(token);
    setUser(me);
    setStatus('signedIn');
  }, []);

  // Restore the session on app start.
  useEffect(() => {
    let cancelled = false;
    (async () => {
      const stored = await tokenStorage.get().catch(() => null);
      if (!stored) {
        if (!cancelled) setStatus('signedOut');
        return;
      }
      session.token = stored;
      try {
        const { data } = await api.get<Resource<User>>('me');
        if (!cancelled) {
          setUser(data);
          setStatus('signedIn');
        }
      } catch (error) {
        if (cancelled) return;
        if (error instanceof ApiError && error.status === 401) {
          await clearSession();
        } else {
          // Offline: keep the token and retry on the next start.
          session.token = null;
          setStatus('signedOut');
        }
      }
    })();
    return () => {
      cancelled = true;
    };
  }, [api, clearSession]);

  const value = useMemo<AuthContextValue>(
    () => ({
      status,
      user,
      api,
      signIn: async (email, password) => {
        await startSession(await api.post<AuthResponse>('auth/login', { email, password, device_name: deviceName }));
      },
      register: async (input) => {
        await startSession(await api.post<AuthResponse>('auth/register', { ...input, device_name: deviceName }));
      },
      signOut: async () => {
        await unregisterPush(api);
        try {
          await api.post('auth/logout');
        } catch {
          // Logging out locally is enough if the server is unreachable.
        }
        await clearSession();
      },
      refreshUser: async () => {
        const { data } = await api.get<Resource<User>>('me');
        setUser(data);
      },
      checkInvitation: async (token) =>
        (await api.get<Resource<Invitation>>(`invitations/${encodeURIComponent(token)}`)).data,
      requestPasswordReset: async (email) =>
        (await api.post<MessageResponse>('auth/forgot-password', { email })).message,
      resetPassword: async (input) => (await api.post<MessageResponse>('auth/reset-password', input)).message,
    }),
    [status, user, api, startSession, clearSession],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthContextValue {
  const context = useContext(AuthContext);
  if (!context) throw new Error('useAuth must be used inside <AuthProvider>');
  return context;
}
