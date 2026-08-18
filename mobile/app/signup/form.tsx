import { useLocalSearchParams, useRouter } from 'expo-router';
import { useState } from 'react';
import { KeyboardAvoidingView, Platform, ScrollView, View, Text, StyleSheet } from 'react-native';
import { Mail, Phone, User } from 'lucide-react-native';
import { colors } from '../../components/theme';
import { AppButton, AppInput, ErrorMessage, PasswordInput, ScreenContainer } from '../../components/ui';
import { ApiError, register } from '../../lib/api';
import { saveSession } from '../../lib/session';
import { setSignupDraft } from '../../lib/signupDraft';

type FieldErrors = Record<string, string | undefined>;

function firstApiError(error: unknown, field: string) {
  return error instanceof ApiError ? error.errors[field]?.[0] : undefined;
}

export default function SignupFormScreen() {
  const router = useRouter();
  const { role } = useLocalSearchParams<{ role: string }>();
  const selectedRole = role === 'artisan' ? 'artisan' : 'client';
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [phone, setPhone] = useState('');
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [loading, setLoading] = useState(false);
  const [formError, setFormError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<FieldErrors>({});

  const validate = () => {
    const nextErrors: FieldErrors = {};
    if (!name.trim()) nextErrors.name = 'Le nom complet est requis.';
    if (!phone.trim()) nextErrors.phone = 'Le téléphone est requis.';
    if (!email.trim()) nextErrors.email = 'L’adresse e-mail est requise.';
    if (password.length < 8) nextErrors.password = 'Le mot de passe doit contenir au moins 8 caractères.';
    if (password !== passwordConfirmation) nextErrors.password_confirmation = 'Les mots de passe ne correspondent pas.';

    setFieldErrors(nextErrors);
    return Object.keys(nextErrors).length === 0;
  };

  const submit = async () => {
    if (loading) return;
    setFormError(null);
    if (!validate()) return;

    if (selectedRole === 'artisan') {
      setSignupDraft({ name: name.trim(), email: email.trim(), phone: phone.trim(), password, passwordConfirmation, categoryId: null, city: '', district: '' });
      router.push('/signup/artisan');
      return;
    }

    setLoading(true);
    try {
      const session = await register({
        name: name.trim(),
        email: email.trim(),
        phone: phone.trim(),
        password,
        password_confirmation: passwordConfirmation,
        role: 'client',
      });
      await saveSession(session);
      if (session.email_verified === false) {
        router.replace('/verify-email');
      } else {
        router.replace('/');
      }
    } catch (err) {
      setFormError(err instanceof ApiError ? err.message : 'Création du compte impossible.');
      setFieldErrors({
        name: firstApiError(err, 'name'),
        email: firstApiError(err, 'email'),
        phone: firstApiError(err, 'phone'),
        password: firstApiError(err, 'password'),
      });
    } finally {
      setLoading(false);
    }
  };

  return (
    <ScreenContainer>
      <KeyboardAvoidingView behavior={Platform.OS === 'ios' ? 'padding' : undefined} style={styles.flex}>
        <ScrollView contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
          <View style={styles.header}>
            {selectedRole === 'artisan' ? <Text style={styles.step}>Étape 1 sur 2</Text> : null}
            <Text style={styles.title}>Créer votre compte</Text>
            <Text style={styles.subtitle}>Quelques informations suffisent pour commencer.</Text>
          </View>

          <ErrorMessage message={formError} />

          <AppInput label="Nom complet" value={name} onChangeText={setName} placeholder="Jean Dupont" icon={<User size={18} color={colors.muted} />} error={fieldErrors.name} />
          <AppInput label="Numéro de téléphone" value={phone} onChangeText={setPhone} placeholder="+229 01 00 00 00 00" keyboardType="phone-pad" icon={<Phone size={18} color={colors.muted} />} error={fieldErrors.phone} />
          <AppInput label="Adresse e-mail" value={email} onChangeText={setEmail} placeholder="vous@example.com" keyboardType="email-address" icon={<Mail size={18} color={colors.muted} />} error={fieldErrors.email} />
          <PasswordInput label="Mot de passe" value={password} onChangeText={setPassword} placeholder="Votre mot de passe" error={fieldErrors.password} />
          <PasswordInput label="Confirmation du mot de passe" value={passwordConfirmation} onChangeText={setPasswordConfirmation} placeholder="Confirmez le mot de passe" error={fieldErrors.password_confirmation} />

          <View style={styles.actions}>
            <AppButton title={selectedRole === 'artisan' ? 'Continuer' : 'Créer mon compte'} onPress={submit} loading={loading} disabled={loading} />
            <AppButton title="Retour" variant="secondary" onPress={() => router.back()} disabled={loading} />
          </View>
        </ScrollView>
      </KeyboardAvoidingView>
    </ScreenContainer>
  );
}

const styles = StyleSheet.create({
  flex: { flex: 1 },
  content: { paddingVertical: 24 },
  header: { marginBottom: 24 },
  step: { color: colors.primary, fontSize: 13, fontWeight: '700', marginBottom: 10 },
  title: { fontSize: 28, lineHeight: 36, fontWeight: '700', color: colors.text, marginBottom: 8 },
  subtitle: { fontSize: 15, lineHeight: 22, color: colors.muted },
  actions: { gap: 12, marginTop: 12, paddingBottom: 24 },
});