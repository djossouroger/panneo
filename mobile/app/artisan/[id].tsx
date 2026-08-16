import { useCallback, useEffect, useState } from 'react';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { Image, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { Briefcase, CalendarDays, ChevronLeft, Heart, MapPin, Star, Wrench } from 'lucide-react-native';
import { colors } from '../../components/theme';
import { AppButton, ErrorMessage, LoadingState, Logo, ScreenContainer, StarRating, VerifiedBadge } from '../../components/ui';
import { formatLocation, getCategoryIcon } from '../../components/repairRequests';
import { ApiError, getFavoriteStatus, getPublicArtisanProfile, PublicArtisanProfile, toggleFavorite } from '../../lib/api';
import { handleSessionExpired, restoreSession } from '../../lib/session';

export default function ArtisanPublicProfileScreen() {
  const router = useRouter();
  const { id } = useLocalSearchParams<{ id: string }>();
  const [artisan, setArtisan] = useState<PublicArtisanProfile | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [token, setToken] = useState<string | null>(null);
  const [isClient, setIsClient] = useState(false);
  const [isFavorite, setIsFavorite] = useState(false);
  const [favoriteLoading, setFavoriteLoading] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const data = await getPublicArtisanProfile(id);
      setArtisan(data);
      const session = await restoreSession();
      if (session) {
        setToken(session.token);
        if (session.user.role === 'client') {
          setIsClient(true);
          try {
            setIsFavorite(await getFavoriteStatus(session.token, id));
          } catch {
            // les favoris restent cachés en cas d'erreur
          }
        }
      }
    } catch (err) {
      if (await handleSessionExpired(err)) {
        router.replace('/login');
        return;
      }
      setError(err instanceof ApiError ? err.message : 'Impossible de charger le profil de cet artisan.');
    } finally {
      setLoading(false);
    }
  }, [id, router]);

  useEffect(() => {
    load();
  }, [load]);

  const toggleFavoritePress = async () => {
    if (!token || favoriteLoading) return;
    setFavoriteLoading(true);
    setError(null);
    try {
      const result = await toggleFavorite(token, id, !isFavorite);
      setIsFavorite(result.is_favorite);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Impossible de mettre à jour le favori.');
    } finally {
      setFavoriteLoading(false);
    }
  };

  if (loading) {
    return <ScreenContainer><LoadingState label="Chargement du profil..." /></ScreenContainer>;
  }

  if (!artisan) {
    return (
      <ScreenContainer>
        <View style={styles.topBar}><Logo compact /></View>
        <View style={styles.center}>
          <ErrorMessage message={error || 'Artisan introuvable.'} />
          <AppButton title="Retour" variant="secondary" onPress={() => router.back()} />
        </View>
      </ScreenContainer>
    );
  }

  const profile = artisan.profile;
  const primaryCategory = profile.categories.find((cat) => cat.is_primary) || profile.categories[0];
  const catName = primaryCategory?.name || 'Métier non renseigné';
  const Icon = primaryCategory ? getCategoryIcon(primaryCategory.icon) : Wrench;
  const stats = artisan.stats;
  const rating = stats?.average_rating ?? null;
  const reviewsCount = stats?.reviews_count ?? 0;
  const completed = stats?.completed_interventions ?? 0;
  const isVerified = profile.verification_status === 'verified';

  return (
    <ScreenContainer>
      <View style={styles.topBar}>
        <Pressable onPress={() => router.back()} style={styles.backButton}>
          <ChevronLeft size={22} color={colors.text} />
        </Pressable>
        <Logo compact />
      </View>

      <ScrollView contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
        <View style={styles.header}>
          {profile.profile_photo_url ? (
            <Image source={{ uri: profile.profile_photo_url }} style={styles.avatarImage} resizeMode="cover" />
          ) : (
            <View style={styles.avatar}><Text style={styles.avatarText}>{initialsFor(artisan.name)}</Text></View>
          )}
          <View style={styles.titleWrap}>
            <View style={styles.nameRow}>
              <Text style={styles.name}>{artisan.name}</Text>
              {isVerified ? <VerifiedBadge label={profile.verified_label} /> : null}
            </View>
            <View style={styles.categoryRow}>
              <Icon size={15} color={colors.muted} />
              <Text style={styles.category}>{catName}</Text>
            </View>
            <View style={styles.locationRow}>
              <MapPin size={14} color={colors.muted} />
              <Text style={styles.location}>{formatLocation(profile.district, profile.city)}</Text>
            </View>
          </View>
        </View>

        <View style={styles.statsCard}>
          {rating !== null ? (
            <View style={styles.ratingRow}>
              <StarRating rating={rating} size={16} showValue={true} />
              <Text style={styles.reviewCount}>({reviewsCount} avis)</Text>
            </View>
          ) : (
            <Text style={styles.noRating}>Aucun avis pour le moment</Text>
          )}
          <Text style={styles.completedText}>{completed} intervention{completed !== 1 ? 's' : ''} effectuée{completed !== 1 ? 's' : ''}</Text>
          {profile.years_of_experience != null ? (
            <View style={styles.experienceRow}>
              <Briefcase size={14} color={colors.muted} />
              <Text style={styles.completedText}>{profile.years_of_experience} an{profile.years_of_experience !== 1 ? 's' : ''} d’expérience</Text>
            </View>
          ) : null}
        </View>

        {isClient ? (
          <AppButton
            title={isFavorite ? 'Retirer des favoris' : 'Ajouter aux favoris'}
            variant={isFavorite ? 'secondary' : 'primary'}
            onPress={toggleFavoritePress}
            loading={favoriteLoading}
            disabled={favoriteLoading}
            icon={isFavorite ? <Heart size={18} color={colors.danger} fill={colors.danger} /> : <Heart size={18} color={colors.white} />}
          />
        ) : null}

        {profile.categories.length > 1 ? (
          <View style={styles.card}>
            <Text style={styles.sectionLabel}>Métiers</Text>
            <View style={styles.chipWrap}>
              {profile.categories.map((cat) => (
                <View key={cat.id} style={[styles.chip, cat.is_primary && styles.chipPrimary]}>
                  <Text style={[styles.chipText, cat.is_primary && styles.chipTextPrimary]}>{cat.name}</Text>
                </View>
              ))}
            </View>
          </View>
        ) : null}

        {profile.specialties.length > 0 ? (
          <View style={styles.card}>
            <Text style={styles.sectionLabel}>Spécialités</Text>
            <View style={styles.chipWrap}>
              {profile.specialties.map((specialty) => (
                <View key={specialty} style={styles.chip}>
                  <Text style={styles.chipText}>{specialty}</Text>
                </View>
              ))}
            </View>
          </View>
        ) : null}

        {profile.description ? (
          <View style={styles.card}>
            <Text style={styles.sectionLabel}>À propos</Text>
            <Text style={styles.bodyText}>{profile.description}</Text>
          </View>
        ) : null}

        {profile.service_areas.length > 0 ? (
          <View style={styles.card}>
            <Text style={styles.sectionLabel}>Zones d’intervention</Text>
            {profile.service_areas.map((area) => (
              <View key={area.id ?? `${area.city}-${area.district}`} style={styles.areaRow}>
                <MapPin size={14} color={colors.muted} />
                <Text style={styles.bodyText}>{area.district ? `${area.district}, ${area.city}` : area.city}</Text>
              </View>
            ))}
          </View>
        ) : null}

        {profile.portfolio.length > 0 ? (
          <View style={styles.card}>
            <Text style={styles.sectionLabel}>Réalisations</Text>
            <View style={styles.portfolioGrid}>
              {profile.portfolio.map((item) => (
                <View key={item.id} style={styles.portfolioItem}>
                  {item.image_url ? <Image source={{ uri: item.image_url }} style={styles.portfolioImage} /> : null}
                  {item.caption ? <Text style={styles.portfolioCaption} numberOfLines={2}>{item.caption}</Text> : null}
                </View>
              ))}
            </View>
          </View>
        ) : null}

        {!isVerified ? (
          <View style={styles.pendingCard}>
            <CalendarDays size={18} color={colors.urgent} />
            <Text style={styles.pendingText}>
              {profile.verification_status === 'rejected'
                ? 'Le compte de cet artisan n’a pas été vérifié.'
                : 'Ce compte est en attente de vérification.'}
            </Text>
          </View>
        ) : null}
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
  topBar: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingVertical: 14, borderBottomWidth: 1, borderBottomColor: colors.border },
  backButton: { width: 40, height: 40, borderRadius: 12, backgroundColor: colors.white, borderWidth: 1, borderColor: colors.border, alignItems: 'center', justifyContent: 'center' },
  content: { paddingVertical: 20, gap: 14, paddingBottom: 84 },
  header: { flexDirection: 'row', gap: 14, alignItems: 'center' },
  avatar: { width: 64, height: 64, borderRadius: 16, backgroundColor: colors.primaryLight, alignItems: 'center', justifyContent: 'center' },
  avatarImage: { width: 64, height: 64, borderRadius: 16, backgroundColor: colors.primaryLight },
  avatarText: { color: colors.primary, fontSize: 22, fontWeight: '800' },
  titleWrap: { flex: 1, gap: 4 },
  nameRow: { flexDirection: 'row', alignItems: 'center', gap: 8, flexWrap: 'wrap' },
  name: { color: colors.text, fontSize: 22, fontWeight: '700' },
  categoryRow: { flexDirection: 'row', alignItems: 'center', gap: 6 },
  category: { color: colors.muted, fontSize: 14, fontWeight: '600', flexShrink: 1 },
  locationRow: { flexDirection: 'row', alignItems: 'center', gap: 4 },
  location: { color: colors.muted, fontSize: 13, fontWeight: '600' },
  statsCard: { backgroundColor: colors.white, borderRadius: 12, borderWidth: 1, borderColor: colors.border, padding: 16, gap: 6, alignItems: 'center' },
  ratingRow: { flexDirection: 'row', alignItems: 'center', gap: 6 },
  reviewCount: { color: colors.muted, fontSize: 13, fontWeight: '600' },
  completedText: { color: colors.muted, fontSize: 12, fontWeight: '600', textAlign: 'center' },
  experienceRow: { flexDirection: 'row', alignItems: 'center', gap: 6 },
  noRating: { color: colors.muted, fontSize: 14, fontStyle: 'italic' },
  card: { backgroundColor: colors.white, borderRadius: 12, borderWidth: 1, borderColor: colors.border, padding: 16, gap: 10 },
  sectionLabel: { color: colors.muted, fontSize: 12, fontWeight: '700', textTransform: 'uppercase' },
  bodyText: { color: colors.text, fontSize: 15, lineHeight: 22 },
  chipWrap: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  chip: { backgroundColor: '#F2F4F7', borderRadius: 8, paddingHorizontal: 10, paddingVertical: 6 },
  chipPrimary: { backgroundColor: colors.primaryLight },
  chipText: { color: colors.text, fontSize: 13, fontWeight: '600' },
  chipTextPrimary: { color: colors.primary, fontWeight: '700' },
  areaRow: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  portfolioGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: 10 },
  portfolioItem: { width: '31%', gap: 6 },
  portfolioImage: { width: '100%', aspectRatio: 1, borderRadius: 10, backgroundColor: '#F2F4F7' },
  portfolioCaption: { color: colors.muted, fontSize: 12, lineHeight: 16 },
  pendingCard: { flexDirection: 'row', alignItems: 'center', gap: 10, backgroundColor: '#FFF3E2', borderRadius: 12, padding: 14 },
  pendingText: { color: colors.text, fontSize: 13, lineHeight: 19, flex: 1 },
  center: { flex: 1, justifyContent: 'center', gap: 12, paddingHorizontal: 20 },
});
