import { useAuth } from './auth';
import type { CalendarMonth, Category, Dashboard, History, Reserve, Resource, Subscription, Template } from './types';
import { useQuery } from './useQuery';

/** Query keys; invalidate('subscriptions', 'dashboard') after changes. */
export const keys = {
  dashboard: (period: string) => `dashboard:${period}`,
  subscriptions: (params: string) => `subscriptions:list:${params}`,
  subscription: (id: number | string) => `subscriptions:detail:${id}`,
  categories: 'categories',
  templates: (q: string) => `templates:${q}`,
  calendar: (month: string) => `calendar:${month}`,
  reserve: 'stats:reserve',
  history: (months: number) => `stats:history:${months}`,
};

export function useDashboard(period: 'month' | 'year') {
  const { api, user } = useAuth();
  return useQuery(
    keys.dashboard(period),
    async () => (await api.get<Resource<Dashboard>>(`dashboard?period=${period}`)).data,
    { persist: true, enabled: !!user?.email_verified },
  );
}

export type ListParams = {
  status?: 'all' | 'active' | 'cancelled' | 'paused';
  category?: number;
  q?: string;
  sort?: 'next_charge' | 'price' | 'name';
};

export function useSubscriptions(params: ListParams = {}) {
  const { api, user } = useAuth();
  const search = new URLSearchParams();
  for (const [k, v] of Object.entries(params)) {
    if (v !== undefined && v !== '') search.set(k, String(v));
  }
  const qs = search.toString();
  return useQuery(
    keys.subscriptions(qs),
    async () => (await api.get<Resource<Subscription[]>>(`subscriptions${qs ? `?${qs}` : ''}`)).data,
    { persist: qs === '', enabled: !!user?.email_verified },
  );
}

export function useSubscription(id: number | string | undefined) {
  const { api } = useAuth();
  return useQuery(
    keys.subscription(id ?? 'none'),
    async () => (await api.get<Resource<Subscription>>(`subscriptions/${id}`)).data,
    { enabled: id !== undefined },
  );
}

export function useCategories() {
  const { api } = useAuth();
  return useQuery(keys.categories, async () => (await api.get<Resource<Category[]>>('categories')).data, {
    persist: true,
  });
}

export function useTemplates(q: string) {
  const { api } = useAuth();
  return useQuery(
    keys.templates(q),
    async () => (await api.get<Resource<Template[]>>(`templates${q ? `?q=${encodeURIComponent(q)}` : ''}`)).data,
  );
}

/** Charges per day of a month ("2026-11"). */
export function useCalendar(month: string) {
  const { api, user } = useAuth();
  return useQuery(keys.calendar(month), async () => (await api.get<Resource<CalendarMonth>>(`calendar?month=${month}`)).data, {
    persist: true,
    enabled: !!user?.email_verified,
  });
}

export function useReserve() {
  const { api, user } = useAuth();
  return useQuery(keys.reserve, async () => (await api.get<Resource<Reserve>>('stats/reserve')).data, {
    persist: true,
    enabled: !!user?.email_verified,
  });
}

export function useHistory(months = 12) {
  const { api, user } = useAuth();
  return useQuery(keys.history(months), async () => (await api.get<Resource<History>>(`stats/history?months=${months}`)).data, {
    persist: true,
    enabled: !!user?.email_verified,
  });
}
