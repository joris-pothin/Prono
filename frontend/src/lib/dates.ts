export function isLocked(deadline: string | null): boolean {
  if (!deadline) return false;
  return new Date() >= new Date(deadline);
}

export function formatDeadline(deadline: string | null): string {
  if (!deadline) return 'inconnu';
  return new Date(deadline).toLocaleString('fr-FR', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}
