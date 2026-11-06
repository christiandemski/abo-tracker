import { urlFromNotification } from '../push';

function response(data: Record<string, unknown>) {
  return { notification: { request: { content: { data } } } } as never;
}

describe('urlFromNotification', () => {
  it('returns in-app routes from the push payload', () => {
    expect(urlFromNotification(response({ url: '/subscription/5', subscription_id: 5 }))).toBe('/subscription/5');
  });

  it('ignores missing or external URLs', () => {
    expect(urlFromNotification(null)).toBeNull();
    expect(urlFromNotification(response({}))).toBeNull();
    expect(urlFromNotification(response({ url: 'https://evil.example' }))).toBeNull();
  });
});
