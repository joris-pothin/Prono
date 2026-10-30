'use client';

import { use, useMemo, useState } from 'react';
import useSWR from 'swr';
import { RequireAuth } from '@/components/RequireAuth';
import { LineupEditor } from '@/components/LineupEditor';
import { RankingEditor, type RankableItem } from '@/components/RankingEditor';
import { useAuth } from '@/context/AuthContext';
import { ApiError, fetchGpLineup, fetchGrandPrix, fetchMyGpPrediction, fetchSeasons, putGpPrediction } from '@/lib/api';
import type { GpLineup, GpPrediction, GrandPrix } from '@/lib/types';
import { formatDeadline, isLocked } from '@/lib/dates';

function DriverSelect({
  items,
  value,
  onChange,
  disabled,
}: {
  items: RankableItem[];
  value: number | '';
  onChange: (v: number | '') => void;
  disabled?: boolean;
}) {
  return (
    <select
      value={value}
      disabled={disabled}
      onChange={(e) => onChange(e.target.value ? Number(e.target.value) : '')}
      className="rounded-md bg-neutral-800 border border-neutral-700 px-3 py-2 outline-none focus:border-red-500 disabled:opacity-50"
    >
      <option value="">—</option>
      {items.map((item) => (
        <option key={item.id} value={item.id}>
          {item.label}
        </option>
      ))}
    </select>
  );
}

/** Keeps `order` in step with the lineup: drivers no longer in it dropped, new ones appended last. */
function reconcileOrder(order: number[], lineupIds: number[]): number[] {
  const inLineup = new Set(lineupIds);
  const kept = order.filter((id) => inLineup.has(id));
  const keptSet = new Set(kept);
  return [...kept, ...lineupIds.filter((id) => !keptSet.has(id))];
}

function GpForm({
  gp,
  lineup,
  onLineupChange,
  existing,
}: {
  gp: GrandPrix;
  lineup: GpLineup;
  onLineupChange: (lineup: GpLineup) => void;
  existing: GpPrediction | null;
}) {
  const [driverOrder, setDriverOrder] = useState(() =>
    existing ? [...existing.driverPicks].sort((a, b) => a.predictedRank - b.predictedRank).map((p) => p.driver.id) : [],
  );
  const [poleDriverId, setPoleDriverId] = useState<number | ''>(existing?.poleDriver?.id ?? '');
  const [fastestLapDriverId, setFastestLapDriverId] = useState<number | ''>(existing?.fastestLapDriver?.id ?? '');
  const [sprintP1, setSprintP1] = useState<number | ''>(existing?.sprintP1?.id ?? '');
  const [sprintP2, setSprintP2] = useState<number | ''>(existing?.sprintP2?.id ?? '');
  const [sprintP3, setSprintP3] = useState<number | ''>(existing?.sprintP3?.id ?? '');
  const [saving, setSaving] = useState(false);
  const [message, setMessage] = useState<{ type: 'ok' | 'error'; text: string } | null>(null);

  const driverItems: RankableItem[] = useMemo(
    () =>
      lineup.entries.map((e) => ({ id: e.driver.id, label: e.driver.fullName, sublabel: e.team.name, colorHex: e.team.colorHex })),
    [lineup],
  );

  // The lineup can change under the form (this player or another one editing it): the local
  // ranking and picks are derived against it on every render rather than synced via effects.
  const lineupIds = driverItems.map((i) => i.id);
  const lineupKey = lineupIds.join(',');
  const order = reconcileOrder(driverOrder, lineupIds);
  const inLineup = (id: number | ''): number | '' => (id !== '' && lineupIds.includes(id) ? id : '');
  const pole = inLineup(poleDriverId);
  const fastestLap = inLineup(fastestLapDriverId);
  const sprint = [inLineup(sprintP1), inLineup(sprintP2), inLineup(sprintP3)];

  const locked = isLocked(gp.fp1At);

  async function handleSubmit() {
    if (!pole || !fastestLap) {
      setMessage({ type: 'error', text: 'Choisis un pilote pour la pole et le meilleur tour.' });
      return;
    }
    if (gp.hasSprint && sprint.some((id) => !id)) {
      setMessage({ type: 'error', text: 'Choisis les trois pilotes du podium sprint.' });
      return;
    }
    setSaving(true);
    setMessage(null);
    try {
      await putGpPrediction(gp.id, {
        driverRanks: order.map((driverId, i) => ({ driverId, rank: i + 1 })),
        poleDriverId: Number(pole),
        fastestLapDriverId: Number(fastestLap),
        ...(gp.hasSprint
          ? { sprintP1Id: Number(sprint[0]), sprintP2Id: Number(sprint[1]), sprintP3Id: Number(sprint[2]) }
          : {}),
      });
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
        <h1 className="text-xl font-bold">
          Round {gp.round} — {gp.name}
        </h1>
        <p className="text-sm text-neutral-400">
          {locked ? `Clos depuis le ${formatDeadline(gp.fp1At)}.` : `Modifiable jusqu'au ${formatDeadline(gp.fp1At)} (début des EL1).`}
        </p>
      </div>

      {!locked && <LineupEditor grandPrixId={gp.id} lineup={lineup} onChange={onLineupChange} />}

      <section className="grid sm:grid-cols-2 gap-4">
        <label className="flex flex-col gap-1">
          <span className="text-sm text-neutral-400">Pole position</span>
          <DriverSelect items={driverItems} value={pole} onChange={setPoleDriverId} disabled={locked} />
        </label>
        <label className="flex flex-col gap-1">
          <span className="text-sm text-neutral-400">Meilleur tour</span>
          <DriverSelect items={driverItems} value={fastestLap} onChange={setFastestLapDriverId} disabled={locked} />
        </label>
      </section>

      {gp.hasSprint && (
        <section>
          <h2 className="font-semibold mb-2">Podium sprint</h2>
          <div className="grid sm:grid-cols-3 gap-4">
            <label className="flex flex-col gap-1">
              <span className="text-sm text-neutral-400">P1</span>
              <DriverSelect items={driverItems} value={sprint[0]} onChange={setSprintP1} disabled={locked} />
            </label>
            <label className="flex flex-col gap-1">
              <span className="text-sm text-neutral-400">P2</span>
              <DriverSelect items={driverItems} value={sprint[1]} onChange={setSprintP2} disabled={locked} />
            </label>
            <label className="flex flex-col gap-1">
              <span className="text-sm text-neutral-400">P3</span>
              <DriverSelect items={driverItems} value={sprint[2]} onChange={setSprintP3} disabled={locked} />
            </label>
          </div>
        </section>
      )}

      <section>
        <h2 className="font-semibold mb-2">Classement course prédit</h2>
        {/* Remounted on lineup changes so it picks up the reconciled order. */}
        <RankingEditor key={lineupKey} items={driverItems} initialOrder={order} onChange={setDriverOrder} disabled={locked} />
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

function GpPredictionContent({ grandPrixId }: { grandPrixId: number }) {
  const { user } = useAuth();
  const { data: seasons } = useSWR('seasons', fetchSeasons);
  const season = seasons?.[0] ?? null;
  const { data: allGp } = useSWR(season ? ['grand-prix', season.id] : null, () => fetchGrandPrix(season!.id));
  const { data: lineup, mutate: mutateLineup } = useSWR(['gp-lineup', grandPrixId], () => fetchGpLineup(grandPrixId));
  const { data: existing } = useSWR(
    user ? ['gp-prediction', grandPrixId, user.id] : null,
    () => fetchMyGpPrediction(grandPrixId, user!.id),
  );

  if (!seasons) return <p className="text-neutral-400">Chargement…</p>;
  if (!season) return <p className="text-neutral-400">Aucune saison disponible.</p>;
  if (!allGp || !lineup || existing === undefined) return <p className="text-neutral-400">Chargement…</p>;

  const gp = allGp.find((g) => g.id === grandPrixId) ?? null;
  if (!gp) return <p className="text-neutral-400">Grand Prix introuvable.</p>;

  return (
    <GpForm
      gp={gp}
      lineup={lineup}
      onLineupChange={(next) => mutateLineup(next, { revalidate: false })}
      existing={existing}
    />
  );
}

export default function GpPredictionPage(props: { params: Promise<{ id: string }> }) {
  const { id } = use(props.params);
  const grandPrixId = Number(id);

  return (
    <RequireAuth>
      <GpPredictionContent grandPrixId={grandPrixId} />
    </RequireAuth>
  );
}
