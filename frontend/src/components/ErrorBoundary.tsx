import { Component, type ErrorInfo, type ReactNode } from 'react';
import { toast } from 'sonner';
import { copy } from '../data/copy';

interface ErrorBoundaryProps {
  children: ReactNode;
  fallback?: (props: { error: Error; retry: () => void }) => ReactNode;
}

interface ErrorBoundaryState {
  hasError: boolean;
  error: Error | null;
}

export class ErrorBoundary extends Component<ErrorBoundaryProps, ErrorBoundaryState> {
  override state: ErrorBoundaryState = {
    hasError: false,
    error: null,
  };

  static getDerivedStateFromError(error: Error): ErrorBoundaryState {
    return { hasError: true, error };
  }

  override componentDidCatch(error: Error, info: ErrorInfo): void {
    void error;
    void info;
    toast.error(copy.errors.generic);
  }

  private handleRetry = (): void => {
    this.setState({ hasError: false, error: null });
  };

  override render(): ReactNode {
    if (this.state.hasError && this.state.error) {
      if (this.props.fallback) {
        return this.props.fallback({ error: this.state.error, retry: this.handleRetry });
      }

      return (
        <div className="flex flex-col items-center justify-center gap-4 p-8" role="alert">
          <p className="text-gray-700">{copy.errors.generic}</p>
          <button
            type="button"
            onClick={this.handleRetry}
            className="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
          >
            {copy.common.tryAgain}
          </button>
        </div>
      );
    }

    return this.props.children;
  }
}

export default ErrorBoundary;
