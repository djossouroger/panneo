import { useCallback, useState } from 'react';
import { useRouter } from 'expo-router';
import { useFocusEffect } from '@react-navigation/native';
import { Alert, Image, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import * as ImagePicker from 'expo-image-picker';
import { Camera, ChevronLeft } from 'lucide-react-native';
import { colors } from '../../components/theme';
import { AppButton, AppInput, ErrorMessage, LoadingState, ScreenContainer, Textarea } from '../../components/ui';
import { ApiError, ArtisanProfileResponse, fetchArtisanProfile, friendlyError, updateArtisanProfile, uploadProfilePhoto } from '../../lib/api';
import { handleSessionExpired, restoreSession } from '../../lib/session';

export default function ArtisanEditScreen() {
  const router = useRouter();
  const [token, setToken] = useState<string | null>(null);
  const [info, setInfo] = useState<ArtisanProfileResponse | null>(null);
  const [photo, setPhoto] = useState<string | null>(null);
  const [description, setDescription] = useState('');
  const [years, setYears] = useState('');
  const [specialties, setSpecialties] = useState('');
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const session = await restoreSession();
      if (!session || session.user.role !== 'artisan') {
        router.replace('/login');
        return;
      }
      setToken(session.token);
      const profile = await fetchArtisanProfile(session.token);
      setInfo(profile);
      setPhoto(profile.profile.profile_photo_url);
      setDescription(profile.profile.description || '');
      setYears(profile.profile.years_of_experience != null ? String(profile.profile.years_of_experience) : '');
      setSpecialties(profile.profile.specialties.join(', '));
    } catch (err) {
      if (await handleSessionExpired(err)) {
        router.replace('/login');
        return;
      }
      setError(err instanceof ApiError ? err.message : friendlyError(err));
    } finally {
      setLoading(false);
    }
  }, [router]);

  useFocusEffect(useCallback(() => {
    load();
  }, [load]));

  const pickPhoto = async () => {
    if (!token || saving) return;
    try {
      const permission = await ImagePicker.requestMediaLibraryPermissionsAsync();
      if (!permission.granted) {
        setError('Autorisez l’accès à vos photos pour changer votre photo de profil.');
        return;
      }
      const result = await ImagePicker.launchImageLibraryAsync({ mediaTypes: ['images'], quality: 0.8, allowsEditing: true, aspect: [1, 1] });
      if (result.canceled || !result.assets?.length) return;
      const uri = result.assets[0].uri;
      setSaving(true);
      setError(null);
      try {
        const updated = await uploadProfilePhoto(token, uri);
        setPhoto(updated.profile_photo_url);
      } catch (err) {
        setError(err instanceof ApiError ? err.message : friendlyError(err));
      } finally {
        setSaving(false);
      }
    } catch {
      setError('Impossible d’accéder à vos photos.');
    }
  };

  const save = async () => {
    if (!token || saving) return;
    const yearsNumber = years.trim() === '' ? null : Number(years);
    if (years.trim() !== '' && (Number.isNaN(yearsNumber) || yearsNumber! < 0 || yearsNumber! > 99)) {
      setError('Indiquez une expérience valide (nombre d’années).');
      return;
    }
    setSaving(true);
    setError(null);
    try {
      await updateArtisanProfile(token, {
        description: description.trim() || null,
        years_of_experience: yearsNumber,
        specialties: specialties.split(',').map((item) => item.trim()).filter(Boolean).slice(0, 10),
      });
      Alert.alert('Profil enregistré', 'Vos informations ont été mises à jour.', [{ text: 'OK', onPress: () => router.back() }]);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : friendlyError(err));
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return <ScreenContainer><LoadingState label="Chargement de vos informations..." /></ScreenContainer>;
  }

  return (
    <ScreenContainer>
      <View style={styles.header}>
        <Pressable onPress={() => router.back()} style={styles.backButton}>
          <ChevronLeft size={22} color={colors.text} />
        </Pressable>
        <Text style={styles.headerTitle}>Mes informations</Text>
      </View>

      <ScrollView contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
        <ErrorMessage message={error} />

        {info ? (
          <View style={styles.infoCard}>
            <Text style={styles.infoTitle}>Informations personnelles</Text>
            <InfoRow label="Email" value={info.email} />
            <InfoRow label="Téléphone" value={info.phone || 'Non renseigné'} />
            <InfoRow label="Métier" value={info.profile.categories.find((cat) => cat.is_primary)?.name || info.profile.categories[0]?.name || 'Non renseigné'} />
            <InfoRow label="Ville" value={info.profile.city || 'Non renseignée'} />
            <InfoRow label="Quartier" value={info.profile.district || 'Non renseigné'} />
            <InfoRow label="Disponibilité" value={info.profile.is_available ? 'Disponible' : 'Indisponible'} last />
          </View>
        ) : null}

        <View style={styles.photoBox}>
          {photo ? <Image source={{ uri: photo }} style={styles.photo} resizeMode="cover" /> : <View style={styles.photoPlaceholder}><Camera size={26} color={colors.muted} /></View>}
          <AppButton title="Changer la photo" variant="secondary" onPress={pickPhoto} loading={saving} disabled={saving} />
        </View>

        <View style={styles.card}>
          <Textarea label="Description" value={description} onChangeText={setDescription} placeholder="Présentez votre activité et votre savoir-faire." maxLength={500} />
          <AppInput label="Années d’expérience" value={years} onChangeText={setYears} placeholder="Ex. 5" keyboardType="phone-pad" />
          <AppInput label="Spécialités (séparées par des virgules)" value={specialties} onChangeText={setSpecialties} placeholder="Ex. Climatisation, Froid commercial" />
        </View>

        <AppButton title="Enregistrer" onPress={save} loading={saving} disabled={saving} />
      </ScrollView>
    </ScreenContainer>
  );
}

function InfoRow({ label, value, last = false }: { label: string; value: string; last?: boolean }) {
  return (
    <View style={[styles.infoRow, last && styles.infoRowLast]}>
      <Text style={styles.infoLabel}>{label}</Text>
      <Text style={styles.infoValue}>{value}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  header: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingVertical: 14, borderBottomWidth: 1, borderBottomColor: colors.border },
  backButton: { width: 40, height: 40, borderRadius: 12, backgroundColor: colors.white, borderWidth: 1, borderColor: colors.border, alignItems: 'center', justifyContent: 'center' },
  headerTitle: { color: colors.text, fontSize: 20, fontWeight: '700' },
  content: { paddingVertical: 20, paddingBottom: 84, gap: 14 },
  infoCard: { backgroundColor: colors.white, borderRadius: 12, borderWidth: 1, borderColor: colors.border, paddingHorizontal: 16, paddingVertical: 6 },
  infoTitle: { color: colors.muted, fontSize: 12, fontWeight: '700', textTransform: 'uppercase', marginTop: 10, marginBottom: 2 },
  infoRow: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingVertical: 13, borderBottomWidth: 1, borderBottomColor: colors.border },
  infoRowLast: { borderBottomWidth: 0 },
  infoLabel: { color: colors.muted, fontSize: 13, fontWeight: '600' },
  infoValue: { color: colors.text, fontSize: 15, fontWeight: '600', flexShrink: 1, textAlign: 'right' },
  photoBox: { alignItems: 'center', gap: 12 },
  photo: { width: 110, height: 110, borderRadius: 28, backgroundColor: colors.primaryLight },
  photoPlaceholder: { width: 110, height: 110, borderRadius: 28, backgroundColor: '#F2F4F7', alignItems: 'center', justifyContent: 'center' },
  card: { backgroundColor: colors.white, borderRadius: 12, borderWidth: 1, borderColor: colors.border, padding: 16, gap: 4 },
});
