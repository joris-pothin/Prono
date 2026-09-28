'use client';

import useSWR from 'swr';
import { RequireAuth } from '@/components/RequireAuth';
import { fetchLeaderboard, fetchSeasons } from '@/lib/api';

function LeaderboardContent() {
  const { data: seasons } = useSWR('seasons', fetchSeasons);
  const season = seasons?.[0] ?? null;
  const { data: rows } = useSWR(season ? ['leaderboard', season.id] : null, () => fetchLeaderboard(season!.id));

  if (!seasons) return <p className="text-neutral-400">Chargement…</p>;
  if (!season) return <p className="text-neutral-400">Aucune saison disponible.</p>;

  return (
    <div className="flex flex-col gap-4">
      <h1 className="text-xl font-bold">Classement joueurs — saison {season.year}</h1>
      <div className="overflow-x-auto">
        <table className="w-full text-sm border-collapse">
          <thead>
            <tr className="text-left text-neutral-400 border-b border-neutral-800">
              <th className="py-2 pr-3">#</th>
              <th className="py-2 pr-3">Joueur</th>
              <th className="py-2 pr-3 text-right">Grands Prix</th>
              <th className="py-2 pr-3 text-right">Saison</th>
              <th className="py-2 pr-3 text-right">Risque</th>
              <th className="py-2 pr-3 text-right font-semibold">Total</th>
            </tr>
          </thead>
          <tbody>
            {(rows ?? []).map((row, i) => (
              <tr key={row.user.id} className="border-b border-neutral-900">
                <td className="py-2 pr-3 text-neutral-500">{i + 1}</td>
                <td className="py-2 pr-3 font-medium">{row.user.displayName}</td>
                <td className="py-2 pr-3 text-right font-mono">{row.gpPoints}</td>
                <td className="py-2 pr-3 text-right font-mono">{row.seasonPoints}</td>
                <td className="py-2 pr-3 text-right font-mono">{row.riskPoints}</td>
                <td className="py-2 pr-3 text-right font-mono font-bold text-red-400">{row.totalPoints}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}

export default function LeaderboardPage() {
  return (
    <RequireAuth>
      <LeaderboardContent />
    </RequireAuth>
  );
}
