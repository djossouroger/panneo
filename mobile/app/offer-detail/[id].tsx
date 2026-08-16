import { useCallback, useState } from 'react';
import { useFocusEffect, useLocalSearchParams, useRouter } from 'expo-router';
import { Linking, Pressable, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { CalendarDays, Check, ChevronLeft, CircleCheck, MapPin, Phone, X } from 'lucide-react-native';
import { colors } from '../../components/theme';
import { AppButton, ErrorMessage, LoadingState, Logo, ScreenContainer } from '../../components/ui';
import { ConfirmationModal, formatLocation, formatRequestDate, getCategoryIcon, OfferStatusBadge, RequestImages } from '../../components/repairRequests';
import { acceptArtisanOffer, ApiError, getArtisanOffer, rejectArtisanOffer, RepairRequestOffer } from '../../lib/api';
import { buildTelUrl, buildWhatsAppUrl } from '../../lib/contact';
import { restoreSession } from '../../lib/session';

export default function OfferDetailScreen() {
  const router = useRouter();
  const { id } = useLocalSearchParams<{ id: string }>();
  const [token, setToken] = useState<string | null>(null);
  const [offer, setOffer] = useState<RepairRequestOffer | null>(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [acceptModalVisible, setAcceptModalVisible] = useState(false);
  const [rejectModalVisible, setRejectModalVisible] = useState(false);
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
      setOffer(await getArtisanOffer(session.token, id));
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

  const accept = async () => {
    if (!token || !offer || submitting) return;
    setSubmitting(true);
    setError(null);
    try {
      const updated = await acceptArtisanOffer(token, offer.id);
      setOffer(updated);
      setAcceptModalVisible(false);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Acceptation impossible.');
    } finally {
      setSubmitting(false);
    }
  };

  const reject = async () => {
    if (!token || !offer || submitting) return;
    setSubmitting(true);
    setError(null);
    try {
      const updated = await rejectArtisanOffer(token, offer.id);
      setOffer(updated);
      setRejectModalVisible(false);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Refus impossible.');
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
    return <ScreenContainer><LoadingState label="Chargement de la demande..." /></ScreenContainer>;
  }

  if (!offer || !offer.request) {
    return (
      <ScreenContainer>
        <View style={styles.topBar}><Logo compact /></View>
        <View style={styles.center}><ErrorMessage message={error || 'Offre introuvable.'} /><AppButton title="Retour" variant="secondary" onPress={() => router.back()} /></View>
      </ScreenContainer>
    );
  }

  const request = offer.request;
  const Icon = getCategoryIcon(request.category.icon);
  const canAnswer = offer.status === 'pending' && request.status === 'awaiting_artisan';
  const isCancelled = offer.status === 'cancelled' || request.status === 'cancelled';
  const isAccepted = offer.status === 'accepted';
  const clientPhone = request.client?.phone || null;
  const telUrl = buildTelUrl(clientPhone);
  const whatsappUrl = buildWhatsAppUrl(clientPhone);

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
            <Text style={styles.title}>Demande de dépannage</Text>
            <Text style={styles.reference}>{request.reference}</Text>
          </View>
          <OfferStatusBadge status={offer.status} />
        </View>

        <ErrorMessage message={error} />

        {isCancelled ? (
          <View style={styles.cancelledCard}>
            <X size={24} color={colors.danger} />
            <View style={styles.flex}>
              <Text style={styles.stateTitle}>Cette demande n’est plus disponible</Text>
              <Text style={styles.stateText}>Le client a annulé cette demande.</Text>
            </View>
          </View>
        ) : null}

        {isAccepted ? (
          <View style={styles.acceptedCard}>
            <CircleCheck size={26} color={colors.success} />
            <View style={styles.flex}>
              <Text style={styles.stateTitle}>Intervention acceptée</Text>
              <Text style={styles.stateText}>Vous pouvez maintenant contacter le client pour organiser votre arrivée.</Text>
              <View style={styles.contactBox}>
                <Info label="Client" value={request.client?.name || 'Client'} />
                <Info label="Téléphone" value={clientPhone || 'Non renseigné'} />
                <Info label="Localisation" value={formatLocation(request.location.district, request.location.city)} />
                <Info label="Indications" value={request.location.address_details || '—'} />
              </View>
              <View style={styles.actionsInline}>
                <AppButton title="Voir l’intervention" onPress={() => router.push(`/intervention/${request.id}` as never)} icon={<CircleCheck size={18} color={colors.white} />} />
                <AppButton title="Appeler le client" variant="secondary" onPress={() => openUrl(telUrl)} disabled={!telUrl} />
                <AppButton title="WhatsApp" variant="secondary" onPress={() => openUrl(whatsappUrl)} disabled={!whatsappUrl} />
              </View>
            </View>
          </View>
        ) : null}

        <View style={styles.detailsBlock}>
          <Text style={styles.sectionHeading}>Détails de la panne</Text>
          <View style={styles.heroCategory}>
            <View style={styles.heroIcon}><Icon size={20} color={colors.primary} /></View>
            <Text style={styles.heroCategoryText}>{request.category.name}</Text>
          </View>
          {request.title ? <Text style={styles.heroTitle}>{request.title}</Text> : null}
          <Text style={styles.heroDescription}>{request.description}</Text>
        </View>

        <RequestImages images={request.images} />

        <View style={styles.detailsBlock}>
          <Text style={styles.sectionHeading}>Informations</Text>
          <InfoRow icon={<MapPin size={18} color={colors.muted} />} label="Localisation" value={formatLocation(request.location.district, request.location.city)} />
          {request.location.address_details ? <InfoRow label="Indications complémentaires" value={request.location.address_details} /> : null}
          <View style={styles.divider} />
          <InfoRow icon={<CalendarDays size={18} color={colors.muted} />} label="Date de création" value={formatRequestDate(request.created_at)} />
        </View>

        {canAnswer ? (
          <View style={styles.actionRow}>
            <View style={styles.actionCell}><AppButton title="Refuser" variant="secondary" onPress={() => setRejectModalVisible(true)} disabled={submitting} /></View>
            <View style={styles.actionCell}><AppButton title="Accepter l’intervention" onPress={() => setAcceptModalVisible(true)} disabled={submitting} /></View>
          </View>
        ) : null}
      </ScrollView>

      <ConfirmationModal
        visible={acceptModalVisible}
        title="Accepter cette intervention ?"
        text="En acceptant, vous confirmez que vous êtes disponible pour prendre en charge cette demande."
        cancelLabel="Annuler"
        confirmLabel="Oui, accepter"
        confirmVariant="primary"
        loading={submitting}
        onCancel={() => setAcceptModalVisible(false)}
        onConfirm={accept}
      />

      <ConfirmationModal
        visible={rejectModalVisible}
        title="Refuser cette demande ?"
        text="La demande retournera au client afin qu’il puisse choisir un autre dépanneur."
        cancelLabel="Retour"
        confirmLabel="Refuser"
        loading={submitting}
        onCancel={() => setRejectModalVisible(false)}
        onConfirm={reject}
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
  center: { flex: 1, justifyContent: 'center' },
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
  cancelledCard: { flexDirection: 'row', gap: 12, backgroundColor: '#FFFBFA', borderRadius: 12, borderWidth: 1, borderColor: '#FECDCA', padding: 16 },
  acceptedCard: { flexDirection: 'row', gap: 12, backgroundColor: '#F6FEF9', borderRadius: 12, borderWidth: 1, borderColor: '#ABEFC6', padding: 16 },
  stateTitle: { color: colors.text, fontSize: 18, fontWeight: '700', lineHeight: 24 },
  stateText: { color: colors.muted, fontSize: 14, lineHeight: 21 },
  contactBox: { backgroundColor: colors.white, borderRadius: 12, borderWidth: 1, borderColor: colors.border, paddingHorizontal: 12 },
  infoBlock: { paddingVertical: 11, borderBottomWidth: 1, borderBottomColor: colors.border },
  infoLabel: { color: colors.muted, fontSize: 12, fontWeight: '700', marginBottom: 3 },
  infoValue: { color: colors.text, fontSize: 15, fontWeight: '700', lineHeight: 21 },
  actionsInline: { gap: 10 },
  actionRow: { flexDirection: 'row', gap: 10 },
  actionCell: { flex: 1 },
});