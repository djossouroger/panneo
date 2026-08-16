import { useCallback, useState } from 'react';
import { useRouter } from 'expo-router';
import { Alert, Pressable, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { ChevronLeft, ChevronRight, Mail, MonitorSmartphone, ShieldCheck, Trash2, UserRound } from 'lucide-react-native';
import { colors } from '../components/theme';
import { AppButton, AppInput, ErrorMessage, LoadingState, ScreenContainer, StatusBadge } from '../components/ui';
import { ApiError, friendlyError, updateAccountProfile, User } from '../lib/api';
import { handleSessionExpired, restoreSession, saveUser } from '../lib/session';
import { useFocusLoad } from '../lib/focusLoad';

export default function AccountScreen() {
  const router = useRouter();
  const [user, setUser] = useState<User | null>(null);
  const [name, setName] = useState('');
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [saving, setSaving] = useState(false);
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
      setUser(session.user);
      setName(session.user.name);
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

  useFocusLoad(load);

  const saveName = async () => {
    if (!user || saving) return;
    if (!name.trim()) {
      setError('Renseignez votre nom.');
      return;
    }
    setSaving(true);
    setError(null);
    try {
      const session = await restoreSession();
      if (!session) return;
      const updated = await updateAccountProfile(session.token, name.trim());
      setUser(updated);
      await saveUser(updated);
      Alert.alert('Nom mis à jour', 'Votre nom a été enregistré.', [{ text: 'OK' }]);
    } catch (err) {
      if (await handleSessionExpired(err)) {
        router.replace('/login');
        return;
      }
      setError(err instanceof ApiError ? err.message : friendlyError(err));
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return <ScreenContainer><LoadingState label="Chargement du compte..." /></ScreenContainer>;
  }

  if (!user) {
    return <ScreenContainer><LoadingState label="Connexion requise..." /></ScreenContainer>;
  }

  return (
    <ScreenContainer>
      <View style={styles.header}>
        <Pressable onPress={() => router.back()} style={styles.backButton}>
          <ChevronLeft size={22} color={colors.text} />
        </Pressable>
        <Text style={styles.headerTitle}>Compte et sécurité</Text>
      </View>

      <ScrollView contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => load(true)} tintColor={colors.primary} />}
      >
        <ErrorMessage message={error} />

        <View style={styles.card}>
          <View style={styles.fieldRow}>
            <View style={styles.fieldIcon}><UserRound size={18} color={colors.primary} /></View>
            <Text style={styles.fieldLabel}>Nom</Text>
          </View>
          <AppInput label="Nom complet" value={name} onChangeText={setName} />
          <AppButton title="Enregistrer le nom" variant="secondary" onPress={saveName} loading={saving} disabled={saving} />
        </View>

        <View style={styles.card}>
          <View style={styles.fieldRow}>
            <View style={styles.fieldIcon}><Mail size={18} color={colors.primary} /></View>
            <Text style={styles.fieldLabel}>Adresse e-mail</Text>
          </View>
          <View style={styles.valueRow}>
            <Text style={styles.valueText}>{user.email}</Text>
          </View>
          <AppButton title="Changer l’adresse e-mail" variant="secondary" onPress={() => router.push('/account/email')} />
        </View>

        <View style={styles.card}>
          <View style={styles.fieldRow}>
            <View style={styles.fieldIcon}><ShieldCheck size={18} color={colors.primary} /></View>
            <Text style={styles.fieldLabel}>Numéro de téléphone</Text>
          </View>
          <View style={styles.valueRow}>
            <Text style={styles.valueText}>{user.phone || 'Non renseigné'}</Text>
            {user.phone ? <StatusBadge label={user.phone_verified ? 'Vérifié' : 'Non vérifié'} tone={user.phone_verified ? 'success' : 'neutral'} /> : null}
          </View>
          {user.phone && !user.phone_verified ? (
            <AppButton title="Vérifier mon numéro" variant="secondary" onPress={() => router.push('/account/verify-phone')} />
          ) : null}
          <AppButton title="Changer le numéro de téléphone" variant="secondary" onPress={() => router.push('/account/phone')} />
        </View>

        <AccountRow icon={<MonitorSmartphone size={18} color={colors.primary} />} title="Sessions actives" subtitle="Gérez les appareils connectés" onPress={() => router.push('/account/sessions')} />

        <AccountRow icon={<Trash2 size={18} color={colors.danger} />} title="Supprimer mon compte" subtitle="Suppression définitive du compte" onPress={() => router.push('/account/delete')} danger />
      </ScrollView>
    </ScreenContainer>
  );
}

function AccountRow({ icon, title, subtitle, onPress, danger = false }: {
  icon: React.ReactNode;
  title: string;
  subtitle: string;
  onPress: () => void;
  danger?: boolean;
}) {
  return (
    <Pressable onPress={onPress} style={[styles.rowCard, danger && styles.rowDanger]}>
      <View style={[styles.fieldIcon, danger && styles.fieldIconDanger]}>{icon}</View>
      <View style={styles.rowText}>
        <Text style={[styles.rowTitle, danger && styles.rowTitleDanger]}>{title}</Text>
        <Text style={styles.rowSubtitle}>{subtitle}</Text>
      </View>
      <ChevronRight size={18} color={colors.muted} />
    </Pressable>
  );
}

const styles = StyleSheet.create({
  header: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingVertical: 14, borderBottomWidth: 1, borderBottomColor: colors.border },
  backButton: { width: 40, height: 40, borderRadius: 12, backgroundColor: colors.white, borderWidth: 1, borderColor: colors.border, alignItems: 'center', justifyContent: 'center' },
  headerTitle: { color: colors.text, fontSize: 20, fontWeight: '700' },
  content: { paddingVertical: 20, paddingBottom: 84, gap: 12 },
  card: { backgroundColor: colors.white, borderRadius: 12, borderWidth: 1, borderColor: colors.border, padding: 16, gap: 10 },
  fieldRow: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  fieldIcon: { width: 34, height: 34, borderRadius: 10, backgroundColor: colors.primaryLight, alignItems: 'center', justifyContent: 'center' },
  fieldIconDanger: { backgroundColor: '#FEE4E2' },
  fieldLabel: { color: colors.muted, fontSize: 12, fontWeight: '700', textTransform: 'uppercase' },
  valueRow: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 8 },
  valueText: { color: colors.text, fontSize: 16, fontWeight: '600', flex: 1 },
  rowCard: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: colors.white, borderRadius: 12, borderWidth: 1, borderColor: colors.border, padding: 16 },
  rowDanger: { borderColor: '#FEE4E2' },
  rowText: { flex: 1 },
  rowTitle: { color: colors.text, fontSize: 15, fontWeight: '700' },
  rowTitleDanger: { color: colors.danger },
  rowSubtitle: { color: colors.muted, fontSize: 13 },
});
