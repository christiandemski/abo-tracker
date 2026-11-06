import { ApiError, createApiClient } from '../api';

function jsonResponse(status: number, body: unknown): Response {
  return {
    ok: status >= 200 && status < 300,
    status,
    json: async () => body,
  } as Response;
}

function setup(token: string | null = 'secret-token') {
  const fetchImpl = jest.fn<Promise<Response>, Parameters<typeof fetch>>();
  const onUnauthorized = jest.fn();
  const client = createApiClient({
    baseUrl: 'https://abo.codekorb.de/api/v1/',
    getToken: async () => token,
    onUnauthorized,
    fetchImpl: fetchImpl as unknown as typeof fetch,
  });
  return { client, fetchImpl, onUnauthorized };
}

describe('createApiClient', () => {
  it('sends JSON with the bearer token to the joined URL', async () => {
    const { client, fetchImpl } = setup();
    fetchImpl.mockResolvedValue(jsonResponse(200, { data: { id: 1 } }));

    await expect(client.post('/auth/login', { email: 'a@b.de' })).resolves.toEqual({ data: { id: 1 } });

    const [url, init] = fetchImpl.mock.calls[0];
    expect(url).toBe('https://abo.codekorb.de/api/v1/auth/login');
    expect(init?.method).toBe('POST');
    expect(init?.body).toBe(JSON.stringify({ email: 'a@b.de' }));
    expect(init?.headers).toMatchObject({
      Accept: 'application/json',
      'Content-Type': 'application/json',
      Authorization: 'Bearer secret-token',
    });
  });

  it('omits the Authorization header without a token', async () => {
    const { client, fetchImpl } = setup(null);
    fetchImpl.mockResolvedValue(jsonResponse(200, {}));

    await client.get('health');

    expect(fetchImpl.mock.calls[0][1]?.headers).not.toHaveProperty('Authorization');
    expect(fetchImpl.mock.calls[0][1]?.body).toBeUndefined();
  });

  it('returns undefined for 204 No Content', async () => {
    const { client, fetchImpl } = setup();
    fetchImpl.mockResolvedValue({ ok: true, status: 204, json: async () => null } as Response);

    await expect(client.post('auth/logout')).resolves.toBeUndefined();
  });

  it('exposes Laravel validation errors (422)', async () => {
    const { client, fetchImpl } = setup();
    fetchImpl.mockResolvedValue(
      jsonResponse(422, { message: 'E-Mail oder Passwort stimmt nicht.', errors: { email: ['E-Mail oder Passwort stimmt nicht.'] } }),
    );

    const error = await client.post('auth/login', {}).catch((e: unknown) => e);

    expect(error).toBeInstanceOf(ApiError);
    expect((error as ApiError).isValidation).toBe(true);
    expect((error as ApiError).field('email')).toBe('E-Mail oder Passwort stimmt nicht.');
    expect((error as ApiError).field('password')).toBeUndefined();
  });

  it('calls onUnauthorized on 401 when a token was sent', async () => {
    const { client, fetchImpl, onUnauthorized } = setup();
    fetchImpl.mockResolvedValue(jsonResponse(401, { message: 'Unauthenticated.' }));

    await expect(client.get('me')).rejects.toMatchObject({ status: 401 });
    expect(onUnauthorized).toHaveBeenCalledTimes(1);
  });

  it('does not call onUnauthorized for anonymous requests', async () => {
    const { client, fetchImpl, onUnauthorized } = setup(null);
    fetchImpl.mockResolvedValue(jsonResponse(401, { message: 'Unauthenticated.' }));

    await expect(client.get('me')).rejects.toMatchObject({ status: 401 });
    expect(onUnauthorized).not.toHaveBeenCalled();
  });

  it('maps network failures to status 0 with a German message', async () => {
    const { client, fetchImpl } = setup();
    fetchImpl.mockRejectedValue(new TypeError('Network request failed'));

    await expect(client.get('me')).rejects.toMatchObject({
      status: 0,
      message: 'Keine Verbindung zum Server. Prüf deine Internetverbindung.',
    });
  });

  it('uses friendly messages for rate limits and server errors', async () => {
    const { client, fetchImpl } = setup();
    fetchImpl.mockResolvedValueOnce(jsonResponse(429, { message: 'Too Many Attempts.' }));
    fetchImpl.mockResolvedValueOnce({ ok: false, status: 500, json: async () => { throw new Error('html'); } } as unknown as Response);

    await expect(client.get('me')).rejects.toMatchObject({ status: 429, message: expect.stringContaining('Zu viele Versuche') });
    await expect(client.get('me')).rejects.toMatchObject({ status: 500, message: expect.stringContaining('Server') });
  });
});

describe('upload', () => {
  it('sends FormData without a JSON content type', async () => {
    const { client, fetchImpl } = setup();
    fetchImpl.mockResolvedValue(jsonResponse(201, { data: { id: 1 } }));
    const form = new FormData();
    form.append('bank_format', 'sparkasse_camt_v2');

    await client.upload('imports', form);

    const [, init] = fetchImpl.mock.calls[0];
    expect(init?.body).toBe(form);
    expect(init?.headers).not.toHaveProperty('Content-Type');
    expect(init?.headers).toMatchObject({ Authorization: 'Bearer secret-token' });
  });
});
