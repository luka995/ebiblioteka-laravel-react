import { Link } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import type { InventoryDiscrepancy } from '@/types'

interface InventoryDiscrepancyDialogProps {
  discrepancy: InventoryDiscrepancy | null
  open: boolean
  onClose: () => void
}

export function InventoryDiscrepancyDialog({ discrepancy, open, onClose }: InventoryDiscrepancyDialogProps) {
  const { t } = useTranslation()

  return (
    <Dialog open={open} onOpenChange={(value) => !value && onClose()}>
      <DialogContent className="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle className="font-brand-heading text-xl">{t('books.reconciliation.title')}</DialogTitle>
          <DialogDescription>{t('books.reconciliation.description')}</DialogDescription>
        </DialogHeader>

        {discrepancy ? (
          <div className="space-y-3 text-sm">
            <div className="grid grid-cols-3 gap-2 rounded-lg border bg-muted/40 p-3 text-center">
              <div>
                <p className="text-xs text-muted-foreground">{t('books.reconciliation.nextAuto')}</p>
                <p className="font-semibold">{discrepancy.next_auto}</p>
              </div>
              <div>
                <p className="text-xs text-muted-foreground">{t('books.reconciliation.maxExisting')}</p>
                <p className="font-semibold">{discrepancy.max_existing}</p>
              </div>
              <div>
                <p className="text-xs text-muted-foreground">{t('books.reconciliation.maxUsed')}</p>
                <p className="font-semibold">{discrepancy.max_used}</p>
              </div>
            </div>

            {discrepancy.archived_copies.length ? (
              <div>
                <p className="font-medium">{t('books.reconciliation.archivedTitle')}</p>
                <ul className="mt-1 space-y-0.5 text-muted-foreground">
                  {discrepancy.archived_copies.map((copy) => (
                    <li key={copy.id}>
                      {t('books.reconciliation.archivedItem', {
                        number: copy.order_number ?? '—',
                        book: copy.book_name ?? '',
                      })}
                    </li>
                  ))}
                </ul>
              </div>
            ) : null}
          </div>
        ) : null}

        <DialogFooter>
          <Button variant="outline" onClick={onClose}>
            {t('common.close')}
          </Button>
          <Button asChild variant="brand">
            <Link to="/books/archive" onClick={onClose}>
              {t('books.reconciliation.openArchive')}
            </Link>
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
