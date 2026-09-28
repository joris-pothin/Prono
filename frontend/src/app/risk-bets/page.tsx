'use client';

import { useState, type FormEvent } from 'react';
import useSWR from 'swr';
import { RequireAuth } from '@/components/RequireAuth';
import { useAuth } from '@/context/AuthContext';
import {
  ApiError,
  cancelRiskBet,
  fetchRiskBets,
  fetchSeasons,
  proposeRiskBet,
  resolveRiskBet,
  voteRiskBet,
} from '@/lib/api';
import type { RiskBetStatus, Season } from '@/lib/types';

const STATUS_LABEL: Record<RiskBetStatus, string> = {
  pending: 'En attente de vote',
  approved: 'Approuvé — en attente de résolution',
  rejected: 'Rejeté',
  resolved_win: 'Gagné',
  resolved_lose: 'Perdu',
  cancelled: 'Annulé',
};

const STATUS_COLOR: Record<RiskBetStatus, string> = {
  pending: 'text-amber-400',
  approved: 'text-sky-400',
  rejected: 'text-neutral-500',
  resolved_win: 'text-emerald-400',
  resolved_lose: 'text-red-400',
  cancelled: 'text-neutral-500',
};

function RiskBetsContent({ season }: { season: Season }) {
  const { user } = useAuth();
  const { data: bets, mutate } = useSWR(['risk-bets', season.id], () => fetchRiskBets(season.id));
  const [error, setError] = useState<string | null>(null);

  async function withErrorHandling(fn: () => Promise<unknown>) {
    setError(null);
    try {
      await fn();
      await mutate();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Action impossible.');
    }
  }

  if (!user) return null;

  return (
    <div className="flex flex-col gap-6">
      <h1 className="text-xl font-bold">Pronos de risque — saison {season.year}</h1>
      <p className="text-sm text-neutral-400">
        Un pari libre, accepté seulement si tous les autres joueurs l&apos;approuvent à l&apos;unanimité. Gain ou perte de
        points au résultat.
      </p>

      {error && <p className="text-sm text-red-400">{error}</p>}

      <ProposeForm seasonId={season.id} onProposed={() => mutate()} />

      <ul className="flex flex-col gap-3">
        {(bets ?? []).map((bet) => {
          const isProposer = bet.proposedBy.id === user.id;
          const myVote = bet.votes.find((v) => v.user.id === user.id);
          return (
            <li key={bet.id} className="rounded-lg border border-neutral-800 bg-neutral-900 p-4">
              <div className="flex items-center justify-between gap-2 flex-wrap">
                <h3 className="font-semibold">{bet.title}</h3>
                <span className={`text-sm font-medium ${STATUS_COLOR[bet.status]}`}>{STATUS_LABEL[bet.status]}</span>
              </div>
              <p className="text-sm text-neutral-400 mt-1">{bet.description}</p>
              <p className="text-xs text-neutral-500 mt-1">
                Proposé par {bet.proposedBy.displayName} · +{bet.pointsIfWin} pts si gagné, -{bet.pointsIfLose} pts si perdu
              </p>

              {bet.votes.length > 0 && (
                <p className="text-xs text-neutral-500 mt-2">
                  Votes : {bet.votes.map((v) => `${v.user.displayName} (${v.approved ? '✅' : '❌'})`).join(', ')}
                </p>
              )}

              <div className="flex items-center gap-2 mt-3">
                {bet.status === 'pending' && !isProposer && !myVote && (
                  <>
                    <button
                      onClick={() => withErrorHandling(() => voteRiskBet(bet.id, true))}
                      className="text-sm px-3 py-1.5 rounded-md bg-emerald-700 hover:bg-emerald-600 text-white"
                    >
                      Approuver
                    </button>
                    <button
                      onClick={() => withErrorHandling(() => voteRiskBet(bet.id, false))}
                      className="text-sm px-3 py-1.5 rounded-md bg-red-700 hover:bg-red-600 text-white"
                    >
                      Rejeter
                    </button>
                  </>
                )}
                {bet.status === 'approved' && !isProposer && (
                  <>
                    <button
                      onClick={() => withErrorHandling(() => resolveRiskBet(bet.id, 'win'))}
                      className="text-sm px-3 py-1.5 rounded-md bg-emerald-700 hover:bg-emerald-600 text-white"
                    >
                      Marquer gagné
                    </button>
                    <button
                      onClick={() => withErrorHandling(() => resolveRiskBet(bet.id, 'lose'))}
                      className="text-sm px-3 py-1.5 rounded-md bg-red-700 hover:bg-red-600 text-white"
                    >
                      Marquer perdu
                    </button>
                  </>
                )}
                {isProposer && ['pending', 'approved'].includes(bet.status) && (
                  <button
                    onClick={() => withErrorHandling(() => cancelRiskBet(bet.id))}
                    className="text-sm px-3 py-1.5 rounded-md border border-neutral-700 hover:bg-neutral-800"
                  >
                    Annuler
                  </button>
                )}
              </div>
            </li>
          );
        })}
        {bets && bets.length === 0 && <p className="text-neutral-500 text-sm">Aucun prono de risque pour l&apos;instant.</p>}
      </ul>
    </div>
  );
}

function ProposeForm({ seasonId, onProposed }: { seasonId: number; onProposed: () => void }) {
  const [open, setOpen] = useState(false);
  const [title, setTitle] = useState('');
  const [description, setDescription] = useState('');
  const [pointsIfWin, setPointsIfWin] = useState(5);
  const [pointsIfLose, setPointsIfLose] = useState(5);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    setSubmitting(true);
    setError(null);
    try {
      await proposeRiskBet(seasonId, title, description, pointsIfWin, pointsIfLose);
      setTitle('');
      setDescription('');
      setOpen(false);
      onProposed();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Proposition impossible.');
    } finally {
      setSubmitting(false);
    }
  }

  if (!open) {
    return (
      <button
        onClick={() => setOpen(true)}
        className="self-start text-sm px-3 py-1.5 rounded-md bg-red-600 hover:bg-red-500 text-white"
      >
        + Proposer un prono de risque
      </button>
    );
  }

  return (
    <form onSubmit={handleSubmit} className="flex flex-col gap-3 rounded-lg border border-neutral-800 bg-neutral-900 p-4">
      <input
        required
        placeholder="Titre du pari"
        value={title}
        onChange={(e) => setTitle(e.target.value)}
        className="rounded-md bg-neutral-800 border border-neutral-700 px-3 py-2 outline-none focus:border-red-500"
      />
      <textarea
        required
        placeholder="Description / conditions de validation"
        value={description}
        onChange={(e) => setDescription(e.target.value)}
        className="rounded-md bg-neutral-800 border border-neutral-700 px-3 py-2 outline-none focus:border-red-500"
        rows={2}
      />
      <div className="flex gap-3">
        <label className="flex flex-col gap-1 flex-1">
          <span className="text-sm text-neutral-400">Points si gagné</span>
          <input
            type="number"
            min={0}
            value={pointsIfWin}
            onChange={(e) => setPointsIfWin(Number(e.target.value))}
            className="rounded-md bg-neutral-800 border border-neutral-700 px-3 py-2 outline-none focus:border-red-500"
          />
        </label>
        <label className="flex flex-col gap-1 flex-1">
          <span className="text-sm text-neutral-400">Points si perdu</span>
          <input
            type="number"
            min={0}
            value={pointsIfLose}
            onChange={(e) => setPointsIfLose(Number(e.target.value))}
            className="rounded-md bg-neutral-800 border border-neutral-700 px-3 py-2 outline-none focus:border-red-500"
          />
        </label>
      </div>
      {error && <p className="text-sm text-red-400">{error}</p>}
      <div className="flex gap-2">
        <button
          type="submit"
          disabled={submitting}
          className="text-sm px-3 py-1.5 rounded-md bg-red-600 hover:bg-red-500 disabled:opacity-50 text-white"
        >
          {submitting ? 'Envoi…' : 'Proposer'}
        </button>
        <button
          type="button"
          onClick={() => setOpen(false)}
          className="text-sm px-3 py-1.5 rounded-md border border-neutral-700 hover:bg-neutral-800"
        >
          Annuler
        </button>
      </div>
    </form>
  );
}

function RiskBetsPageBody() {
  const { data: seasons } = useSWR('seasons', fetchSeasons);
  const season = seasons?.[0] ?? null;

  if (!seasons) return <p className="text-neutral-400">Chargement…</p>;
  if (!season) return <p className="text-neutral-400">Aucune saison disponible.</p>;

  return <RiskBetsContent season={season} />;
}

export default function RiskBetsPage() {
  return (
    <RequireAuth>
      <RiskBetsPageBody />
    </RequireAuth>
  );
}
