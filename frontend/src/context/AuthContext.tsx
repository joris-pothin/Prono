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
  // Checked inside the fetcher (not via a conditional SWR key) so the key stays stable
  // ('me', always) — a stable key is what makes the bound `mutate` below reliable right
  // after login/logout. A conditional key computed at render time would still reflect
  // the *previous* render's token when login() calls mutate() synchronously after
  // setToken(), making that mutate silently target the wrong (null) key and appear to
  // not persist the session until some unrelated re-render happened to catch up.
  if (!getToken()) {
    return null;
  }
  try {
    return await fetchMe();
  } catch {
    clearToken();
    return null;
  }
}

export function AuthProvider({ children }: { children: ReactNode }) {
  const { data, isLoading, mutate } = useSWR<CurrentUser | null>('me', meFetcher, {
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
    loading: isLoading,
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
