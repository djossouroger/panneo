import { useCallback, useState } from 'react';
import { useRouter } from 'expo-router';
import { useFocusEffect } from '@react-navigation/native';
import { Alert, Pressable, ScrollView, StyleSheet, Switch, Text, View } from 'react-native';
import { ChevronLeft, Clock3 } from 'lucide-react-native';
import { colors } from '../../components/theme';
import { AppButton, AppInput, ErrorMessage, LoadingState, ScreenContainer } from '../../components/ui';
import { ApiError, fetchArtisanProfile, friendlyError, updateArtisanWorkingHours, WorkingHour } from '../../lib/api';
import { handleSessionExpired, restoreSession } from '../../lib/session';

const DAY_LABELS = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
const TIME_PATTERN = /^([01]\d|2[0-3]):([0-5]\d)$/;

export default function ArtisanHoursScreen() {
  const router = useRouter();
  const [token, setToken] = useState<string | null>(null);
  const [hours, setHours] = useState<WorkingHour[]>([]);
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
      setHours(Array.from({ length: 7 }, (_, day) => profile.profile.working_hours.find((hour) => hour.day_of_week === day) || { day_of_week: day, start_time: '08:00', end_time: '18:00', is_working_day: false }));
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

  const updateDay = (index: number, patch: Partial<WorkingHour>) => {
    setHours((prev) => prev.map((hour, i) => (i === index ? { ...hour, ...patch } : hour)));
  };

  const save = async () => {
    if (!token || saving) return;
    for (const hour of hours) {
      if (hour.is_working_day) {
        if (!hour.start_time || !TIME_PATTERN.test(hour.start_time) || !hour.end_time || !TIME_PATTERN.test(hour.end_time)) {
          setError(`Indiquez des horaires valides (HH:MM) pour ${DAY_LABELS[hour.day_of_week].toLowerCase()}.`);
          return;
        }
      }
    }
    setSaving(true);
    setError(null);
    try {
      await updateArtisanWorkingHours(token, hours);
      Alert.alert('Horaires enregistrés', 'Vos horaires de travail ont été mis à jour.', [{ text: 'OK', onPress: () => router.back() }]);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : friendlyError(err));
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return <ScreenContainer><LoadingState label="Chargement de vos horaires..." /></ScreenContainer>;
  }

  return (
    <ScreenContainer>
      <View style={styles.header}>
        <Pressable onPress={() => router.back()} style={styles.backButton}>
          <ChevronLeft size={22} color={colors.text} />
        </Pressable>
        <Text style={styles.headerTitle}>Horaires de travail</Text>
      </View>

      <ScrollView contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
        <ErrorMessage message={error} />
        <Text style={styles.hint}>
          Indiquez les jours et les plages horaires où vous êtes disponible. Un jour sans horaire est considéré comme fermé.
        </Text>

        {hours.map((hour, index) => (
          <View key={hour.day_of_week} style={styles.dayCard}>
            <View style={styles.dayHeader}>
              <View style={styles.dayIcon}>
                <Clock3 size={16} color={hour.is_working_day ? colors.primary : colors.muted} />
              </View>
              <Text style={[styles.dayLabel, !hour.is_working_day && styles.dayLabelOff]}>{DAY_LABELS[hour.day_of_week]}</Text>
              <Switch
                value={hour.is_working_day}
                onValueChange={(value) => updateDay(index, { is_working_day: value })}
                trackColor={{ false: colors.border, true: colors.primary }}
                thumbColor={colors.white}
              />
            </View>
            {hour.is_working_day ? (
              <View style={styles.timeRow}>
                <View style={styles.timeField}>
                  <AppInput label="Début" value={hour.start_time || ''} onChangeText={(text) => updateDay(index, { start_time: text })} placeholder="08:00" />
                </View>
                <Text style={styles.timeSeparator}>→</Text>
                <View style={styles.timeField}>
                  <AppInput label="Fin" value={hour.end_time || ''} onChangeText={(text) => updateDay(index, { end_time: text })} placeholder="18:00" />
                </View>
              </View>
            ) : (
              <Text style={styles.closedText}>Fermé ce jour</Text>
            )}
          </View>
        ))}

        <AppButton title="Enregistrer" onPress={save} loading={saving} disabled={saving} />
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
  dayCard: { backgroundColor: colors.white, borderRadius: 12, borderWidth: 1, borderColor: colors.border, padding: 14, gap: 12 },
  dayHeader: { flexDirection: 'row', alignItems: 'center', gap: 10 },
  dayIcon: { width: 32, height: 32, borderRadius: 9, backgroundColor: colors.primaryLight, alignItems: 'center', justifyContent: 'center' },
  dayLabel: { flex: 1, color: colors.text, fontSize: 16, fontWeight: '700' },
  dayLabelOff: { color: colors.muted },
  timeRow: { flexDirection: 'row', alignItems: 'flex-end', gap: 8 },
  timeField: { flex: 1 },
  timeSeparator: { color: colors.muted, fontSize: 16, fontWeight: '700', marginBottom: 18 },
  closedText: { color: colors.muted, fontSize: 13, fontStyle: 'italic' },
});
