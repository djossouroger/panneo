export function formatRelativeDate(iso: string | null): string {
  if (!iso) return '';

  const date = new Date(iso);
  const now = new Date();

  const diffMs = now.getTime() - date.getTime();
  const diffSeconds = Math.floor(diffMs / 1000);

  if (diffSeconds < 60) return 'À l’instant';

  const diffMinutes = Math.floor(diffSeconds / 60);
  if (diffMinutes < 60) return `Il y a ${diffMinutes} min`;

  const diffHours = Math.floor(diffMinutes / 60);
  if (diffHours < 24) return `Il y a ${diffHours} h`;

  const startOfToday = new Date(now.getFullYear(), now.getMonth(), now.getDate());
  const startOfDay = new Date(date.getFullYear(), date.getMonth(), date.getDate());
  const dayDiff = Math.round((startOfToday.getTime() - startOfDay.getTime()) / 86400000);

  if (dayDiff === 1) return `Hier à ${formatTime(date)}`;
  if (dayDiff === 0) return `Aujourd’hui à ${formatTime(date)}`;

  return formatLongDate(date);
}

export function formatLongDate(date: Date): string {
  const day = date.getDate();
  const month = MONTHS[date.getMonth()];
  const year = date.getFullYear();

  return `${day} ${month} ${year}`;
}

export function formatTime(date: Date): string {
  const hours = date.getHours().toString().padStart(2, '0');
  const minutes = date.getMinutes().toString().padStart(2, '0');
  return `${hours}:${minutes}`;
}

export function formatShortDate(iso: string | null): string {
  if (!iso) return '';
  const date = new Date(iso);
  const day = date.getDate().toString().padStart(2, '0');
  const month = MONTHS_SHORT[date.getMonth()];
  return `${day} ${month} ${date.getFullYear()}`;
}

const MONTHS = [
  'janvier', 'février', 'mars', 'avril', 'mai', 'juin',
  'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre',
];

const MONTHS_SHORT = [
  'janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin',
  'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.',
];
