import { useState } from 'react';
import { useRouter } from 'expo-router';
import { Pressable, View, Text, StyleSheet } from 'react-native';
import { CircleCheck, Mail } from 'lucide-react-native';
import { colors } from '../components/theme';
import { AppButton, AppInput, ErrorMessage, Logo, ScreenContainer } from '../components/ui';
import { ApiError, forgotPassword } from '../lib/api';

export default function ForgotPasswordScreen() {
  const router = useRouter();
  const [email, setEmail] = useState('');
  const [sent, setSent] = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const submit = async () => {
    if (loading) return;
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.trim())) {
      setError('Indiquez une adresse e-mail valide.');
      return;
    }
    setLoading(true);
    setError(null);
    try {
      await forgotPassword(email.trim());
      setSent(true);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Impossible d’envoyer le code. Vérifiez le serveur puis réessayez.');
    } finally {
      setLoading(false);
    }
  };

  if (sent) {
    return (
      <ScreenContainer style={styles.container}>
        <View style={styles.header}>
          <Logo />
        </View>

        <View style={styles.successIcon}>
          <CircleCheck size={34} color={colors.success} />
        </View>
        <Text style={styles.title}>Vérifiez votre boîte mail</Text>
        <Text style={styles.subtitle}>
          Si un compte correspond à cette adresse, vous recevrez un code de vérification par e-mail pour réinitialiser votre mot de passe.
        </Text>

        <AppButton title="Saisir le code reçu" onPress={() => router.replace(`/reset-password?email=${encodeURIComponent(email.trim())}` as never)} />

        <View style={styles.footerRow}>
          <Text style={styles.footerText}>Vous n’avez pas reçu le code ?</Text>
          <Pressable onPress={() => setSent(false)}>
            <Text style={styles.linkText}>Réessayer</Text>
          </Pressable>
        </View>
      </ScreenContainer>
    );
  }

  return (
    <ScreenContainer style={styles.container}>
      <View style={styles.header}>
        <Logo />
      </View>

      <Text style={styles.title}>Mot de passe oublié</Text>
      <Text style={styles.subtitle}>Saisissez l’adresse e-mail associée à votre compte.</Text>

      <ErrorMessage message={error} />

      <AppInput label="Adresse e-mail" value={email} onChangeText={setEmail} placeholder="vous@example.com" keyboardType="email-address" icon={<Mail size={18} color={colors.muted} />} />

      <AppButton title="Continuer" onPress={submit} loading={loading} disabled={loading} />

      <View style={styles.footerRow}>
        <Text style={styles.footerText}>Vous connaissez votre mot de passe ?</Text>
        <Pressable onPress={() => router.back()}>
          <Text style={styles.linkText}>Se connecter</Text>
        </Pressable>
      </View>
    </ScreenContainer>
  );
}

const styles = StyleSheet.create({
  container: { justifyContent: 'center' },
  header: { alignItems: 'center', marginBottom: 24 },
  title: { fontSize: 28, lineHeight: 36, fontWeight: '700', color: colors.text, marginBottom: 8, textAlign: 'center' },
  subtitle: { fontSize: 15, color: colors.muted, marginBottom: 24, lineHeight: 22, textAlign: 'center' },
  successIcon: { alignSelf: 'center', width: 72, height: 72, borderRadius: 24, backgroundColor: '#E7F9F0', alignItems: 'center', justifyContent: 'center', marginBottom: 16 },
  footerRow: { flexDirection: 'row', alignItems: 'center', justifyContent: 'center', marginTop: 16, gap: 6 },
  footerText: { color: colors.muted },
  linkText: { color: colors.primary, fontWeight: '700' },
});
