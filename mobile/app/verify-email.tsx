import { useCallback, useState } from 'react';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { useFocusEffect } from '@react-navigation/native';
import { Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { ChevronLeft, MailCheck } from 'lucide-react-native';
import { colors } from '../components/theme';
import { AppButton, AppInput, ErrorMessage, LoadingState, ScreenContainer } from '../components/ui';
import { ApiError, confirmEmailVerify, fetchMe, friendlyError, sendEmailVerifyCode } from '../lib/api';
import { getStoredUser, getToken, restoreSession, saveUser } from '../lib/session';

export default function VerifyEmailScreen() {
  const router = useRouter();
  const params = useLocalSearchParams<{ email?: string; next?: string }>();
  const [email, setEmail] = useState('');
  const [code, setCode] = useState('');
  const [sending, setSending] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      if (params.email) {
        setEmail(params.email);
        return;
      }
      const session = await restoreSession();
      if (!session) {
        router.replace('/login');
        return;
      }
      setEmail(session.user.email);
    } finally {
      setLoading(false);
    }
  }, [params.email, router]);

  useFocusEffect(useCallback(() => {
    load();
  }, [load]));

  const sendCode = async () => {
    if (sending) return;
    if (!email.trim()) {
      setError('Adresse e-mail manquante.');
      return;
    }
    setSending(true);
    setError(null);
    try {
      await sendEmailVerifyCode(email.trim());
    } catch (err) {
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
      await confirmEmailVerify(email.trim(), code.trim());

      const token = await getToken();
      if (token) {
        try {
          const user = await fetchMe(token);
          await saveUser(user);
        } catch {
          const stored = await getStoredUser();
          if (stored) await saveUser({ ...stored, email_verified_at: new Date().toISOString() });
        }
        router.replace(params.next ?? '/');
      } else {
        router.replace('/login');
      }
    } catch (err) {
      setError(err instanceof ApiError ? err.message : friendlyError(err));
    } finally {
      setSubmitting(false);
    }
  };

  if (loading) {
    return <ScreenContainer><LoadingState label="Chargement..." /></ScreenContainer>;
  }

  return (
    <ScreenContainer>
      <View style={styles.topBar}>
        <Pressable onPress={() => (router.canGoBack() ? router.back() : router.replace('/login'))} style={styles.backButton} hitSlop={8}>
          <ChevronLeft size={22} color={colors.text} />
        </Pressable>
        <Text style={styles.headerTitle}>Vérification e-mail</Text>
      </View>
      <ScrollView contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
        <View style={styles.header}>
          <View style={styles.iconBox}>
            <MailCheck size={28} color={colors.primary} />
          </View>
          <Text style={styles.title}>Vérifiez votre adresse e-mail</Text>
          <Text style={styles.hint}>
            Un code de confirmation a été envoyé à <Text style={styles.emailText}>{email}</Text>. Saisissez-le pour activer votre compte.
          </Text>
        </View>

        <ErrorMessage message={error} />

        <AppInput label="Code de vérification" value={code} onChangeText={setCode} placeholder="6 chiffres" keyboardType="phone-pad" />

        <AppButton title="Activer mon compte" onPress={confirm} loading={submitting} disabled={submitting} />

        <Pressable onPress={sendCode} disabled={sending}>
          <Text style={styles.linkText}>{sending ? 'Envoi en cours...' : 'Renvoyer le code'}</Text>
        </Pressable>
      </ScrollView>
    </ScreenContainer>
  );
}

const styles = StyleSheet.create({
  topBar: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingVertical: 14, borderBottomWidth: 1, borderBottomColor: colors.border },
  backButton: { width: 40, height: 40, borderRadius: 12, backgroundColor: colors.white, borderWidth: 1, borderColor: colors.border, alignItems: 'center', justifyContent: 'center' },
  headerTitle: { color: colors.text, fontSize: 20, fontWeight: '700' },
  content: { paddingVertical: 32, gap: 8 },
  header: { alignItems: 'center', marginBottom: 20, gap: 10 },
  iconBox: { width: 64, height: 64, borderRadius: 20, backgroundColor: colors.primaryLight, alignItems: 'center', justifyContent: 'center', marginBottom: 4 },
  title: { fontSize: 24, lineHeight: 32, fontWeight: '700', color: colors.text, textAlign: 'center' },
  hint: { fontSize: 14, lineHeight: 21, color: colors.muted, textAlign: 'center', paddingHorizontal: 8 },
  emailText: { color: colors.text, fontWeight: '700' },
  linkText: { color: colors.primary, fontWeight: '700', textAlign: 'center', paddingVertical: 12 },
});
