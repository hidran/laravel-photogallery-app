import { Suspense, lazy } from 'react';
import { QueryClientProvider } from '@tanstack/react-query';
import { BrowserRouter, Routes, Route } from 'react-router-dom';
import { Toaster } from 'sonner';
import { queryClient } from './lib/queryClient';
import { copy } from './data/copy';
import { Shell } from './components/Shell';
import { RequireAuth } from './components/RequireAuth';
import { AuthEventListener } from './components/AuthEventListener';

const GalleryPage = lazy(() => import('./pages/GalleryPage'));
const FavoritesPage = lazy(() => import('./pages/FavoritesPage'));
const AlbumPage = lazy(() => import('./pages/AlbumPage'));
const LoginPage = lazy(() => import('./pages/LoginPage'));
const NotFoundPage = lazy(() => import('./pages/NotFoundPage'));

export function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <BrowserRouter>
        <AuthEventListener />
        <Suspense fallback={<div>{copy.gallery.loading}</div>}>
          <Routes>
            <Route
              element={
                <RequireAuth>
                  <Shell />
                </RequireAuth>
              }
            >
              <Route path="/" element={<GalleryPage />} />
              <Route path="/favorites" element={<FavoritesPage />} />
              <Route path="/albums/:albumId" element={<AlbumPage />} />
            </Route>
            <Route path="/login" element={<LoginPage />} />
            <Route path="/register" element={<LoginPage />} />
            <Route path="*" element={<NotFoundPage />} />
          </Routes>
        </Suspense>
        <Toaster />
      </BrowserRouter>
    </QueryClientProvider>
  );
}

export default App;
