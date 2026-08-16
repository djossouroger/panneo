import { useCallback, useState } from 'react';
import { useRouter } from 'expo-router';
import { useFocusEffect } from '@react-navigation/native';
import { Alert, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { ChevronLeft, Smartphone } from 'lucide-react-native';
import { colors } from '../../components/theme';
import { AppButton, AppInput, ErrorMessage, LoadingState, ScreenContainer } from '../../components/ui';
import { ApiError, friendlyError, sendPhoneVerifyCode, User, verifyPhone } from '../../lib/api';
import { handleSessionExpired, restoreSession, saveUser } from '../../lib/session';

export default function VerifyPhoneScreen() {
  const router = useRouter();
  const [user, setUser] = useState<User | null>(null);
  const [phone, setPhone] = useState('');
  const [code, setCode] = useState('');
  const [codeSent, setCodeSent] = useState(false);
  const [sending, setSending] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [loading, setLoading] = useState(true);
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
      setUser(session.user);
      setPhone(session.user.phone || '');
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

  const sendCode = async () => {
    if (sending) return;
    if (!/^\d{8,15}$/.test(phone.replace(/[\s.-]/g, ''))) {
      setError('Indiquez un numéro de téléphone valide.');
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
      await sendPhoneVerifyCode(session.token, phone.trim());
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
      await verifyPhone(session.token, phone.trim(), code.trim());
      const updated = { ...session.user, phone_verified: true };
      await saveUser(updated);
      Alert.alert('Numéro vérifié', 'Votre numéro de téléphone a été vérifié.', [{ text: 'OK', onPress: () => router.replace('/account') }]);
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

  if (loading) {
    return <ScreenContainer><LoadingState label="Chargement..." /></ScreenContainer>;
  }

  return (
    <ScreenContainer>
      <View style={styles.header}>
        <Pressable onPress={() => router.back()} style={styles.backButton}>
          <ChevronLeft size={22} color={colors.text} />
        </Pressable>
        <Text style={styles.headerTitle}>Vérifier mon numéro</Text>
      </View>

      <ScrollView contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
        <ErrorMessage message={error} />
        <Text style={styles.hint}>
          Un code de vérification sera envoyé par SMS au numéro enregistré sur votre compte.
        </Text>

        <AppInput label="Numéro de téléphone" value={phone} onChangeText={setPhone} placeholder="+229 90 00 00 00" keyboardType="phone-pad" icon={<Smartphone size={18} color={colors.muted} />} />

        {!codeSent ? (
          <AppButton title="Envoyer le code" onPress={sendCode} loading={sending} disabled={sending} />
        ) : (
          <>
            <AppInput label="Code de vérification" value={code} onChangeText={setCode} placeholder="6 chiffres" keyboardType="phone-pad" />
            <AppButton title="Confirmer" onPress={confirm} loading={submitting} disabled={submitting} />
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
