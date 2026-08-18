import Constants from 'expo-constants';

const API_PORT = 8001;

function resolveApiBaseUrl(): string {
  const fromEnv = process.env.EXPO_PUBLIC_API_URL;
  if (fromEnv) return fromEnv.replace(/\/+$/, '');
  const hostUri = Constants.expoConfig?.hostUri;
  const host = hostUri?.split(':')[0];
  if (host) return `http://${host}:${API_PORT}/api/v1`;
  return '';
}

export const API_BASE_URL = resolveApiBaseUrl();

export type UserRole = 'client' | 'artisan' | 'admin';

export type Category = {
  id: number;
  name: string;
  slug: string;
  icon: string;
  is_active: boolean;
  indicative_min_price?: number | null;
  indicative_max_price?: number | null;
  callout_fee_label?: string | null;
  callout_fee?: number | null;
  currency?: string | null;
};

export type ArtisanCategory = {
  id: number;
  name: string;
  slug: string;
  icon: string;
  is_primary: boolean;
  indicative_min_price?: number | null;
  indicative_max_price?: number | null;
  currency?: string | null;
};

export type ServiceArea = {
  id?: number;
  city: string;
  district: string | null;
};

export type WorkingHour = {
  day_of_week: number;
  start_time: string | null;
  end_time: string | null;
  is_working_day: boolean;
};

export type Unavailability = {
  id: number;
  type: 'pause' | 'leave' | 'temporary_unavailable';
  starts_at: string;
  ends_at: string | null;
  reason: string | null;
  is_active: boolean;
};

export type PortfolioItem = {
  id: number;
  image_url: string;
  caption: string | null;
};

export type VerificationInfo = {
  verification_status: 'pending' | 'verified' | 'rejected';
  has_pending_submission: boolean;
  submission: {
    id: number;
    status: 'pending' | 'approved' | 'rejected';
    submitted_at: string | null;
    reviewed_at: string | null;
    rejection_reason: string | null;
    documents: Array<{
      id: number;
      document_type: 'identity_document' | 'professional_proof' | 'selfie';
      original_name: string;
      mime_type: string;
      file_size: number;
    }>;
  } | null;
};

export type ArtisanProfile = {
  id: number;
  description: string | null;
  city: string | null;
  district: string | null;
  is_available: boolean;
  verification_status: 'pending' | 'verified' | 'rejected';
  profile_photo_url: string | null;
  years_of_experience: number | null;
  specialties: string[];
  categories: ArtisanCategory[];
  service_areas: ServiceArea[];
  working_hours: WorkingHour[];
  unavailabilities: Unavailability[];
  portfolio: PortfolioItem[];
  verification: {
    submission_status: string | null;
    submitted_at: string | null;
    reviewed_at: string | null;
    rejection_reason: string | null;
    documents_count: number;
  };
};

export type User = {
  id: number;
  name: string;
  email: string;
  phone: string | null;
  phone_verified: boolean;
  role: UserRole;
  is_active: boolean;
  email_verified_at?: string | null;
  profile_photo_url?: string | null;
  artisan_profile?: ArtisanProfile | null;
  artisanProfile?: ArtisanProfile | null;
};

export type RepairRequestStatus = 'pending' | 'awaiting_artisan' | 'accepted' | 'in_progress' | 'completed' | 'cancelled';
export type RepairRequestOfferStatus = 'pending' | 'accepted' | 'rejected' | 'cancelled';

export type ArtisanStats = {
  completed_interventions: number;
  average_rating: number | string | null;
  reviews_count: number;
};

export type PublicArtisan = {
  id: number;
  name: string;
  category: {
    id?: number | null;
    name: string | null;
  } | string | null;
  city: string | null;
  district: string | null;
  description?: string | null;
  is_available?: boolean;
  is_verified?: boolean;
  phone?: string | null;
  stats?: ArtisanStats;
};

export type RepairClient = {
  id: number;
  name: string;
  phone: string | null;
};

export type RepairRequestOfferSummary = {
  id: number;
  status: RepairRequestOfferStatus;
  artisan: PublicArtisan | null;
  created_at: string | null;
  responded_at: string | null;
};

export type AcceptedArtisan = PublicArtisan & {
  category: string | null;
  phone: string | null;
};

export type Review = {
  id: number;
  rating: number;
  comment: string | null;
  client: { id: number; name: string } | null;
  artisan?: { id: number; name: string } | null;
  created_at: string;
};

export type RepairRequest = {
  id: number;
  reference: string;
  category: Pick<Category, 'id' | 'name' | 'slug' | 'icon'>;
  title: string | null;
  description: string;
  images: string[];
  location: {
    city: string;
    district: string;
    address_details: string | null;
  };
  status: RepairRequestStatus;
  current_offer: RepairRequestOfferSummary | null;
  last_offer: RepairRequestOfferSummary | null;
  offers?: RepairRequestOfferSummary[];
  artisan: AcceptedArtisan | null;
  client?: RepairClient | null;
  review?: Review | null;
  created_at: string;
  accepted_at: string | null;
  started_at: string | null;
  completed_at: string | null;
};

export type AvailableArtisan = {
  id: number;
  name: string;
  categories: ArtisanCategory[];
  primary_category: { id: number; name: string; icon: string } | null;
  city: string | null;
  district: string | null;
  description: string | null;
  is_available: boolean;
  verification_status: 'pending' | 'verified' | 'rejected';
  verified_label: string;
  profile_photo_url: string | null;
  years_of_experience: number | null;
  specialties: string[];
  stats: ArtisanStats;
};

export type RepairRequestOffer = {
  id: number;
  status: RepairRequestOfferStatus;
  responded_at: string | null;
  created_at: string | null;
  artisan: PublicArtisan | null;
  request: {
    id: number;
    reference: string;
    status: RepairRequestStatus;
    category: Pick<Category, 'id' | 'name' | 'slug' | 'icon'>;
    title: string | null;
    description: string;
    images: string[];
    location: {
      city: string;
      district: string;
      address_details: string | null;
    };
    client: RepairClient | null;
    created_at: string;
    accepted_at: string | null;
    started_at: string | null;
    completed_at: string | null;
  } | null;
};

export type CreateRepairRequestPayload = {
  category_id: number;
  title?: string | null;
  description: string;
  city: string;
  district: string;
  address_details?: string | null;
  images?: string[];
};

export type PaginationMeta = {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
};

export type Paginated<T> = {
  data: T[];
  meta: PaginationMeta;
};

const DEFAULT_META: PaginationMeta = { current_page: 1, last_page: 1, per_page: 20, total: 0 };

type ApiEnvelope<T> = {
  success: boolean;
  message: string;
  data: T;
  meta?: PaginationMeta;
  errors?: Record<string, string[]>;
  code?: string;
};

export class ApiError extends Error {
  status: number;
  errors: Record<string, string[]>;
  network: boolean;
  code?: string;
  data?: unknown;

  constructor(message: string, status: number, errors: Record<string, string[]> = {}, network = false, code?: string, data?: unknown) {
    super(message);
    this.name = 'ApiError';
    this.status = status;
    this.errors = errors;
    this.network = network;
    this.code = code;
    this.data = data;
  }
}

export function friendlyError(error: unknown): string {
  if (error instanceof ApiError) {
    if (error.network) {
      return 'Connexion impossible. Vérifiez votre connexion Internet puis réessayez.';
    }
    if (error.status === 401) {
      return 'Votre session a expiré. Connectez-vous à nouveau.';
    }
    if (error.status === 403) {
      return error.message || 'Action non autorisée.';
    }
    if (error.status === 404) {
      return 'Cette ressource n’est plus disponible.';
    }
    if (error.status >= 500) {
      return 'Le service est momentanément indisponible. Réessayez dans quelques instants.';
    }
    return error.message || 'Une erreur est survenue. Réessayez.';
  }
  return 'Une erreur est survenue. Réessayez.';
}

type RequestOptions = RequestInit & { token?: string | null };

export async function apiRequest<T>(path: string, options: RequestOptions = {}): Promise<ApiEnvelope<T>> {
  const { token, headers, body, ...requestOptions } = options;

  const isForm = typeof FormData !== 'undefined' && body instanceof FormData;

  let response: Response;
  try {
    response = await fetch(`${API_BASE_URL}${path}`, {
      ...requestOptions,
      body,
      headers: {
        Accept: 'application/json',
        ...(!isForm ? { 'Content-Type': 'application/json' } : {}),
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
        ...headers,
      },
    });
  } catch {
    throw new ApiError('Connexion impossible au serveur Pannéo.', 0, {}, true);
  }

  const payload = await response.json().catch(() => null) as ApiEnvelope<T> | null;

  if (!response.ok || payload?.success === false) {
    throw new ApiError(
      payload?.message || 'Connexion impossible au serveur Pannéo.',
      response.status,
      payload?.errors || {},
      false,
      payload?.code,
      payload?.data,
    );
  }

  if (!payload) {
    throw new ApiError('Réponse API invalide.', response.status);
  }

  return payload;
}

async function apiPaginated<T>(path: string, options: RequestOptions = {}): Promise<Paginated<T>> {
  const response = await apiRequest<T[]>(path, options);
  return {
    data: response.data ?? [],
    meta: response.meta ?? DEFAULT_META,
  };
}

export async function getCategories(): Promise<Category[]> {
  const response = await apiRequest<Category[]>('/categories');
  return response.data;
}

export async function login(email: string, password: string) {
  const response = await apiRequest<{ user: User; token: string }>('/auth/login', {
    method: 'POST',
    body: JSON.stringify({ email, password }),
  });
  return response.data;
}

export async function register(payload: Record<string, unknown>) {
  const response = await apiRequest<{ user: User; token: string; email_verified?: boolean; requires_email_verification?: boolean }>('/auth/register', {
    method: 'POST',
    body: JSON.stringify(payload),
  });
  return response.data;
}

export async function fetchMe(token: string): Promise<User> {
  const response = await apiRequest<User>('/auth/me', { token });
  return response.data;
}

export async function logout(token: string): Promise<void> {
  await apiRequest('/auth/logout', { method: 'POST', token });
}

export type ArtisanProfileResponse = {
  id: number;
  name: string;
  email: string;
  phone: string | null;
  phone_verified: boolean;
  role: UserRole;
  profile: ArtisanProfile;
  within_working_hours: boolean;
  stats: ArtisanStats;
};

export async function fetchArtisanProfile(token: string): Promise<ArtisanProfileResponse> {
  const response = await apiRequest<ArtisanProfileResponse>('/artisan/profile', { token });
  return response.data;
}

export async function updateArtisanProfile(token: string, payload: { description?: string | null; years_of_experience?: number | null; specialties?: string[] | null }): Promise<ArtisanProfileResponse> {
  const response = await apiRequest<ArtisanProfileResponse>('/artisan/profile', {
    method: 'PUT',
    token,
    body: JSON.stringify(payload),
  });
  return response.data;
}

export async function updateArtisanCategories(token: string, categoryIds: number[], primaryCategoryId: number): Promise<ArtisanProfileResponse> {
  const response = await apiRequest<ArtisanProfileResponse>('/artisan/categories', {
    method: 'PUT',
    token,
    body: JSON.stringify({ category_ids: categoryIds, primary_category_id: primaryCategoryId }),
  });
  return response.data;
}

export async function updateArtisanServiceAreas(token: string, areas: Array<{ city: string; district: string | null }>): Promise<ArtisanProfileResponse> {
  const response = await apiRequest<ArtisanProfileResponse>('/artisan/service-areas', {
    method: 'PUT',
    token,
    body: JSON.stringify({ areas }),
  });
  return response.data;
}

export async function updateArtisanWorkingHours(token: string, hours: WorkingHour[]): Promise<ArtisanProfileResponse> {
  const response = await apiRequest<ArtisanProfileResponse>('/artisan/working-hours', {
    method: 'PUT',
    token,
    body: JSON.stringify({ hours }),
  });
  return response.data;
}

export async function uploadProfilePhoto(token: string, uri: string): Promise<{ profile_photo_url: string }> {
  const form = new FormData();
  form.append('photo', { uri, name: 'photo.jpg', type: 'image/jpeg' } as any);
  const response = await apiRequest<{ profile_photo_url: string }>('/artisan/profile-photo', {
    method: 'POST',
    token,
    body: form,
  });
  return response.data;
}

export async function uploadUserProfilePhoto(token: string, uri: string): Promise<{ profile_photo_url: string }> {
  const form = new FormData();
  form.append('photo', { uri, name: 'photo.jpg', type: 'image/jpeg' } as any);
  const response = await apiRequest<{ profile_photo_url: string }>('/account/profile-photo', {
    method: 'POST',
    token,
    body: form,
  });
  return response.data;
}

export async function getUnavailabilities(token: string): Promise<Unavailability[]> {
  const response = await apiRequest<Unavailability[]>('/artisan/unavailabilities', { token });
  return response.data;
}

export async function createUnavailability(token: string, payload: { type: Unavailability['type']; starts_at: string; ends_at?: string | null; reason?: string | null }): Promise<Unavailability> {
  const response = await apiRequest<Unavailability>('/artisan/unavailabilities', {
    method: 'POST',
    token,
    body: JSON.stringify(payload),
  });
  return response.data;
}

export async function cancelUnavailability(token: string, id: number | string): Promise<void> {
  await apiRequest(`/artisan/unavailabilities/${id}`, { method: 'DELETE', token });
}

export async function getPortfolio(token: string): Promise<PortfolioItem[]> {
  const response = await apiRequest<PortfolioItem[]>('/artisan/portfolio', { token });
  return response.data;
}

export async function addPortfolioItem(token: string, uri: string, caption?: string | null): Promise<PortfolioItem> {
  const form = new FormData();
  form.append('image', { uri, name: 'photo.jpg', type: 'image/jpeg' } as any);
  if (caption) form.append('caption', caption);
  const response = await apiRequest<PortfolioItem>('/artisan/portfolio', {
    method: 'POST',
    token,
    body: form,
  });
  return response.data;
}

export async function deletePortfolioItem(token: string, id: number | string): Promise<void> {
  await apiRequest(`/artisan/portfolio/${id}`, { method: 'DELETE', token });
}

export async function getVerificationStatus(token: string): Promise<VerificationInfo> {
  const response = await apiRequest<VerificationInfo>('/artisan/verification', { token });
  return response.data;
}

export async function submitVerification(token: string, documents: Array<{ document_type: 'identity_document' | 'professional_proof' | 'selfie'; uri: string; name: string; type: string }>): Promise<{ submission_id: number; status: string }> {
  const form = new FormData();
  documents.forEach((document, index) => {
    form.append(`documents[${index}][document_type]`, document.document_type);
    form.append(`documents[${index}][file]`, { uri: document.uri, name: document.name, type: document.type } as any);
  });
  const response = await apiRequest<{ submission_id: number; status: string }>('/artisan/verification', {
    method: 'POST',
    token,
    body: form,
  });
  return response.data;
}

export async function cancelVerificationSubmission(token: string): Promise<void> {
  await apiRequest('/artisan/verification/cancel', { method: 'POST', token });
}

export async function updateAvailability(token: string, isAvailable: boolean): Promise<ArtisanProfile> {
  const response = await apiRequest<ArtisanProfile>('/artisan/availability', {
    method: 'PATCH',
    token,
    body: JSON.stringify({ is_available: isAvailable }),
  });
  return response.data;
}

export async function createRepairRequest(token: string, payload: CreateRepairRequestPayload): Promise<RepairRequest> {
  const { images, ...fields } = payload;
  if (images && images.length > 0) {
    const form = new FormData();
    Object.entries(fields).forEach(([key, value]) => {
      if (value !== undefined && value !== null) form.append(key, String(value));
    });
    images.forEach((uri) => {
      form.append('images[]', { uri, name: 'photo.jpg', type: 'image/jpeg' } as any);
    });
    const response = await apiRequest<RepairRequest>('/repair-requests', {
      method: 'POST',
      token,
      body: form,
    });
    return response.data;
  }
  const response = await apiRequest<RepairRequest>('/repair-requests', {
    method: 'POST',
    token,
    body: JSON.stringify(payload),
  });
  return response.data;
}

export async function getRepairRequests(token: string, status?: 'actives' | 'historique' | 'terminees' | 'annulees', page = 1): Promise<Paginated<RepairRequest>> {
  const params = new URLSearchParams({ page: String(page) });
  if (status) params.set('status', status);
  return apiPaginated<RepairRequest>(`/repair-requests?${params.toString()}`, { token });
}

export async function getRepairRequest(token: string, id: number | string): Promise<RepairRequest> {
  const response = await apiRequest<RepairRequest>(`/repair-requests/${id}`, { token });
  return response.data;
}

export async function cancelRepairRequest(token: string, id: number | string): Promise<RepairRequest> {
  const response = await apiRequest<RepairRequest>(`/repair-requests/${id}/cancel`, {
    method: 'PATCH',
    token,
  });
  return response.data;
}

export async function getAvailableArtisans(token: string, id: number | string): Promise<AvailableArtisan[]> {
  const response = await apiRequest<AvailableArtisan[]>(`/repair-requests/${id}/available-artisans`, { token });
  return response.data;
}

export async function sendRepairRequestOffer(token: string, id: number | string, artisanId: number): Promise<RepairRequestOffer> {
  const response = await apiRequest<RepairRequestOffer>(`/repair-requests/${id}/offers`, {
    method: 'POST',
    token,
    body: JSON.stringify({ artisan_id: artisanId }),
  });
  return response.data;
}

export async function getArtisanOffers(token: string, page = 1): Promise<Paginated<RepairRequestOffer>> {
  return apiPaginated<RepairRequestOffer>(`/artisan/offers?page=${page}`, { token });
}

export async function getArtisanOffer(token: string, id: number | string): Promise<RepairRequestOffer> {
  const response = await apiRequest<RepairRequestOffer>(`/artisan/offers/${id}`, { token });
  return response.data;
}

export async function acceptArtisanOffer(token: string, id: number | string): Promise<RepairRequestOffer> {
  const response = await apiRequest<RepairRequestOffer>(`/artisan/offers/${id}/accept`, {
    method: 'POST',
    token,
  });
  return response.data;
}

export async function rejectArtisanOffer(token: string, id: number | string): Promise<RepairRequestOffer> {
  const response = await apiRequest<RepairRequestOffer>(`/artisan/offers/${id}/reject`, {
    method: 'POST',
    token,
  });
  return response.data;
}

export async function getArtisanRepairRequests(token: string, status?: 'active' | 'completed', page = 1): Promise<Paginated<RepairRequest>> {
  const params = new URLSearchParams({ page: String(page) });
  if (status) params.set('status', status);
  return apiPaginated<RepairRequest>(`/artisan/repair-requests?${params.toString()}`, { token });
}

export async function getArtisanRepairRequest(token: string, id: number | string): Promise<RepairRequest> {
  const response = await apiRequest<RepairRequest>(`/artisan/repair-requests/${id}`, { token });
  return response.data;
}

export async function startRepairRequestIntervention(token: string, id: number | string): Promise<RepairRequest> {
  const response = await apiRequest<RepairRequest>(`/artisan/repair-requests/${id}/start`, {
    method: 'POST',
    token,
  });
  return response.data;
}

export async function completeRepairRequestIntervention(token: string, id: number | string): Promise<RepairRequest> {
  const response = await apiRequest<RepairRequest>(`/artisan/repair-requests/${id}/complete`, {
    method: 'POST',
    token,
  });
  return response.data;
}

export type PublicArtisanProfile = {
  id: number;
  name: string;
  profile: {
    categories: ArtisanCategory[];
    city: string | null;
    district: string | null;
    description: string | null;
    is_available: boolean;
    verification_status: 'pending' | 'verified' | 'rejected';
    verified_label: string;
    profile_photo_url: string | null;
    years_of_experience: number | null;
    specialties: string[];
    service_areas: ServiceArea[];
    portfolio: PortfolioItem[];
  };
  stats: ArtisanStats;
};

export async function getPublicArtisanProfile(id: number | string): Promise<PublicArtisanProfile> {
  const response = await apiRequest<PublicArtisanProfile>(`/artisans/${id}`);
  return response.data;
}

export type SubmitReviewPayload = {
  rating: number;
  comment?: string;
};

export async function submitReview(token: string, repairRequestId: number | string, payload: SubmitReviewPayload): Promise<{ id: number; rating: number; comment: string | null; created_at: string }> {
  const response = await apiRequest<{ id: number; rating: number; comment: string | null; created_at: string }>(
    `/repair-requests/${repairRequestId}/review`,
    {
      method: 'POST',
      token,
      body: JSON.stringify(payload),
    }
  );
  return response.data;
}

export async function getRepairRequestReview(token: string, repairRequestId: number | string): Promise<Review | null> {
  const response = await apiRequest<Review | null>(`/repair-requests/${repairRequestId}/review`, { token });
  return response.data;
}

export type NotificationType =
  | 'repair_request_received'
  | 'repair_request_accepted'
  | 'repair_request_rejected'
  | 'repair_request_started'
  | 'repair_request_completed'
  | 'review_received'
  | 'account_verified';

export type AppNotification = {
  id: number;
  type: NotificationType;
  title: string;
  message: string;
  data: {
    repair_request_id?: number;
    offer_id?: number;
    reference?: string;
    rating?: number;
  } | null;
  read_at: string | null;
  created_at: string;
};

export async function getNotifications(token: string, page = 1): Promise<Paginated<AppNotification>> {
  return apiPaginated<AppNotification>(`/notifications?page=${page}`, { token });
}

export async function getUnreadNotificationCount(token: string): Promise<number> {
  const response = await apiRequest<{ count: number }>('/notifications/unread-count', { token });
  return response.data.count;
}

export async function markNotificationAsRead(token: string, id: number | string): Promise<AppNotification> {
  const response = await apiRequest<AppNotification>(`/notifications/${id}/read`, {
    method: 'PATCH',
    token,
  });
  return response.data;
}

export async function markAllNotificationsAsRead(token: string): Promise<void> {
  await apiRequest('/notifications/read-all', {
    method: 'PATCH',
    token,
  });
}

export type FavoriteArtisan = {
  id: number;
  name: string;
  profile_photo_url: string | null;
  verification_status: 'pending' | 'verified' | 'rejected';
  primary_category: string | null;
  city: string | null;
  district: string | null;
  is_available: boolean;
  stats: ArtisanStats;
};

export async function getFavorites(token: string, page = 1): Promise<Paginated<FavoriteArtisan>> {
  return apiPaginated<FavoriteArtisan>(`/favorites?page=${page}`, { token });
}

export async function toggleFavorite(token: string, artisanId: number | string, favorite: boolean): Promise<{ is_favorite: boolean }> {
  const response = await apiRequest<{ is_favorite: boolean }>(`/artisans/${artisanId}/favorite`, {
    method: 'POST',
    token,
    body: JSON.stringify({ favorite }),
  });
  return response.data;
}

export async function getFavoriteStatus(token: string, artisanId: number | string): Promise<boolean> {
  const response = await apiRequest<{ is_favorite: boolean }>(`/artisans/${artisanId}/favorite`, { token });
  return response.data.is_favorite;
}

export type Dispute = {
  id: number;
  subject: string;
  description: string;
  type: 'behavior' | 'service_quality' | 'no_show' | 'safety' | 'other';
  type_label: string;
  status: 'open' | 'in_review' | 'resolved' | 'rejected';
  status_label: string;
  repair_request: {
    id: number;
    reference: string;
    title: string | null;
    category: string | null;
  };
  resolution_notes: string | null;
  resolved_at: string | null;
  created_at: string;
};

export async function getDisputes(token: string, page = 1): Promise<Paginated<Dispute>> {
  return apiPaginated<Dispute>(`/disputes?page=${page}`, { token });
}

export async function getDispute(token: string, id: number | string): Promise<Dispute> {
  const response = await apiRequest<Dispute>(`/disputes/${id}`, { token });
  return response.data;
}

export async function createDispute(token: string, repairRequestId: number | string, payload: { subject: string; description: string; type: Dispute['type'] }): Promise<Dispute> {
  const response = await apiRequest<Dispute>(`/repair-requests/${repairRequestId}/disputes`, {
    method: 'POST',
    token,
    body: JSON.stringify(payload),
  });
  return response.data;
}

export async function updateAccountProfile(token: string, name: string): Promise<User> {
  const response = await apiRequest<{ user: User }>('/account/profile', {
    method: 'PUT',
    token,
    body: JSON.stringify({ name }),
  });
  return response.data.user;
}

export async function requestEmailChange(token: string, newEmail: string): Promise<void> {
  await apiRequest('/account/email/send-code', {
    method: 'POST',
    token,
    body: JSON.stringify({ new_email: newEmail }),
  });
}

export async function changeEmail(token: string, newEmail: string, code: string): Promise<User> {
  const response = await apiRequest<{ user: User }>('/account/email', {
    method: 'POST',
    token,
    body: JSON.stringify({ new_email: newEmail, code }),
  });
  return response.data.user;
}

export async function requestPhoneChange(token: string, newPhone: string): Promise<void> {
  await apiRequest('/account/phone/send-code', {
    method: 'POST',
    token,
    body: JSON.stringify({ new_phone: newPhone }),
  });
}

export async function changePhone(token: string, newPhone: string, code: string): Promise<User> {
  const response = await apiRequest<{ user: User }>('/account/phone', {
    method: 'POST',
    token,
    body: JSON.stringify({ new_phone: newPhone, code }),
  });
  return response.data.user;
}

export async function sendPhoneVerifyCode(token: string, phone: string): Promise<void> {
  await apiRequest('/auth/phone/send-code', {
    method: 'POST',
    token,
    body: JSON.stringify({ phone }),
  });
}

export async function verifyPhone(token: string, phone: string, code: string): Promise<void> {
  await apiRequest('/auth/phone/verify', {
    method: 'POST',
    token,
    body: JSON.stringify({ phone, code }),
  });
}

export type SessionInfo = {
  id: number;
  device_name: string;
  last_used_at: string | null;
  created_at: string | null;
  is_current: boolean;
};

export async function getSessions(token: string): Promise<SessionInfo[]> {
  const response = await apiRequest<{ sessions: SessionInfo[] }>('/account/sessions', { token });
  return response.data.sessions;
}

export async function revokeSession(token: string, id: number | string): Promise<void> {
  await apiRequest(`/account/sessions/${id}`, { method: 'DELETE', token });
}

export async function revokeOtherSessions(token: string, password: string): Promise<void> {
  await apiRequest('/account/sessions/others', {
    method: 'POST',
    token,
    body: JSON.stringify({ password }),
  });
}

export async function deleteAccount(token: string, password: string): Promise<void> {
  await apiRequest('/account/delete', {
    method: 'POST',
    token,
    body: JSON.stringify({ password }),
  });
}

export async function forgotPassword(email: string): Promise<{ code?: string }> {
  const response = await apiRequest<{ code?: string }>('/auth/forgot-password', {
    method: 'POST',
    body: JSON.stringify({ email }),
  });
  return response.data;
}

export async function resetPassword(email: string, code: string, password: string): Promise<void> {
  await apiRequest('/auth/password/reset', {
    method: 'POST',
    body: JSON.stringify({ email, code, password, password_confirmation: password }),
  });
}

export async function sendEmailVerifyCode(email: string): Promise<{ email_verified?: boolean }> {
  const response = await apiRequest<{ email_verified?: boolean }>('/auth/email-verify/send', {
    method: 'POST',
    body: JSON.stringify({ email }),
  });
  return response.data;
}

export async function confirmEmailVerify(email: string, code: string): Promise<{ email_verified: boolean }> {
  const response = await apiRequest<{ email_verified: boolean }>('/auth/email-verify/confirm', {
    method: 'POST',
    body: JSON.stringify({ email, code }),
  });
  return response.data;
}
