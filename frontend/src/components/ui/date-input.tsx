import * as React from "react"

import { Input } from "@/components/ui/input"

const ISO_PATTERN = /^(\d{4})-(\d{2})-(\d{2})/

function isoToDisplay(iso: string | null | undefined): string {
  if (!iso) return ""
  const match = ISO_PATTERN.exec(iso)
  if (!match) return ""
  return `${match[3]}.${match[2]}.${match[1]}`
}

function onlyDigits(value: string): string {
  return value.replace(/\D/g, "").slice(0, 8)
}

function mask(digits: string): string {
  return [digits.slice(0, 2), digits.slice(2, 4), digits.slice(4, 8)].filter(Boolean).join(".")
}

function toIso(display: string): string | null {
  const digits = onlyDigits(display)
  if (digits.length !== 8) return null

  const day = Number(digits.slice(0, 2))
  const month = Number(digits.slice(2, 4))
  const year = Number(digits.slice(4, 8))
  const date = new Date(year, month - 1, day)

  if (date.getFullYear() !== year || date.getMonth() !== month - 1 || date.getDate() !== day) {
    return null
  }

  const pad = (value: number) => String(value).padStart(2, "0")
  return `${year}-${pad(month)}-${pad(day)}`
}

export interface DateInputProps
  extends Omit<React.ComponentProps<typeof Input>, "value" | "onChange" | "type"> {
  value: string | null | undefined
  onChange: (value: string) => void
}

export function DateInput({ value, onChange, onBlur, placeholder, ...props }: DateInputProps) {
  const [display, setDisplay] = React.useState(() => isoToDisplay(value))

  React.useEffect(() => {
    setDisplay(isoToDisplay(value))
  }, [value])

  const handleChange = (event: React.ChangeEvent<HTMLInputElement>) => {
    const pasted = /^(\d{4})-(\d{2})-(\d{2})$/.exec(event.target.value.trim())
    if (pasted) {
      const iso = `${pasted[1]}-${pasted[2]}-${pasted[3]}`
      setDisplay(isoToDisplay(iso))
      onChange(iso)
      return
    }

    const digits = onlyDigits(event.target.value)
    const nextDisplay = mask(digits)
    setDisplay(nextDisplay)

    const iso = toIso(nextDisplay)
    if (iso) {
      onChange(iso)
    } else if (digits.length === 0) {
      onChange("")
    }
  }

  const handleBlur = (event: React.FocusEvent<HTMLInputElement>) => {
    const iso = toIso(display)
    if (iso) {
      setDisplay(isoToDisplay(iso))
      if (iso !== value) onChange(iso)
    } else {
      setDisplay(isoToDisplay(value))
    }
    onBlur?.(event)
  }

  return (
    <Input
      type="text"
      inputMode="numeric"
      autoComplete="off"
      placeholder={placeholder ?? "d.m.Y"}
      value={display}
      onChange={handleChange}
      onBlur={handleBlur}
      {...props}
    />
  )
}
