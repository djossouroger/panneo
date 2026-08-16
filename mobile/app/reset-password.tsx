import { useState } from 'react';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { Pressable, View, Text, StyleSheet } from 'react-native';
import { CircleCheck, KeyRound } from 'lucide-react-native';
import { colors } from '../components/theme';
import { AppButton, AppInput, ErrorMessage, Logo, PasswordInput, ScreenContainer } from '../components/ui';
import { ApiError, resetPassword } from '../lib/api';

export default function ResetPasswordScreen() {
  const router = useRouter();
  const { email } = useLocalSearchParams<{ email: string }>();
  const [code, setCode] = useState('');
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [reset, setReset] = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const submit = async () => {
    if (loading) return;
    if (!email) {
      setError('E-mail manquant. Recommencez depuis la page « Mot de passe oublié ».');
      return;
    }
    if (!code.trim()) {
      setError('Saisissez le code de vérification reçu par e-mail.');
      return;
    }
    if (password.length < 8) {
      setError('Le mot de passe doit contenir au moins 8 caractères.');
      return;
    }
    if (password !== passwordConfirmation) {
      setError('Les deux mots de passe ne correspondent pas.');
      return;
    }
    setLoading(true);
    setError(null);
    try {
      await resetPassword(email, code.trim(), password);
      setReset(true);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'La réinitialisation a échoué. Vérifiez le code puis réessayez.');
    } finally {
      setLoading(false);
    }
  };

  if (reset) {
    return (
      <ScreenContainer style={styles.container}>
        <View style={styles.header}>
          <Logo />
        </View>

        <View style={styles.successIcon}>
          <CircleCheck size={34} color={colors.success} />
        </View>
        <Text style={styles.title}>Mot de passe modifié</Text>
        <Text style={styles.subtitle}>Votre mot de passe a bien été réinitialisé. Vous pouvez maintenant vous connecter avec votre nouveau mot de passe.</Text>

        <AppButton title="Se connecter" onPress={() => router.replace('/login')} />
      </ScreenContainer>
    );
  }

  return (
    <ScreenContainer style={styles.container}>
      <View style={styles.header}>
        <Logo />
      </View>

      <Text style={styles.title}>Créer un nouveau mot de passe</Text>
      <Text style={styles.subtitle}>Saisissez le code reçu par e-mail et choisissez un nouveau mot de passe.</Text>

      <ErrorMessage message={error} />

      <AppInput label="Code de vérification" value={code} onChangeText={setCode} placeholder="6 chiffres" keyboardType="phone-pad" icon={<KeyRound size={18} color={colors.muted} />} />
      <PasswordInput label="Nouveau mot de passe" value={password} onChangeText={setPassword} placeholder="Au moins 8 caractères" />
      <PasswordInput label="Confirmation" value={passwordConfirmation} onChangeText={setPasswordConfirmation} placeholder="Répétez le mot de passe" />

      <AppButton title="Modifier mon mot de passe" onPress={submit} loading={loading} disabled={loading} />

      <View style={styles.footerRow}>
        <Text style={styles.footerText}>Vous vous souvenez du mot de passe ?</Text>
        <Pressable onPress={() => router.replace('/login')}>
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
