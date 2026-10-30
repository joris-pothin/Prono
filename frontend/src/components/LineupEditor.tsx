'use client';

import { useState } from 'react';
import useSWR from 'swr';
import { ApiError, addGpLineupDriver, fetchDrivers, removeGpLineupDriver, type AddLineupDriverPayload } from '@/lib/api';
import type { GpLineup } from '@/lib/types';

interface Props {
  grandPrixId: number;
  lineup: GpLineup;
  onChange: (lineup: GpLineup) => void;
}

const inputClass =
  'rounded-md bg-neutral-800 border border-neutral-700 px-3 py-2 outline-none focus:border-red-500 disabled:opacity-50';

/**
 * Lets any player adjust who races this Grand Prix: withdraw a season driver (injury,
 * forfeit) or add a stand-in. It's shared by everyone — the backend adapts every player's
 * prediction accordingly (removed driver dropped, added driver appended last).
 */
export function LineupEditor({ grandPrixId, lineup, onChange }: Props) {
  const [open, setOpen] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [confirmRemoveId, setConfirmRemoveId] = useState<number | null>(null);

  const [mode, setMode] = useState<'existing' | 'new'>('existing');
  const [driverId, setDriverId] = useState<number | ''>('');
  const [teamId, setTeamId] = useState<number | ''>('');
  const [newDriver, setNewDriver] = useState({ firstName: '', lastName: '', code: '', number: '' });

  const { data: allDrivers } = useSWR(open ? 'drivers' : null, fetchDrivers);

  const takenIds = new Set([...lineup.entries, ...lineup.removed].map((e) => e.driver.id));
  const candidates = (allDrivers ?? [])
    .filter((d) => !takenIds.has(d.id))
    .sort((a, b) => a.lastName.localeCompare(b.lastName));

  async function run(action: () => Promise<GpLineup>) {
    setBusy(true);
    setError(null);
    try {
      onChange(await action());
      return true;
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'La modification du plateau a échoué.');
      return false;
    } finally {
      setBusy(false);
      setConfirmRemoveId(null);
    }
  }

  async function handleAdd(e: React.FormEvent) {
    e.preventDefault();
    if (!teamId) {
      setError('Choisis l’écurie pour laquelle ce pilote court ce week-end.');
      return;
    }
    let payload: AddLineupDriverPayload;
    if (mode === 'existing') {
      if (!driverId) {
        setError('Choisis un pilote.');
        return;
      }
      payload = { driverId, teamId };
    } else {
      payload = {
        newDriver: {
          firstName: newDriver.firstName.trim(),
          lastName: newDriver.lastName.trim(),
          code: newDriver.code.trim().toUpperCase(),
          ...(newDriver.number ? { number: Number(newDriver.number) } : {}),
        },
        teamId,
      };
    }
    if (await run(() => addGpLineupDriver(grandPrixId, payload))) {
      setDriverId('');
      setNewDriver({ firstName: '', lastName: '', code: '', number: '' });
    }
  }

  if (!open) {
    return (
      <button
        type="button"
        onClick={() => setOpen(true)}
        className="self-start text-sm text-neutral-400 hover:text-neutral-200 underline underline-offset-2"
      >
        Un pilote absent ou remplacé ? Modifier le plateau
      </button>
    );
  }

  return (
    <section className="flex flex-col gap-4 rounded-md border border-neutral-800 bg-neutral-900/50 p-4">
      <div className="flex items-start justify-between gap-4">
        <div>
          <h2 className="font-semibold">Plateau du Grand Prix ({lineup.entries.length} pilotes)</h2>
          <p className="text-sm text-neutral-400">
            Modifie le plateau pour tout le monde : un pilote retiré disparaît des pronos de chacun, un pilote ajouté y est
            placé en dernier.
          </p>
        </div>
        <button type="button" onClick={() => setOpen(false)} className="text-sm text-neutral-400 hover:text-neutral-200">
          Fermer
        </button>
      </div>

      <ul className="flex flex-wrap gap-2">
        {lineup.entries.map(({ driver, team, added }) => (
          <li
            key={driver.id}
            className="flex items-center gap-2 rounded-full border border-neutral-700 bg-neutral-900 pl-3 pr-1 py-1 text-sm"
          >
            {team.colorHex && <span className="w-2 h-2 rounded-full" style={{ backgroundColor: team.colorHex }} />}
            <span>
              {driver.fullName}
              {added && <span className="text-emerald-400"> · ajouté</span>}
            </span>
            {confirmRemoveId === driver.id ? (
              <button
                type="button"
                disabled={busy}
                onClick={() => run(() => removeGpLineupDriver(grandPrixId, driver.id))}
                onBlur={() => setConfirmRemoveId(null)}
                autoFocus
                className="rounded-full bg-red-600 hover:bg-red-500 disabled:opacity-50 px-2 py-0.5 text-xs text-white"
              >
                Retirer ?
              </button>
            ) : (
              <button
                type="button"
                disabled={busy}
                onClick={() => setConfirmRemoveId(driver.id)}
                className="rounded-full px-1.5 text-neutral-500 hover:text-red-400 hover:bg-neutral-800 disabled:opacity-50"
                title={`Retirer ${driver.fullName} de ce Grand Prix`}
              >
                ×
              </button>
            )}
          </li>
        ))}
      </ul>

      {lineup.removed.length > 0 && (
        <div>
          <h3 className="text-sm text-neutral-400 mb-2">Absents de ce Grand Prix</h3>
          <ul className="flex flex-wrap gap-2">
            {lineup.removed.map(({ driver }) => (
              <li
                key={driver.id}
                className="flex items-center gap-2 rounded-full border border-dashed border-neutral-700 pl-3 pr-1 py-1 text-sm text-neutral-400"
              >
                <span className="line-through">{driver.fullName}</span>
                <button
                  type="button"
                  disabled={busy}
                  onClick={() => run(() => addGpLineupDriver(grandPrixId, { driverId: driver.id }))}
                  className="rounded-full border border-neutral-700 px-2 py-0.5 text-xs text-neutral-200 hover:bg-neutral-800 disabled:opacity-50"
                >
                  Remettre
                </button>
              </li>
            ))}
          </ul>
        </div>
      )}

      <form onSubmit={handleAdd} className="flex flex-col gap-3">
        <h3 className="text-sm text-neutral-400">Ajouter un pilote</h3>
        <div className="flex gap-4 text-sm">
          <label className="flex items-center gap-2">
            <input type="radio" checked={mode === 'existing'} onChange={() => setMode('existing')} />
            Pilote connu
          </label>
          <label className="flex items-center gap-2">
            <input type="radio" checked={mode === 'new'} onChange={() => setMode('new')} />
            Nouveau pilote
          </label>
        </div>

        <div className="grid sm:grid-cols-2 gap-3">
          {mode === 'existing' ? (
            <select
              value={driverId}
              onChange={(e) => setDriverId(e.target.value ? Number(e.target.value) : '')}
              className={inputClass}
              aria-label="Pilote"
            >
              <option value="">{allDrivers ? '— Pilote —' : 'Chargement…'}</option>
              {candidates.map((d) => (
                <option key={d.id} value={d.id}>
                  {d.lastName} {d.firstName} ({d.code})
                </option>
              ))}
            </select>
          ) : (
            <div className="grid grid-cols-2 gap-2 sm:col-span-2">
              <input
                value={newDriver.firstName}
                onChange={(e) => setNewDriver({ ...newDriver, firstName: e.target.value })}
                placeholder="Prénom"
                className={inputClass}
              />
              <input
                value={newDriver.lastName}
                onChange={(e) => setNewDriver({ ...newDriver, lastName: e.target.value })}
                placeholder="Nom"
                className={inputClass}
              />
              <input
                value={newDriver.code}
                onChange={(e) => setNewDriver({ ...newDriver, code: e.target.value.toUpperCase().slice(0, 3) })}
                placeholder="Code (ex : LIN)"
                className={`${inputClass} uppercase`}
              />
              <input
                value={newDriver.number}
                onChange={(e) => setNewDriver({ ...newDriver, number: e.target.value.replace(/\D/g, '').slice(0, 2) })}
                placeholder="Numéro (optionnel)"
                inputMode="numeric"
                className={inputClass}
              />
            </div>
          )}
          <select
            value={teamId}
            onChange={(e) => setTeamId(e.target.value ? Number(e.target.value) : '')}
            className={inputClass}
            aria-label="Écurie"
          >
            <option value="">— Écurie —</option>
            {lineup.teams.map((t) => (
              <option key={t.id} value={t.id}>
                {t.name}
              </option>
            ))}
          </select>
        </div>

        <button
          type="submit"
          disabled={busy}
          className="self-start rounded-md border border-neutral-700 hover:bg-neutral-800 disabled:opacity-50 px-3 py-1.5 text-sm"
        >
          {busy ? 'Modification…' : 'Ajouter au plateau'}
        </button>
      </form>

      {error && <p className="text-sm text-red-400">{error}</p>}
    </section>
  );
}
