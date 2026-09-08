import * as React from "react"

import { cn } from "@/lib/utils"

function List({ className, ...props }: React.ComponentProps<"ul">) {
  return (
    <ul
      data-slot="list"
      className={cn("divide-y", className)}
      {...props}
    />
  )
}

function ListItem({ className, ...props }: React.ComponentProps<"li">) {
  return (
    <li
      data-slot="list-item"
      className={cn("flex flex-col gap-3 px-4 py-3", className)}
      {...props}
    />
  )
}

function ListItemHeader({ className, ...props }: React.ComponentProps<"div">) {
  return (
    <div
      data-slot="list-item-header"
      className={cn("flex items-center gap-2 [&:has([data-slot=list-item-title])]:min-w-0", className)}
      {...props}
    />
  )
}

function ListItemTitle({ className, ...props }: React.ComponentProps<"div">) {
  return (
    <div
      data-slot="list-item-title"
      className={cn("min-w-0 flex-1 leading-none font-medium", className)}
      {...props}
    />
  )
}

function ListItemMeta({ className, ...props }: React.ComponentProps<"dl">) {
  return (
    <dl
      data-slot="list-item-meta"
      className={cn("flex min-w-0 flex-col gap-1.5 text-sm", className)}
      {...props}
    />
  )
}

function ListItemField({
  label,
  value,
  className,
  ...props
}: React.ComponentProps<"div"> & { label: React.ReactNode; value?: React.ReactNode }) {
  return (
    <div data-slot="list-item-field" className={cn("flex items-baseline gap-2", className)} {...props}>
      <dt className="shrink-0 text-muted-foreground">{label}</dt>
      <dd className="min-w-0 truncate">{value ?? "—"}</dd>
    </div>
  )
}

export {
  List,
  ListItem,
  ListItemHeader,
  ListItemTitle,
  ListItemMeta,
  ListItemField,
}
