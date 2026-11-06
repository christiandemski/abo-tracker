import { formatCents, formatDate, formatLongDate, parseDate } from '../format';

const NBSP = ' ';

describe('formatCents', () => {
  it.each([
    [0, `0,00${NBSP}€`],
    [1, `0,01${NBSP}€`],
    [99, `0,99${NBSP}€`],
    [1399, `13,99${NBSP}€`],
    [123456, `1.234,56${NBSP}€`],
    [100000000, `1.000.000,00${NBSP}€`],
    [-1699, `-16,99${NBSP}€`],
  ])('formats %d cents as %s', (cents, expected) => {
    expect(formatCents(cents)).toBe(expected);
  });

  it('never shows negative zero', () => {
    expect(formatCents(-0)).toBe(`0,00${NBSP}€`);
  });

  it('rounds fractional cents from summed values', () => {
    expect(formatCents(1399.5)).toBe(`14,00${NBSP}€`);
    expect(formatCents(1399.4)).toBe(`13,99${NBSP}€`);
  });

  it('rejects non-finite values', () => {
    expect(() => formatCents(Number.NaN)).toThrow(TypeError);
    expect(() => formatCents(Number.POSITIVE_INFINITY)).toThrow(TypeError);
  });
});

describe('dates', () => {
  it('parses API dates as local calendar dates', () => {
    const d = parseDate('2026-03-29'); // DST switch day in Germany
    expect([d.getFullYear(), d.getMonth(), d.getDate()]).toEqual([2026, 2, 29]);
  });

  it('rejects malformed dates', () => {
    expect(() => parseDate('29.03.2026')).toThrow(TypeError);
  });

  it('formats dates in German notation', () => {
    expect(formatDate('2026-02-01')).toBe('01.02.2026');
    expect(formatDate(new Date(2028, 1, 29))).toBe('29.02.2028');
  });

  it('formats the long overview date', () => {
    expect(formatLongDate('2026-11-05')).toBe('Donnerstag, 5. November');
    expect(formatLongDate('2026-03-01')).toBe('Sonntag, 1. März');
  });
});
