'use client';

import { createContext, useCallback, useContext, type ReactNode } from 'react';
import useSWR from 'swr';
import { clearToken, fetchMe, getToken, login as apiLogin, setToken } from '@/lib/api';
import type { CurrentUser } from '@/lib/types';

interface AuthContextValue {
  user: CurrentUser | null;
  loading: boolean;
  login: (email: string, password: string) => Promise<void>;
  logout: () => void;
}

const AuthContext = createContext<AuthContextValue | null>(null);

async function meFetcher(): Promise<CurrentUser | null> {
  try {
    return await fetchMe();
  } catch {
    clearToken();
    return null;
  }
}

export function AuthProvider({ children }: { children: ReactNode }) {
  // Reading localStorage directly in render (not in an effect) keeps this a plain
  // synchronous key computation for SWR, which owns the actual fetch/subscribe lifecycle.
  const hasToken = getToken() !== null;
  const { data, isLoading, mutate } = useSWR<CurrentUser | null>(hasToken ? 'me' : null, meFetcher, {
    revalidateOnFocus: false,
    shouldRetryOnError: false,
  });

  const login = useCallback(
    async (email: string, password: string) => {
      const token = await apiLogin(email, password);
      setToken(token);
      const me = await fetchMe();
      await mutate(me, { revalidate: false });
    },
    [mutate],
  );

  const logout = useCallback(() => {
    clearToken();
    void mutate(null, { revalidate: false });
  }, [mutate]);

  const value: AuthContextValue = {
    user: data ?? null,
    loading: hasToken && isLoading,
    login,
    logout,
  };

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthContextValue {
  const ctx = useContext(AuthContext);
  if (!ctx) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return ctx;
}
