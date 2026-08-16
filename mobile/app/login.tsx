import { useRouter } from 'expo-router';
import { useState } from 'react';
import { Pressable, View, Text, StyleSheet } from 'react-native';
import { Mail } from 'lucide-react-native';
import { colors } from '../components/theme';
import { AppButton, AppInput, ErrorMessage, Logo, PasswordInput, ScreenContainer } from '../components/ui';
import { ApiError, login } from '../lib/api';
import { routeForRole, saveSession, clearSession } from '../lib/session';

export default function LoginScreen() {
  const router = useRouter();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const submit = async () => {
    if (loading) return;
    if (!email.trim() || !password) {
      setError('Renseignez votre email et votre mot de passe.');
      return;
    }

    setLoading(true);
    setError(null);

    try {
      const session = await login(email.trim(), password);
      await saveSession(session);
      router.replace(routeForRole(session.user.role));
    } catch (err) {
      if (err instanceof ApiError && err.code === 'EMAIL_NOT_VERIFIED') {
        const targetEmail = (err.data as { email?: string } | undefined)?.email || email.trim();
        await clearSession();
        router.replace(`/verify-email?email=${encodeURIComponent(targetEmail)}`);
        return;
      }
      const message = err instanceof ApiError ? err.message : 'Connexion impossible. Vérifiez le serveur puis réessayez.';
      setError(message);
    } finally {
      setLoading(false);
    }
  };

  return (
    <ScreenContainer style={styles.container}>
      <View style={styles.header}>
        <Logo />
      </View>

      <Text style={styles.title}>Bon retour parmi nous</Text>
      <Text style={styles.subtitle}>Connectez-vous pour accéder à votre espace.</Text>

      <ErrorMessage message={error} />

      <AppInput label="Adresse e-mail" value={email} onChangeText={setEmail} placeholder="vous@example.com" keyboardType="email-address" icon={<Mail size={18} color={colors.muted} />} />
      <PasswordInput label="Mot de passe" value={password} onChangeText={setPassword} placeholder="Votre mot de passe" />

      <Pressable style={styles.forgotButton} onPress={() => router.push('/forgot-password')}>
        <Text style={styles.linkText}>Mot de passe oublié ?</Text>
      </Pressable>

      <AppButton title="Se connecter" onPress={submit} loading={loading} disabled={loading} />

      <View style={styles.footerRow}>
        <Text style={styles.footerText}>Pas encore de compte ?</Text>
        <Pressable onPress={() => router.push('/signup/role')}>
          <Text style={styles.linkText}>Créer un compte</Text>
        </Pressable>
      </View>
    </ScreenContainer>
  );
}

const styles = StyleSheet.create({
  container: { justifyContent: 'center' },
  header: { alignItems: 'center', marginBottom: 24 },
  title: { fontSize: 28, lineHeight: 36, fontWeight: '700', color: colors.text, marginBottom: 8 },
  subtitle: { fontSize: 15, color: colors.muted, marginBottom: 24, lineHeight: 22 },
  forgotButton: { alignSelf: 'flex-end', marginBottom: 18, marginTop: -4 },
  footerRow: { flexDirection: 'row', alignItems: 'center', justifyContent: 'center', marginTop: 16, gap: 6 },
  footerText: { color: colors.muted },
  linkText: { color: colors.primary, fontWeight: '700' },
});