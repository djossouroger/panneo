import { useCallback, useEffect, useState } from 'react';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { Pressable, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { ChevronLeft, RefreshCw, SearchX } from 'lucide-react-native';
import { colors } from '../../components/theme';
import { AppButton, EmptyState, ErrorMessage, LoadingState, Logo, ScreenContainer } from '../../components/ui';
import { ArtisanCard, categoryName, ConfirmationModal, formatLocation, getCategoryIcon } from '../../components/repairRequests';
import { ApiError, AvailableArtisan, getAvailableArtisans, getRepairRequest, RepairRequest, sendRepairRequestOffer } from '../../lib/api';
import { restoreSession } from '../../lib/session';

export default function AvailableArtisansScreen() {
  const router = useRouter();
  const { id } = useLocalSearchParams<{ id: string }>();
  const [token, setToken] = useState<string | null>(null);
  const [repairRequest, setRepairRequest] = useState<RepairRequest | null>(null);
  const [artisans, setArtisans] = useState<AvailableArtisan[]>([]);
  const [selectedArtisan, setSelectedArtisan] = useState<AvailableArtisan | null>(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [sending, setSending] = useState(false);
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
      if (session.user.role !== 'client') {
        router.replace('/');
        return;
      }
      const [requestData, artisanData] = await Promise.all([
        getRepairRequest(session.token, id),
        getAvailableArtisans(session.token, id),
      ]);
      setToken(session.token);
      setRepairRequest(requestData);
      setArtisans(artisanData);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Impossible de charger les dépanneurs disponibles.');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [id, router]);

  useEffect(() => {
    load();
  }, [load]);

  const sendOffer = async () => {
    if (!token || !repairRequest || !selectedArtisan || sending) return;
    setSending(true);
    setError(null);
    try {
      await sendRepairRequestOffer(token, repairRequest.id, selectedArtisan.id);
      setSelectedArtisan(null);
      router.replace(`/awaiting-response/${repairRequest.id}` as never);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'La demande n’a pas pu être envoyée à cet artisan.');
    } finally {
      setSending(false);
    }
  };

  if (loading) {
    return <ScreenContainer><LoadingState label="Chargement des dépanneurs..." /></ScreenContainer>;
  }

  const Icon = getCategoryIcon(repairRequest?.category.icon);

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
        <View style={styles.titleBlock}>
          <Text style={styles.title}>Dépanneurs disponibles</Text>
          <Text style={styles.subtitle}>Professionnels correspondant à votre demande dans votre ville.</Text>
        </View>

        {repairRequest ? (
          <View style={styles.requestCard}>
            <View style={styles.requestHeader}>
              <View style={styles.iconBox}><Icon size={22} color={colors.primary} /></View>
              <View style={styles.flex}>
                <Text style={styles.requestCategory}>{repairRequest.category.name}</Text>
                <Text style={styles.requestTitle}>{repairRequest.title || repairRequest.description}</Text>
              </View>
            </View>
            <Text style={styles.locationText}>{formatLocation(repairRequest.location.district, repairRequest.location.city)}</Text>
          </View>
        ) : null}

        <ErrorMessage message={error} />

        <View style={styles.sectionHeader}>
          <Text style={styles.sectionTitle}>Professionnels disponibles</Text>
          <Pressable onPress={() => load(true)} disabled={refreshing} style={styles.refreshButton}>
            <RefreshCw size={18} color={colors.primary} />
          </Pressable>
        </View>

        {artisans.length === 0 ? (
          <View style={styles.emptyWrap}>
            <EmptyState
              icon={<SearchX size={24} color={colors.primary} />}
              title="Aucun dépanneur disponible"
              text="Aucun professionnel correspondant à votre besoin n’est disponible dans votre ville pour le moment."
            />
            <AppButton title="Réessayer" onPress={() => load(true)} loading={refreshing} disabled={refreshing} />
            {repairRequest ? <AppButton title="Retour à ma demande" variant="secondary" onPress={() => router.replace(`/repair-request/${repairRequest.id}` as never)} /> : null}
          </View>
        ) : (
          <View style={styles.list}>
             {artisans.map((artisan) => (
              <ArtisanCard
                key={artisan.id}
                artisan={artisan}
                onChoose={() => setSelectedArtisan(artisan)}
                onViewProfile={() => router.push(`/artisan/${artisan.id}` as never)}
                disabled={sending}
              />
            ))}
          </View>
        )}
      </ScrollView>

      <ConfirmationModal
        visible={Boolean(selectedArtisan)}
        title={`Envoyer votre demande à ${selectedArtisan?.name || 'cet artisan'} ?`}
        text="Il pourra consulter les informations nécessaires concernant votre panne avant de décider s’il peut intervenir."
        cancelLabel="Annuler"
        confirmLabel="Envoyer la demande"
        confirmVariant="primary"
        loading={sending}
        onCancel={() => !sending && setSelectedArtisan(null)}
        onConfirm={sendOffer}
      >
        {selectedArtisan ? (
          <View style={styles.modalSummary}>
            <Text style={styles.modalName}>{selectedArtisan.name}</Text>
            <Text style={styles.modalMeta}>{categoryName(selectedArtisan)}</Text>
            <Text style={styles.modalMeta}>{formatLocation(selectedArtisan.district, selectedArtisan.city)}</Text>
          </View>
        ) : null}
      </ConfirmationModal>
    </ScreenContainer>
  );
}

const styles = StyleSheet.create({
  topBar: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingVertical: 14, borderBottomWidth: 1, borderBottomColor: colors.border },
  backButton: { width: 40, height: 40, borderRadius: 12, backgroundColor: colors.white, borderWidth: 1, borderColor: colors.border, alignItems: 'center', justifyContent: 'center' },
  content: { paddingVertical: 20, paddingBottom: 34, gap: 14 },
  titleBlock: { gap: 6 },
  title: { color: colors.text, fontSize: 25, lineHeight: 32, fontWeight: '700' },
  subtitle: { color: colors.muted, fontSize: 15, lineHeight: 22 },
  requestCard: { backgroundColor: colors.white, borderRadius: 12, borderWidth: 1, borderColor: colors.border, padding: 16, gap: 12 },
  requestHeader: { flexDirection: 'row', gap: 12, alignItems: 'center' },
  iconBox: { width: 42, height: 42, borderRadius: 12, backgroundColor: colors.primaryLight, alignItems: 'center', justifyContent: 'center' },
  flex: { flex: 1 },
  requestCategory: { color: colors.primary, fontSize: 13, fontWeight: '700' },
  requestTitle: { color: colors.text, fontSize: 17, fontWeight: '700', lineHeight: 23, marginTop: 2 },
  locationText: { color: colors.muted, fontSize: 14, fontWeight: '600' },
  sectionHeader: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 12, marginTop: 4 },
  sectionTitle: { color: colors.text, fontSize: 18, fontWeight: '700' },
  refreshButton: { width: 40, height: 40, borderRadius: 12, backgroundColor: colors.white, borderWidth: 1, borderColor: colors.border, alignItems: 'center', justifyContent: 'center' },
  emptyWrap: { gap: 12 },
  list: { gap: 12 },
  modalSummary: { borderRadius: 12, backgroundColor: colors.background, padding: 12, gap: 4 },
  modalName: { color: colors.text, fontSize: 16, fontWeight: '700' },
  modalMeta: { color: colors.muted, fontSize: 13, fontWeight: '600' },
});