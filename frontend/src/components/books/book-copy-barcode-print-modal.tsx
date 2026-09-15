import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { api, apiPaths, ApiError, downloadBlob } from '@/lib/api'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import {
  BarcodePrintFormatPicker,
  type BarcodePrintFormat,
} from '@/components/barcode/barcode-print-format-picker'

interface BookCopyBarcodePrintModalProps {
  open: boolean
  onClose: () => void
  copyIds: number[]
  onSuccess?: () => void
}

export function BookCopyBarcodePrintModal({
  open,
  onClose,
  copyIds,
  onSuccess,
}: BookCopyBarcodePrintModalProps) {
  const { t } = useTranslation()
  const [format, setFormat] = useState<BarcodePrintFormat>(copyIds.length > 1 ? 'a4' : 'label')
  const [busy, setBusy] = useState(false)

  const run = async () => {
    setBusy(true)
    try {
      const blob = await api.download(apiPaths.bookCopiesBulkPrint, {
        method: 'POST',
        body: { ids: copyIds, format },
      })
      downloadBlob(blob, 'barkodovi-knjiga.pdf')
      toast.success(t('books.copies.printSuccess'))
      onSuccess?.()
      onClose()
    } catch (error) {
      if (error instanceof ApiError) {
        toast.error(error.messageText ?? t('errors.unexpected'))
      }
    } finally {
      setBusy(false)
    }
  }

  return (
    <Dialog open={open} onOpenChange={(value) => !value && !busy && onClose()}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle className="font-brand-heading text-xl">{t('books.copies.printTitle')}</DialogTitle>
          <DialogDescription>
            {copyIds.length > 1
              ? t('books.copies.printDescriptionBulk', { count: copyIds.length })
              : t('books.copies.printDescriptionSingle')}
          </DialogDescription>
        </DialogHeader>

        <BarcodePrintFormatPicker
          value={format}
          onChange={setFormat}
          name="book-copy-barcode-print-format"
        />

        <DialogFooter>
          <Button variant="outline" disabled={busy} onClick={onClose}>
            {t('common.cancel')}
          </Button>
          <Button variant="brand" disabled={busy || copyIds.length === 0} onClick={() => void run()}>
            {t('books.copies.printSubmit')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
