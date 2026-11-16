import { Navigate, Route, Routes } from 'react-router-dom'
import { Toaster } from '@/components/ui/sonner'
import { AppShell } from '@/components/layout/app-shell'
import { GuestOnly, RequireAuth } from '@/components/auth/guards'
import { AuthLayout } from '@/pages/auth/auth-layout'
import { LoginPage } from '@/pages/auth/login-page'
import { ForgotPasswordPage } from '@/pages/auth/forgot-password-page'
import { ResetPasswordPage } from '@/pages/auth/reset-password-page'
import { DashboardPage } from '@/pages/dashboard-page'
import { UsersPage } from '@/pages/users/users-page'
import { UserDetailPage } from '@/pages/users/user-detail-page'
import { LibrariesPage } from '@/pages/libraries/libraries-page'
import { LibraryDetailPage } from '@/pages/libraries/library-detail-page'
import { RegionsPage } from '@/pages/regions/regions-page'
import { PlacesPage } from '@/pages/places/places-page'
import { TagsPage } from '@/pages/tags/tags-page'
import { CategoriesPage } from '@/pages/categories/categories-page'
import { AuthorsPage } from '@/pages/authors/authors-page'
import { BooksLandingPage } from '@/pages/books/books-landing-page'
import { BooksPage } from '@/pages/books/books-page'
import { BookDetailPage } from '@/pages/books/book-detail-page'
import { BookCopiesPage } from '@/pages/books/book-copies-page'
import { BookCopyDetailPage } from '@/pages/books/book-copy-detail-page'
import { BooksArchivePage } from '@/pages/books/books-archive-page'
import { BooksInventoryBooksPage } from '@/pages/books/books-inventory-books-page'
import { BooksBarcodePrintPage } from '@/pages/books/books-barcode-print-page'
import { LibraryBorrowsPage } from '@/pages/borrows/library-borrows-page'
import { MyBorrowsPage } from '@/pages/borrows/my-borrows-page'
import { CatalogBookPage } from '@/pages/catalog/catalog-book-page'
import { MyReservationsPage } from '@/pages/reservations/my-reservations-page'
import { LibraryReservationsPage } from '@/pages/reservations/library-reservations-page'
import { NewsPage } from '@/pages/news/news-page'
import { LibraryRequestsPage } from '@/pages/library-requests/library-requests-page'
import { ProfilePage } from '@/pages/profile/profile-page'
import { SettingsPage } from '@/pages/settings/settings-page'
import { SystemSettingsPage } from '@/pages/settings/system-settings-page'

function App() {
  return (
    <>
      <Routes>
        <Route
          element={
            <GuestOnly>
              <AuthLayout />
            </GuestOnly>
          }
        >
          <Route path="/login" element={<LoginPage />} />
          <Route path="/forgot-password" element={<ForgotPasswordPage />} />
          <Route path="/reset-password" element={<ResetPasswordPage />} />
        </Route>

        <Route
          element={
            <RequireAuth>
              <AppShell />
            </RequireAuth>
          }
        >
          <Route path="/" element={<DashboardPage />} />
          <Route path="/reservations" element={<MyReservationsPage />} />
          <Route path="/reservations/library" element={<LibraryReservationsPage />} />
          <Route path="/borrows" element={<MyBorrowsPage />} />
          <Route path="/borrows/library" element={<LibraryBorrowsPage />} />
          <Route path="/catalog/books/:id" element={<CatalogBookPage />} />
          <Route path="/users" element={<UsersPage />} />
          <Route path="/users/:id" element={<UserDetailPage />} />
          <Route path="/libraries" element={<LibrariesPage />} />
          <Route path="/libraries/:id" element={<LibraryDetailPage />} />
          <Route path="/regions" element={<RegionsPage />} />
          <Route path="/places" element={<PlacesPage />} />
          <Route path="/tags" element={<TagsPage />} />
          <Route path="/categories" element={<CategoriesPage />} />
          <Route path="/authors" element={<AuthorsPage />} />
          <Route path="/books" element={<BooksLandingPage />} />
          <Route path="/books/titles" element={<BooksPage />} />
          <Route path="/books/titles/:id" element={<BookDetailPage />} />
          <Route path="/books/copies" element={<BookCopiesPage />} />
          <Route path="/books/copies/:id" element={<BookCopyDetailPage />} />
          <Route path="/books/archive" element={<BooksArchivePage />} />
          <Route path="/books/inventory-books" element={<BooksInventoryBooksPage />} />
          <Route path="/books/barcode-print" element={<BooksBarcodePrintPage />} />
          <Route path="/news" element={<NewsPage />} />
          <Route path="/library-requests" element={<LibraryRequestsPage />} />
          <Route path="/profile" element={<ProfilePage />} />
          <Route path="/settings" element={<SettingsPage />} />
          <Route path="/settings/system" element={<SystemSettingsPage />} />
        </Route>

        <Route path="*" element={<Navigate to="/" replace />} />
      </Routes>
      <Toaster position="top-right" richColors closeButton />
    </>
  )
}

export default App
