/**
 * Izvor ISBN metapodataka se prikazuje korisnicima bez oznake NBS kataloga.
 * Rezultat iz NBS-a tretiramo kao rezultat iz baze.
 */
export function normalizeIsbnSource(source: string | null | undefined): string | null {
  if (!source) return null

  return source === 'nbs' ? 'database' : source
}
