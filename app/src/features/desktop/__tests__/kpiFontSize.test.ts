import { KPI_MAX_FONT, KPI_MIN_FONT, kpiFontSize } from '../kpiFontSize';

describe('kpiFontSize', () => {
  it('keeps the full size when the value fits', () => {
    expect(kpiFontSize('285,09 €', 250)).toBe(KPI_MAX_FONT);
    expect(kpiFontSize('12', 100)).toBe(KPI_MAX_FONT);
  });

  it('shrinks long amounts in narrow tiles so they stay on one line', () => {
    // "3.421,12 €" in a ~170 px tile (four tiles at 1024 px window width)
    const size = kpiFontSize('3.421,12 €', 170);
    expect(size).toBeLessThan(KPI_MAX_FONT);
    expect(size * 0.62 * '3.421,12 €'.length).toBeLessThanOrEqual(170);
  });

  it('never goes below the minimum size', () => {
    expect(kpiFontSize('123.456.789,00 €', 80)).toBe(KPI_MIN_FONT);
  });

  it('uses the full size before the tile has been measured', () => {
    expect(kpiFontSize('3.421,12 €', 0)).toBe(KPI_MAX_FONT);
  });
});
