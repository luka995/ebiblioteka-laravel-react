import { useState, type FormEvent } from 'react'
import { useTranslation } from 'react-i18next'
import { Upload } from 'lucide-react'
import { toast } from 'sonner'
import { api, apiPaths, ApiError } from '@/lib/api'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import type { BookImport } from '@/types'

interface BookImportUploadDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  onUploaded: (importId: number) => void
}

function getErrorMessage(error: unknown, fallback: string): string {
  if (!(error instanceof ApiError)) return fallback
  const validationMessage = Object.values(error.validationErrors ?? {}).flat()[0]
  return error.messageText ?? validationMessage ?? fallback
}

export function BookImportUploadDialog({
  open,
  onOpenChange,
  onUploaded,
}: BookImportUploadDialogProps) {
  const { t } = useTranslation()
  const [file, setFile] = useState<File | null>(null)
  const [uploading, setUploading] = useState(false)

  const changeOpen = (nextOpen: boolean) => {
    if (!nextOpen && uploading) return
    if (!nextOpen) setFile(null)
    onOpenChange(nextOpen)
  }

  const submitUpload = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    if (!file) return

    if (!file.name.toLowerCase().endsWith('.xlsx')) {
      toast.error(t('bookImports.upload.xlsxOnly'))
      return
    }

    setUploading(true)
    try {
      const response = await api.uploadFile<{ data: BookImport }>(apiPaths.bookImports, file)
      toast.success(t('bookImports.upload.success'))
      setFile(null)
      onOpenChange(false)
      onUploaded(response.data.id)
    } catch (error) {
      toast.error(getErrorMessage(error, t('errors.unexpected')))
    } finally {
      setUploading(false)
    }
  }

  return (
    <Dialog open={open} onOpenChange={changeOpen}>
      <DialogContent>
        <form onSubmit={submitUpload} className="space-y-5">
          <DialogHeader>
            <DialogTitle>{t('bookImports.upload.title')}</DialogTitle>
            <DialogDescription>{t('bookImports.upload.description')}</DialogDescription>
          </DialogHeader>
          <div className="space-y-2">
            <label htmlFor="book-import-file" className="text-sm font-medium">
              {t('bookImports.upload.fileLabel')}
            </label>
            <Input
              id="book-import-file"
              type="file"
              accept=".xlsx"
              onChange={(event) => setFile(event.target.files?.[0] ?? null)}
              required
            />
            {file ? <p className="text-xs text-muted-foreground">{file.name}</p> : null}
          </div>
          <div className="rounded-md bg-muted p-3 text-sm text-muted-foreground">
            <p>{t('bookImports.upload.inventoryGuidance')}</p>
            <p className="mt-2">{t('bookImports.upload.processingGuidance')}</p>
          </div>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => changeOpen(false)} disabled={uploading}>
              {t('common.cancel')}
            </Button>
            <Button type="submit" variant="brand" disabled={!file || uploading}>
              <Upload />
              {uploading ? t('bookImports.upload.uploading') : t('bookImports.upload.submit')}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
