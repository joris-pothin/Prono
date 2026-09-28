'use client';

import Link from 'next/link';
import { usePathname, useRouter } from 'next/navigation';
import { useAuth } from '@/context/AuthContext';

const LINKS = [
  { href: '/', label: 'Accueil' },
  { href: '/season', label: 'Classement général' },
  { href: '/gp', label: 'Grands Prix' },
  { href: '/leaderboard', label: 'Classement joueurs' },
  { href: '/risk-bets', label: 'Pronos de risque' },
];

export function Nav() {
  const { user, logout, loading } = useAuth();
  const pathname = usePathname();
  const router = useRouter();

  if (loading) {
    return <div className="h-14 border-b border-neutral-800" />;
  }

  return (
    <header className="border-b border-neutral-800 bg-neutral-950/80 backdrop-blur sticky top-0 z-10">
      <div className="max-w-5xl mx-auto px-4 h-14 flex items-center justify-between gap-4">
        <Link href="/" className="font-bold text-red-500 tracking-tight shrink-0">
          🏁 Prono F1
        </Link>
        {user && (
          <nav className="hidden sm:flex items-center gap-1 overflow-x-auto">
            {LINKS.map((link) => (
              <Link
                key={link.href}
                href={link.href}
                className={`px-3 py-1.5 rounded-md text-sm whitespace-nowrap transition-colors ${
                  pathname === link.href
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
              <span className="text-sm text-neutral-400 hidden md:inline">{user.displayName}</span>
              <button
                onClick={() => {
                  logout();
                  router.push('/login');
                }}
                className="text-sm px-3 py-1.5 rounded-md border border-neutral-700 hover:bg-neutral-800"
              >
                Déconnexion
              </button>
            </>
          ) : (
            <Link href="/login" className="text-sm px-3 py-1.5 rounded-md bg-red-600 hover:bg-red-500 text-white">
              Connexion
            </Link>
          )}
        </div>
      </div>
      {user && (
        <nav className="sm:hidden flex items-center gap-1 overflow-x-auto px-4 pb-2">
          {LINKS.map((link) => (
            <Link
              key={link.href}
              href={link.href}
              className={`px-3 py-1.5 rounded-md text-sm whitespace-nowrap transition-colors ${
                pathname === link.href ? 'bg-red-600 text-white' : 'text-neutral-300 hover:bg-neutral-800'
              }`}
            >
              {link.label}
            </Link>
          ))}
        </nav>
      )}
    </header>
  );
}
