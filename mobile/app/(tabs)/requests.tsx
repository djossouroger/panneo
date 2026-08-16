import { useCallback, useState } from 'react';
import { useRouter } from 'expo-router';
import { Pressable, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { Inbox, ShieldX } from 'lucide-react-native';
import { colors } from '../../components/theme';
import { AppButton, EmptyState, ErrorMessage, LoadingState, LoadMore, Logo, ScreenContainer } from '../../components/ui';
import { friendlyError, getArtisanOffers, getArtisanRepairRequests, getRepairRequests, RepairRequest, RepairRequestOffer, User as ApiUser } from '../../lib/api';
import { IncomingRequestCard, InterventionCard, RepairRequestCard } from '../../components/repairRequests';
import { NotificationBell } from '../../components/notifications';
import { restoreSession, handleSessionExpired } from '../../lib/session';
import { useFocusLoad } from '../../lib/focusLoad';

export default function RequestsScreen() {
  const router = useRouter();
  const [user, setUser] = useState<ApiUser | null>(null);
  const [token, setToken] = useState<string | null>(null);
  const [requests, setRequests] = useState<RepairRequest[]>([]);
  const [offers, setOffers] = useState<RepairRequestOffer[]>([]);
  const [activeInterventions, setActiveInterventions] = useState<RepairRequest[]>([]);
  const [completedInterventions, setCompletedInterventions] = useState<RepairRequest[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [clientFilter, setClientFilter] = useState<'active' | 'history'>('active');
  const [offersPage, setOffersPage] = useState(1);
  const [offersHasMore, setOffersHasMore] = useState(false);
  const [offersLoadingMore, setOffersLoadingMore] = useState(false);
  const [completedPage, setCompletedPage] = useState(1);
  const [completedHasMore, setCompletedHasMore] = useState(false);
  const [completedLoadingMore, setCompletedLoadingMore] = useState(false);
  const [requestsPage, setRequestsPage] = useState(1);
  const [requestsHasMore, setRequestsHasMore] = useState(false);
  const [requestsLoadingMore, setRequestsLoadingMore] = useState(false);

  const load = useCallback(async (refresh = false, filterOverride?: 'active' | 'history') => {
    if (refresh) setRefreshing(true);
    else setLoading(true);
    setError(null);
    try {
      const session = await restoreSession();
      if (!session) {
        router.replace('/login');
        return;
      }
      setUser(session.user);
      setToken(session.token);
      const filter = filterOverride ?? clientFilter;
      if (session.user.role === 'client') {
        const result = await getRepairRequests(session.token, filter === 'active' ? 'actives' : 'historique', 1);
        setRequests(result.data);
        setRequestsPage(1);
        setRequestsHasMore(result.meta.current_page < result.meta.last_page);
        setOffers([]);
        setActiveInterventions([]);
        setCompletedInterventions([]);
      } else {
        const verified = session.user.artisan_profile?.verification_status === 'verified' || session.user.artisanProfile?.verification_status === 'verified';
        if (verified) {
          const [nextOffers, nextActiveInterventions, nextCompletedInterventions] = await Promise.all([
            getArtisanOffers(session.token, 1),
            getArtisanRepairRequests(session.token, 'active', 1),
            getArtisanRepairRequests(session.token, 'completed', 1),
          ]);
          setOffers(nextOffers.data);
          setOffersPage(1);
          setOffersHasMore(nextOffers.meta.current_page < nextOffers.meta.last_page);
          setActiveInterventions(nextActiveInterventions.data);
          setCompletedInterventions(nextCompletedInterventions.data);
          setCompletedPage(1);
          setCompletedHasMore(nextCompletedInterventions.meta.current_page < nextCompletedInterventions.meta.last_page);
        } else {
          setOffers([]);
          setOffersHasMore(false);
          setActiveInterventions([]);
          setCompletedInterventions([]);
          setCompletedHasMore(false);
        }
        setRequests([]);
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
  }, [router, clientFilter]);

  useFocusLoad(load);

  const switchFilter = (filter: 'active' | 'history') => {
    if (filter === clientFilter) return;
    setClientFilter(filter);
    load(true, filter);
  };

  const loadMoreOffers = async () => {
    if (!token || offersLoadingMore || !offersHasMore) return;
    setOffersLoadingMore(true);
    setError(null);
    try {
      const result = await getArtisanOffers(token, offersPage + 1);
      setOffers((prev) => [...prev, ...result.data]);
      setOffersPage(result.meta.current_page);
      setOffersHasMore(result.meta.current_page < result.meta.last_page);
    } catch (err) {
      setError(friendlyError(err));
    } finally {
      setOffersLoadingMore(false);
    }
  };

  const loadMoreCompleted = async () => {
    if (!token || completedLoadingMore || !completedHasMore) return;
    setCompletedLoadingMore(true);
    setError(null);
    try {
      const result = await getArtisanRepairRequests(token, 'completed', completedPage + 1);
      setCompletedInterventions((prev) => [...prev, ...result.data]);
      setCompletedPage(result.meta.current_page);
      setCompletedHasMore(result.meta.current_page < result.meta.last_page);
    } catch (err) {
      setError(friendlyError(err));
    } finally {
      setCompletedLoadingMore(false);
    }
  };

  const loadMoreRequests = async () => {
    if (!token || requestsLoadingMore || !requestsHasMore) return;
    setRequestsLoadingMore(true);
    setError(null);
    try {
      const result = await getRepairRequests(token, clientFilter === 'active' ? 'actives' : 'historique', requestsPage + 1);
      setRequests((prev) => [...prev, ...result.data]);
      setRequestsPage(result.meta.current_page);
      setRequestsHasMore(result.meta.current_page < result.meta.last_page);
    } catch (err) {
      setError(friendlyError(err));
    } finally {
      setRequestsLoadingMore(false);
    }
  };

  if (loading || !user) {
    return <ScreenContainer><LoadingState label="Chargement des demandes..." /></ScreenContainer>;
  }

  const isArtisan = user.role === 'artisan';
  const isArtisanVerified = isArtisan && (user.artisan_profile?.verification_status === 'verified' || user.artisanProfile?.verification_status === 'verified');
  const pendingOffers = offers.filter((offer) => offer.status === 'pending');
  const closedOffers = offers.filter((offer) => offer.status === 'rejected' || offer.status === 'cancelled');
  const hasHistory = completedInterventions.length > 0 || closedOffers.length > 0;

  return (
    <ScreenContainer>
      <View style={styles.header}>
        <View style={styles.headerLeft}>
          <Logo compact />
          <Text style={styles.title}>{isArtisan ? 'Demandes reçues' : 'Mes demandes'}</Text>
        </View>
        <NotificationBell token={token} />
      </View>

      <ScrollView
        contentContainerStyle={styles.scrollContent}
        showsVerticalScrollIndicator={false}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => load(true)} tintColor={colors.primary} />}
      >
        <ErrorMessage message={error} />

        {isArtisan ? (
          !isArtisanVerified ? (
            <View style={styles.verificationNotice}>
              <View style={styles.verificationNoticeIcon}>
                <ShieldX size={22} color={colors.urgent} />
              </View>
              <Text style={styles.verificationNoticeTitle}>Compte en attente de validation</Text>
              <Text style={styles.verificationNoticeText}>Vous pourrez consulter vos demandes une fois votre compte validé par un administrateur.</Text>
              <AppButton title="Compléter la vérification" onPress={() => router.push('/artisan/verification')} />
            </View>
          ) : (
            <View style={styles.sections}>
            <View style={styles.section}>
              <View style={styles.sectionHeaderRow}>
                <Text style={styles.sectionTitle}>À répondre</Text>
                {pendingOffers.length > 0 ? <Text style={styles.counterBadge}>{pendingOffers.length}</Text> : null}
              </View>
              {pendingOffers.length === 0 ? (
                <EmptyState icon={<Inbox size={22} color={colors.primary} />} title="Aucune demande à répondre" text="Les nouvelles demandes reçues apparaîtront ici." />
              ) : (
                <View style={styles.list}>
                  {pendingOffers.map((offer) => (
                    <IncomingRequestCard key={offer.id} offer={offer} onPress={() => router.push(`/offer-detail/${offer.id}` as never)} />
                  ))}
                </View>
              )}
              {offersHasMore ? <LoadMore loading={offersLoadingMore} onPress={loadMoreOffers} /> : null}
            </View>

            <View style={styles.section}>
              <View style={styles.sectionHeaderRow}>
                <Text style={styles.sectionTitle}>En cours</Text>
                {activeInterventions.length > 0 ? <Text style={styles.counterBadge}>{activeInterventions.length}</Text> : null}
              </View>
              {activeInterventions.length === 0 ? (
                <EmptyState icon={<Inbox size={22} color={colors.primary} />} title="Aucune intervention en cours" text="Les interventions acceptées ou commencées apparaîtront ici." />
              ) : (
                <View style={styles.list}>
                  {activeInterventions.map((intervention) => (
                    <InterventionCard key={intervention.id} repairRequest={intervention} onPress={() => router.push(`/intervention/${intervention.id}` as never)} />
                  ))}
                </View>
              )}
            </View>

            <View style={styles.section}>
              <Text style={styles.sectionTitle}>Historique</Text>
              {!hasHistory ? (
                <EmptyState icon={<Inbox size={22} color={colors.primary} />} title="Aucun historique" text="Les interventions terminées, refusées ou annulées seront listées ici." />
              ) : (
                <View style={styles.list}>
                  {completedInterventions.map((intervention) => (
                    <InterventionCard key={intervention.id} repairRequest={intervention} onPress={() => router.push(`/intervention/${intervention.id}` as never)} />
                  ))}
                  {closedOffers.map((offer) => (
                    <IncomingRequestCard key={offer.id} offer={offer} onPress={() => router.push(`/offer-detail/${offer.id}` as never)} />
                  ))}
                </View>
              )}
              {completedHasMore ? <LoadMore loading={completedLoadingMore} onPress={loadMoreCompleted} /> : null}
            </View>
            </View>
          )
        ) : (
          <>
            <View style={styles.clientFilterRow}>
              <Pressable
                onPress={() => switchFilter('active')}
                style={[styles.clientFilterBtn, clientFilter === 'active' && styles.clientFilterBtnActive]}
              >
                <Text style={[styles.clientFilterText, clientFilter === 'active' && styles.clientFilterTextActive]}>Actives</Text>
              </Pressable>
              <Pressable
                onPress={() => switchFilter('history')}
                style={[styles.clientFilterBtn, clientFilter === 'history' && styles.clientFilterBtnActive]}
              >
                <Text style={[styles.clientFilterText, clientFilter === 'history' && styles.clientFilterTextActive]}>Historique</Text>
              </Pressable>
            </View>

            {requests.length === 0 ? (
              <View style={styles.emptyWrap}>
                <EmptyState
                  icon={<Inbox size={22} color={colors.primary} />}
                  title={clientFilter === 'active' ? 'Aucune demande active' : 'Aucun historique'}
                  text={clientFilter === 'active' ? 'Vos demandes en cours apparaîtront ici.' : 'Vos demandes terminées ou annulées seront listées ici.'}
                />
                {clientFilter === 'active' ? <AppButton title="Signaler une panne" onPress={() => router.push('/report')} /> : null}
              </View>
            ) : (
              <>
                <View style={styles.list}>
                  {requests.map((request) => (
                    <RepairRequestCard key={request.id} repairRequest={request} onPress={() => router.push(`/repair-request/${request.id}` as never)} />
                  ))}
                </View>
                {requestsHasMore ? <LoadMore loading={requestsLoadingMore} onPress={loadMoreRequests} /> : null}
              </>
            )}
          </>
        )}
      </ScrollView>
    </ScreenContainer>
  );
}

const styles = StyleSheet.create({
  header: { paddingVertical: 14, borderBottomWidth: 1, borderBottomColor: colors.border, gap: 8, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' },
  headerLeft: { gap: 8 },
  title: { color: colors.text, fontSize: 24, fontWeight: '700' },
  scrollContent: { paddingVertical: 20, gap: 14 },
  sections: { gap: 22 },
  section: { gap: 12 },
  sectionHeaderRow: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' },
  sectionTitle: { color: colors.text, fontSize: 18, fontWeight: '700' },
  counterBadge: { minWidth: 28, textAlign: 'center', overflow: 'hidden', borderRadius: 999, backgroundColor: colors.primary, color: colors.white, fontSize: 13, fontWeight: '800', paddingHorizontal: 8, paddingVertical: 4 },
  list: { gap: 12 },
  emptyWrap: { gap: 12 },
  verificationNotice: { backgroundColor: '#FFF3E2', borderRadius: 12, borderWidth: 1, borderColor: '#FFE8C2', padding: 16, gap: 12, alignItems: 'center' },
  verificationNoticeIcon: { width: 44, height: 44, borderRadius: 12, backgroundColor: colors.white, alignItems: 'center', justifyContent: 'center' },
  verificationNoticeTitle: { color: colors.text, fontSize: 16, fontWeight: '700', textAlign: 'center' },
  verificationNoticeText: { color: colors.muted, fontSize: 13, lineHeight: 19, textAlign: 'center' },
  clientFilterRow: { flexDirection: 'row', gap: 8, marginBottom: 8 },
  clientFilterBtn: { flex: 1, borderRadius: 999, borderWidth: 1, borderColor: colors.border, backgroundColor: colors.white, paddingVertical: 10, alignItems: 'center' },
  clientFilterBtnActive: { backgroundColor: colors.primary, borderColor: colors.primary },
  clientFilterText: { color: colors.muted, fontSize: 14, fontWeight: '700' },
  clientFilterTextActive: { color: colors.white },
});
