// Shapes of API responses (see api/app/Http/Resources and api/app/Services/DashboardBuilder.php).

export type User = {
  id: number;
  name: string;
  email: string;
  email_verified: boolean;
  is_admin: boolean;
  timezone: string;
};

export type AuthResponse = {
  token: string;
  user: User;
};

export type Invitation = {
  email: string;
  expires_at: string;
};

export type Resource<T> = { data: T };

export type MessageResponse = { message: string };

export type IntervalUnit = 'week' | 'month' | 'year';
export type NoticeUnit = 'day' | 'week' | 'month';
export type SubscriptionStatus = 'active' | 'cancelled' | 'paused';

export type Category = {
  id: number;
  key: string | null;
  name: string;
  color: string;
  sort: number;
  is_default: boolean;
};

export type PriceChange = {
  id: number;
  old_cents: number;
  new_cents: number;
  diff_cents: number;
  percent: number | null;
  effective_date: string;
  source: string;
  status: 'pending' | 'accepted' | 'dismissed';
};

export type Subscription = {
  id: number;
  name: string;
  category: Category | null;
  category_id: number | null;
  amount_cents: number;
  monthly_cents: number;
  yearly_cents: number;
  daily_cents: number;
  interval_unit: IntervalUnit;
  interval_count: number;
  rhythm_label: string;
  is_converted: boolean;
  anchor_date: string;
  next_charge_date: string | null;
  days_until_charge: number | null;
  start_date: string | null;
  min_term_months: number | null;
  renewal_months: number | null;
  notice_value: number | null;
  notice_unit: NoticeUnit | null;
  notice_label: string | null;
  has_term: boolean;
  term_end_date: string | null;
  cancel_by_date: string | null;
  days_until_deadline: number | null;
  status: SubscriptionStatus;
  ends_at: string | null;
  remind: boolean;
  payment_method: string | null;
  provider_url: string | null;
  notes: string | null;
  source: 'manual' | 'import' | 'template';
  template_key: string | null;
  price_history?: PriceChange[];
  /** Current price vs. first recorded price, percent; null without history */
  price_trend_percent?: number | null;
};

export type SubscriptionInput = {
  name: string;
  amount_cents: number;
  interval_unit: IntervalUnit;
  interval_count: number;
  anchor_date: string;
  category_id: number | null;
  start_date: string | null;
  min_term_months: number | null;
  renewal_months: number | null;
  notice_value: number | null;
  notice_unit: NoticeUnit | null;
  remind: boolean;
  payment_method: string | null;
  provider_url: string | null;
  notes: string | null;
  template_key: string | null;
};

export type CompactSubscription = {
  id: number;
  name: string;
  amount_cents: number;
  monthly_cents: number;
  rhythm_label: string;
  next_charge_date: string | null;
  term_end_date: string | null;
  cancel_by_date: string | null;
  has_term: boolean;
  category: Category | null;
};

export type UpcomingItem = {
  type: 'deadline' | 'charge';
  date: string;
  days: number;
  subscription: CompactSubscription;
};

export type Dashboard = {
  period: 'month' | 'year';
  total_cents: number;
  subscription_count: number;
  converted_count: number;
  inactive_count: number;
  categories: {
    category: Category | null;
    label: string;
    total_cents: number;
    share: number;
    count: number;
  }[];
  upcoming: UpcomingItem[];
  biggest: { subscription: CompactSubscription; period_cents: number }[];
};

export type Template = {
  key: string;
  name: string;
  category_key: string;
  category_id: number | null;
  interval_unit: IntervalUnit;
  interval_count: number;
  popular: boolean;
};

export type BillingPreview = {
  monthly_cents: number;
  yearly_cents: number;
  daily_cents: number;
  rhythm_label: string;
  is_converted: boolean;
};

export type ReminderSettings = {
  offsets_days: number[];
  available_offsets: number[];
  push: boolean;
  email: boolean;
  charge_reminders: boolean;
  push_devices: number;
};
