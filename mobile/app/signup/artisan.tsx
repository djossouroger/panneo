import { useRouter } from 'expo-router';
import { useEffect, useState } from 'react';
import { Pressable, ScrollView, Text, View, StyleSheet } from 'react-native';
import { Fan, KeyRound, PlugZap, Wrench, Zap } from 'lucide-react-native';
import { colors } from '../../components/theme';
import { AppButton, AppInput, EmptyState, ErrorMessage, LoadingState, ScreenContainer } from '../../components/ui';
import { Category, getCategories } from '../../lib/api';
import { getSignupDraft, setSignupDraft } from '../../lib/signupDraft';

const iconMap = {
  plumbing: Wrench,
  electricity: Zap,
  locksmith: KeyRound,
  air_conditioning: Fan,
  appliance: PlugZap,
};

export default function ArtisanProfileScreen() {
  const router = useRouter();
  const [categories, setCategories] = useState<Category[]>([]);
  const [categoryId, setCategoryId] = useState<number | null>(null);
  const [city, setCity] = useState('');
  const [district, setDistrict] = useState('');
  const [loadingCategories, setLoadingCategories] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const loadCategories = async () => {
    setLoadingCategories(true);
    setError(null);
    try {
      const nextCategories = await getCategories();
      setCategories(nextCategories);
      setCategoryId((current) => current ?? nextCategories[0]?.id ?? null);
    } catch {
      setError('Impossible de charger les métiers. Vérifiez que le backend est lancé.');
    } finally {
      setLoadingCategories(false);
    }
  };

  useEffect(() => {
    loadCategories();
  }, []);

  const continueToIdentity = () => {
    if (!categoryId || !city.trim()) {
      setError('Choisissez un métier principal et renseignez votre ville.');
      return;
    }
    const draft = getSignupDraft();
    if (!draft) {
      setError('Les informations personnelles sont manquantes. Reprenez l’inscription depuis le début.');
      return;
    }
    setSignupDraft({ ...draft, categoryId, city: city.trim(), district: district.trim() || '' });
    router.push('/signup/identity');
  };

  return (
    <ScreenContainer>
      <ScrollView contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
        <View style={styles.header}>
          <Text style={styles.step}>Étape 2 sur 3</Text>
          <Text style={styles.title}>Parlez-nous de votre activité</Text>
          <Text style={styles.subtitle}>Ces informations aideront Pannéo à préparer votre espace artisan.</Text>
        </View>

        <ErrorMessage message={error} />

        <Text style={styles.label}>Métier principal</Text>
        {loadingCategories ? (
          <View style={styles.loadingBox}><LoadingState label="Chargement des métiers..." /></View>
        ) : categories.length === 0 ? (
          <EmptyState icon={<Wrench size={22} color={colors.primary} />} title="Aucun métier disponible" text="Réessayez dans un instant." />
        ) : (
          <View style={styles.categories}>
            {categories.map((category) => {
              const Icon = iconMap[category.icon as keyof typeof iconMap] || Wrench;
              const active = category.id === categoryId;
              return (
                <Pressable key={category.id} onPress={() => setCategoryId(category.id)} style={[styles.categoryCard, active && styles.categoryCardActive]}>
                  <Icon size={20} color={active ? colors.primary : colors.text} />
                  <Text style={[styles.categoryText, active && styles.categoryTextActive]}>{category.name}</Text>
                </Pressable>
              );
            })}
          </View>
        )}

        <AppInput label="Ville" value={city} onChangeText={setCity} placeholder="Cotonou" />
        <AppInput label="Quartier" value={district} onChangeText={setDistrict} placeholder="Quartier, facultatif" />

        <View style={styles.actions}>
          <AppButton title="Continuer" onPress={continueToIdentity} disabled={loadingCategories} />
          <AppButton title="Retour" variant="secondary" onPress={() => router.back()} />
          {error && !loadingCategories ? <AppButton title="Réessayer de charger les métiers" variant="secondary" onPress={loadCategories} /> : null}
        </View>
      </ScrollView>
    </ScreenContainer>
  );
}

const styles = StyleSheet.create({
  content: { paddingVertical: 24 },
  header: { marginBottom: 24 },
  step: { color: colors.primary, fontSize: 13, fontWeight: '700', marginBottom: 10 },
  title: { fontSize: 28, lineHeight: 36, fontWeight: '700', color: colors.text, marginBottom: 8 },
  subtitle: { fontSize: 15, lineHeight: 22, color: colors.muted },
  label: { color: colors.text, fontSize: 14, fontWeight: '700', marginBottom: 10 },
  loadingBox: { height: 120, marginBottom: 18 },
  categories: { gap: 10, marginBottom: 18 },
  categoryCard: { flexDirection: 'row', alignItems: 'center', gap: 10, minHeight: 52, paddingHorizontal: 14, borderRadius: 12, borderWidth: 1, borderColor: colors.border, backgroundColor: colors.white },
  categoryCardActive: { borderColor: colors.primary, backgroundColor: colors.primaryLight },
  categoryText: { flex: 1, color: colors.text, fontSize: 15, fontWeight: '600' },
  categoryTextActive: { color: colors.primary },
  actions: { gap: 12, marginTop: 12, paddingBottom: 24 },
});
