import { createContext, useContext, useMemo, type ReactNode } from 'react';
import type { AuthUser } from '../types';
import { useMe } from '../hooks/useAuth';

interface UserContextValue {
  user: AuthUser | null;
  isLoading: boolean;
}

const UserContext = createContext<UserContextValue | null>(null);

export function UserProvider({ children }: { children: ReactNode }) {
  const { data, isLoading } = useMe();
  const user = data?.data ?? null;

  const value = useMemo(() => ({ user, isLoading }), [user, isLoading]);

  return <UserContext.Provider value={value}>{children}</UserContext.Provider>;
}

export function useUser(): UserContextValue {
  const context = useContext(UserContext);
  if (!context) {
    throw new Error('useUser must be used within a UserProvider');
  }
  return context;
}
