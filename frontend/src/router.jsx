import { createBrowserRouter } from 'react-router-dom'
import App from './App'
import HomePage from './pages/HomePage'
import MenuPage from './pages/MenuPage'
import LoginPage from './pages/LoginPage'
import SignupPage from './pages/SignupPage'
import ChangePasswordPage from './pages/ChangePasswordPage'
import NotFoundPage from './pages/NotFoundPage'
import AdminMenuPage from './pages/admin/AdminMenuPage'
import AdminMenuNewPage from './pages/admin/AdminMenuNewPage'
import AdminMenuEditPage from './pages/admin/AdminMenuEditPage'
import AdminBookingsPage from './pages/admin/AdminBookingsPage'
import AdminBookingDetailPage from './pages/admin/AdminBookingDetailPage'

export const router = createBrowserRouter([
  {
    path: '/',
    element: <App />,
    children: [
      { index: true, element: <HomePage /> },
      { path: 'menu', element: <MenuPage /> },
      { path: 'admin/menu', element: <AdminMenuPage /> },
      { path: 'admin/menu/new', element: <AdminMenuNewPage /> },
      { path: 'admin/menu/:id/edit', element: <AdminMenuEditPage /> },
      { path: 'admin/bookings', element: <AdminBookingsPage /> },
      { path: 'admin/bookings/:id', element: <AdminBookingDetailPage /> },
      { path: 'login', element: <LoginPage /> },
      { path: 'signup', element: <SignupPage /> },
      { path: 'change-password', element: <ChangePasswordPage /> },
      { path: '*', element: <NotFoundPage /> },
    ],
  },
])
