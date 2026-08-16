import { useCallback, useState } from 'react';
import { useRouter } from 'expo-router';
import { Pressable, RefreshControl, ScrollView, StyleSheet, Text, View, Image } from 'react-native';
import { ChevronLeft, Heart, ShieldCheck } from 'lucide-react-native';
import { colors } from '../components/theme';
import { EmptyState, ErrorMessage, LoadingState, LoadMore, ScreenContainer } from '../components/ui';
import { formatLocation, getCategoryIcon } from '../components/repairRequests';
import { ApiError, FavoriteArtisan, friendlyError, getFavorites } from '../lib/api';
import { handleSessionExpired, restoreSession } from '../lib/session';
import { useFocusLoad } from '../lib/focusLoad';

export default function FavoritesScreen() {
  const router = useRouter();
  const [token, setToken] = useState<string | null>(null);
  const [favorites, setFavorites] = useState<FavoriteArtisan[]>([]);
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
      setToken(session.token);
      const result = await getFavorites(session.token, 1);
      setFavorites(result.data);
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
    if (!token || loadingMore || !hasMore) return;
    setLoadingMore(true);
    setError(null);
    try {
      const result = await getFavorites(token, page + 1);
      setFavorites((prev) => [...prev, ...result.data]);
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
    return <ScreenContainer><LoadingState label="Chargement de vos favoris..." /></ScreenContainer>;
  }

  return (
    <ScreenContainer>
      <View style={styles.header}>
        <Pressable onPress={() => router.back()} style={styles.backButton}>
          <ChevronLeft size={22} color={colors.text} />
        </Pressable>
        <Text style={styles.headerTitle}>Mes artisans favoris</Text>
      </View>

      <ScrollView
        contentContainerStyle={styles.content}
        showsVerticalScrollIndicator={false}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => load(true)} tintColor={colors.primary} />}
      >
        <ErrorMessage message={error} />

        {favorites.length === 0 ? (
          <EmptyState
            icon={<Heart size={22} color={colors.primary} />}
            title="Aucun favori"
            text="Enregistrez un artisan pour le retrouver facilement depuis son profil."
          />
        ) : (
          <>
            <View style={styles.list}>
              {favorites.map((artisan) => {
                const Icon = getCategoryIcon(undefined);
                return (
                  <Pressable key={artisan.id} onPress={() => router.push(`/artisan/${artisan.id}` as never)} style={styles.card}>
                    {artisan.profile_photo_url ? (
                      <Image source={{ uri: artisan.profile_photo_url }} style={styles.avatarImage} resizeMode="cover" />
                    ) : (
                      <View style={styles.avatar}><Text style={styles.avatarText}>{initialsFor(artisan.name)}</Text></View>
                    )}
                    <View style={styles.cardText}>
                      <View style={styles.nameRow}>
                        <Text style={styles.name} numberOfLines={1}>{artisan.name}</Text>
                        {artisan.verification_status === 'verified' ? (
                          <View style={styles.verifiedBadge}>
                            <ShieldCheck size={11} color={colors.success} strokeWidth={2.5} />
                          </View>
                        ) : null}
                      </View>
                      <View style={styles.metaRow}>
                        <Icon size={13} color={colors.muted} />
                        <Text style={styles.meta}>{artisan.primary_category || 'Métier non renseigné'}</Text>
                      </View>
                      <View style={styles.metaRow}>
                        <Text style={styles.meta}>{formatLocation(artisan.district, artisan.city)}</Text>
                      </View>
                      <Text style={styles.stats}>
                        {artisan.stats.completed_interventions} intervention{artisan.stats.completed_interventions !== 1 ? 's' : ''}
                        {artisan.stats.reviews_count > 0 ? ` · ${artisan.stats.average_rating?.toFixed(1) ?? '—'} (${artisan.stats.reviews_count} avis)` : ''}
                      </Text>
                    </View>
                  </Pressable>
                );
              })}
            </View>
            {hasMore ? <LoadMore loading={loadingMore} onPress={loadMore} /> : null}
          </>
        )}
      </ScrollView>
    </ScreenContainer>
  );
}

function initialsFor(name: string) {
  const parts = name.trim().split(/\s+/).filter(Boolean);
  if (parts.length === 0) return '??';
  return parts.slice(0, 2).map((part) => part[0]?.toUpperCase()).join('');
}

const styles = StyleSheet.create({
  header: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingVertical: 14, borderBottomWidth: 1, borderBottomColor: colors.border },
  backButton: { width: 40, height: 40, borderRadius: 12, backgroundColor: colors.white, borderWidth: 1, borderColor: colors.border, alignItems: 'center', justifyContent: 'center' },
  headerTitle: { color: colors.text, fontSize: 20, fontWeight: '700' },
  content: { paddingVertical: 20, paddingBottom: 84, gap: 14 },
  list: { gap: 12 },
  card: { backgroundColor: colors.white, borderRadius: 12, borderWidth: 1, borderColor: colors.border, padding: 14, flexDirection: 'row', alignItems: 'center', gap: 12 },
  avatar: { width: 50, height: 50, borderRadius: 14, backgroundColor: colors.primaryLight, alignItems: 'center', justifyContent: 'center' },
  avatarImage: { width: 50, height: 50, borderRadius: 14, backgroundColor: colors.primaryLight },
  avatarText: { color: colors.primary, fontSize: 16, fontWeight: '800' },
  cardText: { flex: 1, gap: 3 },
  nameRow: { flexDirection: 'row', alignItems: 'center', gap: 6 },
  name: { color: colors.text, fontSize: 16, fontWeight: '700', flexShrink: 1 },
  verifiedBadge: { backgroundColor: '#E7F9F0', borderRadius: 9, paddingHorizontal: 5, paddingVertical: 3 },
  metaRow: { flexDirection: 'row', alignItems: 'center', gap: 5 },
  meta: { color: colors.muted, fontSize: 13, fontWeight: '500' },
  stats: { color: colors.muted, fontSize: 12, fontWeight: '600', marginTop: 2 },
});
