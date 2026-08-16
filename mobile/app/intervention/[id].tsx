import { useCallback, useState } from 'react';
import { useFocusEffect, useLocalSearchParams, useRouter } from 'expo-router';
import { Linking, Pressable, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { ChevronLeft, CircleCheck, Clock3, MapPin, Play, User, Wrench } from 'lucide-react-native';
import { colors } from '../../components/theme';
import { AppButton, ErrorMessage, LoadingState, Logo, ScreenContainer, StarRating } from '../../components/ui';
import { categoryName, ConfirmationModal, ContactActions, formatLocation, formatRequestDate, formatTime, getCategoryIcon, InterventionStatusBadge, RequestImages } from '../../components/repairRequests';
import { ApiError, completeRepairRequestIntervention, fetchArtisanProfile, getArtisanRepairRequest, RepairRequest, startRepairRequestIntervention } from '../../lib/api';
import { buildTelUrl, buildWhatsAppUrl } from '../../lib/contact';
import { restoreSession, saveUser } from '../../lib/session';

export default function InterventionDetailScreen() {
  const router = useRouter();
  const { id } = useLocalSearchParams<{ id: string }>();
  const [token, setToken] = useState<string | null>(null);
  const [repairRequest, setRepairRequest] = useState<RepairRequest | null>(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [startModalVisible, setStartModalVisible] = useState(false);
  const [completeModalVisible, setCompleteModalVisible] = useState(false);
  const [submitting, setSubmitting] = useState(false);
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
      setToken(session.token);
      setRepairRequest(await getArtisanRepairRequest(session.token, id));
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Impossible de charger cette intervention.');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [id, router]);

  useFocusEffect(useCallback(() => {
    load();
  }, [load]));

  const startIntervention = async () => {
    if (!token || !repairRequest || submitting) return;
    setSubmitting(true);
    setError(null);
    try {
      const updated = await startRepairRequestIntervention(token, repairRequest.id);
      setRepairRequest(updated);
      setStartModalVisible(false);
      await load(true);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Démarrage impossible.');
    } finally {
      setSubmitting(false);
    }
  };

  const completeIntervention = async () => {
    if (!token || !repairRequest || submitting) return;
    setSubmitting(true);
    setError(null);
    try {
      const updated = await completeRepairRequestIntervention(token, repairRequest.id);
      setRepairRequest(updated);
      setCompleteModalVisible(false);
const session = await restoreSession();
      if (session) {
        const profileResponse = await fetchArtisanProfile(session.token);
        await saveUser({ ...session.user, artisan_profile: profileResponse.profile, artisanProfile: profileResponse.profile });
      }
      await load(true);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Clôture impossible.');
    } finally {
      setSubmitting(false);
    }
  };

  const openUrl = async (url: string | null) => {
    if (!url) return;
    const canOpen = await Linking.canOpenURL(url).catch(() => false);
    if (canOpen) await Linking.openURL(url);
  };

  if (loading) {
    return <ScreenContainer><LoadingState label="Chargement de l’intervention..." /></ScreenContainer>;
  }

  if (!repairRequest) {
    return (
      <ScreenContainer>
        <View style={styles.topBar}><Logo compact /></View>
        <View style={styles.center}><ErrorMessage message={error || 'Intervention introuvable.'} /><AppButton title="Retour" variant="secondary" onPress={() => router.back()} /></View>
      </ScreenContainer>
    );
  }

  const Icon = getCategoryIcon(repairRequest.category.icon);
  const client = repairRequest.client;
  const phone = client?.phone || null;
  const telUrl = buildTelUrl(phone);
  const whatsappUrl = buildWhatsAppUrl(phone);
  const isAccepted = repairRequest.status === 'accepted';
  const isInProgress = repairRequest.status === 'in_progress';
  const isCompleted = repairRequest.status === 'completed';

  return (
    <ScreenContainer>
      <View style={styles.topBar}>
        <Pressable onPress={() => router.back()} style={styles.backButton}>
          <ChevronLeft size={22} color={colors.text} />
        </Pressable>
        <View style={styles.headerTitleWrap}>
          <Text style={styles.headerTitle}>Intervention</Text>
          <Text style={styles.reference}>{repairRequest.reference}</Text>
        </View>
      </View>

      <ScrollView
        contentContainerStyle={styles.content}
        showsVerticalScrollIndicator={false}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => load(true)} tintColor={colors.primary} />}
      >
        <ErrorMessage message={error} />

{isCompleted ? (
          <View style={styles.successCard}>
            <View style={styles.successIcon}><CircleCheck size={34} color={colors.success} /></View>
            <Text style={styles.successTitle}>Intervention terminée</Text>
            <Text style={styles.successText}>Le dépannage a été clôturé avec succès. Vous êtes maintenant disponible pour recevoir une nouvelle demande.</Text>
            <View style={styles.summaryCard}>
              <Info label="Référence" value={repairRequest.reference} />
              <Info label="Client" value={client?.name || 'Client'} />
              <Info label="Catégorie" value={repairRequest.category.name} />
              <Info label="Terminée à" value={formatTime(repairRequest.completed_at)} />
            </View>
            {repairRequest.review ? (
              <View style={styles.reviewCard}>
                <Text style={styles.reviewCardTitle}>Avis du client</Text>
                <View style={styles.reviewRatingRow}>
                  <StarRating rating={repairRequest.review.rating} size={16} showValue={false} />
                  <Text style={styles.reviewRatingValue}>{repairRequest.review.rating}/5</Text>
                </View>
                {repairRequest.review.comment ? <Text style={styles.reviewComment}>{repairRequest.review.comment}</Text> : null}
                <Text style={styles.reviewDate}>{formatRequestDate(repairRequest.review.created_at)}</Text>
              </View>
            ) : null}
            <AppButton title="Signaler un litige" variant="secondary" onPress={() => router.push(`/disputes/new?repairRequestId=${repairRequest.id}` as never)} />
            <AppButton title="Retour à l’accueil" onPress={() => router.replace('/')} icon={<CircleCheck size={18} color={colors.white} />} />
          </View>
        ) : (
          <>
            <View style={styles.titleRow}>
              <View style={styles.titleWrap}>
                <Text style={styles.title}>{isInProgress ? 'Intervention en cours' : 'Intervention acceptée'}</Text>
                {isInProgress && repairRequest.started_at ? <Text style={styles.subtitle}>Commencée à {formatTime(repairRequest.started_at)}</Text> : null}
              </View>
              <InterventionStatusBadge status={repairRequest.status} />
            </View>

            <View style={styles.card}>
              <View style={styles.cardHeader}><User size={18} color={colors.primary} /><Text style={styles.cardTitle}>Client</Text></View>
              <Info label="Nom" value={client?.name || 'Client'} />
              <Info label="Téléphone" value={phone || 'Non renseigné'} />
            </View>

            <View style={styles.card}>
              <View style={styles.cardHeader}><MapPin size={18} color={colors.primary} /><Text style={styles.cardTitle}>Localisation</Text></View>
              <Info label="Ville" value={repairRequest.location.city} />
              <Info label="Quartier" value={repairRequest.location.district} />
              <Info label="Indication" value={repairRequest.location.address_details || '—'} />
            </View>

            <View style={styles.card}>
              <View style={styles.categoryRow}>
                <View style={styles.iconBox}><Icon size={22} color={colors.primary} /></View>
                <View style={styles.flex}>
                  <Text style={styles.sectionLabel}>Catégorie</Text>
                  <Text style={styles.strong}>{repairRequest.category.name}</Text>
                </View>
              </View>
            </View>

            <View style={styles.card}>
              <View style={styles.cardHeader}><Wrench size={18} color={colors.primary} /><Text style={styles.cardTitle}>Problème</Text></View>
              {repairRequest.title ? <Text style={styles.strong}>{repairRequest.title}</Text> : null}
              <Text style={styles.bodyText}>{repairRequest.description}</Text>
            </View>

            <RequestImages images={repairRequest.images} />

            <ContactActions
              onCall={() => openUrl(telUrl)}
              onWhatsApp={() => openUrl(whatsappUrl)}
              callDisabled={!telUrl}
              whatsappDisabled={!whatsappUrl}
            />

            {isAccepted ? (
              <AppButton title="Commencer l’intervention" onPress={() => setStartModalVisible(true)} disabled={submitting} icon={<Play size={18} color={colors.white} />} />
            ) : null}

            {isInProgress ? (
              <View style={styles.actionBlock}>
                <View style={styles.startedRow}>
                  <Clock3 size={18} color={colors.primary} />
                  <Text style={styles.startedText}>Commencée à {formatTime(repairRequest.started_at)}</Text>
                </View>
                <AppButton title="Terminer l’intervention" onPress={() => setCompleteModalVisible(true)} disabled={submitting} icon={<CircleCheck size={18} color={colors.white} />} />
              </View>
            ) : null}
          </>
        )}
      </ScrollView>

      <ConfirmationModal
        visible={startModalVisible}
        title="Commencer l’intervention ?"
        text="Confirmez que vous avez commencé la prise en charge de cette panne."
        cancelLabel="Pas encore"
        confirmLabel="Commencer"
        confirmVariant="primary"
        loading={submitting}
        onCancel={() => !submitting && setStartModalVisible(false)}
        onConfirm={startIntervention}
      />

      <ConfirmationModal
        visible={completeModalVisible}
        title="Terminer cette intervention ?"
        text="Confirmez que le dépannage est terminé."
        cancelLabel="Retour"
        confirmLabel="Confirmer la fin"
        confirmVariant="primary"
        loading={submitting}
        onCancel={() => !submitting && setCompleteModalVisible(false)}
        onConfirm={completeIntervention}
      />
    </ScreenContainer>
  );
}

function Info({ label, value }: { label: string; value: string }) {
  return (
    <View style={styles.infoBlock}>
      <Text style={styles.infoLabel}>{label}</Text>
      <Text style={styles.infoValue}>{value}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  topBar: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingVertical: 14, borderBottomWidth: 1, borderBottomColor: colors.border },
  backButton: { width: 40, height: 40, borderRadius: 12, backgroundColor: colors.white, borderWidth: 1, borderColor: colors.border, alignItems: 'center', justifyContent: 'center' },
  headerTitleWrap: { flex: 1 },
  headerTitle: { color: colors.text, fontSize: 20, fontWeight: '700' },
  reference: { color: colors.primary, fontSize: 13, fontWeight: '700', marginTop: 2 },
  content: { paddingVertical: 20, paddingBottom: 34, gap: 14 },
  center: { flex: 1, justifyContent: 'center' },
  titleRow: { flexDirection: 'row', alignItems: 'flex-start', justifyContent: 'space-between', gap: 12 },
  titleWrap: { flex: 1 },
  title: { color: colors.text, fontSize: 25, lineHeight: 32, fontWeight: '700' },
  subtitle: { color: colors.muted, fontSize: 14, marginTop: 4, fontWeight: '600' },
  card: { backgroundColor: colors.white, borderRadius: 12, borderWidth: 1, borderColor: colors.border, padding: 16, gap: 10 },
  cardHeader: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  cardTitle: { color: colors.text, fontSize: 17, fontWeight: '700' },
  infoBlock: { gap: 3 },
  infoLabel: { color: colors.muted, fontSize: 12, fontWeight: '700' },
  infoValue: { color: colors.text, fontSize: 15, fontWeight: '700', lineHeight: 21 },
  categoryRow: { flexDirection: 'row', alignItems: 'center', gap: 12 },
  iconBox: { width: 42, height: 42, borderRadius: 12, backgroundColor: colors.primaryLight, alignItems: 'center', justifyContent: 'center' },
  flex: { flex: 1 },
  sectionLabel: { color: colors.muted, fontSize: 13, fontWeight: '700' },
  strong: { color: colors.text, fontSize: 16, fontWeight: '700', lineHeight: 22 },
  bodyText: { color: colors.muted, fontSize: 14, lineHeight: 21 },
  actionBlock: { gap: 12 },
  startedRow: { flexDirection: 'row', alignItems: 'center', gap: 8, backgroundColor: colors.primaryLight, borderRadius: 12, padding: 12 },
  startedText: { color: colors.primary, fontSize: 14, fontWeight: '700' },
  successCard: { backgroundColor: colors.white, borderRadius: 12, borderWidth: 1, borderColor: colors.border, padding: 18, gap: 14, alignItems: 'stretch' },
  successIcon: { alignSelf: 'center', width: 70, height: 70, borderRadius: 18, backgroundColor: '#E7F9F0', alignItems: 'center', justifyContent: 'center' },
  successTitle: { color: colors.text, fontSize: 24, fontWeight: '700', textAlign: 'center' },
  successText: { color: colors.muted, fontSize: 15, lineHeight: 22, textAlign: 'center' },
summaryCard: { backgroundColor: colors.background, borderRadius: 12, padding: 14, gap: 10 },
  reviewCard: { backgroundColor: colors.white, borderRadius: 12, borderWidth: 1, borderColor: colors.border, padding: 14, gap: 8, marginTop: 8 },
  reviewCardTitle: { color: colors.text, fontSize: 14, fontWeight: '700' },
  reviewRatingRow: { flexDirection: 'row', alignItems: 'center', gap: 6 },
  reviewRatingValue: { color: colors.muted, fontSize: 13, fontWeight: '700' },
  reviewComment: { color: colors.text, fontSize: 14, lineHeight: 21 },
  reviewDate: { color: colors.muted, fontSize: 12, fontWeight: '600' },
});
