import { useCallback, useState } from 'react';
import { useRouter } from 'expo-router';
import { useFocusEffect } from '@react-navigation/native';
import { Alert, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { ChevronLeft, LogOut, MonitorSmartphone } from 'lucide-react-native';
import { colors } from '../../components/theme';
import { AppButton, AppInput, ErrorMessage, LoadingState, PasswordInput, ScreenContainer } from '../../components/ui';
import { ApiError, friendlyError, getSessions, revokeOtherSessions, revokeSession, SessionInfo } from '../../lib/api';
import { handleSessionExpired, restoreSession } from '../../lib/session';

function deviceLabel(deviceName: string | undefined) {
  if (!deviceName) return 'Appareil inconnu';
  return deviceName;
}

export default function SessionsScreen() {
  const router = useRouter();
  const [sessions, setSessions] = useState<SessionInfo[]>([]);
  const [password, setPassword] = useState('');
  const [showOthersForm, setShowOthersForm] = useState(false);
  const [loading, setLoading] = useState(true);
  const [revoking, setRevoking] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const session = await restoreSession();
      if (!session) {
        router.replace('/login');
        return;
      }
      setSessions(await getSessions(session.token));
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

  const revokeOne = async (id: number) => {
    if (revoking) return;
    Alert.alert('Déconnecter l’appareil', 'Cette session sera immédiatement fermée.', [
      { text: 'Annuler', style: 'cancel' },
      {
        text: 'Déconnecter',
        style: 'destructive',
        onPress: async () => {
          setRevoking(true);
          setError(null);
          try {
            const session = await restoreSession();
            if (!session) return;
            await revokeSession(session.token, id);
            await load();
          } catch (err) {
            if (await handleSessionExpired(err)) {
              router.replace('/login');
              return;
            }
            setError(err instanceof ApiError ? err.message : friendlyError(err));
          } finally {
            setRevoking(false);
          }
        },
      },
    ]);
  };

  const revokeOthers = async () => {
    if (revoking) return;
    if (!password) {
      setError('Confirmez votre mot de passe.');
      return;
    }
    setRevoking(true);
    setError(null);
    try {
      const session = await restoreSession();
      if (!session) return;
      await revokeOtherSessions(session.token, password);
      setPassword('');
      setShowOthersForm(false);
      Alert.alert('Sessions fermées', 'Tous les autres appareils ont été déconnectés.', [{ text: 'OK' }]);
      await load();
    } catch (err) {
      if (await handleSessionExpired(err)) {
        router.replace('/login');
        return;
      }
      setError(err instanceof ApiError ? err.message : friendlyError(err));
    } finally {
      setRevoking(false);
    }
  };

  if (loading) {
    return <ScreenContainer><LoadingState label="Chargement des sessions..." /></ScreenContainer>;
  }

  return (
    <ScreenContainer>
      <View style={styles.header}>
        <Pressable onPress={() => router.back()} style={styles.backButton}>
          <ChevronLeft size={22} color={colors.text} />
        </Pressable>
        <Text style={styles.headerTitle}>Sessions actives</Text>
      </View>

      <ScrollView contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
        <ErrorMessage message={error} />
        <Text style={styles.hint}>Les appareils actuellement connectés à votre compte. Votre session actuelle ne peut pas être fermée ici.</Text>

        <View style={styles.list}>
          {sessions.map((session) => (
            <View key={session.id} style={styles.sessionCard}>
              <View style={styles.sessionIcon}>
                <MonitorSmartphone size={20} color={session.is_current ? colors.primary : colors.muted} />
              </View>
              <View style={styles.sessionText}>
                <Text style={styles.sessionName}>{deviceLabel(session.device_name)}{session.is_current ? ' (cet appareil)' : ''}</Text>
                <Text style={styles.sessionMeta}>
                  {session.last_used_at ? `Utilisé en dernier le ${new Date(session.last_used_at).toLocaleDateString('fr-FR', { day: '2-digit', month: 'long' })}` : 'Connexion récente'}
                </Text>
              </View>
              {!session.is_current ? (
                <Pressable onPress={() => revokeOne(session.id)} style={styles.revokeButton} disabled={revoking}>
                  <LogOut size={16} color={colors.danger} />
                </Pressable>
              ) : null}
            </View>
          ))}
        </View>

        {!showOthersForm ? (
          <AppButton title="Déconnecter les autres appareils" variant="secondary" onPress={() => setShowOthersForm(true)} />
        ) : (
          <View style={styles.card}>
            <Text style={styles.cardTitle}>Fermer toutes les autres sessions</Text>
            <PasswordInput label="Confirmer le mot de passe" value={password} onChangeText={setPassword} placeholder="Votre mot de passe" />
            <AppButton title="Confirmer" onPress={revokeOthers} loading={revoking} disabled={revoking} />
            <Pressable onPress={() => setShowOthersForm(false)} disabled={revoking}>
              <Text style={styles.linkText}>Annuler</Text>
            </Pressable>
          </View>
        )}
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
  list: { gap: 10 },
  sessionCard: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: colors.white, borderRadius: 12, borderWidth: 1, borderColor: colors.border, padding: 14 },
  sessionIcon: { width: 42, height: 42, borderRadius: 12, backgroundColor: colors.primaryLight, alignItems: 'center', justifyContent: 'center' },
  sessionText: { flex: 1 },
  sessionName: { color: colors.text, fontSize: 15, fontWeight: '700' },
  sessionMeta: { color: colors.muted, fontSize: 12, marginTop: 2 },
  revokeButton: { width: 36, height: 36, borderRadius: 18, backgroundColor: '#FEE4E2', alignItems: 'center', justifyContent: 'center' },
  card: { backgroundColor: colors.white, borderRadius: 12, borderWidth: 1, borderColor: colors.border, padding: 16, gap: 8 },
  cardTitle: { color: colors.text, fontSize: 17, fontWeight: '700' },
  linkText: { color: colors.primary, fontWeight: '700', textAlign: 'center', paddingVertical: 12 },
});
