export function normalizeBarcode(value: string): string {
  const trimmed = value.trim()

  return /^\d{12}$/.test(trimmed) ? `0${trimmed}` : trimmed
}
