export const BOOK_TITLE_MAX_LENGTH = 80

/**
 * Skracuje naziv knjige za prikaz u tabelama, karticama i formama.
 * Pun naziv ostaje dostupan kroz title atribut na elementu.
 */
export function truncateBookTitle(
  title: string | null | undefined,
  maxLength = BOOK_TITLE_MAX_LENGTH,
): string {
  if (!title) return '—'
  if (title.length <= maxLength) return title

  return `${title.slice(0, maxLength).trimEnd()}...`
}
