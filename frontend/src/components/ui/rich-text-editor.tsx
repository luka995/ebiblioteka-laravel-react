import { useEffect, useRef, useState } from 'react'
import { EditorContent, useEditor, type Editor } from '@tiptap/react'
import StarterKit from '@tiptap/starter-kit'
import Image from '@tiptap/extension-image'
import {
  Bold,
  Heading2,
  Heading3,
  ImagePlus,
  Italic,
  Link as LinkIcon,
  List,
  ListOrdered,
  Quote,
  Redo2,
  Strikethrough,
  Undo2,
  Unlink,
} from 'lucide-react'
import { toast } from 'sonner'
import { api, apiPaths, ApiError } from '@/lib/api'
import { laravelBaseUrl, storageUrl } from '@/lib/environment'
import { Button } from '@/components/ui/button'
import { cn } from '@/lib/utils'

interface RichTextEditorProps {
  value: string
  onChange: (html: string) => void
  disabled?: boolean
  placeholder?: string
}

function escapeRe(value: string): string {
  return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
}

/**
 * Admin (drugi origin) mora da prikaže slike sa apsolutnim URL-om, dok se u bazi
 * čuva root-relative putanja (/storage/...) koja radi na javnom sajtu.
 */
function toStoredHtml(html: string): string {
  const base = laravelBaseUrl()
  return html.replace(new RegExp(`src="${escapeRe(base)}/storage/`, 'g'), 'src="/storage/')
}

function toEditorHtml(html: string): string {
  const base = laravelBaseUrl()
  return html.replace(/src="\/storage\//g, `src="${base}/storage/`)
}

export function RichTextEditor({ value, onChange, disabled, placeholder }: RichTextEditorProps) {
  const editorRef = useRef<Editor | null>(null)
  const fileInputRef = useRef<HTMLInputElement>(null)
  const [uploading, setUploading] = useState(false)

  const uploadImages = async (files: File[]): Promise<void> => {
    const editor = editorRef.current
    if (!editor || files.length === 0) return

    setUploading(true)
    try {
      for (const file of files) {
        const result = await api.upload<{ path: string; url: string }>(
          apiPaths.newsUploadImage,
          file,
          { directory: 'news/body' },
        )
        const src = storageUrl(result.url)
        if (src) {
          editor.chain().focus().setImage({ src, alt: file.name || '' }).run()
        }
      }
    } catch (error) {
      if (error instanceof ApiError) {
        toast.error(error.messageText ?? 'Otpremanje slike nije uspelo.')
      } else {
        toast.error('Otpremanje slike nije uspelo.')
      }
    } finally {
      setUploading(false)
    }
  }

  const editor = useEditor({
    extensions: [StarterKit.configure({ link: { openOnClick: false } }), Image],
    content: toEditorHtml(value),
    editable: !disabled,
    onUpdate: ({ editor }) => onChange(toStoredHtml(editor.getHTML())),
    editorProps: {
      attributes: {
        class: cn(
          'prose prose-sm min-h-[220px] max-w-none px-3 py-2 focus:outline-none',
          'text-sm text-foreground',
        ),
      },
      handlePaste: (_view, event) => {
        const files = Array.from(event.clipboardData?.items ?? [])
          .filter((item) => item.type.startsWith('image/'))
          .map((item) => item.getAsFile())
          .filter((file): file is File => file !== null)

        if (files.length === 0) return false

        event.preventDefault()
        void uploadImages(files)
        return true
      },
      handleDrop: (_view, event) => {
        const files = Array.from(event.dataTransfer?.files ?? []).filter((file) =>
          file.type.startsWith('image/'),
        )

        if (files.length === 0) return false

        event.preventDefault()
        void uploadImages(files)
        return true
      },
    },
  })

  editorRef.current = editor

  useEffect(() => {
    if (!editor) return
    if (toStoredHtml(editor.getHTML()) !== value) {
      editor.commands.setContent(toEditorHtml(value || ''))
    }
  }, [value, editor])

  if (!editor) return null

  const setLink = () => {
    const previousUrl = editor.getAttributes('link').href as string | undefined
    const url = window.prompt(placeholder ?? 'URL', previousUrl ?? 'https://')
    if (url === null) return
    if (url === '') {
      editor.chain().focus().extendMarkRange('link').unsetLink().run()
      return
    }
    editor.chain().focus().extendMarkRange('link').setLink({ href: url }).run()
  }

  const buttons = [
    { label: 'Bold', icon: Bold, onClick: () => editor.chain().focus().toggleBold().run(), active: editor.isActive('bold') },
    { label: 'Italic', icon: Italic, onClick: () => editor.chain().focus().toggleItalic().run(), active: editor.isActive('italic') },
    { label: 'Strike', icon: Strikethrough, onClick: () => editor.chain().focus().toggleStrike().run(), active: editor.isActive('strike') },
    { label: 'H2', icon: Heading2, onClick: () => editor.chain().focus().toggleHeading({ level: 2 }).run(), active: editor.isActive('heading', { level: 2 }) },
    { label: 'H3', icon: Heading3, onClick: () => editor.chain().focus().toggleHeading({ level: 3 }).run(), active: editor.isActive('heading', { level: 3 }) },
    { label: 'Bullet list', icon: List, onClick: () => editor.chain().focus().toggleBulletList().run(), active: editor.isActive('bulletList') },
    { label: 'Ordered list', icon: ListOrdered, onClick: () => editor.chain().focus().toggleOrderedList().run(), active: editor.isActive('orderedList') },
    { label: 'Quote', icon: Quote, onClick: () => editor.chain().focus().toggleBlockquote().run(), active: editor.isActive('blockquote') },
    { label: 'Link', icon: LinkIcon, onClick: setLink, active: editor.isActive('link') },
    { label: 'Unlink', icon: Unlink, onClick: () => editor.chain().focus().unsetLink().run() },
    { label: 'Undo', icon: Undo2, onClick: () => editor.chain().focus().undo().run() },
    { label: 'Redo', icon: Redo2, onClick: () => editor.chain().focus().redo().run() },
  ]

  return (
    <div className="rounded-md border border-input focus-within:border-ring focus-within:ring-[3px] focus-within:ring-ring/50">
      <div className="flex flex-wrap items-center gap-1 border-b border-input px-2 py-1.5">
        {buttons.map((button) => (
          <Button
            key={button.label}
            type="button"
            variant={button.active ? 'secondary' : 'ghost'}
            size="icon-sm"
            aria-label={button.label}
            onClick={button.onClick}
          >
            <button.icon />
          </Button>
        ))}
        <Button
          type="button"
          variant="ghost"
          size="icon-sm"
          aria-label="Insert image"
          disabled={uploading}
          onClick={() => fileInputRef.current?.click()}
        >
          <ImagePlus />
        </Button>
        <input
          ref={fileInputRef}
          type="file"
          accept="image/*"
          multiple
          className="hidden"
          onChange={(event) => {
            void uploadImages(Array.from(event.target.files ?? []))
            event.target.value = ''
          }}
        />
      </div>
      <EditorContent editor={editor} />
    </div>
  )
}
