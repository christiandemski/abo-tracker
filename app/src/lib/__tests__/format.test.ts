import {
  centsToInput,
  currentMonth,
  formatCents,
  formatDate,
  formatDaysUntil,
  formatEuroRounded,
  formatLongDate,
  formatMonthName,
  formatMonthShort,
  formatMonthYear,
  formatPercent,
  formatShortDate,
  formatWeekdayDate,
  formatWeekdayDayMonth,
  formatWeekdayLongDate,
  mondayIndex,
  parseDate,
  parseEuroInput,
  parseGermanDate,
  shiftMonth,
  toIsoDate,
} from '../format';

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


describe('short dates', () => {
  it('formats short and weekday dates', () => {
    expect(formatShortDate('2026-11-10')).toBe('10.11.');
    expect(formatWeekdayDate('2026-11-15')).toBe('So, 15.11.');
    expect(formatWeekdayLongDate('2026-11-10')).toBe('Di, 10.11.2026');
  });

  it('describes days until a date', () => {
    expect(formatDaysUntil(0)).toBe('heute');
    expect(formatDaysUntil(1)).toBe('morgen');
    expect(formatDaysUntil(5)).toBe('in 5 Tagen');
  });
});

describe('formatPercent', () => {
  it('adds sign and German decimal comma', () => {
    expect(formatPercent(20)).toBe('+20 %');
    expect(formatPercent(-5.5)).toBe('−5,5 %');
    expect(formatPercent(0)).toBe('0 %');
  });
});

describe('amount input', () => {
  it.each([
    ['9,99', 999],
    ['9,9', 990],
    ['9', 900],
    ['9,', 900],
    ['1.234,56', 123456],
    ['1234,56', 123456],
    [' 13,99 € ', 1399],
    ['0,01', 1],
  ])('parses %s as %d cents', (input, cents) => {
    expect(parseEuroInput(input)).toBe(cents);
  });

  it.each(['', 'abc', '9,999', '9.99', '1.23,00', '-5', ','])('rejects %p', (input) => {
    expect(parseEuroInput(input)).toBeNull();
  });

  it('formats cents for editing', () => {
    expect(centsToInput(999)).toBe('9,99');
    expect(centsToInput(123456)).toBe('1234,56');
  });
});

describe('date input', () => {
  it('converts German dates to ISO', () => {
    expect(parseGermanDate('05.11.2026')).toBe('2026-11-05');
    expect(parseGermanDate('5.1.26')).toBe('2026-01-05');
    expect(parseGermanDate('29.02.2028')).toBe('2028-02-29');
  });

  it('rejects impossible dates', () => {
    expect(parseGermanDate('29.02.2026')).toBeNull();
    expect(parseGermanDate('31.04.2026')).toBeNull();
    expect(parseGermanDate('2026-11-05')).toBeNull();
  });

  it('formats local dates as ISO', () => {
    expect(toIsoDate(new Date(2026, 2, 29))).toBe('2026-03-29');
  });
});

describe('month helpers', () => {
  it('formats months', () => {
    expect(formatMonthYear('2026-11')).toBe('November 2026');
    expect(formatMonthShort('2027-03')).toBe('Mär');
    expect(formatMonthShort('2027-03', true)).toBe('Mär 2027');
    expect(formatMonthName('2026-01')).toBe('Januar');
  });

  it('shifts months across year boundaries', () => {
    expect(shiftMonth('2026-12', 1)).toBe('2027-01');
    expect(shiftMonth('2026-01', -1)).toBe('2025-12');
    expect(shiftMonth('2026-11', 0)).toBe('2026-11');
    expect(shiftMonth('2026-11', -13)).toBe('2025-10');
  });

  it('knows the current month and Monday-first weekdays', () => {
    expect(currentMonth(new Date(2026, 10, 30))).toBe('2026-11');
    expect(mondayIndex('2026-11-02')).toBe(0); // Monday
    expect(mondayIndex('2026-11-01')).toBe(6); // Sunday
    expect(formatWeekdayDayMonth('2026-11-12')).toBe('Donnerstag, 12.11.');
  });
});

describe('formatEuroRounded', () => {
  it('rounds to whole euros', () => {
    expect(formatEuroRounded(14388)).toBe('144 €');
    expect(formatEuroRounded(2999)).toBe('30 €');
    expect(formatEuroRounded(123456)).toBe('1.235 €');
  });
});
