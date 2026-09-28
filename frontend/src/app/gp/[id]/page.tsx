'use client';

import { use, useMemo, useState } from 'react';
import useSWR from 'swr';
import { RequireAuth } from '@/components/RequireAuth';
import { RankingEditor, type RankableItem } from '@/components/RankingEditor';
import { useAuth } from '@/context/AuthContext';
import { ApiError, fetchGrandPrix, fetchMyGpPrediction, fetchSeasonEntries, fetchSeasons, putGpPrediction } from '@/lib/api';
import type { GpPrediction, GrandPrix, SeasonEntry } from '@/lib/types';
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

function GpForm({ gp, entries, existing }: { gp: GrandPrix; entries: SeasonEntry[]; existing: GpPrediction | null }) {
  const [driverOrder, setDriverOrder] = useState(() => {
    const driverIds = entries.map((e) => e.driver.id);
    const ordered = existing ? [...existing.driverPicks].sort((a, b) => a.predictedRank - b.predictedRank).map((p) => p.driver.id) : [];
    return ordered.length === driverIds.length ? ordered : driverIds;
  });
  const [poleDriverId, setPoleDriverId] = useState<number | ''>(existing?.poleDriver?.id ?? '');
  const [fastestLapDriverId, setFastestLapDriverId] = useState<number | ''>(existing?.fastestLapDriver?.id ?? '');
  const [sprintP1, setSprintP1] = useState<number | ''>(existing?.sprintP1?.id ?? '');
  const [sprintP2, setSprintP2] = useState<number | ''>(existing?.sprintP2?.id ?? '');
  const [sprintP3, setSprintP3] = useState<number | ''>(existing?.sprintP3?.id ?? '');
  const [saving, setSaving] = useState(false);
  const [message, setMessage] = useState<{ type: 'ok' | 'error'; text: string } | null>(null);

  const driverItems: RankableItem[] = useMemo(
    () => entries.map((e) => ({ id: e.driver.id, label: e.driver.fullName, sublabel: e.team.name, colorHex: e.team.colorHex })),
    [entries],
  );

  const locked = isLocked(gp.fp1At);

  async function handleSubmit() {
    if (!poleDriverId || !fastestLapDriverId) {
      setMessage({ type: 'error', text: 'Choisis un pilote pour la pole et le meilleur tour.' });
      return;
    }
    if (gp.hasSprint && (!sprintP1 || !sprintP2 || !sprintP3)) {
      setMessage({ type: 'error', text: 'Choisis les trois pilotes du podium sprint.' });
      return;
    }
    setSaving(true);
    setMessage(null);
    try {
      await putGpPrediction(gp.id, {
        driverRanks: driverOrder.map((driverId, i) => ({ driverId, rank: i + 1 })),
        poleDriverId: Number(poleDriverId),
        fastestLapDriverId: Number(fastestLapDriverId),
        ...(gp.hasSprint
          ? { sprintP1Id: Number(sprintP1), sprintP2Id: Number(sprintP2), sprintP3Id: Number(sprintP3) }
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

      <section className="grid sm:grid-cols-2 gap-4">
        <label className="flex flex-col gap-1">
          <span className="text-sm text-neutral-400">Pole position</span>
          <DriverSelect items={driverItems} value={poleDriverId} onChange={setPoleDriverId} disabled={locked} />
        </label>
        <label className="flex flex-col gap-1">
          <span className="text-sm text-neutral-400">Meilleur tour</span>
          <DriverSelect items={driverItems} value={fastestLapDriverId} onChange={setFastestLapDriverId} disabled={locked} />
        </label>
      </section>

      {gp.hasSprint && (
        <section>
          <h2 className="font-semibold mb-2">Podium sprint</h2>
          <div className="grid sm:grid-cols-3 gap-4">
            <label className="flex flex-col gap-1">
              <span className="text-sm text-neutral-400">P1</span>
              <DriverSelect items={driverItems} value={sprintP1} onChange={setSprintP1} disabled={locked} />
            </label>
            <label className="flex flex-col gap-1">
              <span className="text-sm text-neutral-400">P2</span>
              <DriverSelect items={driverItems} value={sprintP2} onChange={setSprintP2} disabled={locked} />
            </label>
            <label className="flex flex-col gap-1">
              <span className="text-sm text-neutral-400">P3</span>
              <DriverSelect items={driverItems} value={sprintP3} onChange={setSprintP3} disabled={locked} />
            </label>
          </div>
        </section>
      )}

      <section>
        <h2 className="font-semibold mb-2">Classement course prédit</h2>
        <RankingEditor items={driverItems} initialOrder={driverOrder} onChange={setDriverOrder} disabled={locked} />
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
  const { data: entries } = useSWR(season ? ['season-entries', season.id] : null, () => fetchSeasonEntries(season!.id));
  const { data: existing } = useSWR(
    user ? ['gp-prediction', grandPrixId, user.id] : null,
    () => fetchMyGpPrediction(grandPrixId, user!.id),
  );

  if (!seasons) return <p className="text-neutral-400">Chargement…</p>;
  if (!season) return <p className="text-neutral-400">Aucune saison disponible.</p>;
  if (!allGp || !entries || existing === undefined) return <p className="text-neutral-400">Chargement…</p>;

  const gp = allGp.find((g) => g.id === grandPrixId) ?? null;
  if (!gp) return <p className="text-neutral-400">Grand Prix introuvable.</p>;

  return <GpForm gp={gp} entries={entries} existing={existing} />;
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
