import { useState } from 'react';
import { useRouter } from 'expo-router';
import { Alert, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { ChevronLeft, Trash2 } from 'lucide-react-native';
import { colors } from '../../components/theme';
import { AppButton, ErrorMessage, PasswordInput, ScreenContainer } from '../../components/ui';
import { ApiError, deleteAccount, friendlyError } from '../../lib/api';
import { clearSession, handleSessionExpired, restoreSession } from '../../lib/session';

export default function DeleteAccountScreen() {
  const router = useRouter();
  const [password, setPassword] = useState('');
  const [deleting, setDeleting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const submit = () => {
    if (deleting) return;
    if (!password) {
      setError('Confirmez votre mot de passe pour supprimer le compte.');
      return;
    }
    Alert.alert('Supprimer définitivement le compte ?', 'Cette action est irréversible. Toutes vos données personnelles seront supprimées.', [
      { text: 'Annuler', style: 'cancel' },
      {
        text: 'Oui, supprimer',
        style: 'destructive',
        onPress: async () => {
          setDeleting(true);
          setError(null);
          try {
            const session = await restoreSession();
            if (!session) {
              router.replace('/login');
              return;
            }
            await deleteAccount(session.token, password);
            await clearSession();
            router.replace('/login');
          } catch (err) {
            if (await handleSessionExpired(err)) {
              router.replace('/login');
              return;
            }
            setError(err instanceof ApiError ? err.message : friendlyError(err));
          } finally {
            setDeleting(false);
          }
        },
      },
    ]);
  };

  return (
    <ScreenContainer>
      <View style={styles.header}>
        <Pressable onPress={() => router.back()} style={styles.backButton}>
          <ChevronLeft size={22} color={colors.text} />
        </Pressable>
        <Text style={styles.headerTitle}>Supprimer mon compte</Text>
      </View>

      <ScrollView contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
        <ErrorMessage message={error} />

        <View style={styles.warningCard}>
          <View style={styles.warningIcon}>
            <Trash2 size={22} color={colors.danger} />
          </View>
          <Text style={styles.warningTitle}>Suppression définitive</Text>
          <Text style={styles.warningText}>
            Votre compte et vos données personnelles seront définitivement supprimés. Vos interventions en cours doivent être terminées avant la suppression.
          </Text>
        </View>

        <PasswordInput label="Confirmer le mot de passe" value={password} onChangeText={setPassword} placeholder="Votre mot de passe" />

        <AppButton title="Supprimer mon compte" variant="danger" onPress={submit} loading={deleting} disabled={deleting} />
      </ScrollView>
    </ScreenContainer>
  );
}

const styles = StyleSheet.create({
  header: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingVertical: 14, borderBottomWidth: 1, borderBottomColor: colors.border },
  backButton: { width: 40, height: 40, borderRadius: 12, backgroundColor: colors.white, borderWidth: 1, borderColor: colors.border, alignItems: 'center', justifyContent: 'center' },
  headerTitle: { color: colors.text, fontSize: 20, fontWeight: '700' },
  content: { paddingVertical: 20, paddingBottom: 84, gap: 14 },
  warningCard: { backgroundColor: '#FEE4E2', borderRadius: 12, padding: 16, gap: 8 },
  warningIcon: { width: 44, height: 44, borderRadius: 12, backgroundColor: colors.white, alignItems: 'center', justifyContent: 'center' },
  warningTitle: { color: colors.danger, fontSize: 17, fontWeight: '700' },
  warningText: { color: colors.text, fontSize: 14, lineHeight: 21 },
});
