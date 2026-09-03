import { Navigate, Route, Routes } from 'react-router-dom'
import { AppShell } from '@/components/layout/app-shell'
import { GuestOnly, RequireAuth } from '@/components/auth/guards'
import { AuthLayout } from '@/pages/auth/auth-layout'
import { LoginPage } from '@/pages/auth/login-page'
import { ForgotPasswordPage } from '@/pages/auth/forgot-password-page'
import { ResetPasswordPage } from '@/pages/auth/reset-password-page'
import { DashboardPage } from '@/pages/dashboard-page'
import { UsersPage } from '@/pages/users/users-page'
import { LibrariesPage } from '@/pages/libraries/libraries-page'

function App() {
  return (
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
        <Route path="/users" element={<UsersPage />} />
        <Route path="/libraries" element={<LibrariesPage />} />
      </Route>

      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  )
}

export default App
