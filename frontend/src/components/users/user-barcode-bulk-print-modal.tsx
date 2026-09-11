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
} from '@/components/users/barcode-print-format-picker'

interface UserBarcodeBulkPrintModalProps {
  open: boolean
  onClose: () => void
  userIds: number[]
}

export function UserBarcodeBulkPrintModal({ open, onClose, userIds }: UserBarcodeBulkPrintModalProps) {
  const { t } = useTranslation()
  const [format, setFormat] = useState<BarcodePrintFormat>('a4')
  const [busy, setBusy] = useState(false)

  const run = async () => {
    setBusy(true)
    try {
      const blob = await api.download(apiPaths.usersBulkBarcodePrint, {
        method: 'POST',
        body: { user_ids: userIds, format },
      })
      downloadBlob(blob, 'barkodovi.pdf')
      toast.success(t('users.bulkPrintBarcodeSuccess'))
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
          <DialogTitle className="font-brand-heading text-xl">{t('users.bulkPrintBarcodeTitle')}</DialogTitle>
          <DialogDescription>
            {t('users.bulkPrintBarcodeDescription', { count: userIds.length })}
          </DialogDescription>
        </DialogHeader>

        <BarcodePrintFormatPicker value={format} onChange={setFormat} name="barcode-bulk-print-format" />

        <DialogFooter>
          <Button variant="outline" disabled={busy} onClick={onClose}>
            {t('common.cancel')}
          </Button>
          <Button variant="brand" disabled={busy} onClick={() => void run()}>
            {t('users.printBarcodeSubmit')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
