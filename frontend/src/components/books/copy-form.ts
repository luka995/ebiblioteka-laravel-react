export interface CopyFormValues {
  copies: string
  orderNumber: string
  isbn: string
  publisher: string
  publishPlace: string
  publishYear: string
  issueNumber: string
  numOfPages: string
  dimension: string
  part: string
  udk: string
  binding: string
  origin: string
  bookNumber: string
  placeOnShelf: string
  price: string
  dateAdd: string
  notice: string
}

export const EMPTY_COPY: CopyFormValues = {
  copies: '1',
  orderNumber: '',
  isbn: '',
  publisher: '',
  publishPlace: '',
  publishYear: '',
  issueNumber: '',
  numOfPages: '',
  dimension: '',
  part: '',
  udk: '',
  binding: '',
  origin: '',
  bookNumber: '',
  placeOnShelf: '',
  price: '0',
  dateAdd: '',
  notice: '',
}

/**
 * Autori se razdvajaju samo tackom-zarezom (ili novim redom); zarez je deo
 * formata imena "Prezime, Ime" i ne sme biti separator.
 */
export function splitAuthors(value: string): string[] {
  return value
    .split(/[;\n]+/)
    .map((name) => name.trim())
    .filter(Boolean)
}

export function copyMetadataPayload(copy: CopyFormValues): Record<string, unknown> {
  return {
    isbn: copy.isbn.trim() || null,
    publisher: copy.publisher.trim() || null,
    publish_place: copy.publishPlace.trim() || null,
    publish_year: copy.publishYear.trim() || null,
    issue_number: copy.issueNumber.trim() || null,
    num_of_pages: copy.numOfPages === '' ? null : Number(copy.numOfPages),
    dimension: copy.dimension.trim() || null,
    part: copy.part.trim() || null,
    udk: copy.udk.trim() || null,
    binding: copy.binding || null,
    origin: copy.origin || null,
    book_number: copy.bookNumber.trim() || null,
    place_on_shelf: copy.placeOnShelf.trim() || null,
    price: copy.price === '' ? 0 : Number(copy.price),
    date_add: copy.dateAdd || null,
    notice: copy.notice.trim() || null,
  }
}

export function copyQuantityPayload(copy: CopyFormValues, invNumberAuto: boolean): Record<string, unknown> {
  return invNumberAuto
    ? { copies: Math.max(1, Number(copy.copies) || 1) }
    : { order_number: copy.orderNumber.trim() }
}
