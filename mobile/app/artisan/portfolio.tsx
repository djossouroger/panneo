import { useCallback, useState } from 'react';
import { useRouter } from 'expo-router';
import { useFocusEffect } from '@react-navigation/native';
import { Alert, Image, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import * as ImagePicker from 'expo-image-picker';
import { ChevronLeft, ImagePlus, Plus, Trash2 } from 'lucide-react-native';
import { colors } from '../../components/theme';
import { AppButton, AppInput, ErrorMessage, LoadingState, ScreenContainer } from '../../components/ui';
import { addPortfolioItem, ApiError, deletePortfolioItem, friendlyError, getPortfolio, PortfolioItem } from '../../lib/api';
import { handleSessionExpired, restoreSession } from '../../lib/session';

const MAX_PORTFOLIO = 6;

export default function ArtisanPortfolioScreen() {
  const router = useRouter();
  const [token, setToken] = useState<string | null>(null);
  const [items, setItems] = useState<PortfolioItem[]>([]);
  const [caption, setCaption] = useState('');
  const [loading, setLoading] = useState(true);
  const [adding, setAdding] = useState(false);
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
      setItems(await getPortfolio(session.token));
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

  const addPhoto = async () => {
    if (!token || adding) return;
    if (items.length >= MAX_PORTFOLIO) {
      Alert.alert('Limite atteinte', `Vous pouvez publier ${MAX_PORTFOLIO} réalisations au maximum.`);
      return;
    }
    try {
      const permission = await ImagePicker.requestMediaLibraryPermissionsAsync();
      if (!permission.granted) {
        setError('Autorisez l’accès à vos photos pour ajouter une réalisation.');
        return;
      }
      const result = await ImagePicker.launchImageLibraryAsync({
        mediaTypes: ['images'],
        quality: 0.8,
        allowsEditing: true,
        aspect: [4, 3],
      });
      if (result.canceled || !result.assets?.length) return;
      const uri = result.assets[0].uri;
      setAdding(true);
      setError(null);
      try {
        await addPortfolioItem(token, uri, caption.trim() || null);
        setCaption('');
        await load();
      } catch (err) {
        setError(err instanceof ApiError ? err.message : friendlyError(err));
      } finally {
        setAdding(false);
      }
    } catch {
      setError('Impossible d’accéder à vos photos.');
    }
  };

  const removeItem = async (id: number) => {
    if (!token) return;
    Alert.alert('Supprimer la réalisation', 'Voulez-vous retirer cette photo de votre portfolio ?', [
      { text: 'Non', style: 'cancel' },
      {
        text: 'Oui, supprimer',
        style: 'destructive',
        onPress: async () => {
          try {
            await deletePortfolioItem(token, id);
            await load();
          } catch (err) {
            setError(err instanceof ApiError ? err.message : friendlyError(err));
          }
        },
      },
    ]);
  };

  if (loading) {
    return <ScreenContainer><LoadingState label="Chargement de votre portfolio..." /></ScreenContainer>;
  }

  return (
    <ScreenContainer>
      <View style={styles.header}>
        <Pressable onPress={() => router.back()} style={styles.backButton}>
          <ChevronLeft size={22} color={colors.text} />
        </Pressable>
        <Text style={styles.headerTitle}>Portfolio</Text>
      </View>

      <ScrollView contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
        <ErrorMessage message={error} />
        <Text style={styles.hint}>
          Montrez vos réalisations aux clients ({MAX_PORTFOLIO} photos maximum). Les photos sont visibles publiquement.
        </Text>

        {items.length > 0 ? (
          <View style={styles.grid}>
            {items.map((item) => (
              <View key={item.id} style={styles.gridItem}>
                <Image source={{ uri: item.image_url }} style={styles.gridImage} />
                {item.caption ? <Text style={styles.gridCaption} numberOfLines={2}>{item.caption}</Text> : null}
                <Pressable onPress={() => removeItem(item.id)} style={styles.gridRemove}>
                  <Trash2 size={14} color={colors.white} />
                </Pressable>
              </View>
            ))}
          </View>
        ) : (
          <View style={styles.emptyBox}>
            <View style={styles.emptyIcon}>
              <ImagePlus size={22} color={colors.primary} />
            </View>
            <Text style={styles.emptyTitle}>Aucune réalisation</Text>
            <Text style={styles.emptyText}>Ajoutez des photos de vos interventions terminées.</Text>
          </View>
        )}

        <View style={styles.card}>
          <Text style={styles.cardTitle}>Ajouter une photo</Text>
          <AppInput label="Légende (facultatif)" value={caption} onChangeText={setCaption} placeholder="Ex. Rénovation électrique complète" />
          <AppButton title="Choisir une photo" onPress={addPhoto} loading={adding} disabled={adding || items.length >= MAX_PORTFOLIO} icon={<Plus size={18} color={colors.white} />} />
        </View>
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
  grid: { flexDirection: 'row', flexWrap: 'wrap', gap: 10 },
  gridItem: { width: '47%', gap: 6, position: 'relative' },
  gridImage: { width: '100%', aspectRatio: 4 / 3, borderRadius: 12, backgroundColor: '#F2F4F7' },
  gridCaption: { color: colors.muted, fontSize: 12, lineHeight: 16 },
  gridRemove: { position: 'absolute', top: 8, right: 8, width: 30, height: 30, borderRadius: 15, backgroundColor: 'rgba(240, 68, 56, 0.9)', alignItems: 'center', justifyContent: 'center' },
  emptyBox: { backgroundColor: colors.white, borderRadius: 12, borderWidth: 1, borderColor: colors.border, padding: 20, alignItems: 'center', gap: 8 },
  emptyIcon: { width: 46, height: 46, borderRadius: 12, backgroundColor: colors.primaryLight, alignItems: 'center', justifyContent: 'center' },
  emptyTitle: { color: colors.text, fontSize: 17, fontWeight: '700' },
  emptyText: { color: colors.muted, fontSize: 14, textAlign: 'center' },
  card: { backgroundColor: colors.white, borderRadius: 12, borderWidth: 1, borderColor: colors.border, padding: 16, gap: 12 },
  cardTitle: { color: colors.text, fontSize: 17, fontWeight: '700' },
});
