// Thin fetch wrapper for the Laravel API (/api/v1). All calculations happen on the server.

export type ValidationErrors = Record<string, string[]>;

export class ApiError extends Error {
  constructor(
    message: string,
    /** HTTP status, 0 = network error */
    public readonly status: number,
    public readonly errors: ValidationErrors = {},
  ) {
    super(message);
    this.name = 'ApiError';
  }

  /** First validation message for a field, if any. */
  field(name: string): string | undefined {
    return this.errors[name]?.[0];
  }

  get isValidation(): boolean {
    return this.status === 422;
  }
}

type Method = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';

export type ApiClientOptions = {
  baseUrl: string;
  getToken: () => Promise<string | null>;
  /** Called on 401 for authenticated requests (token expired or revoked). */
  onUnauthorized?: () => void;
  fetchImpl?: typeof fetch;
};

export type ApiClient = ReturnType<typeof createApiClient>;

export function createApiClient({ baseUrl, getToken, onUnauthorized, fetchImpl }: ApiClientOptions) {
  const root = baseUrl.replace(/\/+$/, '');

  async function request<T>(method: Method, path: string, body?: unknown): Promise<T> {
    const token = await getToken();
    const headers: Record<string, string> = { Accept: 'application/json' };
    if (body !== undefined) headers['Content-Type'] = 'application/json';
    if (token) headers.Authorization = `Bearer ${token}`;

    let response: Response;
    try {
      response = await (fetchImpl ?? fetch)(`${root}/${path.replace(/^\/+/, '')}`, {
        method,
        headers,
        body: body === undefined ? undefined : JSON.stringify(body),
      });
    } catch {
      throw new ApiError('Keine Verbindung zum Server. Prüf deine Internetverbindung.', 0);
    }

    if (response.status === 204) {
      return undefined as T;
    }

    const data = await response.json().catch(() => null);

    if (response.ok) {
      return data as T;
    }

    if (response.status === 401 && token) {
      onUnauthorized?.();
    }

    throw new ApiError(messageFor(response.status, data), response.status, data?.errors ?? {});
  }

  return {
    get: <T>(path: string) => request<T>('GET', path),
    post: <T>(path: string, body?: unknown) => request<T>('POST', path, body ?? {}),
    put: <T>(path: string, body?: unknown) => request<T>('PUT', path, body ?? {}),
    patch: <T>(path: string, body?: unknown) => request<T>('PATCH', path, body ?? {}),
    delete: <T = void>(path: string) => request<T>('DELETE', path),
  };
}

function messageFor(status: number, data: { message?: unknown } | null): string {
  if (status === 429) return 'Zu viele Versuche. Warte kurz und probier es noch mal.';
  if (status >= 500) return 'Auf dem Server ist etwas schiefgelaufen. Probier es später noch mal.';
  if (typeof data?.message === 'string' && data.message) return data.message;
  if (status === 401) return 'Bitte melde dich an.';
  if (status === 403) return 'Dafür fehlt dir die Berechtigung.';
  if (status === 404) return 'Nicht gefunden.';
  return 'Das hat nicht geklappt.';
}
