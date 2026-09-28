'use client';

import useSWR from 'swr';
import Link from 'next/link';
import { RequireAuth } from '@/components/RequireAuth';
import { fetchGrandPrix, fetchLeaderboard, fetchSeasons } from '@/lib/api';
import { formatDeadline, isLocked } from '@/lib/dates';

function DashboardContent() {
  const { data: seasons } = useSWR('seasons', fetchSeasons);
  const season = seasons?.[0] ?? null;
  const { data: grandPrix } = useSWR(season ? ['grand-prix', season.id] : null, () => fetchGrandPrix(season!.id));
  const { data: leaderboard } = useSWR(season ? ['leaderboard', season.id] : null, () => fetchLeaderboard(season!.id));

  if (!seasons) return <p className="text-neutral-400">Chargement…</p>;
  if (!season) return <p className="text-neutral-400">Aucune saison n&apos;a encore été synchronisée.</p>;

  const nextGp = grandPrix?.find((gp) => !isLocked(gp.fp1At));
  const seasonLocked = isLocked(season.predictionsDeadline);

  return (
    <div className="flex flex-col gap-6">
      <section className="bg-neutral-900 border border-neutral-800 rounded-lg p-5">
        <h1 className="text-xl font-bold mb-1">Saison {season.year}</h1>
        <p className="text-sm text-neutral-400">
          Prono classement général :{' '}
          {seasonLocked ? (
            <span className="text-neutral-500">clos depuis le {formatDeadline(season.predictionsDeadline)}</span>
          ) : (
            <span className="text-emerald-400">ouvert jusqu&apos;au {formatDeadline(season.predictionsDeadline)}</span>
          )}
        </p>
        {!seasonLocked && (
          <Link href="/season" className="inline-block mt-3 text-sm px-3 py-1.5 rounded-md bg-red-600 hover:bg-red-500 text-white">
            Faire mon prono saison
          </Link>
        )}
      </section>

      {nextGp && (
        <section className="bg-neutral-900 border border-neutral-800 rounded-lg p-5">
          <h2 className="text-lg font-semibold mb-1">Prochain Grand Prix</h2>
          <p className="font-medium">
            Round {nextGp.round} — {nextGp.name}
            {nextGp.hasSprint && <span className="ml-2 text-xs px-1.5 py-0.5 rounded bg-neutral-800 text-neutral-300">sprint</span>}
          </p>
          <p className="text-sm text-neutral-400">Pronos ouverts jusqu&apos;au {formatDeadline(nextGp.fp1At)} (début des EL1)</p>
          <Link
            href={`/gp/${nextGp.id}`}
            className="inline-block mt-3 text-sm px-3 py-1.5 rounded-md bg-red-600 hover:bg-red-500 text-white"
          >
            Faire mon prono
          </Link>
        </section>
      )}

      <section className="bg-neutral-900 border border-neutral-800 rounded-lg p-5">
        <div className="flex items-center justify-between mb-3">
          <h2 className="text-lg font-semibold">Classement</h2>
          <Link href="/leaderboard" className="text-sm text-red-400 hover:underline">
            Voir tout →
          </Link>
        </div>
        <ol className="flex flex-col gap-1">
          {(leaderboard ?? []).slice(0, 5).map((row, i) => (
            <li key={row.user.id} className="flex items-center justify-between text-sm py-1">
              <span>
                <span className="text-neutral-500 w-5 inline-block">{i + 1}.</span> {row.user.displayName}
              </span>
              <span className="font-mono">{row.totalPoints} pts</span>
            </li>
          ))}
        </ol>
      </section>
    </div>
  );
}

export default function Home() {
  return (
    <RequireAuth>
      <DashboardContent />
    </RequireAuth>
  );
}
