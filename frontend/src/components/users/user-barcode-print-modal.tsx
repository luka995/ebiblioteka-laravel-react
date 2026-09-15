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
import type { User } from '@/types'

interface UserBarcodePrintModalProps {
  open: boolean
  onClose: () => void
  user: User
}

export function UserBarcodePrintModal({ open, onClose, user }: UserBarcodePrintModalProps) {
  const { t } = useTranslation()
  const [format, setFormat] = useState<BarcodePrintFormat>('label')
  const [busy, setBusy] = useState(false)

  const run = async () => {
    setBusy(true)
    try {
      const blob = await api.download(apiPaths.userBarcodePrint(user.id, format))
      downloadBlob(blob, `barkod-${user.bar_code ?? user.id}.pdf`)
      toast.success(t('users.printBarcodeSuccess'))
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
          <DialogTitle className="font-brand-heading text-xl">{t('users.printBarcodeTitle')}</DialogTitle>
          <DialogDescription>{t('users.printBarcodeDescription')}</DialogDescription>
        </DialogHeader>

        <BarcodePrintFormatPicker value={format} onChange={setFormat} />

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
