import type {
  CurrentUser,
  GpPrediction,
  GrandPrix,
  LeaderboardRow,
  RiskBet,
  Season,
  SeasonEntry,
  SeasonPrediction,
} from './types';

const API_URL = process.env.NEXT_PUBLIC_API_URL ?? 'http://127.0.0.1:8000';
const TOKEN_KEY = 'prono-f1-token';

export function getToken(): string | null {
  if (typeof window === 'undefined') return null;
  return window.localStorage.getItem(TOKEN_KEY);
}

export function setToken(token: string): void {
  window.localStorage.setItem(TOKEN_KEY, token);
}

export function clearToken(): void {
  window.localStorage.removeItem(TOKEN_KEY);
}

export class ApiError extends Error {
  constructor(
    message: string,
    public status: number,
    public details?: unknown,
  ) {
    super(message);
  }
}

async function request<T>(path: string, options: RequestInit = {}): Promise<T> {
  const token = getToken();
  const headers: Record<string, string> = {
    'Content-Type': 'application/json',
    Accept: 'application/json',
    ...(options.headers as Record<string, string> | undefined),
  };
  if (token) {
    headers.Authorization = `Bearer ${token}`;
  }

  const res = await fetch(`${API_URL}${path}`, { ...options, headers });

  if (!res.ok) {
    let body: { error?: string; message?: string; details?: unknown } = {};
    try {
      body = await res.json();
    } catch {
      // ignore non-JSON error bodies
    }
    throw new ApiError(body.error ?? body.message ?? `Erreur ${res.status}`, res.status, body.details);
  }

  if (res.status === 204) {
    return undefined as T;
  }

  return res.json() as Promise<T>;
}

interface Collection<T> {
  member: T[];
  totalItems: number;
}

async function collection<T>(path: string): Promise<T[]> {
  const data = await request<Collection<T>>(path);
  return data.member;
}

// --- Auth ---

export async function login(email: string, password: string): Promise<string> {
  const data = await request<{ token: string }>('/api/login_check', {
    method: 'POST',
    body: JSON.stringify({ email, password }),
  });
  return data.token;
}

export async function register(
  email: string,
  displayName: string,
  password: string,
  inviteCode: string,
): Promise<void> {
  await request('/api/register', {
    method: 'POST',
    body: JSON.stringify({ email, displayName, password, inviteCode }),
  });
}

export async function fetchMe(): Promise<CurrentUser> {
  return request<CurrentUser>('/api/me');
}

// --- Reference data ---

export async function fetchSeasons(): Promise<Season[]> {
  return collection<Season>('/api/seasons?itemsPerPage=50');
}

export async function fetchGrandPrix(seasonId: number): Promise<GrandPrix[]> {
  return collection<GrandPrix>(`/api/grand_prixes?season=${seasonId}&itemsPerPage=50`);
}

export async function fetchSeasonEntries(seasonId: number): Promise<SeasonEntry[]> {
  return collection<SeasonEntry>(`/api/season_entries?season=${seasonId}&itemsPerPage=50`);
}

// --- Predictions ---

export async function fetchMySeasonPrediction(seasonId: number, userId: number): Promise<SeasonPrediction | null> {
  const items = await collection<SeasonPrediction>(`/api/season_predictions?season=${seasonId}&user=${userId}`);
  return items[0] ?? null;
}

export async function fetchSeasonPredictionsFor(seasonId: number): Promise<SeasonPrediction[]> {
  return collection<SeasonPrediction>(`/api/season_predictions?season=${seasonId}&itemsPerPage=50`);
}

export async function putSeasonPrediction(
  seasonId: number,
  driverRanks: { driverId: number; rank: number }[],
  constructorRanks: { teamId: number; rank: number }[],
): Promise<SeasonPrediction> {
  return request<SeasonPrediction>(`/api/predictions/season/${seasonId}`, {
    method: 'PUT',
    body: JSON.stringify({ driverRanks, constructorRanks }),
  });
}

export async function fetchMyGpPrediction(grandPrixId: number, userId: number): Promise<GpPrediction | null> {
  const items = await collection<GpPrediction>(`/api/gp_predictions?grandPrix=${grandPrixId}&user=${userId}`);
  return items[0] ?? null;
}

export async function fetchGpPredictionsFor(grandPrixId: number): Promise<GpPrediction[]> {
  return collection<GpPrediction>(`/api/gp_predictions?grandPrix=${grandPrixId}&itemsPerPage=50`);
}

export interface GpPredictionPayload {
  driverRanks: { driverId: number; rank: number }[];
  poleDriverId: number;
  fastestLapDriverId: number;
  sprintP1Id?: number;
  sprintP2Id?: number;
  sprintP3Id?: number;
}

export async function putGpPrediction(grandPrixId: number, payload: GpPredictionPayload): Promise<GpPrediction> {
  return request<GpPrediction>(`/api/predictions/gp/${grandPrixId}`, {
    method: 'PUT',
    body: JSON.stringify(payload),
  });
}

// --- Leaderboard ---

export async function fetchLeaderboard(seasonId: number): Promise<LeaderboardRow[]> {
  return request<LeaderboardRow[]>(`/api/leaderboard/${seasonId}`);
}

// --- Risk bets ---

export async function fetchRiskBets(seasonId: number): Promise<RiskBet[]> {
  return collection<RiskBet>(`/api/risk_bets?season=${seasonId}&itemsPerPage=50`);
}

export async function proposeRiskBet(
  seasonId: number,
  title: string,
  description: string,
  pointsIfWin: number,
  pointsIfLose: number,
): Promise<RiskBet> {
  return request<RiskBet>('/api/risk-bets', {
    method: 'POST',
    body: JSON.stringify({ seasonId, title, description, pointsIfWin, pointsIfLose }),
  });
}

export async function voteRiskBet(id: number, approved: boolean): Promise<RiskBet> {
  return request<RiskBet>(`/api/risk-bets/${id}/vote`, {
    method: 'POST',
    body: JSON.stringify({ approved }),
  });
}

export async function resolveRiskBet(id: number, result: 'win' | 'lose'): Promise<RiskBet> {
  return request<RiskBet>(`/api/risk-bets/${id}/resolve`, {
    method: 'POST',
    body: JSON.stringify({ result }),
  });
}

export async function cancelRiskBet(id: number): Promise<RiskBet> {
  return request<RiskBet>(`/api/risk-bets/${id}/cancel`, { method: 'POST' });
}
