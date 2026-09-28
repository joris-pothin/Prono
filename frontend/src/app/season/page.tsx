'use client';

import { useMemo, useState } from 'react';
import useSWR from 'swr';
import { RequireAuth } from '@/components/RequireAuth';
import { RankingEditor, type RankableItem } from '@/components/RankingEditor';
import { useAuth } from '@/context/AuthContext';
import { ApiError, fetchMySeasonPrediction, fetchSeasonEntries, fetchSeasons, putSeasonPrediction } from '@/lib/api';
import type { Season, SeasonEntry, SeasonPrediction } from '@/lib/types';
import { formatDeadline, isLocked } from '@/lib/dates';

function SeasonForm({ season, entries, existing }: { season: Season; entries: SeasonEntry[]; existing: SeasonPrediction | null }) {
  // Lazy initializers: SeasonForm is only mounted once season/entries/existing are all
  // resolved (see the guard in SeasonContent below), so a one-time computation from props
  // is enough to seed each ranking — no effect needed to keep it in sync afterwards.
  const [driverOrder, setDriverOrder] = useState(() => {
    const driverIds = entries.map((e) => e.driver.id);
    const ordered = existing ? [...existing.driverPicks].sort((a, b) => a.predictedRank - b.predictedRank).map((p) => p.driver.id) : [];
    return ordered.length === driverIds.length ? ordered : driverIds;
  });
  const [teamOrder, setTeamOrder] = useState(() => {
    const teamIds = [...new Set(entries.map((e) => e.team.id))];
    const ordered = existing ? [...existing.constructorPicks].sort((a, b) => a.predictedRank - b.predictedRank).map((p) => p.team.id) : [];
    return ordered.length === teamIds.length ? ordered : teamIds;
  });
  const [saving, setSaving] = useState(false);
  const [message, setMessage] = useState<{ type: 'ok' | 'error'; text: string } | null>(null);

  const driverItems: RankableItem[] = useMemo(
    () => entries.map((e) => ({ id: e.driver.id, label: e.driver.fullName, sublabel: e.team.name, colorHex: e.team.colorHex })),
    [entries],
  );
  const teamItems: RankableItem[] = useMemo(() => {
    const seen = new Map<number, RankableItem>();
    for (const e of entries) {
      if (!seen.has(e.team.id)) seen.set(e.team.id, { id: e.team.id, label: e.team.name, colorHex: e.team.colorHex });
    }
    return [...seen.values()];
  }, [entries]);

  const locked = isLocked(season.predictionsDeadline);

  async function handleSubmit() {
    setSaving(true);
    setMessage(null);
    try {
      await putSeasonPrediction(
        season.id,
        driverOrder.map((driverId, i) => ({ driverId, rank: i + 1 })),
        teamOrder.map((teamId, i) => ({ teamId, rank: i + 1 })),
      );
      setMessage({ type: 'ok', text: 'Prono enregistré !' });
    } catch (err) {
      setMessage({ type: 'error', text: err instanceof ApiError ? err.message : 'Échec de l’enregistrement.' });
    } finally {
      setSaving(false);
    }
  }

  return (
    <div className="flex flex-col gap-6">
      <div>
        <h1 className="text-xl font-bold">Prono classement général — saison {season.year}</h1>
        <p className="text-sm text-neutral-400">
          {locked
            ? `Clos depuis le ${formatDeadline(season.predictionsDeadline)}.`
            : `Modifiable jusqu'au ${formatDeadline(season.predictionsDeadline)} (début des EL1 du 1er Grand Prix).`}
        </p>
      </div>

      <section>
        <h2 className="font-semibold mb-2">Classement pilotes prédit</h2>
        <RankingEditor items={driverItems} initialOrder={driverOrder} onChange={setDriverOrder} disabled={locked} />
      </section>

      <section>
        <h2 className="font-semibold mb-2">Classement constructeurs prédit</h2>
        <RankingEditor items={teamItems} initialOrder={teamOrder} onChange={setTeamOrder} disabled={locked} />
      </section>

      {!locked && (
        <button
          onClick={handleSubmit}
          disabled={saving}
          className="self-start rounded-md bg-red-600 hover:bg-red-500 disabled:opacity-50 text-white px-4 py-2 font-medium"
        >
          {saving ? 'Enregistrement…' : 'Enregistrer mon prono'}
        </button>
      )}
      {message && <p className={message.type === 'ok' ? 'text-emerald-400' : 'text-red-400'}>{message.text}</p>}
    </div>
  );
}

function SeasonContent() {
  const { user } = useAuth();
  const { data: seasons } = useSWR('seasons', fetchSeasons);
  const season = seasons?.[0] ?? null;
  const { data: entries } = useSWR(season ? ['season-entries', season.id] : null, () => fetchSeasonEntries(season!.id));
  const { data: existing } = useSWR(
    season && user ? ['season-prediction', season.id, user.id] : null,
    () => fetchMySeasonPrediction(season!.id, user!.id),
  );

  if (!seasons) return <p className="text-neutral-400">Chargement…</p>;
  if (!season) return <p className="text-neutral-400">Aucune saison disponible.</p>;
  if (!entries || existing === undefined) return <p className="text-neutral-400">Chargement…</p>;

  return <SeasonForm season={season} entries={entries} existing={existing} />;
}

export default function SeasonPage() {
  return (
    <RequireAuth>
      <SeasonContent />
    </RequireAuth>
  );
}
