import { useCallback, useState } from 'react';
import { useRouter } from 'expo-router';
import { Pressable, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { BellOff, CheckCheck, ChevronLeft, Inbox } from 'lucide-react-native';
import { colors } from '../components/theme';
import { EmptyState, ErrorMessage, LoadingState, LoadMore, Logo, ScreenContainer } from '../components/ui';
import { AppNotification, friendlyError, getNotifications, markAllNotificationsAsRead, markNotificationAsRead } from '../lib/api';
import { NotificationItem } from '../components/notifications';
import { restoreSession, handleSessionExpired } from '../lib/session';
import { useFocusLoad } from '../lib/focusLoad';

export default function NotificationsScreen() {
  const router = useRouter();
  const [token, setToken] = useState<string | null>(null);
  const [role, setRole] = useState<'client' | 'artisan' | null>(null);
  const [notifications, setNotifications] = useState<AppNotification[]>([]);
  const [page, setPage] = useState(1);
  const [hasMore, setHasMore] = useState(false);
  const [loading, setLoading] = useState(true);
  const [loadingMore, setLoadingMore] = useState(false);
  const [refreshing, setRefreshing] = useState(false);
  const [markingAll, setMarkingAll] = useState(false);
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
      setToken(session.token);
      setRole(session.user.role === 'artisan' ? 'artisan' : 'client');
      const result = await getNotifications(session.token, 1);
      setNotifications(result.data);
      setPage(1);
      setHasMore(result.meta.current_page < result.meta.last_page);
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

  const loadMore = async () => {
    if (!token || loadingMore || !hasMore) return;
    setLoadingMore(true);
    setError(null);
    try {
      const result = await getNotifications(token, page + 1);
      setNotifications((prev) => [...prev, ...result.data]);
      setPage(result.meta.current_page);
      setHasMore(result.meta.current_page < result.meta.last_page);
    } catch (err) {
      setError(friendlyError(err));
    } finally {
      setLoadingMore(false);
    }
  };

  useFocusLoad(load);

  const markAll = async () => {
    if (!token || markingAll || notifications.every((n) => n.read_at !== null)) return;
    setMarkingAll(true);
    setError(null);
    try {
      await markAllNotificationsAsRead(token);
      setNotifications((prev) => prev.map((n) => ({ ...n, read_at: new Date().toISOString() })));
    } catch (err) {
      if (await handleSessionExpired(err)) {
        router.replace('/login');
        return;
      }
      setError(friendlyError(err));
    } finally {
      setMarkingAll(false);
    }
  };

  const openNotification = async (notification: AppNotification) => {
    const nextToken = token;
    if (!nextToken) return;

    if (notification.read_at === null) {
      setNotifications((prev) => prev.map((n) => (n.id === notification.id ? { ...n, read_at: new Date().toISOString() } : n)));
      markNotificationAsRead(nextToken, notification.id).catch(() => undefined);
    }

    const repairRequestId = notification.data?.repair_request_id;
    const offerId = notification.data?.offer_id;

    if (role === 'artisan') {
      if (offerId) {
        router.push(`/offer-detail/${offerId}` as never);
        return;
      }
      if (repairRequestId) {
        router.push(`/intervention/${repairRequestId}` as never);
        return;
      }
      router.push('/requests' as never);
      return;
    }

    if (repairRequestId) {
      router.push(`/repair-request/${repairRequestId}` as never);
      return;
    }
    router.push('/requests' as never);
  };

  if (loading) {
    return <ScreenContainer><LoadingState label="Chargement des notifications..." /></ScreenContainer>;
  }

  const unreadCount = notifications.filter((n) => n.read_at === null).length;

  return (
    <ScreenContainer>
      <View style={styles.topBar}>
        <Pressable onPress={() => router.back()} style={styles.backButton}>
          <ChevronLeft size={22} color={colors.text} />
        </Pressable>
        <Logo compact />
        <Pressable
          onPress={markAll}
          disabled={markingAll || unreadCount === 0}
          style={[styles.readAllButton, (markingAll || unreadCount === 0) && styles.readAllButtonDisabled]}
          hitSlop={6}
        >
          <CheckCheck size={18} color={unreadCount === 0 ? colors.muted : colors.primary} />
          <Text style={[styles.readAllText, unreadCount === 0 && styles.readAllTextDisabled]}>Tout lire</Text>
        </Pressable>
      </View>

      <ScrollView
        contentContainerStyle={styles.content}
        showsVerticalScrollIndicator={false}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => load(true)} tintColor={colors.primary} />}
      >
        <View style={styles.titleRow}>
          <Text style={styles.title}>Notifications</Text>
          {unreadCount > 0 ? <Text style={styles.unreadBadge}>{unreadCount} non lue{unreadCount > 1 ? 's' : ''}</Text> : null}
        </View>

        <ErrorMessage message={error} />

        {notifications.length === 0 ? (
          <View style={styles.empty}>
            <EmptyState icon={<BellOff size={22} color={colors.primary} />} title="Aucune notification" text="Les alertes concernant vos demandes apparaîtront ici." />
          </View>
        ) : (
          <>
            <View style={styles.list}>
              {notifications.map((notification) => (
                <NotificationItem key={notification.id} notification={notification} onPress={() => openNotification(notification)} />
              ))}
            </View>
            {hasMore ? <LoadMore loading={loadingMore} onPress={loadMore} /> : null}
          </>
        )}

        {error ? <Pressable onPress={() => load()} style={styles.retryButton}><Text style={styles.retryText}>Réessayer</Text></Pressable> : null}
      </ScrollView>
    </ScreenContainer>
  );
}

const styles = StyleSheet.create({
  topBar: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingVertical: 14, borderBottomWidth: 1, borderBottomColor: colors.border },
  backButton: { width: 42, height: 42, borderRadius: 12, backgroundColor: colors.primaryLight, alignItems: 'center', justifyContent: 'center' },
  readAllButton: { flexDirection: 'row', alignItems: 'center', gap: 5 },
  readAllButtonDisabled: { opacity: 0.5 },
  readAllText: { color: colors.primary, fontSize: 13, fontWeight: '700' },
  readAllTextDisabled: { color: colors.muted },
  content: { paddingVertical: 20, paddingBottom: 40, gap: 16 },
  titleRow: { flexDirection: 'row', alignItems: 'center', gap: 10 },
  title: { color: colors.text, fontSize: 24, fontWeight: '700', flex: 1 },
  unreadBadge: { backgroundColor: colors.primary, color: colors.white, fontSize: 12, fontWeight: '800', borderRadius: 999, paddingHorizontal: 10, paddingVertical: 4, overflow: 'hidden' },
  empty: { paddingTop: 24 },
  list: { gap: 10 },
  retryButton: { alignSelf: 'center', borderRadius: 999, borderWidth: 1, borderColor: colors.primary, paddingHorizontal: 18, paddingVertical: 10 },
  retryText: { color: colors.primary, fontSize: 14, fontWeight: '700' },
});

