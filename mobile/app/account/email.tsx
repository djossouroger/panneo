import { useState } from 'react';
import { useRouter } from 'expo-router';
import { Alert, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { ChevronLeft, Mail } from 'lucide-react-native';
import { colors } from '../../components/theme';
import { AppButton, AppInput, ErrorMessage, ScreenContainer } from '../../components/ui';
import { ApiError, changeEmail, friendlyError, requestEmailChange } from '../../lib/api';
import { handleSessionExpired, restoreSession, saveUser } from '../../lib/session';

export default function ChangeEmailScreen() {
  const router = useRouter();
  const [email, setEmail] = useState('');
  const [code, setCode] = useState('');
  const [codeSent, setCodeSent] = useState(false);
  const [sending, setSending] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const sendCode = async () => {
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.trim())) {
      setError('Indiquez une adresse e-mail valide.');
      return;
    }
    setSending(true);
    setError(null);
    try {
      const session = await restoreSession();
      if (!session) {
        router.replace('/login');
        return;
      }
      await requestEmailChange(session.token, email.trim());
      setCodeSent(true);
    } catch (err) {
      if (await handleSessionExpired(err)) {
        router.replace('/login');
        return;
      }
      setError(err instanceof ApiError ? err.message : friendlyError(err));
    } finally {
      setSending(false);
    }
  };

  const confirm = async () => {
    if (submitting) return;
    if (!code.trim()) {
      setError('Saisissez le code de vérification.');
      return;
    }
    setSubmitting(true);
    setError(null);
    try {
      const session = await restoreSession();
      if (!session) {
        router.replace('/login');
        return;
      }
      const updated = await changeEmail(session.token, email.trim(), code.trim());
      await saveUser(updated);
      Alert.alert('Adresse e-mail mise à jour', 'Votre nouvelle adresse e-mail est active.', [{ text: 'OK', onPress: () => router.replace('/account') }]);
    } catch (err) {
      if (await handleSessionExpired(err)) {
        router.replace('/login');
        return;
      }
      setError(err instanceof ApiError ? err.message : friendlyError(err));
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <ScreenContainer>
      <View style={styles.header}>
        <Pressable onPress={() => router.back()} style={styles.backButton}>
          <ChevronLeft size={22} color={colors.text} />
        </Pressable>
        <Text style={styles.headerTitle}>Changer mon e-mail</Text>
      </View>

      <ScrollView contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
        <ErrorMessage message={error} />
        <Text style={styles.hint}>
          Un code de vérification sera envoyé à votre nouvelle adresse e-mail. Saisissez-le pour confirmer le changement.
        </Text>

        <AppInput label="Nouvelle adresse e-mail" value={email} onChangeText={setEmail} placeholder="vous@nouvelle-email.com" keyboardType="email-address" icon={<Mail size={18} color={colors.muted} />} />

        {!codeSent ? (
          <AppButton title="Envoyer le code" onPress={sendCode} loading={sending} disabled={sending} />
        ) : (
          <>
            <AppInput label="Code de vérification" value={code} onChangeText={setCode} placeholder="6 chiffres" keyboardType="phone-pad" />
            <AppButton title="Confirmer le changement" onPress={confirm} loading={submitting} disabled={submitting} />
            <Pressable onPress={sendCode} disabled={sending}>
              <Text style={styles.linkText}>Renvoyer le code</Text>
            </Pressable>
          </>
        )}
      </ScrollView>
    </ScreenContainer>
  );
}

const styles = StyleSheet.create({
  header: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingVertical: 14, borderBottomWidth: 1, borderBottomColor: colors.border },
  backButton: { width: 40, height: 40, borderRadius: 12, backgroundColor: colors.white, borderWidth: 1, borderColor: colors.border, alignItems: 'center', justifyContent: 'center' },
  headerTitle: { color: colors.text, fontSize: 20, fontWeight: '700' },
  content: { paddingVertical: 20, paddingBottom: 84, gap: 8 },
  hint: { color: colors.muted, fontSize: 13, lineHeight: 19, marginBottom: 8 },
  linkText: { color: colors.primary, fontWeight: '700', textAlign: 'center', paddingVertical: 12 },
});
