import { useCallback, useState } from 'react';
import { useRouter } from 'expo-router';
import { Image, Pressable, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { CircleAlert, Clock3, Inbox, LogOut, Power, ShieldX } from 'lucide-react-native';
import { colors } from '../../components/theme';
import { AppButton, EmptyState, ErrorMessage, LoadingState, Logo, ScreenContainer, StatusBadge, VerifiedBadge } from '../../components/ui';
import { ApiError, ArtisanProfile, ArtisanProfileResponse, fetchArtisanProfile, friendlyError, getArtisanOffers, getArtisanRepairRequests, RepairRequest, RepairRequestOffer, updateAvailability, User as ApiUser } from '../../lib/api';
import { IncomingRequestCard, InterventionCard } from '../../components/repairRequests';
import { NotificationBell } from '../../components/notifications';
import { restoreSession, saveUser, handleSessionExpired, logoutSession } from '../../lib/session';
import { useFocusLoad } from '../../lib/focusLoad';
import { formatShortDate } from '../../lib/dates';

function firstName(name: string) {
  return name.trim().split(' ')[0] || name;
}

function initialsFor(name: string) {
  const parts = name.trim().split(/\s+/).filter(Boolean);
  if (parts.length === 0) return '?';
  return parts.slice(0, 2).map((part) => part[0]?.toUpperCase()).join('');
}

function primaryCategoryLabel(profile: ArtisanProfile) {
  return profile.categories.find((cat) => cat.is_primary)?.name || profile.categories[0]?.name || 'Métier non renseigné';
}

export default function ArtisanHomeScreen() {
  const router = useRouter();
  const [token, setToken] = useState<string | null>(null);
  const [user, setUser] = useState<ApiUser | null>(null);
  const [profile, setProfile] = useState<ArtisanProfileResponse | null>(null);
  const [offers, setOffers] = useState<RepairRequestOffer[]>([]);
  const [activeInterventions, setActiveInterventions] = useState<RepairRequest[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async (refresh = false) => {
    if (refresh) setRefreshing(true);
    else setLoading(true);
    setError(null);
    try {
      const session = await restoreSession();
      if (!session) {
        router.replace('/login');
        return;
      }
      if (session.user.role !== 'artisan') {
        router.replace('/');
        return;
      }
      const nextProfile = await fetchArtisanProfile(session.token);
      setToken(session.token);
      setUser(session.user);
      setProfile(nextProfile);
      const verified = nextProfile.profile.verification_status === 'verified';
      if (verified) {
        const [nextOffers, nextActiveInterventions] = await Promise.all([
          getArtisanOffers(session.token),
          getArtisanRepairRequests(session.token, 'active'),
        ]);
        setOffers(nextOffers.data.slice(0, 3));
        setActiveInterventions(nextActiveInterventions.data.slice(0, 3));
      } else {
        setOffers([]);
        setActiveInterventions([]);
      }
    } catch (err) {
      if (await handleSessionExpired(err)) {
        router.replace('/login');
        return;
      }
      setError(friendlyError(err));
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [router]);

  useFocusLoad(load);

  const handleLogout = async () => {
    await logoutSession();
    router.replace('/login');
  };

  const toggleAvailability = async () => {
    if (!token || !profile?.profile || saving) return;
    if (activeInterventions.length > 0) {
      setError('Terminez votre intervention actuelle avant de vous rendre disponible.');
      return;
    }

    setSaving(true);
    setError(null);
    try {
      const updatedProfile = await updateAvailability(token, !profile.profile.is_available);
      setProfile(prev => prev ? { ...prev, profile: { ...prev.profile, is_available: updatedProfile.is_available } } : null);
      if (user) {
        const nextUser = {
          ...user,
          artisan_profile: user.artisan_profile ? { ...user.artisan_profile, is_available: updatedProfile.is_available } : user.artisan_profile,
          artisanProfile: user.artisanProfile ? { ...user.artisanProfile, is_available: updatedProfile.is_available } : user.artisanProfile,
        };
        setUser(nextUser);
        await saveUser(nextUser);
      }
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'La disponibilité n’a pas pu être mise à jour.');
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return <ScreenContainer><LoadingState label="Chargement de votre espace artisan..." /></ScreenContainer>;
  }

  const currentIntervention = activeInterventions[0];
  const hasActiveIntervention = Boolean(currentIntervention);
  const available = Boolean(profile?.profile.is_available) && !hasActiveIntervention;
  const verificationStatus = profile?.profile.verification_status || 'pending';
  const isVerified = verificationStatus === 'verified';
  const isRejected = verificationStatus === 'rejected';
  const pendingOffers = offers.filter((offer) => offer.status === 'pending');
  const verificationInfo = profile?.profile.verification;
  const submittedAt = verificationInfo?.submitted_at ? formatShortDate(verificationInfo.submitted_at) : null;
  const rejectionReason = verificationInfo?.rejection_reason || null;

  return (
    <ScreenContainer>
      <View style={styles.header}>
        <View style={styles.headerLeft}>
          <Logo compact />
          <Text style={styles.greeting}>Bonjour, {user ? firstName(user.name) : 'vous'}</Text>
          <View style={styles.badgeRow}>
            {profile?.profile.categories.length ? <StatusBadge label={primaryCategoryLabel(profile.profile)} tone="neutral" /> : null}
            {isVerified ? <VerifiedBadge /> : null}
          </View>
        </View>
        <View style={styles.headerActions}>
          {isVerified ? (
            <Pressable
              onPress={toggleAvailability}
              disabled={saving || hasActiveIntervention}
              style={[styles.availToggle, available && styles.availToggleActive, hasActiveIntervention && styles.availToggleBusy]}
              accessibilityLabel="Changer la disponibilité"
            >
              <Power size={16} color={available ? colors.white : colors.muted} />
              <Text style={[styles.availToggleText, available && styles.availToggleTextActive]}>
                {hasActiveIntervention ? 'En intervention' : available ? 'Disponible' : 'Indisponible'}
              </Text>
            </Pressable>
          ) : null}
          <NotificationBell token={token} />
          <View style={styles.avatarButton}>
            {profile?.profile.profile_photo_url ? (
              <Image source={{ uri: profile.profile.profile_photo_url }} style={styles.avatarImage} resizeMode="cover" />
            ) : (
              <View style={styles.avatarFallback}>
                <Text style={styles.avatarText}>{user ? initialsFor(user.name) : '?'}</Text>
              </View>
            )}
          </View>
        </View>
      </View>

      <ScrollView
        contentContainerStyle={styles.scrollContent}
        showsVerticalScrollIndicator={false}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => load(true)} tintColor={colors.primary} />}
      >
        <ErrorMessage message={error} />

        {isRejected ? (
          <View style={styles.statusScreen}>
            <View style={[styles.statusIcon, styles.statusIconRejected]}>
              <CircleAlert size={30} color={colors.danger} />
            </View>
            <Text style={styles.statusTitle}>Compte non validé</Text>
            <Text style={styles.statusText}>
              {rejectionReason
                ? `Votre dossier a été refusé pour le motif suivant : « ${rejectionReason} ».`
                : 'Votre dossier n’a pas pu être validé.'}
            </Text>
            <Text style={styles.statusText}>Vous ne pouvez pas encore recevoir de demandes de dépannage.</Text>
            <View style={styles.statusActions}>
              <AppButton title="Envoyer une nouvelle demande" onPress={() => router.push('/artisan/verification')} />
              <AppButton title="Se déconnecter" variant="danger" icon={<LogOut size={18} color={colors.white} />} onPress={handleLogout} />
            </View>
          </View>
        ) : !isVerified ? (
          <View style={styles.statusScreen}>
            <View style={styles.statusIcon}>
              <Clock3 size={30} color={colors.urgent} />
            </View>
            <Text style={styles.statusTitle}>Compte en cours de validation</Text>
            {submittedAt ? (
              <Text style={styles.statusDate}>Dossier soumis le {submittedAt}</Text>
            ) : (
              <Text style={styles.statusDate}>Dossier soumis</Text>
            )}
            <Text style={styles.statusText}>
              Un administrateur examine vos documents d’identité. Tant que votre compte n’est pas vérifié, vous ne pouvez pas recevoir de demandes de dépannage.
            </Text>
            <View style={styles.statusActions}>
              <AppButton title="Voir ma demande" onPress={() => router.push('/artisan/verification')} />
              <AppButton title="Se déconnecter" variant="danger" icon={<LogOut size={18} color={colors.white} />} onPress={handleLogout} />
            </View>
          </View>
        ) : (
          <>
            {currentIntervention ? (
              <View style={styles.section}>
                <Text style={styles.sectionTitle}>Intervention actuelle</Text>
                <InterventionCard repairRequest={currentIntervention} onPress={() => router.push(`/intervention/${currentIntervention.id}` as never)} />
              </View>
            ) : null}

            {pendingOffers.length > 0 ? (
              <View style={styles.section}>
                <View style={styles.sectionHeaderRow}>
                  <Text style={styles.sectionTitle}>Nouvelles demandes</Text>
                  <Text style={styles.counterBadge}>{pendingOffers.length}</Text>
                </View>
                <View style={styles.list}>
                  {pendingOffers.slice(0, 3).map((offer) => (
                    <IncomingRequestCard key={offer.id} offer={offer} onPress={() => router.push(`/offer-detail/${offer.id}` as never)} />
                  ))}
                </View>
              </View>
            ) : null}

            <View style={styles.section}>
              <Text style={styles.sectionTitle}>Demandes reçues</Text>
              {offers.length === 0 && activeInterventions.length === 0 ? (
                <EmptyState icon={<Inbox size={22} color={colors.primary} />} title="Aucune demande pour le moment" text="Les nouvelles demandes correspondant à votre métier apparaîtront ici." />
              ) : (
                <AppButton title="Voir toutes les demandes" variant="secondary" onPress={() => router.push('/requests')} />
              )}
            </View>
          </>
        )}

        {error ? <AppButton title="Réessayer" variant="secondary" onPress={() => load(true)} disabled={saving} /> : null}
      </ScrollView>
    </ScreenContainer>
  );
}

const styles = StyleSheet.create({
  header: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingVertical: 14, borderBottomWidth: 1, borderBottomColor: colors.border },
  headerActions: { flexDirection: 'row', alignItems: 'center', gap: 10 },
  headerLeft: { flex: 1, gap: 8 },
  greeting: { color: colors.text, fontSize: 18, fontWeight: '700' },
  badgeRow: { flexDirection: 'row', alignItems: 'center', gap: 8, flexWrap: 'wrap' },
  availToggle: { flexDirection: 'row', alignItems: 'center', gap: 6, borderRadius: 999, borderWidth: 1, borderColor: colors.border, backgroundColor: colors.white, paddingHorizontal: 12, paddingVertical: 9 },
  availToggleActive: { backgroundColor: colors.success, borderColor: colors.success },
  availToggleBusy: { backgroundColor: '#FFF3E2', borderColor: colors.urgent },
  availToggleText: { color: colors.muted, fontSize: 13, fontWeight: '700' },
  availToggleTextActive: { color: colors.white },
  avatarButton: { width: 42, height: 42, borderRadius: 12, backgroundColor: colors.primaryLight, overflow: 'hidden' },
  avatarImage: { width: '100%', height: '100%' },
  avatarFallback: { flex: 1, alignItems: 'center', justifyContent: 'center' },
  avatarText: { color: colors.primary, fontSize: 16, fontWeight: '700' },
  scrollContent: { paddingVertical: 20, paddingBottom: 84, gap: 20 },
  statusScreen: { alignItems: 'center', paddingHorizontal: 8, paddingTop: 24, gap: 12 },
  statusIcon: { width: 72, height: 72, borderRadius: 24, backgroundColor: '#FFF3E2', alignItems: 'center', justifyContent: 'center', marginBottom: 4 },
  statusIconRejected: { backgroundColor: '#FEE4E2' },
  statusTitle: { color: colors.text, fontSize: 20, fontWeight: '700', textAlign: 'center' },
  statusDate: { color: colors.primary, fontSize: 14, fontWeight: '600', textAlign: 'center' },
  statusText: { color: colors.muted, fontSize: 14, lineHeight: 21, textAlign: 'center' },
  statusActions: { alignSelf: 'stretch', gap: 12, marginTop: 12 },
  section: { gap: 12 },
  sectionHeaderRow: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' },
  sectionTitle: { fontSize: 18, fontWeight: '700', color: colors.text },
  counterBadge: { minWidth: 28, textAlign: 'center', overflow: 'hidden', borderRadius: 999, backgroundColor: colors.primary, color: colors.white, fontSize: 13, fontWeight: '800', paddingHorizontal: 8, paddingVertical: 4 },
  list: { gap: 12 },
});
