'use client';

import useSWR from 'swr';
import Link from 'next/link';
import { RequireAuth } from '@/components/RequireAuth';
import { fetchGrandPrix, fetchSeasons } from '@/lib/api';
import { formatDeadline, isLocked } from '@/lib/dates';

function GpListContent() {
  const { data: seasons } = useSWR('seasons', fetchSeasons);
  const season = seasons?.[0] ?? null;
  const { data: grandPrix } = useSWR(season ? ['grand-prix', season.id] : null, () => fetchGrandPrix(season!.id));

  if (!seasons) return <p className="text-neutral-400">Chargement…</p>;
  if (!season) return <p className="text-neutral-400">Aucune saison disponible.</p>;

  return (
    <div className="flex flex-col gap-4">
      <h1 className="text-xl font-bold">Calendrier {season.year}</h1>
      <ol className="flex flex-col gap-1">
        {(grandPrix ?? []).map((gp) => {
          const locked = isLocked(gp.fp1At);
          return (
            <li key={gp.id}>
              <Link
                href={`/gp/${gp.id}`}
                className="flex items-center justify-between gap-3 rounded-md border border-neutral-800 bg-neutral-900 px-4 py-3 hover:border-neutral-600"
              >
                <div>
                  <span className="text-neutral-500 font-mono text-sm mr-2">R{gp.round}</span>
                  <span className="font-medium">{gp.name}</span>
                  {gp.hasSprint && <span className="ml-2 text-xs px-1.5 py-0.5 rounded bg-neutral-800 text-neutral-300">sprint</span>}
                </div>
                <span className={`text-sm ${locked ? 'text-neutral-500' : 'text-emerald-400'}`}>
                  {locked ? `clos le ${formatDeadline(gp.fp1At)}` : `ouvert jusqu'au ${formatDeadline(gp.fp1At)}`}
                </span>
              </Link>
            </li>
          );
        })}
      </ol>
    </div>
  );
}

export default function GpListPage() {
  return (
    <RequireAuth>
      <GpListContent />
    </RequireAuth>
  );
}
