import { useCallback, useEffect, useState } from 'react';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { useFocusEffect } from '@react-navigation/native';
import { Linking, Modal, Pressable, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { CalendarDays, ChevronLeft, CircleCheck, Clock3, MapPin, Star, X } from 'lucide-react-native';
import { colors } from '../../components/theme';
import { AppButton, ErrorMessage, LoadingState, Logo, RatingSelector, ScreenContainer, StarRating, Textarea } from '../../components/ui';
import { categoryName, ConfirmationModal, ContactActions, formatLocation, formatRequestDate, formatTime, getCategoryIcon, RequestImages, RequestStatusBadge, Timeline } from '../../components/repairRequests';
import { ApiError, cancelRepairRequest, getRepairRequest, getRepairRequestReview, RepairRequest, Review, submitReview as submitReviewApi } from '../../lib/api';
import { buildTelUrl, buildWhatsAppUrl } from '../../lib/contact';
import { restoreSession } from '../../lib/session';

export default function RepairRequestDetailScreen() {
  const router = useRouter();
  const { id } = useLocalSearchParams<{ id: string }>();
  const [token, setToken] = useState<string | null>(null);
  const [repairRequest, setRepairRequest] = useState<RepairRequest | null>(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [cancelModalVisible, setCancelModalVisible] = useState(false);
  const [cancelling, setCancelling] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [review, setReview] = useState<Review | null>(null);
  const [reviewLoading, setReviewLoading] = useState(false);
  const [reviewModalVisible, setReviewModalVisible] = useState(false);
  const [submittingReview, setSubmittingReview] = useState(false);
  const [tempRating, setTempRating] = useState(0);
  const [tempComment, setTempComment] = useState('');

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
      if (session.user.role !== 'client') {
        router.replace('/requests');
        return;
      }
      setToken(session.token);
      setRepairRequest(await getRepairRequest(session.token, id));
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Impossible de charger cette demande.');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [id, router]);

  useFocusEffect(useCallback(() => {
    load();
  }, [load]));

  useEffect(() => {
    if (!token || !repairRequest || !['accepted', 'in_progress'].includes(repairRequest.status)) return undefined;
    const interval = setInterval(() => {
      getRepairRequest(token, repairRequest.id).then(setRepairRequest).catch(() => undefined);
    }, 12000);
    return () => clearInterval(interval);
  }, [token, repairRequest?.id, repairRequest?.status]);

  const confirmCancel = async () => {
    if (!token || !repairRequest || cancelling) return;
    setCancelling(true);
    setError(null);
    try {
      const updated = await cancelRepairRequest(token, repairRequest.id);
      setRepairRequest(updated);
      setCancelModalVisible(false);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Annulation impossible.');
    } finally {
      setCancelling(false);
    }
  };

  const openUrl = async (url: string | null) => {
    if (!url) return;
    const canOpen = await Linking.canOpenURL(url).catch(() => false);
    if (canOpen) await Linking.openURL(url);
  };

  const fetchReview = useCallback(async (t: string) => {
    if (!repairRequest) return;
    setReviewLoading(true);
    try {
      const existing = await getRepairRequestReview(t, repairRequest.id);
      setReview(existing);
    } catch {
      setReview(null);
    } finally {
      setReviewLoading(false);
    }
  }, [repairRequest?.id]);

  useEffect(() => {
    if (token && repairRequest?.status === 'completed') {
      fetchReview(token);
    }
  }, [token, repairRequest?.status, fetchReview]);

  const submitReview = async () => {
    if (!token || !repairRequest || submittingReview) return;
    setSubmittingReview(true);
    setError(null);
    try {
      await submitReviewApi(token, repairRequest.id, {
        rating: tempRating,
        comment: tempComment.trim(),
      });
      setReviewModalVisible(false);
      setTempRating(0);
      setTempComment('');
      fetchReview(token);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Impossible d’enregistrer votre avis.');
    } finally {
      setSubmittingReview(false);
    }
  };

  if (loading) {
    return <ScreenContainer><LoadingState label="Chargement du détail..." /></ScreenContainer>;
  }

  if (!repairRequest) {
    return (
      <ScreenContainer>
        <View style={styles.topBar}><Logo compact /></View>
        <View style={styles.center}><ErrorMessage message={error || 'Demande introuvable.'} /><AppButton title="Retour" variant="secondary" onPress={() => router.back()} /></View>
      </ScreenContainer>
    );
  }

  const Icon = getCategoryIcon(repairRequest.category.icon);
  const canCancel = repairRequest.status === 'pending' || repairRequest.status === 'awaiting_artisan';
  const rejected = repairRequest.status === 'pending' && repairRequest.last_offer?.status === 'rejected';
  const artisanPhone = repairRequest.artisan?.phone || null;
  const telUrl = buildTelUrl(artisanPhone);
  const whatsappUrl = buildWhatsAppUrl(artisanPhone);

  return (
    <ScreenContainer>
      <View style={styles.topBar}>
        <Pressable onPress={() => router.back()} style={styles.backButton}>
          <ChevronLeft size={22} color={colors.text} />
        </Pressable>
        <Logo compact />
      </View>

      <ScrollView
        contentContainerStyle={styles.content}
        showsVerticalScrollIndicator={false}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => load(true)} tintColor={colors.primary} />}
      >
        <View style={styles.titleRow}>
          <View style={styles.titleWrap}>
            <Text style={styles.title}>Détail de la demande</Text>
            <Text style={styles.reference}>{repairRequest.reference}</Text>
          </View>
          <RequestStatusBadge status={repairRequest.status} />
        </View>

        <ErrorMessage message={error} />

        {rejected ? (
          <View style={styles.rejectedCard}>
            <View style={styles.stateIconDanger}><X size={24} color={colors.danger} /></View>
            <View style={styles.flex}>
              <Text style={styles.stateTitle}>Le dépanneur n’est pas disponible</Text>
              <Text style={styles.stateText}>Cet artisan ne peut pas prendre en charge votre demande. Vous pouvez en choisir un autre.</Text>
              <AppButton title="Voir d’autres dépanneurs" onPress={() => router.push(`/available-artisans/${repairRequest.id}` as never)} />
            </View>
          </View>
        ) : null}

        {repairRequest.status === 'awaiting_artisan' && repairRequest.current_offer?.artisan ? (
          <View style={styles.awaitingCard}>
            <Clock3 size={24} color={colors.urgent} />
            <View style={styles.flex}>
              <Text style={styles.stateTitle}>Réponse en attente</Text>
              <Text style={styles.stateText}>Envoyée à {repairRequest.current_offer.artisan.name}. Le dépanneur doit accepter ou refuser l’intervention.</Text>
            </View>
          </View>
        ) : null}

        {repairRequest.status === 'accepted' && repairRequest.artisan ? (
          <View style={styles.acceptedCard}>
            <View style={styles.stateIconSuccess}><CircleCheck size={24} color={colors.success} /></View>
            <View style={styles.flex}>
              <Text style={styles.stateTitle}>Votre dépanneur a accepté</Text>
              <Text style={styles.stateText}>{repairRequest.artisan.name} a accepté votre demande. Vous pouvez maintenant le contacter.</Text>
              <ArtisanContactBox repairRequest={repairRequest} />
              <ContactActions onCall={() => openUrl(telUrl)} onWhatsApp={() => openUrl(whatsappUrl)} callDisabled={!telUrl} whatsappDisabled={!whatsappUrl} />
            </View>
          </View>
        ) : null}

        {repairRequest.status === 'in_progress' && repairRequest.artisan ? (
          <View style={styles.inProgressCard}>
            <Clock3 size={26} color={colors.primary} />
            <View style={styles.flex}>
              <Text style={styles.stateTitle}>Intervention en cours</Text>
              <Text style={styles.stateText}>{repairRequest.artisan.name} a commencé la prise en charge de votre panne.</Text>
              <Text style={styles.startedText}>Commencée à {formatTime(repairRequest.started_at)}</Text>
              <ArtisanContactBox repairRequest={repairRequest} />
              <ContactActions onCall={() => openUrl(telUrl)} onWhatsApp={() => openUrl(whatsappUrl)} callDisabled={!telUrl} whatsappDisabled={!whatsappUrl} />
            </View>
          </View>
        ) : null}

        {repairRequest.status === 'completed' ? (
          <View style={styles.completedCard}>
            <View style={styles.completedIcon}><CircleCheck size={36} color={colors.success} /></View>
            <Text style={styles.completedTitle}>Dépannage terminé</Text>
            <Text style={styles.completedText}>Cette intervention a été marquée comme terminée.</Text>
            <View style={styles.completedSummary}>
              <Info label="Artisan" value={repairRequest.artisan?.name || '—'} />
              <Info label="Service" value={repairRequest.category.name} />
              <Info label="Commencée" value={formatTime(repairRequest.started_at)} />
              <Info label="Terminée" value={formatTime(repairRequest.completed_at)} />
            </View>

            {reviewLoading ? (
              <LoadingState label="Chargement de l’avis..." />
            ) : review ? (
              <View style={styles.reviewCard}>
                <Text style={styles.reviewCardTitle}>Votre avis</Text>
                <View style={styles.reviewRatingRow}>
                  <StarRating rating={review.rating} size={16} showValue={false} />
                  <Text style={styles.reviewRatingValue}>{review.rating}/5</Text>
                </View>
                {review.comment ? <Text style={styles.reviewComment}>{review.comment}</Text> : null}
                <Text style={styles.reviewDate}>{formatRequestDate(review.created_at)}</Text>
              </View>
            ) : (
              <AppButton title="Laisser un avis" onPress={() => setReviewModalVisible(true)} icon={<Star size={18} color={colors.white} />} />
            )}

            <AppButton title="Signaler un litige" variant="secondary" onPress={() => router.push(`/disputes/new?repairRequestId=${repairRequest.id}` as never)} />
            <AppButton title="Retour à mes demandes" onPress={() => router.replace('/requests')} icon={<CircleCheck size={18} color={colors.white} />} />
          </View>
        ) : null}

        {repairRequest.status === 'pending' && repairRequest.review ? (
          <View style={styles.reviewCard}>
            <Text style={styles.reviewCardTitle}>Votre avis</Text>
            <View style={styles.reviewRatingRow}>
              <StarRating rating={repairRequest.review.rating} size={16} showValue={false} />
              <Text style={styles.reviewRatingValue}>{repairRequest.review.rating}/5</Text>
            </View>
            {repairRequest.review.comment ? <Text style={styles.reviewComment}>{repairRequest.review.comment}</Text> : null}
            <Text style={styles.reviewDate}>{formatRequestDate(repairRequest.review.created_at)}</Text>
          </View>
        ) : null}

        <View style={styles.detailsBlock}>
          <Text style={styles.sectionHeading}>Détails de la panne</Text>
          <View style={styles.heroCategory}>
            <View style={styles.heroIcon}><Icon size={20} color={colors.primary} /></View>
            <Text style={styles.heroCategoryText}>{repairRequest.category.name}</Text>
          </View>
          {repairRequest.title ? <Text style={styles.heroTitle}>{repairRequest.title}</Text> : null}
          <Text style={styles.heroDescription}>{repairRequest.description}</Text>
        </View>

        <RequestImages images={repairRequest.images} />

        <View style={styles.detailsBlock}>
          <Text style={styles.sectionHeading}>Informations</Text>
          <InfoRow icon={<MapPin size={18} color={colors.muted} />} label="Localisation" value={formatLocation(repairRequest.location.district, repairRequest.location.city)} />
          {repairRequest.location.address_details ? <InfoRow label="Indications complémentaires" value={repairRequest.location.address_details} /> : null}
          <View style={styles.divider} />
          <InfoRow icon={<CalendarDays size={18} color={colors.muted} />} label="Date de création" value={formatRequestDate(repairRequest.created_at)} />
        </View>

        <Timeline repairRequest={repairRequest} />

        {repairRequest.status === 'pending' ? (
          <AppButton title="Trouver un dépanneur" onPress={() => router.push(`/available-artisans/${repairRequest.id}` as never)} />
        ) : null}

        {repairRequest.status === 'awaiting_artisan' ? (
          <AppButton title="Voir l’attente de réponse" variant="secondary" onPress={() => router.push(`/awaiting-response/${repairRequest.id}` as never)} />
        ) : null}

        {canCancel ? (
          <AppButton title="Annuler la demande" variant="secondary" onPress={() => setCancelModalVisible(true)} />
        ) : null}
          </ScrollView>

        <Modal
          visible={reviewModalVisible}
          transparent
          animationType="fade"
          onRequestClose={() => !submittingReview && setReviewModalVisible(false)}
        >
          <View style={styles.modalBackdrop}>
            <View style={styles.modalCard}>
              <Pressable onPress={() => !submittingReview && setReviewModalVisible(false)} style={styles.modalClose} disabled={submittingReview}>
                <X size={18} color={colors.muted} />
              </Pressable>
              <Text style={styles.modalTitle}>Laisser un avis</Text>
              <Text style={styles.modalText}>Donnez votre avis sur l’intervention de {repairRequest.artisan?.name || 'cet artisan'}.</Text>

              <Text style={styles.modalLabel}>Note</Text>
              <RatingSelector rating={tempRating} onPress={setTempRating} size={28} />
              {tempRating === 0 ? <Text style={styles.errorText}>Veuillez sélectionner une note.</Text> : null}

              <Textarea
                label="Commentaire (optionnel)"
                value={tempComment}
                onChangeText={setTempComment}
                placeholder="Décrivez votre expérience..."
                maxLength={500}
              />

              <View style={styles.modalActions}>
                <AppButton title="Annuler" variant="secondary" onPress={() => { setReviewModalVisible(false); setTempRating(0); setTempComment(''); }} disabled={submittingReview} />
                <AppButton title="Enregistrer" onPress={submitReview} loading={submittingReview} disabled={submittingReview || tempRating === 0} />
              </View>
            </View>
          </View>
        </Modal>

        <ConfirmationModal
        visible={cancelModalVisible}
        title="Annuler cette demande ?"
        text="Si une proposition est en attente, elle sera également annulée."
        cancelLabel="Garder la demande"
        confirmLabel="Oui, annuler"
        loading={cancelling}
        onCancel={() => setCancelModalVisible(false)}
        onConfirm={confirmCancel}
      />
    </ScreenContainer>
  );
}

function ArtisanContactBox({ repairRequest }: { repairRequest: RepairRequest }) {
  const artisan = repairRequest.artisan;

  return (
    <View style={styles.contactBox}>
      <Text style={styles.contactName}>{artisan?.name || 'Dépanneur'}</Text>
      <Text style={styles.contactMeta}>{categoryName(artisan)}</Text>
      <Text style={styles.contactMeta}>{formatLocation(artisan?.district, artisan?.city)}</Text>
      {artisan?.phone ? <Text style={styles.contactPhone}>{artisan.phone}</Text> : null}
    </View>
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

function InfoRow({ icon, label, value }: { icon?: React.ReactNode; label: string; value: string }) {
  return (
    <View style={styles.infoRowLine}>
      {icon ? <View style={styles.infoRowIcon}>{icon}</View> : null}
      <View style={styles.infoRowText}>
        <Text style={styles.infoRowLabel}>{label}</Text>
        <Text style={styles.infoRowValue}>{value}</Text>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  topBar: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingVertical: 14, borderBottomWidth: 1, borderBottomColor: colors.border },
  backButton: { width: 40, height: 40, borderRadius: 12, backgroundColor: colors.white, borderWidth: 1, borderColor: colors.border, alignItems: 'center', justifyContent: 'center' },
  content: { paddingVertical: 20, paddingBottom: 34, gap: 22 },
  titleRow: { flexDirection: 'row', alignItems: 'flex-start', justifyContent: 'space-between', gap: 12 },
  titleWrap: { flex: 1 },
  title: { color: colors.text, fontSize: 25, lineHeight: 32, fontWeight: '700' },
  reference: { color: colors.primary, fontSize: 15, fontWeight: '700', marginTop: 4 },
  flex: { flex: 1, gap: 10 },
  detailsBlock: { gap: 12 },
  sectionHeading: { color: colors.muted, fontSize: 12, fontWeight: '700', textTransform: 'uppercase', letterSpacing: 0.4 },
  heroCategory: { flexDirection: 'row', alignItems: 'center', gap: 10 },
  heroIcon: { width: 36, height: 36, borderRadius: 18, backgroundColor: colors.primaryLight, alignItems: 'center', justifyContent: 'center' },
  heroCategoryText: { color: colors.primary, fontSize: 14, fontWeight: '700' },
  heroTitle: { color: colors.text, fontSize: 22, lineHeight: 29, fontWeight: '700' },
  heroDescription: { color: colors.muted, fontSize: 15, lineHeight: 22 },
  infoRowLine: { flexDirection: 'row', alignItems: 'center', gap: 12 },
  infoRowIcon: { width: 34, height: 34, borderRadius: 17, backgroundColor: '#F2F4F7', alignItems: 'center', justifyContent: 'center' },
  infoRowText: { flex: 1 },
  infoRowLabel: { color: colors.muted, fontSize: 12, fontWeight: '700' },
  infoRowValue: { color: colors.text, fontSize: 15, fontWeight: '700', lineHeight: 21, marginTop: 2 },
  divider: { height: 1, backgroundColor: colors.border, marginVertical: 2 },
  center: { flex: 1, justifyContent: 'center' },
  acceptedCard: { flexDirection: 'row', gap: 12, backgroundColor: '#F6FEF9', borderRadius: 12, borderWidth: 1, borderColor: '#ABEFC6', padding: 16 },
  inProgressCard: { flexDirection: 'row', gap: 12, backgroundColor: colors.primaryLight, borderRadius: 12, borderWidth: 1, borderColor: '#B2CCFF', padding: 16 },
  awaitingCard: { flexDirection: 'row', gap: 12, backgroundColor: '#FFFAEB', borderRadius: 12, borderWidth: 1, borderColor: '#FEDF89', padding: 16 },
  rejectedCard: { flexDirection: 'row', gap: 12, backgroundColor: '#FFFBFA', borderRadius: 12, borderWidth: 1, borderColor: '#FECDCA', padding: 16 },
  stateIconSuccess: { width: 40, height: 40, borderRadius: 12, backgroundColor: '#E7F9F0', alignItems: 'center', justifyContent: 'center' },
  stateIconDanger: { width: 40, height: 40, borderRadius: 12, backgroundColor: '#FEE4E2', alignItems: 'center', justifyContent: 'center' },
  stateTitle: { color: colors.text, fontSize: 18, fontWeight: '700', lineHeight: 24 },
  stateText: { color: colors.muted, fontSize: 14, lineHeight: 21 },
  startedText: { color: colors.primary, fontSize: 14, fontWeight: '800' },
  contactBox: { backgroundColor: colors.white, borderRadius: 12, borderWidth: 1, borderColor: colors.border, padding: 12, gap: 4 },
  contactName: { color: colors.text, fontSize: 16, fontWeight: '700' },
  contactMeta: { color: colors.muted, fontSize: 13, fontWeight: '600' },
  contactPhone: { color: colors.primary, fontSize: 15, fontWeight: '800', marginTop: 4 },
  completedCard: { backgroundColor: colors.white, borderRadius: 12, borderWidth: 1, borderColor: colors.border, padding: 18, gap: 14, alignItems: 'stretch' },
  completedIcon: { alignSelf: 'center', width: 72, height: 72, borderRadius: 18, backgroundColor: '#E7F9F0', alignItems: 'center', justifyContent: 'center' },
  completedTitle: { color: colors.text, fontSize: 24, fontWeight: '700', textAlign: 'center' },
  completedText: { color: colors.muted, fontSize: 15, lineHeight: 22, textAlign: 'center' },
  completedSummary: { backgroundColor: colors.background, borderRadius: 12, padding: 14, gap: 10 },
  infoBlock: { gap: 3 },
  infoLabel: { color: colors.muted, fontSize: 12, fontWeight: '700' },
  infoValue: { color: colors.text, fontSize: 15, fontWeight: '700', lineHeight: 21 },
  reviewCard: { backgroundColor: colors.white, borderRadius: 12, borderWidth: 1, borderColor: colors.border, padding: 14, gap: 8 },
  reviewCardTitle: { color: colors.text, fontSize: 14, fontWeight: '700' },
  reviewRatingRow: { flexDirection: 'row', alignItems: 'center', gap: 6 },
  reviewRatingValue: { color: colors.muted, fontSize: 13, fontWeight: '700' },
  reviewComment: { color: colors.text, fontSize: 14, lineHeight: 21 },
  reviewDate: { color: colors.muted, fontSize: 12, fontWeight: '600' },
  modalBackdrop: { flex: 1, backgroundColor: 'rgba(16, 24, 40, 0.42)', alignItems: 'center', justifyContent: 'center', padding: 22 },
  modalCard: { width: '100%', borderRadius: 12, backgroundColor: colors.white, padding: 18, gap: 12 },
  modalClose: { position: 'absolute', top: 12, right: 12, width: 32, height: 32, alignItems: 'center', justifyContent: 'center' },
  modalTitle: { color: colors.text, fontSize: 21, fontWeight: '700', paddingRight: 34 },
  modalText: { color: colors.muted, fontSize: 15, lineHeight: 22 },
  modalLabel: { color: colors.text, fontSize: 13, fontWeight: '700' },
  modalActions: { flexDirection: 'row', gap: 10, marginTop: 4 },
  errorText: { color: colors.danger, fontSize: 12, marginTop: -4 },
});