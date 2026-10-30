'use client';

import Link from 'next/link';
import { usePathname, useRouter } from 'next/navigation';
import { useEffect, useRef, useState } from 'react';
import { useAuth } from '@/context/AuthContext';

const LINKS = [
  { href: '/', label: 'Accueil' },
  { href: '/season', label: 'Classement général' },
  { href: '/gp', label: 'Grands Prix' },
  { href: '/leaderboard', label: 'Classement joueurs' },
  { href: '/risk-bets', label: 'Pronos de risque' },
];

function isActive(pathname: string, href: string): boolean {
  return href === '/' ? pathname === '/' : pathname === href || pathname.startsWith(`${href}/`);
}

export function Nav() {
  const { user, logout, loading } = useAuth();
  const pathname = usePathname();
  const router = useRouter();
  // The page the mobile menu was opened on: navigating elsewhere closes it, no effect needed.
  const [menuOpenedOn, setMenuOpenedOn] = useState<string | null>(null);
  const menuOpen = menuOpenedOn === pathname;
  const headerRef = useRef<HTMLElement>(null);

  useEffect(() => {
    if (!menuOpen) return;
    function onKeyDown(e: KeyboardEvent) {
      if (e.key === 'Escape') setMenuOpenedOn(null);
    }
    function onPointerDown(e: PointerEvent) {
      if (!headerRef.current?.contains(e.target as Node)) setMenuOpenedOn(null);
    }
    window.addEventListener('keydown', onKeyDown);
    window.addEventListener('pointerdown', onPointerDown);
    return () => {
      window.removeEventListener('keydown', onKeyDown);
      window.removeEventListener('pointerdown', onPointerDown);
    };
  }, [menuOpen]);

  if (loading) {
    return <div className="h-14 border-b border-neutral-800" />;
  }

  function handleLogout() {
    setMenuOpenedOn(null);
    logout();
    router.push('/login');
  }

  return (
    <header ref={headerRef} className="border-b border-neutral-800 bg-neutral-950/80 backdrop-blur sticky top-0 z-20">
      <div className="max-w-5xl mx-auto px-4 h-14 flex items-center justify-between gap-4">
        <Link href="/" className="font-bold text-red-500 tracking-tight shrink-0">
          🏁 Prono F1
        </Link>
        {user && (
          <nav className="hidden lg:flex items-center gap-1">
            {LINKS.map((link) => (
              <Link
                key={link.href}
                href={link.href}
                className={`px-3 py-1.5 rounded-md text-sm whitespace-nowrap transition-colors ${
                  isActive(pathname, link.href)
                    ? 'bg-red-600 text-white'
                    : 'text-neutral-300 hover:bg-neutral-800 hover:text-white'
                }`}
              >
                {link.label}
              </Link>
            ))}
          </nav>
        )}
        <div className="flex items-center gap-3 shrink-0">
          {user ? (
            <>
              <span className="text-sm text-neutral-400 hidden xl:inline">{user.displayName}</span>
              <button
                onClick={handleLogout}
                className="hidden lg:inline-block text-sm px-3 py-1.5 rounded-md border border-neutral-700 hover:bg-neutral-800"
              >
                Déconnexion
              </button>
              <button
                type="button"
                onClick={() => setMenuOpenedOn(menuOpen ? null : pathname)}
                aria-expanded={menuOpen}
                aria-controls="mobile-menu"
                aria-label={menuOpen ? 'Fermer le menu' : 'Ouvrir le menu'}
                className="lg:hidden -mr-2 p-2 rounded-md text-neutral-300 hover:bg-neutral-800"
              >
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" aria-hidden>
                  {menuOpen ? <path d="M6 6l12 12M18 6L6 18" /> : <path d="M4 7h16M4 12h16M4 17h16" />}
                </svg>
              </button>
            </>
          ) : (
            <Link href="/login" className="text-sm px-3 py-1.5 rounded-md bg-red-600 hover:bg-red-500 text-white">
              Connexion
            </Link>
          )}
        </div>
      </div>
      {user && menuOpen && (
        <nav
          id="mobile-menu"
          className="lg:hidden absolute inset-x-0 top-full border-b border-neutral-800 bg-neutral-950 shadow-xl shadow-black/50"
        >
          <div className="max-w-5xl mx-auto px-4 py-2 flex flex-col">
            {LINKS.map((link) => (
              <Link
                key={link.href}
                href={link.href}
                onClick={() => setMenuOpenedOn(null)}
                className={`px-3 py-3 rounded-md transition-colors ${
                  isActive(pathname, link.href) ? 'bg-red-600 text-white' : 'text-neutral-300 hover:bg-neutral-800'
                }`}
              >
                {link.label}
              </Link>
            ))}
            <div className="mt-2 pt-3 pb-1 border-t border-neutral-800 flex items-center justify-between gap-3 px-3">
              <span className="text-sm text-neutral-400 truncate">{user.displayName}</span>
              <button
                onClick={handleLogout}
                className="text-sm px-3 py-1.5 rounded-md border border-neutral-700 hover:bg-neutral-800 shrink-0"
              >
                Déconnexion
              </button>
            </div>
          </div>
        </nav>
      )}
    </header>
  );
}
