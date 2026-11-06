/** Space Grotesk digits are about 0.6 em wide; a little extra keeps the € off the edge. */
const KPI_CHAR_EM = 0.62;
export const KPI_MAX_FONT = 34;
export const KPI_MIN_FONT = 22;

/**
 * Largest font size (within bounds) at which `value` fits on one line into `width` px.
 * Used for the desktop key figures, whose amounts must never wrap or be cut off.
 */
export function kpiFontSize(value: string, width: number): number {
  if (width <= 0 || value.length === 0) return KPI_MAX_FONT;
  const fit = Math.floor(width / (value.length * KPI_CHAR_EM));
  return Math.max(KPI_MIN_FONT, Math.min(KPI_MAX_FONT, fit));
}
