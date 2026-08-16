import { useCallback, useState } from 'react';
import { useRouter } from 'expo-router';
import { useFocusEffect } from '@react-navigation/native';
import { Alert, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { ChevronLeft, MapPin, Plus, Trash2 } from 'lucide-react-native';
import { colors } from '../../components/theme';
import { AppButton, AppInput, ErrorMessage, LoadingState, ScreenContainer } from '../../components/ui';
import { ApiError, fetchArtisanProfile, friendlyError, ServiceArea, updateArtisanServiceAreas } from '../../lib/api';
import { handleSessionExpired, restoreSession } from '../../lib/session';

const MAX_ZONES = 10;

export default function ArtisanZonesScreen() {
  const router = useRouter();
  const [token, setToken] = useState<string | null>(null);
  const [areas, setAreas] = useState<ServiceArea[]>([]);
  const [city, setCity] = useState('');
  const [district, setDistrict] = useState('');
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
      setAreas(profile.profile.service_areas);
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

  const addArea = () => {
    setError(null);
    const normalizedCity = city.trim();
    if (!normalizedCity) {
      setError('Renseignez la ville de la zone.');
      return;
    }
    if (areas.length >= MAX_ZONES) {
      Alert.alert('Limite atteinte', `Vous pouvez configurer ${MAX_ZONES} zones au maximum.`);
      return;
    }
    const duplicate = areas.some((area) => area.city.trim().toLowerCase() === normalizedCity.toLowerCase() && (area.district?.trim().toLowerCase() || '') === (district.trim().toLowerCase() || ''));
    if (duplicate) {
      setError('Cette zone est déjà configurée.');
      return;
    }
    setAreas((prev) => [...prev, { city: normalizedCity, district: district.trim() || null }]);
    setCity('');
    setDistrict('');
  };

  const removeArea = (index: number) => {
    setAreas((prev) => prev.filter((_, i) => i !== index));
  };

  const save = async () => {
    if (!token || saving) return;
    if (areas.length === 0) {
      setError('Ajoutez au moins une zone d’intervention.');
      return;
    }
    setSaving(true);
    setError(null);
    try {
      await updateArtisanServiceAreas(token, areas);
      Alert.alert('Zones enregistrées', 'Vos zones d’intervention ont été mises à jour.', [{ text: 'OK', onPress: () => router.back() }]);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : friendlyError(err));
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return <ScreenContainer><LoadingState label="Chargement de vos zones..." /></ScreenContainer>;
  }

  return (
    <ScreenContainer>
      <View style={styles.header}>
        <Pressable onPress={() => router.back()} style={styles.backButton}>
          <ChevronLeft size={22} color={colors.text} />
        </Pressable>
        <Text style={styles.headerTitle}>Zones d’intervention</Text>
      </View>

      <ScrollView contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
        <ErrorMessage message={error} />
        <Text style={styles.hint}>
          Précisez où vous intervenez ({MAX_ZONES} zones maximum). Si le quartier est vide, toute la ville est considérée comme couverte.
        </Text>

        <View style={styles.card}>
          <Text style={styles.cardTitle}>Ajouter une zone</Text>
          <AppInput label="Ville" value={city} onChangeText={setCity} placeholder="Ex. Cotonou" />
          <AppInput label="Quartier (facultatif)" value={district} onChangeText={setDistrict} placeholder="Ex. Haie Vive" />
          <AppButton title="Ajouter la zone" variant="secondary" onPress={addArea} icon={<Plus size={18} color={colors.text} />} />
        </View>

        {areas.length > 0 ? (
          <View style={styles.card}>
            <Text style={styles.cardTitle}>Zones configurées ({areas.length})</Text>
            {areas.map((area, index) => (
              <View key={`${area.city}-${area.district}-${index}`} style={styles.areaRow}>
                <View style={styles.areaIcon}>
                  <MapPin size={16} color={colors.primary} />
                </View>
                <View style={styles.areaText}>
                  <Text style={styles.areaName}>{area.city}</Text>
                  {area.district ? <Text style={styles.areaDistrict}>{area.district}</Text> : <Text style={styles.areaDistrict}>Toute la ville</Text>}
                </View>
                <Pressable onPress={() => removeArea(index)} style={styles.removeButton}>
                  <Trash2 size={16} color={colors.danger} />
                </Pressable>
              </View>
            ))}
          </View>
        ) : (
          <Text style={styles.emptyText}>Aucune zone configurée pour le moment.</Text>
        )}

        <AppButton title="Enregistrer" onPress={save} loading={saving} disabled={saving || areas.length === 0} />
      </ScrollView>
    </ScreenContainer>
  );
}

const styles = StyleSheet.create({
  header: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingVertical: 14, borderBottomWidth: 1, borderBottomColor: colors.border },
  backButton: { width: 40, height: 40, borderRadius: 12, backgroundColor: colors.white, borderWidth: 1, borderColor: colors.border, alignItems: 'center', justifyContent: 'center' },
  headerTitle: { color: colors.text, fontSize: 20, fontWeight: '700' },
  content: { paddingVertical: 20, paddingBottom: 84, gap: 14 },
  hint: { color: colors.muted, fontSize: 13, lineHeight: 19 },
  card: { backgroundColor: colors.white, borderRadius: 12, borderWidth: 1, borderColor: colors.border, padding: 16, gap: 12 },
  cardTitle: { color: colors.text, fontSize: 17, fontWeight: '700' },
  areaRow: { flexDirection: 'row', alignItems: 'center', gap: 10 },
  areaIcon: { width: 34, height: 34, borderRadius: 10, backgroundColor: colors.primaryLight, alignItems: 'center', justifyContent: 'center' },
  areaText: { flex: 1 },
  areaName: { color: colors.text, fontSize: 15, fontWeight: '700' },
  areaDistrict: { color: colors.muted, fontSize: 12 },
  removeButton: { width: 36, height: 36, borderRadius: 18, backgroundColor: '#FEE4E2', alignItems: 'center', justifyContent: 'center' },
  emptyText: { color: colors.muted, fontSize: 14, textAlign: 'center', marginVertical: 8 },
});
