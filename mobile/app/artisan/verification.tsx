import { useCallback, useState } from 'react';
import { useRouter } from 'expo-router';
import { useFocusEffect } from '@react-navigation/native';
import { Alert, Image, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import * as DocumentPicker from 'expo-document-picker';
import * as ImagePicker from 'expo-image-picker';
import { Camera, ChevronLeft, FileText, RefreshCw, ShieldCheck, ShieldX, Trash2 } from 'lucide-react-native';
import { colors } from '../../components/theme';
import { AppButton, ErrorMessage, LoadingState, ScreenContainer, StatusBadge } from '../../components/ui';
import { ApiError, cancelVerificationSubmission, friendlyError, getVerificationStatus, submitVerification, VerificationInfo } from '../../lib/api';
import { handleSessionExpired, restoreSession } from '../../lib/session';

type DocumentType = 'identity_document' | 'professional_proof' | 'selfie';

type PickedDocument = {
  key: string;
  document_type: DocumentType;
  uri: string;
  name: string;
  mimeType: string;
};

const typeLabel: Record<DocumentType, string> = {
  identity_document: 'Pièce d’identité',
  selfie: 'Selfie avec pièce',
  professional_proof: 'Justificatif professionnel',
};

export default function ArtisanVerificationScreen() {
  const router = useRouter();
  const [token, setToken] = useState<string | null>(null);
  const [verification, setVerification] = useState<VerificationInfo | null>(null);
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [picked, setPicked] = useState<PickedDocument[]>([]);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const session = await restoreSession();
      if (!session || session.user.role !== 'artisan') {
        router.replace('/login');
        return;
      }
      setToken(session.token);
      setVerification(await getVerificationStatus(session.token));
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

  const addPicked = (document_type: DocumentType, uri: string, name: string, mimeType: string) => {
    setPicked((prev) => [
      ...prev.filter((item) => item.document_type !== document_type),
      { key: `${document_type}-${Date.now()}`, document_type, uri, name, mimeType },
    ]);
  };

  const pickIdentity = async () => {
    try {
      const result = await DocumentPicker.getDocumentAsync({
        type: ['image/jpeg', 'image/png', 'image/webp'],
        copyToCacheDirectory: true,
        multiple: false,
      });
      if (result.canceled || !result.assets?.length) return;
      const asset = result.assets[0];
      addPicked('identity_document', asset.uri, asset.name || `carte-${Date.now()}.jpg`, asset.mimeType || 'image/jpeg');
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
      addPicked('selfie', asset.uri, asset.fileName || `selfie-${Date.now()}.jpg`, asset.mimeType || 'image/jpeg');
    } catch {
      setError('Impossible d’accéder à l’appareil photo.');
    }
  };

  const pickProfessionalProof = async () => {
    try {
      const result = await DocumentPicker.getDocumentAsync({
        type: ['application/pdf', 'image/jpeg', 'image/png'],
        copyToCacheDirectory: true,
        multiple: false,
      });
      if (result.canceled || !result.assets?.length) return;
      const asset = result.assets[0];
      addPicked('professional_proof', asset.uri, asset.name || `document-${Date.now()}.pdf`, asset.mimeType || 'application/pdf');
    } catch {
      setError('Impossible de sélectionner le document.');
    }
  };

  const submit = async () => {
    if (!token || submitting) return;
    const types = picked.map((document) => document.document_type);
    if (!types.includes('identity_document') || !types.includes('selfie')) {
      setError('Ajoutez votre pièce d’identité et votre selfie avant d’envoyer votre demande.');
      return;
    }
    setSubmitting(true);
    setError(null);
    try {
      const result = await submitVerification(
        token,
        picked.map((document) => ({ document_type: document.document_type, uri: document.uri, name: document.name, type: document.mimeType }))
      );
      if (result.status === 'pending') {
        Alert.alert('Demande envoyée', 'Vos documents ont été transmis. Un administrateur va les examiner.', [{ text: 'OK' }]);
        setPicked([]);
        await load();
      }
    } catch (err) {
      setError(err instanceof ApiError ? err.message : friendlyError(err));
    } finally {
      setSubmitting(false);
    }
  };

  const cancel = async () => {
    if (!token) return;
    Alert.alert('Annuler la demande', 'Voulez-vous retirer votre demande de vérification ?', [
      { text: 'Non', style: 'cancel' },
      {
        text: 'Oui, annuler',
        style: 'destructive',
        onPress: async () => {
          try {
            await cancelVerificationSubmission(token);
            await load();
          } catch (err) {
            setError(err instanceof ApiError ? err.message : friendlyError(err));
          }
        },
      },
    ]);
  };

  if (loading) {
    return <ScreenContainer><LoadingState label="Chargement de votre dossier..." /></ScreenContainer>;
  }

  const status = verification?.verification_status || 'pending';
  const pendingSubmission = Boolean(verification?.has_pending_submission);
  const canSubmit = status !== 'verified' && !pendingSubmission;
  const verified = status === 'verified';
  const rejected = status === 'rejected';
  const rejectedReason = rejected ? verification?.submission?.rejection_reason : null;

  return (
    <ScreenContainer>
      <View style={styles.header}>
        <Pressable onPress={() => router.back()} style={styles.backButton}>
          <ChevronLeft size={22} color={colors.text} />
        </Pressable>
        <Text style={styles.headerTitle}>Vérification</Text>
      </View>

      <ScrollView contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
        <ErrorMessage message={error} />

        <View style={[styles.banner, verified && styles.bannerVerified, rejected && styles.bannerRejected]}>
          <View style={styles.bannerIcon}>
            {verified ? <ShieldCheck size={22} color={colors.success} /> : <ShieldX size={22} color={rejected ? colors.danger : colors.urgent} />}
          </View>
          <View style={styles.bannerText}>
            <Text style={styles.bannerTitle}>
              {verified ? 'Compte vérifié' : rejected ? 'Demande rejetée' : pendingSubmission ? 'Demande en cours d’examen' : 'Compte non vérifié'}
            </Text>
            <Text style={styles.bannerSubtitle}>
              {verified
                ? 'Votre identité a été confirmée. Le badge « Vérifié » est affiché sur votre profil.'
                : rejected
                ? rejectedReason || 'Les documents fournis ne permettaient pas de confirmer votre identité. Vous pouvez soumettre une nouvelle demande.'
                : pendingSubmission
                ? 'Vos documents sont en cours de vérification par un administrateur.'
                : 'Fournissez votre pièce d’identité et votre selfie pour être vérifié.'}
            </Text>
          </View>
          <StatusBadge label={verified ? 'Vérifié' : rejected ? 'Rejeté' : 'En attente'} tone={verified ? 'success' : rejected ? 'danger' : 'warning'} />
        </View>

        {verification?.submission?.documents?.length ? (
          <View style={styles.card}>
            <Text style={styles.cardTitle}>Documents fournis</Text>
            {verification.submission.documents.map((document) => (
              <View key={document.id} style={styles.documentRow}>
                <FileText size={16} color={colors.primary} />
                <View style={styles.documentText}>
                  <Text style={styles.documentName} numberOfLines={1}>{document.original_name}</Text>
                  <Text style={styles.documentMeta}>{typeLabel[document.document_type]} · {formatBytes(document.file_size)}</Text>
                </View>
              </View>
            ))}
          </View>
        ) : null}

        {canSubmit ? (
          <>
            <View style={styles.card}>
              <Text style={styles.cardTitle}>Vérification d’identité</Text>
              <Text style={styles.cardHint}>Formats acceptés : JPG, PNG, WEBP. Taille maximale : 5 Mo par image.</Text>
              <AppButton title={picked.some((d) => d.document_type === 'identity_document') ? 'Remplacer ma pièce d’identité' : 'Ajouter ma pièce d’identité'} variant="secondary" onPress={pickIdentity} disabled={submitting} />
              <AppButton title={picked.some((d) => d.document_type === 'selfie') ? 'Reprendre mon selfie' : 'Prendre mon selfie'} variant="secondary" icon={<Camera size={18} color={colors.primary} />} onPress={takeSelfie} disabled={submitting} />
              <AppButton title="Ajouter un justificatif professionnel (optionnel)" variant="secondary" onPress={pickProfessionalProof} disabled={submitting} />
            </View>

            {picked.length > 0 ? (
              <View style={styles.card}>
                <Text style={styles.cardTitle}>Documents sélectionnés</Text>
                {picked.map((document) => (
                  <View key={document.key} style={styles.pickedRow}>
                    {document.document_type === 'professional_proof' ? (
                      <FileText size={16} color={colors.primary} />
                    ) : (
                      <Image source={{ uri: document.uri }} style={styles.thumbnail} resizeMode="cover" />
                    )}
                    <View style={styles.documentText}>
                      <Text style={styles.documentName} numberOfLines={1}>{document.name}</Text>
                      <Text style={styles.documentMeta}>{typeLabel[document.document_type]}</Text>
                    </View>
                    <Pressable
                      onPress={document.document_type === 'professional_proof' ? pickProfessionalProof : document.document_type === 'selfie' ? takeSelfie : pickIdentity}
                      style={styles.replaceButton}
                      disabled={submitting}
                      accessibilityLabel="Remplacer ce document"
                    >
                      <RefreshCw size={15} color={colors.primary} />
                    </Pressable>
                    <Pressable onPress={() => setPicked((prev) => prev.filter((item) => item.key !== document.key))} style={styles.removeButton} disabled={submitting}>
                      <Trash2 size={16} color={colors.danger} />
                    </Pressable>
                  </View>
                ))}
                <AppButton title={`Envoyer ma demande (${picked.length})`} onPress={submit} loading={submitting} disabled={submitting} />
              </View>
            ) : null}
          </>
        ) : null}

        {pendingSubmission ? (
          <AppButton title="Annuler ma demande" variant="danger" onPress={cancel} />
        ) : null}
      </ScrollView>
    </ScreenContainer>
  );
}

function formatBytes(bytes: number) {
  if (!bytes) return '0 Ko';
  const kb = Math.round(bytes / 1024);
  return kb >= 1024 ? `${(kb / 1024).toFixed(1)} Mo` : `${kb} Ko`;
}

const styles = StyleSheet.create({
  header: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingVertical: 14, borderBottomWidth: 1, borderBottomColor: colors.border },
  backButton: { width: 40, height: 40, borderRadius: 12, backgroundColor: colors.white, borderWidth: 1, borderColor: colors.border, alignItems: 'center', justifyContent: 'center' },
  headerTitle: { color: colors.text, fontSize: 20, fontWeight: '700' },
  content: { paddingVertical: 20, paddingBottom: 84, gap: 14 },
  banner: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: '#FFF3E2', borderRadius: 12, padding: 16 },
  bannerVerified: { backgroundColor: '#E7F9F0' },
  bannerRejected: { backgroundColor: '#FEE4E2' },
  bannerIcon: { width: 44, height: 44, borderRadius: 12, backgroundColor: colors.white, alignItems: 'center', justifyContent: 'center' },
  bannerText: { flex: 1, gap: 4 },
  bannerTitle: { color: colors.text, fontSize: 16, fontWeight: '700' },
  bannerSubtitle: { color: colors.muted, fontSize: 13, lineHeight: 19 },
  card: { backgroundColor: colors.white, borderRadius: 12, borderWidth: 1, borderColor: colors.border, padding: 16, gap: 12 },
  cardTitle: { color: colors.text, fontSize: 17, fontWeight: '700' },
  cardHint: { color: colors.muted, fontSize: 13, lineHeight: 19 },
  documentRow: { flexDirection: 'row', alignItems: 'center', gap: 10 },
  pickedRow: { flexDirection: 'row', alignItems: 'center', gap: 10 },
  thumbnail: { width: 40, height: 40, borderRadius: 8, backgroundColor: colors.primaryLight },
  documentText: { flex: 1 },
  documentName: { color: colors.text, fontSize: 14, fontWeight: '600' },
  documentMeta: { color: colors.muted, fontSize: 12 },
  replaceButton: { width: 36, height: 36, borderRadius: 18, backgroundColor: colors.primaryLight, alignItems: 'center', justifyContent: 'center' },
  removeButton: { width: 36, height: 36, borderRadius: 18, backgroundColor: '#FEE4E2', alignItems: 'center', justifyContent: 'center' },
});
