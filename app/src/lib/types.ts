// Shapes of API responses (see api/app/Http/Resources).

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
