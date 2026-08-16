import { useCallback, useState } from 'react';
import { useRouter } from 'expo-router';
import { useFocusEffect } from '@react-navigation/native';
import { Alert, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { Check, ChevronLeft, Star } from 'lucide-react-native';
import { colors } from '../../components/theme';
import { AppButton, ErrorMessage, LoadingState, ScreenContainer } from '../../components/ui';
import { getCategoryIcon } from '../../components/repairRequests';
import { ApiError, Category, fetchArtisanProfile, friendlyError, getCategories, updateArtisanCategories } from '../../lib/api';
import { handleSessionExpired, restoreSession } from '../../lib/session';

const MAX_CATEGORIES = 3;

export default function ArtisanCategoriesScreen() {
  const router = useRouter();
  const [token, setToken] = useState<string | null>(null);
  const [categories, setCategories] = useState<Category[]>([]);
  const [selected, setSelected] = useState<number[]>([]);
  const [primary, setPrimary] = useState<number | null>(null);
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
      const [allCategories, profile] = await Promise.all([
        getCategories(),
        fetchArtisanProfile(session.token),
      ]);
      const activeCategories = allCategories.filter((category) => category.is_active);
      setCategories(activeCategories);
      const currentIds = profile.profile.categories.map((cat) => cat.id);
      setSelected(currentIds);
      setPrimary(profile.profile.categories.find((cat) => cat.is_primary)?.id ?? currentIds[0] ?? null);
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

  const toggleCategory = (id: number) => {
    setError(null);
    setSelected((prev) => {
      if (prev.includes(id)) {
        if (primary === id) setPrimary(prev.filter((item) => item !== id)[0] ?? null);
        return prev.filter((item) => item !== id);
      }
      if (prev.length >= MAX_CATEGORIES) {
        Alert.alert('Limite atteinte', `Vous pouvez sélectionner ${MAX_CATEGORIES} métiers au maximum.`);
        return prev;
      }
      if (prev.length === 0) setPrimary(id);
      return [...prev, id];
    });
  };

  const save = async () => {
    if (!token || saving) return;
    if (selected.length === 0) {
      setError('Sélectionnez au moins un métier.');
      return;
    }
    if (!primary) {
      setError('Choisissez un métier principal.');
      return;
    }
    setSaving(true);
    setError(null);
    try {
      await updateArtisanCategories(token, selected, primary);
      Alert.alert('Métiers enregistrés', 'Vos métiers ont été mis à jour.', [{ text: 'OK', onPress: () => router.back() }]);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : friendlyError(err));
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return <ScreenContainer><LoadingState label="Chargement des métiers..." /></ScreenContainer>;
  }

  return (
    <ScreenContainer>
      <View style={styles.header}>
        <Pressable onPress={() => router.back()} style={styles.backButton}>
          <ChevronLeft size={22} color={colors.text} />
        </Pressable>
        <Text style={styles.headerTitle}>Mes métiers</Text>
      </View>

      <ScrollView contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
        <ErrorMessage message={error} />
        <Text style={styles.hint}>
          Choisissez jusqu’à {MAX_CATEGORIES} métiers. Le métier principal est affiché en premier sur votre profil et utilisé pour la recherche.
        </Text>

        <View style={styles.list}>
          {categories.map((category) => {
            const isSelected = selected.includes(category.id);
            const isPrimary = primary === category.id;
            const Icon = getCategoryIcon(category.icon);
            return (
              <Pressable key={category.id} onPress={() => toggleCategory(category.id)} style={[styles.categoryCard, isSelected && styles.categoryCardSelected]}>
                <View style={[styles.categoryIcon, isSelected && styles.categoryIconSelected]}>
                  <Icon size={22} color={isSelected ? colors.primary : colors.text} />
                </View>
                <View style={styles.categoryText}>
                  <Text style={styles.categoryName}>{category.name}</Text>
                  {category.indicative_min_price != null ? (
                    <Text style={styles.categoryPrice}>
                      À partir de {category.indicative_min_price.toLocaleString('fr-FR')} {category.currency || 'FCFA'}
                    </Text>
                  ) : null}
                </View>
                {isSelected ? (
                  <View style={styles.checkBadge}>
                    <Check size={14} color={colors.white} strokeWidth={3} />
                  </View>
                ) : null}
                {isSelected && !isPrimary ? (
                  <Pressable onPress={() => setPrimary(category.id)} style={styles.primaryButton}>
                    <Star size={15} color={colors.urgent} />
                    <Text style={styles.primaryButtonText}>Principal</Text>
                  </Pressable>
                ) : null}
                {isPrimary ? (
                  <View style={styles.primaryBadge}>
                    <Star size={14} color={colors.urgent} />
                    <Text style={styles.primaryBadgeText}>Métier principal</Text>
                  </View>
                ) : null}
              </Pressable>
            );
          })}
        </View>

        <AppButton title="Enregistrer" onPress={save} loading={saving} disabled={saving || selected.length === 0} />
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
  list: { gap: 12 },
  categoryCard: { backgroundColor: colors.white, borderRadius: 12, borderWidth: 1, borderColor: colors.border, padding: 14, flexDirection: 'row', alignItems: 'center', gap: 12 },
  categoryCardSelected: { borderColor: colors.primary, backgroundColor: colors.primaryLight },
  categoryIcon: { width: 44, height: 44, borderRadius: 12, backgroundColor: '#F2F4F7', alignItems: 'center', justifyContent: 'center' },
  categoryIconSelected: { backgroundColor: colors.white },
  categoryText: { flex: 1 },
  categoryName: { color: colors.text, fontSize: 15, fontWeight: '700' },
  categoryPrice: { color: colors.muted, fontSize: 12, marginTop: 2 },
  checkBadge: { width: 22, height: 22, borderRadius: 11, backgroundColor: colors.primary, alignItems: 'center', justifyContent: 'center' },
  primaryButton: { alignItems: 'center', gap: 2 },
  primaryButtonText: { color: colors.urgent, fontSize: 10, fontWeight: '700' },
  primaryBadge: { flexDirection: 'row', alignItems: 'center', gap: 4, backgroundColor: '#FFF3E2', borderRadius: 10, paddingHorizontal: 8, paddingVertical: 4 },
  primaryBadgeText: { color: colors.urgent, fontSize: 11, fontWeight: '700' },
});
