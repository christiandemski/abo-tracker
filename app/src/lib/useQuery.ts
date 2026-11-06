import { useFocusEffect } from 'expo-router';
import { useCallback, useEffect, useRef, useState } from 'react';

import { readCache, writeCache } from './cache';

type Listener = () => void;
const listeners = new Map<string, Set<Listener>>();

/**
 * Marks cached data as stale (e.g. after saving a subscription).
 * Every mounted query whose key starts with one of the prefixes refetches.
 */
export function invalidate(...prefixes: string[]) {
  for (const [key, set] of listeners) {
    if (prefixes.some((p) => key.startsWith(p))) {
      set.forEach((fn) => fn());
    }
  }
}

type Options = {
  /** Keep the last response on the device and show it immediately on the next start. */
  persist?: boolean;
  enabled?: boolean;
};

export type QueryState<T> = {
  data: T | undefined;
  error: Error | null;
  /** First load without any data (show skeleton) */
  loading: boolean;
  /** Pull-to-refresh / background refetch */
  refreshing: boolean;
  /** Data comes from the device cache, the server could not be reached */
  stale: boolean;
  refetch: () => Promise<void>;
};

export function useQuery<T>(key: string, fetcher: () => Promise<T>, { persist, enabled = true }: Options = {}): QueryState<T> {
  const [data, setData] = useState<T | undefined>(undefined);
  const [error, setError] = useState<Error | null>(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [stale, setStale] = useState(false);

  const fetcherRef = useRef(fetcher);
  useEffect(() => {
    fetcherRef.current = fetcher;
  });

  const run = useCallback(async () => {
    if (!enabled) return;
    setRefreshing(true);
    try {
      const result = await fetcherRef.current();
      setData(result);
      setError(null);
      setStale(false);
      if (persist) void writeCache(key, result);
    } catch (e) {
      setError(e instanceof Error ? e : new Error(String(e)));
      setStale(true);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [key, persist, enabled]);

  // Show cached data first, then load fresh data.
  useEffect(() => {
    let cancelled = false;
    (async () => {
      if (persist && enabled) {
        const cached = await readCache<T>(key);
        if (!cancelled && cached !== undefined) {
          setData((current) => current ?? cached);
          setLoading(false);
        }
      }
      if (!cancelled) await run();
    })();
    return () => {
      cancelled = true;
    };
  }, [key, persist, enabled, run]);

  // Refetch when the screen comes back into focus (e.g. after editing).
  const skipFirstFocus = useRef(true);
  useFocusEffect(
    useCallback(() => {
      if (skipFirstFocus.current) {
        skipFirstFocus.current = false;
        return;
      }
      void run();
    }, [run]),
  );

  // Refetch on invalidate()
  useEffect(() => {
    const set = listeners.get(key) ?? new Set<Listener>();
    const listener = () => void run();
    set.add(listener);
    listeners.set(key, set);
    return () => {
      set.delete(listener);
      if (set.size === 0) listeners.delete(key);
    };
  }, [key, run]);

  return { data, error, loading, refreshing, stale, refetch: run };
}
