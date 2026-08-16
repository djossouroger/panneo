import { useCallback, useEffect, useState } from 'react';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { Pressable, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { ChevronLeft, CircleCheck, Clock3, RefreshCw, X } from 'lucide-react-native';
import { colors } from '../../components/theme';
import { AppButton, ErrorMessage, LoadingState, Logo, ScreenContainer } from '../../components/ui';
import { categoryName, formatLocation, getCategoryIcon, RequestStatusBadge } from '../../components/repairRequests';
import { ApiError, getRepairRequest, RepairRequest } from '../../lib/api';
import { restoreSession } from '../../lib/session';

export default function AwaitingResponseScreen() {
  const router = useRouter();
  const { id } = useLocalSearchParams<{ id: string }>();
  const [token, setToken] = useState<string | null>(null);
  const [repairRequest, setRepairRequest] = useState<RepairRequest | null>(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
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
      setToken(session.token);
      setRepairRequest(await getRepairRequest(session.token, id));
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Impossible de rafraîchir cette demande.');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [id, router]);

  useEffect(() => {
    load();
  }, [load]);

  useEffect(() => {
    if (!token || repairRequest?.status !== 'awaiting_artisan') return undefined;
    const interval = setInterval(() => {
      getRepairRequest(token, id).then(setRepairRequest).catch(() => undefined);
    }, 12000);
    return () => clearInterval(interval);
  }, [token, id, repairRequest?.status]);

  if (loading) {
    return <ScreenContainer><LoadingState label="Chargement de l’attente..." /></ScreenContainer>;
  }

  if (!repairRequest) {
    return (
      <ScreenContainer>
        <View style={styles.topBar}><Logo compact /></View>
        <View style={styles.center}><ErrorMessage message={error || 'Demande introuvable.'} /><AppButton title="Retour" variant="secondary" onPress={() => router.back()} /></View>
      </ScreenContainer>
    );
  }

  const latestArtisan = repairRequest.current_offer?.artisan || repairRequest.last_offer?.artisan;
  const Icon = getCategoryIcon(repairRequest.category.icon);
  const rejected = repairRequest.status === 'pending' && repairRequest.last_offer?.status === 'rejected';
  const accepted = repairRequest.status === 'accepted';

  return (
    <ScreenContainer>
      <View style={styles.topBar}>
        <Pressable onPress={() => router.replace(`/repair-request/${repairRequest.id}` as never)} style={styles.backButton}>
          <ChevronLeft size={22} color={colors.text} />
        </Pressable>
        <Logo compact />
      </View>

      <ScrollView
        contentContainerStyle={styles.content}
        showsVerticalScrollIndicator={false}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => load(true)} tintColor={colors.primary} />}
      >
        <View style={[styles.heroIcon, rejected ? styles.heroIconDanger : accepted ? styles.heroIconSuccess : styles.heroIconPending]}>
          {accepted ? <CircleCheck size={42} color={colors.success} /> : rejected ? <X size={42} color={colors.danger} /> : <Clock3 size={42} color={colors.urgent} />}
        </View>
        <Text style={styles.title}>{accepted ? 'Demande acceptée' : rejected ? 'Le dépanneur n’est pas disponible' : 'En attente de réponse'}</Text>
        <Text style={styles.subtitle}>
          {accepted
            ? 'Un dépanneur a accepté votre demande. Ses coordonnées sont maintenant disponibles.'
            : rejected
              ? 'Cet artisan ne peut pas prendre en charge votre demande. Vous pouvez en choisir un autre.'
              : `Votre demande a été envoyée à ${latestArtisan?.name || 'l’artisan'}. Il doit maintenant accepter ou refuser l’intervention.`}
        </Text>

        <ErrorMessage message={error} />

        <View style={styles.card}>
          <View style={styles.requestHeader}>
            <View style={styles.iconBox}><Icon size={22} color={colors.primary} /></View>
            <View style={styles.flex}>
              <Text style={styles.requestCategory}>{repairRequest.category.name}</Text>
              <Text style={styles.requestTitle}>{repairRequest.title || repairRequest.description}</Text>
            </View>
            <RequestStatusBadge status={repairRequest.status} />
          </View>
          <Info label="Référence" value={repairRequest.reference} />
          <Info label="Localisation" value={formatLocation(repairRequest.location.district, repairRequest.location.city)} />
          {latestArtisan ? <Info label="Dépanneur" value={`${latestArtisan.name} · ${categoryName(latestArtisan)}`} /> : null}
        </View>

        <View style={styles.actions}>
          {rejected ? <AppButton title="Voir d’autres dépanneurs" onPress={() => router.replace(`/available-artisans/${repairRequest.id}` as never)} /> : null}
          <AppButton title="Voir ma demande" variant={accepted ? 'primary' : 'secondary'} onPress={() => router.replace(`/repair-request/${repairRequest.id}` as never)} />
          <AppButton title="Rafraîchir" variant="secondary" onPress={() => load(true)} loading={refreshing} disabled={refreshing} />
        </View>
      </ScrollView>
    </ScreenContainer>
  );
}

function Info({ label, value }: { label: string; value: string }) {
  return (
    <View style={styles.infoRow}>
      <Text style={styles.infoLabel}>{label}</Text>
      <Text style={styles.infoValue}>{value}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  topBar: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingVertical: 14, borderBottomWidth: 1, borderBottomColor: colors.border },
  backButton: { width: 40, height: 40, borderRadius: 12, backgroundColor: colors.white, borderWidth: 1, borderColor: colors.border, alignItems: 'center', justifyContent: 'center' },
  content: { paddingVertical: 24, paddingBottom: 34, gap: 14 },
  center: { flex: 1, justifyContent: 'center' },
  heroIcon: { alignSelf: 'center', width: 92, height: 92, borderRadius: 24, alignItems: 'center', justifyContent: 'center' },
  heroIconPending: { backgroundColor: '#FFF3E2' },
  heroIconSuccess: { backgroundColor: '#E7F9F0' },
  heroIconDanger: { backgroundColor: '#FEE4E2' },
  title: { color: colors.text, fontSize: 27, lineHeight: 35, fontWeight: '700', textAlign: 'center' },
  subtitle: { color: colors.muted, fontSize: 15, lineHeight: 22, textAlign: 'center' },
  card: { backgroundColor: colors.white, borderRadius: 12, borderWidth: 1, borderColor: colors.border, padding: 16, gap: 12 },
  requestHeader: { flexDirection: 'row', gap: 12, alignItems: 'flex-start' },
  iconBox: { width: 42, height: 42, borderRadius: 12, backgroundColor: colors.primaryLight, alignItems: 'center', justifyContent: 'center' },
  flex: { flex: 1 },
  requestCategory: { color: colors.primary, fontSize: 13, fontWeight: '700' },
  requestTitle: { color: colors.text, fontSize: 17, fontWeight: '700', lineHeight: 23, marginTop: 2 },
  infoRow: { borderTopWidth: 1, borderTopColor: colors.border, paddingTop: 12, gap: 3 },
  infoLabel: { color: colors.muted, fontSize: 12, fontWeight: '700' },
  infoValue: { color: colors.text, fontSize: 15, fontWeight: '700', lineHeight: 21 },
  actions: { gap: 10, marginTop: 4 },
});