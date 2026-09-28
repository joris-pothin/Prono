'use client';

import { useState, type FormEvent } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { useAuth } from '@/context/AuthContext';
import { ApiError } from '@/lib/api';

export default function LoginPage() {
  const { login } = useAuth();
  const router = useRouter();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    setError(null);
    setSubmitting(true);
    try {
      await login(email, password);
      router.push('/');
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Connexion impossible.');
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <div className="max-w-sm mx-auto mt-12">
      <h1 className="text-2xl font-bold mb-6 text-center">🏁 Prono F1</h1>
      <form onSubmit={handleSubmit} className="flex flex-col gap-4 bg-neutral-900 border border-neutral-800 rounded-lg p-6">
        <div className="flex flex-col gap-1">
          <label className="text-sm text-neutral-400">Email</label>
          <input
            type="email"
            required
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            className="rounded-md bg-neutral-800 border border-neutral-700 px-3 py-2 outline-none focus:border-red-500"
          />
        </div>
        <div className="flex flex-col gap-1">
          <label className="text-sm text-neutral-400">Mot de passe</label>
          <input
            type="password"
            required
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            className="rounded-md bg-neutral-800 border border-neutral-700 px-3 py-2 outline-none focus:border-red-500"
          />
        </div>
        {error && <p className="text-sm text-red-400">{error}</p>}
        <button
          type="submit"
          disabled={submitting}
          className="rounded-md bg-red-600 hover:bg-red-500 disabled:opacity-50 text-white py-2 font-medium"
        >
          {submitting ? 'Connexion…' : 'Se connecter'}
        </button>
        <p className="text-sm text-neutral-400 text-center">
          Pas encore de compte ?{' '}
          <Link href="/register" className="text-red-400 hover:underline">
            S&apos;inscrire
          </Link>
        </p>
      </form>
    </div>
  );
}
