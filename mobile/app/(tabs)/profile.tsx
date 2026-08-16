import { useCallback, useState } from 'react';
import { useRouter } from 'expo-router';
import * as ImagePicker from 'expo-image-picker';
import { ActivityIndicator, Alert, Image, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { CalendarRange, Camera, ChevronRight, CircleGauge, Clock3, Eye, Heart, ImagePlus, LockKeyhole, LogOut, MapPin, MessageSquareWarning, ShieldCheck, User, UserCog } from 'lucide-react-native';
import { colors } from '../../components/theme';
import { AppButton, LoadingState, Logo, ScreenContainer, StatusBadge, VerifiedBadge } from '../../components/ui';
import { ApiError, ArtisanProfile, ArtisanProfileResponse, fetchArtisanProfile, uploadUserProfilePhoto, User as ApiUser } from '../../lib/api';
import { getArtisanProfile, logoutSession, restoreSession, saveUser } from '../../lib/session';
import { useFocusLoad } from '../../lib/focusLoad';

function roleLabel(role: ApiUser['role']) {
  if (role === 'artisan') return 'Artisan';
  if (role === 'admin') return 'Admin';
  return 'Client';
}

export default function ProfileScreen() {
  const router = useRouter();
  const [user, setUser] = useState<ApiUser | null>(null);
  const [profile, setProfile] = useState<ArtisanProfileResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [loggingOut, setLoggingOut] = useState(false);
  const [uploadingPhoto, setUploadingPhoto] = useState(false);

  const load = useCallback(async () => {
    const session = await restoreSession();
    if (!session) {
      router.replace('/login');
      return;
    }
    setUser(session.user);
    const localProfile = getArtisanProfile(session.user);
    if (session.user.role === 'artisan') {
      const apiProfile = await fetchArtisanProfile(session.token).catch(() => null);
      setProfile(apiProfile || (localProfile ? ({
        id: session.user.id,
        name: session.user.name,
        email: session.user.email,
        phone: session.user.phone,
        phone_verified: session.user.phone_verified,
        role: session.user.role,
        within_working_hours: false,
        profile: localProfile,
        stats: { completed_interventions: 0, average_rating: null, reviews_count: 0 },
      } as ArtisanProfileResponse) : null));
    }
  }, [router]);

  const focusLoad = useCallback(async (refresh = false) => {
    if (!refresh) setLoading(true);
    try {
      await load();
    } finally {
      setLoading(false);
    }
  }, [load]);

  useFocusLoad(focusLoad);

  const submitLogout = async () => {
    setLoggingOut(true);
    await logoutSession();
    router.replace('/login');
  };

  const pickProfilePhoto = async () => {
    if (!user || uploadingPhoto) return;
    try {
      const permission = await ImagePicker.requestMediaLibraryPermissionsAsync();
      if (!permission.granted) {
        Alert.alert('Photos', 'Autorisez l’accès à vos photos pour changer votre photo de profil.');
        return;
      }
      const result = await ImagePicker.launchImageLibraryAsync({
        mediaTypes: ['images'],
        quality: 0.7,
        allowsEditing: true,
        aspect: [1, 1],
      });
      if (result.canceled || !result.assets?.length) return;
      const session = await restoreSession();
      if (!session) return;
      setUploadingPhoto(true);
      try {
        const { profile_photo_url } = await uploadUserProfilePhoto(session.token, result.assets[0].uri);
        const updated = { ...user, profile_photo_url };
        setUser(updated);
        await saveUser(updated);
      } catch (err) {
        Alert.alert('Photo de profil', err instanceof ApiError ? err.message : 'Impossible de mettre à jour votre photo.');
      } finally {
        setUploadingPhoto(false);
      }
    } catch {
      Alert.alert('Photos', 'Impossible d’accéder à vos photos.');
    }
  };

  if (loading || !user) {
    return <ScreenContainer><LoadingState label="Chargement du profil..." /></ScreenContainer>;
  }

  const role = user.role === 'artisan' ? 'artisan' : 'client';

  return (
    <ScreenContainer>
      <View style={styles.header}>
        <Logo compact />
        <Text style={styles.title}>Profil</Text>
      </View>

      <ScrollView contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
         <View style={styles.avatarBox}>
          {user.role === 'client' ? (
            <Pressable onPress={pickProfilePhoto} style={styles.avatar} disabled={uploadingPhoto}>
              {user.profile_photo_url ? <Image source={{ uri: user.profile_photo_url }} style={styles.avatarImage} resizeMode="cover" /> : <User size={28} color={colors.primary} />}
              {uploadingPhoto ? <ActivityIndicator size="small" color={colors.primary} style={styles.avatarSpinner} /> : <View style={styles.avatarEdit}><Camera size={12} color={colors.white} strokeWidth={2.4} /></View>}
            </Pressable>
          ) : (
            <View style={styles.avatar}><User size={28} color={colors.primary} /></View>
          )}
          <View style={styles.nameRow}>
            <Text style={styles.name}>{user.name}</Text>
            {profile?.profile.verification_status === 'verified' ? <VerifiedBadge /> : null}
          </View>
          <StatusBadge label={roleLabel(user.role)} tone="neutral" />
          {profile?.profile.description ? <Text style={styles.description}>{profile.profile.description}</Text> : null}
        </View>

        {role === 'client' ? (
          <View style={styles.menuCard}>
            <Text style={styles.menuTitle}>Mes favoris</Text>
            <MenuRow icon={<Heart size={18} color={colors.primary} />} title="Mes artisans favoris" subtitle="Retrouvez vos dépanneurs enregistrés" onPress={() => router.push('/favorites')} last />
          </View>
        ) : null}

        {role === 'artisan' && profile ? (
          <View style={styles.menuCard}>
            <Text style={styles.menuTitle}>Mon profil</Text>
            <MenuRow icon={<ShieldCheck size={18} color={colors.primary} />} title="Vérification de l’identité" subtitle="Documents et statut de vérification" onPress={() => router.push('/artisan/verification')} />
            <MenuRow icon={<UserCog size={18} color={colors.primary} />} title="Mes informations" subtitle="Coordonnées, description, expérience, photo" onPress={() => router.push('/artisan/edit')} />
            <MenuRow icon={<CircleGauge size={18} color={colors.primary} />} title="Mes métiers" subtitle={`${profile.profile.categories.length}/3 catégories sélectionnées`} onPress={() => router.push('/artisan/categories')} />
            <MenuRow icon={<MapPin size={18} color={colors.primary} />} title="Zones d’intervention" subtitle={`${profile.profile.service_areas.length}/10 zones configurées`} onPress={() => router.push('/artisan/zones')} />
            <MenuRow icon={<Clock3 size={18} color={colors.primary} />} title="Horaires de travail" subtitle={`${profile.profile.working_hours.length} jour(s) de travail configuré(s)`} onPress={() => router.push('/artisan/hours')} />
            <MenuRow icon={<CalendarRange size={18} color={colors.primary} />} title="Absences et indisponibilités" subtitle={`${profile.profile.unavailabilities.length} indisponibilité(s) enregistrée(s)`} onPress={() => router.push('/artisan/absences')} />
            <MenuRow icon={<ImagePlus size={18} color={colors.primary} />} title="Portfolio" subtitle={`${profile.profile.portfolio.length} réalisation(s) publiée(s)`} onPress={() => router.push('/artisan/portfolio')} />
            <MenuRow icon={<Eye size={18} color={colors.primary} />} title="Voir mon profil public" subtitle="Aperçu visible par les clients" onPress={() => router.push(`/artisan/${profile.id}` as never)} last />
          </View>
        ) : null}

        <View style={styles.menuCard}>
          <Text style={styles.menuTitle}>Mon compte</Text>
          <MenuRow icon={<MessageSquareWarning size={18} color={colors.primary} />} title="Mes litiges" subtitle="Signalez et suivez vos litiges" onPress={() => router.push('/disputes')} />
          <MenuRow icon={<LockKeyhole size={18} color={colors.primary} />} title="Compte et sécurité" subtitle="E-mail, téléphone, sessions, suppression" onPress={() => router.push('/account')} last />
        </View>

        <AppButton title="Se déconnecter" variant="danger" onPress={submitLogout} loading={loggingOut} disabled={loggingOut} />
      </ScrollView>
    </ScreenContainer>
  );
}

function MenuRow({ icon, title, subtitle, onPress, last = false }: {
  icon: React.ReactNode;
  title: string;
  subtitle: string;
  onPress: () => void;
  last?: boolean;
}) {
  return (
    <Pressable onPress={onPress} style={[styles.menuRow, last && styles.menuRowLast]}>
      <View style={styles.menuIcon}>{icon}</View>
      <View style={styles.menuText}>
        <Text style={styles.menuItemTitle}>{title}</Text>
        <Text style={styles.menuItemSubtitle}>{subtitle}</Text>
      </View>
      <ChevronRight size={18} color={colors.muted} />
    </Pressable>
  );
}

const styles = StyleSheet.create({
  header: { paddingVertical: 14, borderBottomWidth: 1, borderBottomColor: colors.border, gap: 8 },
  title: { color: colors.text, fontSize: 24, fontWeight: '700' },
  content: { paddingVertical: 20, gap: 18 },
  avatarBox: { backgroundColor: colors.white, borderRadius: 12, borderWidth: 1, borderColor: colors.border, padding: 18, alignItems: 'center', gap: 10 },
  avatar: { width: 60, height: 60, borderRadius: 16, backgroundColor: colors.primaryLight, alignItems: 'center', justifyContent: 'center', overflow: 'hidden' },
  avatarImage: { width: '100%', height: '100%' },
  avatarEdit: { position: 'absolute', right: -2, bottom: -2, width: 22, height: 22, borderRadius: 11, backgroundColor: colors.primary, alignItems: 'center', justifyContent: 'center', borderWidth: 2, borderColor: colors.white },
  avatarSpinner: { position: 'absolute' },
  name: { color: colors.text, fontSize: 22, fontWeight: '700', textAlign: 'center' },
  nameRow: { flexDirection: 'row', alignItems: 'center', gap: 8, justifyContent: 'center' },
  description: { color: colors.muted, fontSize: 14, lineHeight: 20, textAlign: 'center' },
  menuCard: { backgroundColor: colors.white, borderRadius: 12, borderWidth: 1, borderColor: colors.border, paddingHorizontal: 16, paddingVertical: 6, gap: 0 },
  menuTitle: { color: colors.muted, fontSize: 12, fontWeight: '700', textTransform: 'uppercase', marginTop: 10, marginBottom: 2 },
  menuRow: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingVertical: 13, borderBottomWidth: 1, borderBottomColor: colors.border },
  menuRowLast: { borderBottomWidth: 0 },
  menuIcon: { width: 36, height: 36, borderRadius: 10, backgroundColor: colors.primaryLight, alignItems: 'center', justifyContent: 'center' },
  menuText: { flex: 1 },
  menuItemTitle: { color: colors.text, fontSize: 15, fontWeight: '700' },
  menuItemSubtitle: { color: colors.muted, fontSize: 12, marginTop: 2, lineHeight: 17 },
});
