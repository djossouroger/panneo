import { useCallback, useState } from 'react';
import { useRouter } from 'expo-router';
import { Pressable, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { ChevronLeft, MessageSquareWarning } from 'lucide-react-native';
import { colors } from '../components/theme';
import { EmptyState, ErrorMessage, LoadingState, LoadMore, ScreenContainer, StatusBadge } from '../components/ui';
import { formatRequestDate } from '../components/repairRequests';
import { ApiError, Dispute, friendlyError, getDisputes } from '../lib/api';
import { handleSessionExpired, restoreSession } from '../lib/session';
import { useFocusLoad } from '../lib/focusLoad';

const statusTone: Record<Dispute['status'], 'success' | 'warning' | 'danger' | 'neutral'> = {
  open: 'danger',
  in_review: 'warning',
  resolved: 'success',
  rejected: 'neutral',
};

export default function DisputesScreen() {
  const router = useRouter();
  const [disputes, setDisputes] = useState<Dispute[]>([]);
  const [page, setPage] = useState(1);
  const [hasMore, setHasMore] = useState(false);
  const [loading, setLoading] = useState(true);
  const [loadingMore, setLoadingMore] = useState(false);
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
      const result = await getDisputes(session.token, 1);
      setDisputes(result.data);
      setPage(1);
      setHasMore(result.meta.current_page < result.meta.last_page);
    } catch (err) {
      if (await handleSessionExpired(err)) {
        router.replace('/login');
        return;
      }
      setError(err instanceof ApiError ? err.message : friendlyError(err));
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [router]);

  const loadMore = async () => {
    if (loadingMore || !hasMore) return;
    setLoadingMore(true);
    setError(null);
    try {
      const session = await restoreSession();
      if (!session) {
        router.replace('/login');
        return;
      }
      const result = await getDisputes(session.token, page + 1);
      setDisputes((prev) => [...prev, ...result.data]);
      setPage(result.meta.current_page);
      setHasMore(result.meta.current_page < result.meta.last_page);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : friendlyError(err));
    } finally {
      setLoadingMore(false);
    }
  };

  useFocusLoad(load);

  if (loading) {
    return <ScreenContainer><LoadingState label="Chargement de vos litiges..." /></ScreenContainer>;
  }

  return (
    <ScreenContainer>
      <View style={styles.header}>
        <Pressable onPress={() => router.back()} style={styles.backButton}>
          <ChevronLeft size={22} color={colors.text} />
        </Pressable>
        <Text style={styles.headerTitle}>Mes litiges</Text>
      </View>

      <ScrollView
        contentContainerStyle={styles.content}
        showsVerticalScrollIndicator={false}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => load(true)} tintColor={colors.primary} />}
      >
        <ErrorMessage message={error} />

        {disputes.length === 0 ? (
          <EmptyState
            icon={<MessageSquareWarning size={22} color={colors.primary} />}
            title="Aucun litige"
            text="Un litige peut être signalé après une intervention, uniquement entre les deux parties."
          />
        ) : (
          <>
            <View style={styles.list}>
              {disputes.map((dispute) => (
                <Pressable key={dispute.id} onPress={() => router.push(`/disputes/${dispute.id}` as never)} style={styles.card}>
                  <View style={styles.cardTop}>
                    <Text style={styles.subject} numberOfLines={2}>{dispute.subject}</Text>
                    <StatusBadge label={dispute.status_label} tone={statusTone[dispute.status]} />
                  </View>
                  <Text style={styles.reference}>{dispute.repair_request.reference} · {dispute.repair_request.title || dispute.repair_request.category || 'Demande'}</Text>
                  <Text style={styles.date}>{formatRequestDate(dispute.created_at)}</Text>
                </Pressable>
              ))}
            </View>
            {hasMore ? <LoadMore loading={loadingMore} onPress={loadMore} /> : null}
          </>
        )}
      </ScrollView>
    </ScreenContainer>
  );
}

const styles = StyleSheet.create({
  header: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingVertical: 14, borderBottomWidth: 1, borderBottomColor: colors.border },
  backButton: { width: 40, height: 40, borderRadius: 12, backgroundColor: colors.white, borderWidth: 1, borderColor: colors.border, alignItems: 'center', justifyContent: 'center' },
  headerTitle: { color: colors.text, fontSize: 20, fontWeight: '700' },
  content: { paddingVertical: 20, paddingBottom: 84, gap: 14 },
  list: { gap: 12 },
  card: { backgroundColor: colors.white, borderRadius: 12, borderWidth: 1, borderColor: colors.border, padding: 15, gap: 8 },
  cardTop: { flexDirection: 'row', alignItems: 'flex-start', justifyContent: 'space-between', gap: 10 },
  subject: { color: colors.text, fontSize: 16, fontWeight: '700', flex: 1 },
  reference: { color: colors.muted, fontSize: 13, fontWeight: '600' },
  date: { color: colors.muted, fontSize: 12 },
});
