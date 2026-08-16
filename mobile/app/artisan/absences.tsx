import { useCallback, useState } from 'react';
import { useRouter } from 'expo-router';
import { useFocusEffect } from '@react-navigation/native';
import { Alert, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { CalendarRange, ChevronLeft, Plus, Trash2 } from 'lucide-react-native';
import { colors } from '../../components/theme';
import { AppButton, AppInput, ErrorMessage, LoadingState, ScreenContainer, StatusBadge } from '../../components/ui';
import { ApiError, cancelUnavailability, createUnavailability, fetchArtisanProfile, friendlyError, Unavailability } from '../../lib/api';
import { handleSessionExpired, restoreSession } from '../../lib/session';

const TYPE_LABELS: Record<Unavailability['type'], string> = {
  pause: 'Pause',
  leave: 'Congé',
  temporary_unavailable: 'Indisponible temporairement',
};

const TYPE_TONES: Record<Unavailability['type'], 'success' | 'warning' | 'danger' | 'neutral'> = {
  pause: 'warning',
  leave: 'neutral',
  temporary_unavailable: 'danger',
};

const DATE_PATTERN = /^\d{4}-\d{2}-\d{2}$/;
const TIME_PATTERN = /^([01]\d|2[0-3]):([0-5]\d)$/;

function formatDates(startsAt: string, endsAt: string | null) {
  const format = (value: string) => {
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;
    return date.toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' }) + ' ' + date.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
  };
  return endsAt ? `${format(startsAt)} → ${format(endsAt)}` : format(startsAt);
}

export default function ArtisanAbsencesScreen() {
  const router = useRouter();
  const [token, setToken] = useState<string | null>(null);
  const [unavailabilities, setUnavailabilities] = useState<Unavailability[]>([]);
  const [type, setType] = useState<Unavailability['type']>('pause');
  const [date, setDate] = useState('');
  const [startTime, setStartTime] = useState('08:00');
  const [endTime, setEndTime] = useState('18:00');
  const [reason, setReason] = useState('');
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
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
      const profile = await fetchArtisanProfile(session.token);
      setUnavailabilities(profile.profile.unavailabilities);
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

  const add = async () => {
    if (!token || saving) return;
    if (!DATE_PATTERN.test(date)) {
      setError('Indiquez une date au format AAAA-MM-JJ (ex. 2026-08-20).');
      return;
    }
    if (!TIME_PATTERN.test(startTime) || !TIME_PATTERN.test(endTime)) {
      setError('Indiquez des heures valides au format HH:MM.');
      return;
    }
    const startsAt = `${date} ${startTime}:00`;
    const endsAt = `${date} ${endTime}:00`;
    setSaving(true);
    setError(null);
    try {
      await createUnavailability(token, { type, starts_at: startsAt, ends_at: endsAt, reason: reason.trim() || null });
      setDate('');
      setReason('');
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : friendlyError(err));
    } finally {
      setSaving(false);
    }
  };

  const remove = async (id: number) => {
    if (!token) return;
    Alert.alert('Supprimer l’indisponibilité', 'Voulez-vous annuler cette indisponibilité ?', [
      { text: 'Non', style: 'cancel' },
      {
        text: 'Oui, supprimer',
        style: 'destructive',
        onPress: async () => {
          try {
            await cancelUnavailability(token, id);
            await load();
          } catch (err) {
            setError(err instanceof ApiError ? err.message : friendlyError(err));
          }
        },
      },
    ]);
  };

  if (loading) {
    return <ScreenContainer><LoadingState label="Chargement des indisponibilités..." /></ScreenContainer>;
  }

  return (
    <ScreenContainer>
      <View style={styles.header}>
        <Pressable onPress={() => router.back()} style={styles.backButton}>
          <ChevronLeft size={22} color={colors.text} />
        </Pressable>
        <Text style={styles.headerTitle}>Absences et indisponibilités</Text>
      </View>

      <ScrollView contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
        <ErrorMessage message={error} />
        <Text style={styles.hint}>
          Pendant une indisponibilité, vous n’êtes pas proposé dans les résultats de recherche ni pour de nouvelles demandes.
        </Text>

        <View style={styles.card}>
          <Text style={styles.cardTitle}>Ajouter une indisponibilité</Text>
          <View style={styles.typeRow}>
            {(Object.keys(TYPE_LABELS) as Unavailability['type'][]).map((option) => (
              <Pressable key={option} onPress={() => setType(option)} style={[styles.typeChip, type === option && styles.typeChipActive]}>
                <Text style={[styles.typeChipText, type === option && styles.typeChipTextActive]}>{TYPE_LABELS[option]}</Text>
              </Pressable>
            ))}
          </View>
          <AppInput label="Date (AAAA-MM-JJ)" value={date} onChangeText={setDate} placeholder="2026-08-20" />
          <View style={styles.timeRow}>
            <View style={styles.timeField}>
              <AppInput label="Début (HH:MM)" value={startTime} onChangeText={setStartTime} placeholder="08:00" />
            </View>
            <View style={styles.timeField}>
              <AppInput label="Fin (HH:MM)" value={endTime} onChangeText={setEndTime} placeholder="18:00" />
            </View>
          </View>
          <AppInput label="Motif (facultatif)" value={reason} onChangeText={setReason} placeholder="Ex. formation" />
          <AppButton title="Ajouter" onPress={add} loading={saving} disabled={saving} icon={<Plus size={18} color={colors.white} />} />
        </View>

        {unavailabilities.length > 0 ? (
          <View style={styles.card}>
            <Text style={styles.cardTitle}>Indisponibilités enregistrées</Text>
            {unavailabilities.map((item) => (
              <View key={item.id} style={styles.itemRow}>
                <View style={styles.itemIcon}>
                  <CalendarRange size={16} color={colors.primary} />
                </View>
                <View style={styles.itemText}>
                  <Text style={styles.itemDate}>{formatDates(item.starts_at, item.ends_at)}</Text>
                  <Text style={styles.itemReason}>{TYPE_LABELS[item.type]}{item.reason ? ` · ${item.reason}` : ''}</Text>
                </View>
                {item.is_active ? <StatusBadge label="Actif" tone="success" /> : <StatusBadge label={TYPE_LABELS[item.type]} tone={TYPE_TONES[item.type]} />}
                <Pressable onPress={() => remove(item.id)} style={styles.removeButton}>
                  <Trash2 size={15} color={colors.danger} />
                </Pressable>
              </View>
            ))}
          </View>
        ) : (
          <Text style={styles.emptyText}>Aucune indisponibilité enregistrée.</Text>
        )}
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
  timeRow: { flexDirection: 'row', gap: 10 },
  timeField: { flex: 1 },
  itemRow: { flexDirection: 'row', alignItems: 'center', gap: 10 },
  itemIcon: { width: 34, height: 34, borderRadius: 10, backgroundColor: colors.primaryLight, alignItems: 'center', justifyContent: 'center' },
  itemText: { flex: 1 },
  itemDate: { color: colors.text, fontSize: 14, fontWeight: '700' },
  itemReason: { color: colors.muted, fontSize: 12 },
  removeButton: { width: 32, height: 32, borderRadius: 9, backgroundColor: '#FEE4E2', alignItems: 'center', justifyContent: 'center' },
  emptyText: { color: colors.muted, fontSize: 14, textAlign: 'center', marginVertical: 8 },
});
