import { useEffect, useState } from 'react';
import { Outlet } from 'react-router-dom';
import { UserProvider } from '../contexts/UserContext';
import { eventBus } from '../lib/eventBus';
import { ErrorBoundary } from './ErrorBoundary';
import { Navbar } from './Navbar';
import { Sidebar } from './Sidebar';
import { UploadModal } from './UploadModal';

export function Shell() {
  const [uploadOpen, setUploadOpen] = useState(false);

  useEffect(() => {
    const unsubscribe = eventBus.on('upload-modal:open', () => setUploadOpen(true));
    return unsubscribe;
  }, []);

  return (
    <UserProvider>
      <div className="flex h-screen flex-col bg-surface">
        <Navbar />
        <div className="flex flex-1 overflow-hidden">
          <Sidebar />
          <main className="flex-1 overflow-y-auto p-6">
            <ErrorBoundary>
              <Outlet />
            </ErrorBoundary>
          </main>
        </div>
        <UploadModal isOpen={uploadOpen} onClose={() => setUploadOpen(false)} />
      </div>
    </UserProvider>
  );
}
