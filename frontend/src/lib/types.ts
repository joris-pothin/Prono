export interface RefUser {
  id: number;
  displayName: string;
}

export interface RefDriver {
  id: number;
  code: string;
  firstName: string;
  lastName: string;
  fullName: string;
  number?: number;
  nationality?: string;
}

export interface RefTeam {
  id: number;
  name: string;
  colorHex?: string | null;
  nationality?: string;
}

export interface Season {
  id: number;
  year: number;
  predictionsDeadline: string | null;
}

export interface GrandPrix {
  id: number;
  round: number;
  name: string;
  circuit: string;
  country: string;
  hasSprint: boolean;
  fp1At: string;
  qualifyingAt: string | null;
  sprintAt: string | null;
  raceAt: string;
}

export interface SeasonEntry {
  id: number;
  driver: RefDriver;
  team: RefTeam;
  raceNumber: number | null;
}

export interface SeasonDriverPick {
  driver: RefDriver;
  predictedRank: number;
}

export interface SeasonConstructorPick {
  team: RefTeam;
  predictedRank: number;
}

export interface SeasonPrediction {
  id: number;
  user: RefUser;
  season: Season;
  driverPicks: SeasonDriverPick[];
  constructorPicks: SeasonConstructorPick[];
  updatedAt: string;
}

export interface GpDriverPick {
  driver: RefDriver;
  predictedRank: number;
}

export interface GpPrediction {
  id: number;
  user: RefUser;
  grandPrix: GrandPrix;
  driverPicks: GpDriverPick[];
  poleDriver: RefDriver | null;
  fastestLapDriver: RefDriver | null;
  sprintP1: RefDriver | null;
  sprintP2: RefDriver | null;
  sprintP3: RefDriver | null;
  updatedAt: string;
}

export interface LeaderboardRow {
  user: RefUser;
  gpPoints: number;
  seasonPoints: number;
  riskPoints: number;
  totalPoints: number;
}

export type RiskBetStatus = 'pending' | 'approved' | 'rejected' | 'resolved_win' | 'resolved_lose' | 'cancelled';

export interface RiskBetVote {
  user: RefUser;
  approved: boolean;
  votedAt: string;
}

export interface RiskBet {
  id: number;
  season: Season;
  proposedBy: RefUser;
  title: string;
  description: string;
  pointsIfWin: number;
  pointsIfLose: number;
  status: RiskBetStatus;
  votes: RiskBetVote[];
  createdAt: string;
  resolvedAt: string | null;
}

export interface CurrentUser {
  id: number;
  email: string;
  displayName: string;
}
