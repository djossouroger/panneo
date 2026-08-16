import * as SecureStore from 'expo-secure-store';
import { ApiError, fetchMe, logout as apiLogout, User } from './api';

const TOKEN_KEY = 'panneo.auth_token';
const USER_KEY = 'panneo.user';

export type Session = {
  token: string;
  user: User;
};

export async function saveSession(session: Session): Promise<void> {
  await SecureStore.setItemAsync(TOKEN_KEY, session.token);
  await SecureStore.setItemAsync(USER_KEY, JSON.stringify(session.user));
}

export async function saveUser(user: User): Promise<void> {
  await SecureStore.setItemAsync(USER_KEY, JSON.stringify(user));
}

export async function getToken(): Promise<string | null> {
  return SecureStore.getItemAsync(TOKEN_KEY);
}

export async function getStoredUser(): Promise<User | null> {
  const raw = await SecureStore.getItemAsync(USER_KEY);
  if (!raw) return null;

  try {
    return JSON.parse(raw) as User;
  } catch {
    return null;
  }
}

export async function clearSession(): Promise<void> {
  await SecureStore.deleteItemAsync(TOKEN_KEY);
  await SecureStore.deleteItemAsync(USER_KEY);
}

export async function restoreSession(): Promise<Session | null> {
  const token = await getToken();
  if (!token) return null;

  const storedUser = await getStoredUser();
  if (storedUser) {
    return { token, user: storedUser };
  }

  try {
    const user = await fetchMe(token);
    await saveUser(user);
    return { token, user };
  } catch {
    await clearSession();
    return null;
  }
}

export async function logoutSession(): Promise<void> {
  const token = await getToken();
  if (token) {
    await apiLogout(token).catch(() => undefined);
  }
  await clearSession();
}

export async function handleSessionExpired(error: unknown): Promise<boolean> {
  if (error instanceof ApiError && error.status === 401) {
    await clearSession();
    return true;
  }
  return false;
}

export function routeForRole(_role: User['role']) {
  return '/';
}

export function isEmailVerified(user: User): boolean {
  return !!user.email_verified_at;
}

export function getArtisanProfile(user: User) {
  return user.artisan_profile || user.artisanProfile || null;
}