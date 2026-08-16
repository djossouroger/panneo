import { useCallback, useState } from 'react';
import { useRouter } from 'expo-router';
import { Image, Pressable, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { Clock, User, Wrench } from 'lucide-react-native';
import { colors } from '../../components/theme';
import { AppButton, EmptyState, ErrorMessage, LoadingState, Logo, ScreenContainer } from '../../components/ui';
import { Category, getCategories, getRepairRequests, RepairRequest, User as ApiUser, friendlyError } from '../../lib/api';
import { CategoryCard, RepairRequestCard } from '../../components/repairRequests';
import { NotificationBell } from '../../components/notifications';
import { restoreSession, handleSessionExpired } from '../../lib/session';
import { useFocusLoad } from '../../lib/focusLoad';

function firstName(name: string) {
  return name.trim().split(' ')[0] || name;
}

export default function ClientHomeScreen() {
  const router = useRouter();
  const [user, setUser] = useState<ApiUser | null>(null);
  const [token, setToken] = useState<string | null>(null);
  const [categories, setCategories] = useState<Category[]>([]);
  const [recentRequests, setRecentRequests] = useState<RepairRequest[]>([]);
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
      if (session.user.role === 'artisan') {
        router.replace('/');
        return;
      }
      const [nextCategories, nextRequests] = await Promise.all([
        getCategories(),
        getRepairRequests(session.token),
      ]);
      setUser(session.user);
      setToken(session.token);
      setCategories(nextCategories);
      setRecentRequests(nextRequests.data.slice(0, 2));
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

  if (loading) {
    return <ScreenContainer><LoadingState label="Chargement de l’accueil..." /></ScreenContainer>;
  }

  return (
    <ScreenContainer>
      <View style={styles.header}>
        <View style={styles.headerLeft}>
          <Logo compact />
          <Text style={styles.greeting}>Bonjour, {user ? firstName(user.name) : 'vous'}</Text>
        </View>
        <View style={styles.headerActions}>
          <NotificationBell token={token} />
          <Pressable onPress={() => router.push('/profile')} style={styles.profileButton}>
            {user?.profile_photo_url ? <Image source={{ uri: user.profile_photo_url }} style={styles.profileImage} resizeMode="cover" /> : <User size={21} color={colors.primary} />}
          </Pressable>
        </View>
      </View>

      <ScrollView contentContainerStyle={styles.scrollContent} showsVerticalScrollIndicator={false}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => load(true)} tintColor={colors.primary} />}
      >
        <ErrorMessage message={error} />

        <View style={styles.heroCard}>
          <Text style={styles.heroKicker}>Un problème à résoudre ?</Text>
          <Text style={styles.heroTitle}>Trouvez rapidement un dépanneur</Text>
          <Text style={styles.heroText}>Signalez votre problème et nous vous aiderons à trouver un professionnel disponible.</Text>
          <AppButton title="Signaler une panne" onPress={() => router.push('/report')} />
        </View>

        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Services disponibles</Text>
          {categories.length === 0 ? (
            <EmptyState icon={<Wrench size={22} color={colors.primary} />} title="Aucun service disponible" text="Les services actifs apparaîtront ici." />
          ) : (
            <View style={styles.grid}>
              {categories.map((category) => (
                <View key={category.id} style={styles.categoryCell}>
                  <CategoryCard category={category} selected={false} onPress={() => router.push(`/report?category=${category.slug}`)} />
                </View>
              ))}
            </View>
          )}
        </View>

        <View style={styles.section}>
          <View style={styles.sectionHeaderRow}>
            <Text style={styles.sectionTitle}>Demandes récentes</Text>
            <Pressable onPress={() => router.push('/requests')}><Text style={styles.linkText}>Tout voir</Text></Pressable>
          </View>
          {recentRequests.length === 0 ? (
            <EmptyState icon={<Clock size={22} color={colors.primary} />} title="Aucune demande pour le moment" text="Vos demandes de dépannage apparaîtront ici." />
          ) : (
            <View style={styles.requestList}>
              {recentRequests.map((request) => (
                <RepairRequestCard key={request.id} repairRequest={request} onPress={() => router.push(`/repair-request/${request.id}` as never)} />
              ))}
            </View>
          )}
        </View>

        {error ? <AppButton title="Réessayer" variant="secondary" onPress={load} /> : null}
      </ScrollView>
    </ScreenContainer>
  );
}

const styles = StyleSheet.create({
  header: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingVertical: 14, borderBottomWidth: 1, borderBottomColor: colors.border },
  headerActions: { flexDirection: 'row', alignItems: 'center', gap: 10 },
  headerLeft: { flex: 1, gap: 8 },
  greeting: { color: colors.text, fontSize: 18, fontWeight: '700' },
  profileButton: { width: 42, height: 42, borderRadius: 12, backgroundColor: colors.primaryLight, alignItems: 'center', justifyContent: 'center', overflow: 'hidden' },
  profileImage: { width: '100%', height: '100%' },
  scrollContent: { paddingVertical: 20, gap: 20 },
  heroCard: { backgroundColor: colors.white, borderRadius: 12, borderWidth: 1, borderColor: colors.border, padding: 18, gap: 10 },
  heroKicker: { color: colors.primary, fontSize: 13, fontWeight: '700' },
  heroTitle: { color: colors.text, fontSize: 24, lineHeight: 31, fontWeight: '700' },
  heroText: { color: colors.muted, fontSize: 15, lineHeight: 22, marginBottom: 6 },
  section: { gap: 12 },
  sectionHeaderRow: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' },
  sectionTitle: { fontSize: 18, fontWeight: '700', color: colors.text },
  linkText: { color: colors.primary, fontSize: 13, fontWeight: '700' },
  grid: { flexDirection: 'row', flexWrap: 'wrap', gap: 12 },
  categoryCell: { width: '47.8%' },
  requestList: { gap: 12 },
});