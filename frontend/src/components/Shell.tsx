import { Outlet } from 'react-router-dom';
import { ErrorBoundary } from './ErrorBoundary';
import { Navbar } from './Navbar';
import { Sidebar } from './Sidebar';

export function Shell() {
  return (
    <div className="flex h-screen flex-col">
      <Navbar />
      <div className="flex flex-1 overflow-hidden">
        <Sidebar />
        <main className="flex-1 overflow-y-auto p-6">
          <ErrorBoundary>
            <Outlet />
          </ErrorBoundary>
        </main>
      </div>
    </div>
  );
}
