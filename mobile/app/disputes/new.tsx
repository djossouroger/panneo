import { useEffect, useState } from 'react';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { Alert, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { ChevronLeft } from 'lucide-react-native';
import { colors } from '../../components/theme';
import { AppButton, AppInput, ErrorMessage, LoadingState, ScreenContainer, Textarea } from '../../components/ui';
import { ApiError, createDispute, Dispute, friendlyError } from '../../lib/api';
import { handleSessionExpired, restoreSession } from '../../lib/session';

const TYPES: Array<{ value: Dispute['type']; label: string }> = [
  { value: 'behavior', label: 'Comportement' },
  { value: 'service_quality', label: 'Qualité du service' },
  { value: 'no_show', label: 'Absence sur place' },
  { value: 'safety', label: 'Sécurité' },
  { value: 'other', label: 'Autre' },
];

export default function NewDisputeScreen() {
  const router = useRouter();
  const { repairRequestId } = useLocalSearchParams<{ repairRequestId: string }>();
  const [subject, setSubject] = useState('');
  const [type, setType] = useState<Dispute['type']>('other');
  const [description, setDescription] = useState('');
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let mounted = true;
    restoreSession().then((session) => {
      if (!mounted) return;
      if (!session) {
        router.replace('/login');
        return;
      }
      setLoading(false);
    });
    return () => {
      mounted = false;
    };
  }, [router]);

  const submit = async () => {
    if (!repairRequestId || submitting) return;
    if (!subject.trim()) {
      setError('Indiquez un sujet pour le litige.');
      return;
    }
    if (description.trim().length < 20) {
      setError('Décrivez le litige en au moins 20 caractères.');
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
      await createDispute(session.token, repairRequestId, { subject: subject.trim(), description: description.trim(), type });
      Alert.alert('Litige signalé', 'Votre litige a été transmis à l’équipe de modération.', [{ text: 'OK', onPress: () => router.replace('/disputes') }]);
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
        <Text style={styles.headerTitle}>Signaler un litige</Text>
      </View>

      <ScrollView contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
        <ErrorMessage message={error} />
        <Text style={styles.hint}>
          Un litige ne peut être signalé qu’après une intervention terminée et uniquement par l’une des deux parties.
        </Text>

        <View style={styles.card}>
          <Text style={styles.cardTitle}>Type de litige</Text>
          <View style={styles.typeRow}>
            {TYPES.map((option) => (
              <Pressable key={option.value} onPress={() => setType(option.value)} style={[styles.typeChip, type === option.value && styles.typeChipActive]}>
                <Text style={[styles.typeChipText, type === option.value && styles.typeChipTextActive]}>{option.label}</Text>
              </Pressable>
            ))}
          </View>
          <AppInput label="Sujet" value={subject} onChangeText={setSubject} placeholder="Ex. Travaux non conformes" />
          <Textarea label="Description" value={description} onChangeText={setDescription} placeholder="Expliquez précisément le problème rencontré." maxLength={1000} />
        </View>

        <AppButton title="Envoyer le litige" onPress={submit} loading={submitting} disabled={submitting} />
      </ScrollView>
    </ScreenContainer>
  );
}

const styles = StyleSheet.create({
  header: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingVertical: 14, borderBottomWidth: 1, borderBottomColor: colors.border },
  backButton: { width: 40, height: 40, borderRadius: 12, backgroundColor: colors.white, borderWidth: 1, borderColor: colors.border, alignItems: 'center', justifyContent: 'center' },
  headerTitle: { color: colors.text, fontSize: 20, fontWeight: '700' },
  content: { paddingVertical: 20, paddingBottom: 84, gap: 14 },
  hint: { color: colors.muted, fontSize: 13, lineHeight: 19 },
  card: { backgroundColor: colors.white, borderRadius: 12, borderWidth: 1, borderColor: colors.border, padding: 16, gap: 12 },
  cardTitle: { color: colors.text, fontSize: 17, fontWeight: '700' },
  typeRow: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  typeChip: { borderRadius: 999, borderWidth: 1, borderColor: colors.border, backgroundColor: colors.white, paddingHorizontal: 14, paddingVertical: 8 },
  typeChipActive: { borderColor: colors.primary, backgroundColor: colors.primaryLight },
  typeChipText: { color: colors.text, fontSize: 13, fontWeight: '600' },
  typeChipTextActive: { color: colors.primary, fontWeight: '700' },
});
