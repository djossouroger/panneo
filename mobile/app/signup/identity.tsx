import { useRouter } from 'expo-router';
import { useEffect, useState } from 'react';
import { Alert, Image, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import * as DocumentPicker from 'expo-document-picker';
import * as ImagePicker from 'expo-image-picker';
import { Camera, ChevronLeft, FileText, IdCard, RefreshCw, Trash2 } from 'lucide-react-native';
import { colors } from '../../components/theme';
import { AppButton, ErrorMessage, ScreenContainer } from '../../components/ui';
import { ApiError, register, submitVerification } from '../../lib/api';
import { saveSession } from '../../lib/session';
import { clearSignupDraft, getSignupDraft } from '../../lib/signupDraft';

type PickedDocument = {
  key: string;
  document_type: 'identity_document' | 'selfie';
  uri: string;
  name: string;
  mimeType: string;
};

function fileNameFor(uri: string, fallback: string, ext: string): string {
  const name = uri.split('/').pop() || fallback;
  return /\.(jpg|jpeg|png|webp)$/i.test(name) ? name : `${fallback}.${ext}`;
}

export default function SignupIdentityScreen() {
  const router = useRouter();
  const [identity, setIdentity] = useState<PickedDocument | null>(null);
  const [selfie, setSelfie] = useState<PickedDocument | null>(null);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!getSignupDraft()) {
      setError('L’inscription doit être complétée depuis le début.');
    }
  }, []);

  const pickIdentity = async () => {
    try {
      const result = await DocumentPicker.getDocumentAsync({
        type: ['image/jpeg', 'image/png', 'image/webp'],
        copyToCacheDirectory: true,
        multiple: false,
      });
      if (result.canceled || !result.assets?.length) return;
      const asset = result.assets[0];
      setIdentity({
        key: `identity-${Date.now()}`,
        document_type: 'identity_document',
        uri: asset.uri,
        name: fileNameFor(asset.uri, 'piece-identite', 'jpg'),
        mimeType: asset.mimeType || 'image/jpeg',
      });
      setError(null);
    } catch {
      setError('Impossible de sélectionner la pièce d’identité.');
    }
  };

  const takeSelfie = async () => {
    try {
      const permission = await ImagePicker.requestCameraPermissionsAsync();
      if (!permission.granted) {
        setError('Autorisez l’accès à l’appareil photo pour prendre votre selfie.');
        return;
      }
      const result = await ImagePicker.launchCameraAsync({
        mediaTypes: ['images'],
        quality: 0.8,
        allowsEditing: true,
        aspect: [3, 4],
      });
      if (result.canceled || !result.assets?.length) return;
      const asset = result.assets[0];
      setSelfie({
        key: `selfie-${Date.now()}`,
        document_type: 'selfie',
        uri: asset.uri,
        name: fileNameFor(asset.uri, 'selfie', 'jpg'),
        mimeType: asset.mimeType || 'image/jpeg',
      });
      setError(null);
    } catch {
      setError('Impossible d’accéder à l’appareil photo.');
    }
  };

  const submit = async () => {
    if (submitting) return;
    const draft = getSignupDraft();
    if (!draft) {
      setError('L’inscription doit être complétée depuis le début.');
      return;
    }
    if (!identity || !selfie) {
      setError('Ajoutez votre pièce d’identité et votre selfie pour continuer.');
      return;
    }
    if (draft.categoryId == null || !draft.city) {
      setError('Le métier et la ville sont manquants. Reprenez l’inscription depuis le début.');
      return;
    }

    setSubmitting(true);
    setError(null);
    try {
      const session = await register({
        name: draft.name,
        email: draft.email,
        phone: draft.phone,
        password: draft.password,
        password_confirmation: draft.passwordConfirmation,
        role: 'artisan',
        category_id: draft.categoryId,
        city: draft.city,
        district: draft.district || null,
      });
      await saveSession(session);
      const needsVerify = session.email_verified === false;

      try {
        await submitVerification(session.token, [
          { document_type: 'identity_document', uri: identity.uri, name: identity.name, type: identity.mimeType },
          { document_type: 'selfie', uri: selfie.uri, name: selfie.name, type: selfie.mimeType },
        ]);
      } catch (verificationErr) {
        Alert.alert(
          'Compte créé',
          'Votre compte est créé mais le dossier d’identité n’a pas pu être envoyé. Complétez la vérification depuis votre espace artisan.',
          [{ text: 'OK' }]
        );
        router.replace(needsVerify ? '/verify-email?next=/artisan/verification' : '/artisan/verification');
        return;
      }

      clearSignupDraft();
      router.replace(needsVerify ? '/verify-email?next=/artisan/verification' : '/artisan/verification');
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'La création du compte a échoué. Vérifiez le serveur puis réessayez.');
      setSubmitting(false);
      return;
    }
    setSubmitting(false);
  };

  return (
    <ScreenContainer>
      <ScrollView contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
        <View style={styles.header}>
          <Pressable onPress={() => router.back()} style={styles.backButton}>
            <ChevronLeft size={22} color={colors.text} />
          </Pressable>
          <View style={styles.headerText}>
            <Text style={styles.step}>Étape 3 sur 3</Text>
            <Text style={styles.title}>Vérifions votre identité</Text>
            <Text style={styles.subtitle}>
              Prenez une photo de votre pièce d’identité et un selfie avec cette pièce. Ces documents restent strictement confidentiels.
            </Text>
          </View>
        </View>

        <ErrorMessage message={error} />

        <View style={styles.card}>
          <View style={styles.cardRow}>
            <View style={styles.cardIcon}><IdCard size={20} color={colors.primary} /></View>
            <View style={styles.cardText}>
              <Text style={styles.cardTitle}>Pièce d’identité</Text>
              <Text style={styles.cardHint}>Carte d’identité, passeport ou permis de conduire (JPG, PNG, WEBP).</Text>
            </View>
          </View>

          {identity ? (
            <View style={styles.docRow}>
              <Image source={{ uri: identity.uri }} style={styles.thumbnail} resizeMode="cover" />
              <Text style={styles.docName} numberOfLines={1}>{identity.name}</Text>
              <Pressable onPress={pickIdentity} style={styles.roundButton} accessibilityLabel="Remplacer la pièce d’identité">
                <RefreshCw size={15} color={colors.primary} />
              </Pressable>
              <Pressable onPress={() => setIdentity(null)} style={styles.roundButtonDanger} accessibilityLabel="Supprimer la pièce d’identité">
                <Trash2 size={16} color={colors.danger} />
              </Pressable>
            </View>
          ) : (
            <AppButton title="Ajouter ma pièce d’identité" variant="secondary" onPress={pickIdentity} disabled={submitting} />
          )}
        </View>

        <View style={styles.card}>
          <View style={styles.cardRow}>
            <View style={styles.cardIcon}><Camera size={20} color={colors.primary} /></View>
            <View style={styles.cardText}>
              <Text style={styles.cardTitle}>Selfie avec la pièce</Text>
              <Text style={styles.cardHint}>Prenez une photo de vous tenant votre pièce d’identité près de votre visage.</Text>
            </View>
          </View>

          {selfie ? (
            <View style={styles.docRow}>
              <Image source={{ uri: selfie.uri }} style={styles.thumbnail} resizeMode="cover" />
              <Text style={styles.docName} numberOfLines={1}>{selfie.name}</Text>
              <Pressable onPress={takeSelfie} style={styles.roundButton} accessibilityLabel="Reprendre le selfie">
                <RefreshCw size={15} color={colors.primary} />
              </Pressable>
              <Pressable onPress={() => setSelfie(null)} style={styles.roundButtonDanger} accessibilityLabel="Supprimer le selfie">
                <Trash2 size={16} color={colors.danger} />
              </Pressable>
            </View>
          ) : (
            <AppButton title="Prendre mon selfie" onPress={takeSelfie} disabled={submitting} />
          )}
        </View>

        <View style={styles.infoBox}>
          <FileText size={16} color={colors.muted} />
          <Text style={styles.infoText}>Votre dossier sera examiné par un administrateur. Sans validation, vous ne pourrez pas recevoir de demandes de dépannage.</Text>
        </View>

        <View style={styles.actions}>
          <AppButton title="Créer mon compte artisan" onPress={submit} loading={submitting} disabled={submitting} />
          <AppButton title="Retour" variant="secondary" onPress={() => router.back()} disabled={submitting} />
        </View>
      </ScrollView>
    </ScreenContainer>
  );
}

const styles = StyleSheet.create({
  content: { paddingVertical: 24, paddingBottom: 40, gap: 14 },
  header: { flexDirection: 'row', gap: 12, marginBottom: 20 },
  backButton: { width: 40, height: 40, borderRadius: 12, backgroundColor: colors.white, borderWidth: 1, borderColor: colors.border, alignItems: 'center', justifyContent: 'center' },
  headerText: { flex: 1, gap: 6 },
  step: { color: colors.primary, fontSize: 13, fontWeight: '700' },
  title: { fontSize: 24, lineHeight: 32, fontWeight: '700', color: colors.text },
  subtitle: { fontSize: 14, lineHeight: 21, color: colors.muted },
  card: { backgroundColor: colors.white, borderRadius: 12, borderWidth: 1, borderColor: colors.border, padding: 16, gap: 12 },
  cardRow: { flexDirection: 'row', gap: 12 },
  cardIcon: { width: 40, height: 40, borderRadius: 10, backgroundColor: colors.primaryLight, alignItems: 'center', justifyContent: 'center' },
  cardText: { flex: 1, gap: 2 },
  cardTitle: { color: colors.text, fontSize: 16, fontWeight: '700' },
  cardHint: { color: colors.muted, fontSize: 13, lineHeight: 18 },
  docRow: { flexDirection: 'row', alignItems: 'center', gap: 10 },
  thumbnail: { width: 44, height: 44, borderRadius: 8, backgroundColor: colors.primaryLight },
  docName: { flex: 1, color: colors.text, fontSize: 14, fontWeight: '600' },
  roundButton: { width: 36, height: 36, borderRadius: 18, backgroundColor: colors.primaryLight, alignItems: 'center', justifyContent: 'center' },
  roundButtonDanger: { width: 36, height: 36, borderRadius: 18, backgroundColor: '#FEE4E2', alignItems: 'center', justifyContent: 'center' },
  infoBox: { flexDirection: 'row', gap: 10, alignItems: 'flex-start', backgroundColor: '#F5F7FA', borderRadius: 12, padding: 14 },
  infoText: { flex: 1, color: colors.muted, fontSize: 13, lineHeight: 19 },
  actions: { gap: 12, marginTop: 6 },
});
