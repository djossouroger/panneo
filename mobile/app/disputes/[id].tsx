import { useCallback, useEffect, useState } from 'react';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { ChevronLeft, FileWarning } from 'lucide-react-native';
import { colors } from '../../components/theme';
import { ErrorMessage, LoadingState, Logo, ScreenContainer, StatusBadge } from '../../components/ui';
import { formatRequestDate } from '../../components/repairRequests';
import { ApiError, Dispute, friendlyError, getDispute } from '../../lib/api';
import { handleSessionExpired, restoreSession } from '../../lib/session';

const statusTone: Record<Dispute['status'], 'success' | 'warning' | 'danger' | 'neutral'> = {
  open: 'danger',
  in_review: 'warning',
  resolved: 'success',
  rejected: 'neutral',
};

export default function DisputeDetailScreen() {
  const router = useRouter();
  const { id } = useLocalSearchParams<{ id: string }>();
  const [dispute, setDispute] = useState<Dispute | null>(null);
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
      setDispute(await getDispute(session.token, id));
    } catch (err) {
      if (await handleSessionExpired(err)) {
        router.replace('/login');
        return;
      }
      setError(err instanceof ApiError ? err.message : friendlyError(err));
    } finally {
      setLoading(false);
    }
  }, [id, router]);

  useEffect(() => {
    load();
  }, [load]);

  if (loading) {
    return <ScreenContainer><LoadingState label="Chargement du litige..." /></ScreenContainer>;
  }

  if (!dispute) {
    return (
      <ScreenContainer>
        <View style={styles.topBar}><Logo compact /></View>
        <View style={styles.center}>
          <ErrorMessage message={error || 'Litige introuvable.'} />
        </View>
      </ScreenContainer>
    );
  }

  return (
    <ScreenContainer>
      <View style={styles.header}>
        <Pressable onPress={() => router.back()} style={styles.backButton}>
          <ChevronLeft size={22} color={colors.text} />
        </Pressable>
        <Text style={styles.headerTitle}>Litige #{dispute.id}</Text>
      </View>

      <ScrollView contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
        <ErrorMessage message={error} />

        <View style={styles.statusCard}>
          <View style={styles.statusIcon}>
            <FileWarning size={22} color={colors.primary} />
          </View>
          <View style={styles.statusText}>
            <Text style={styles.subject}>{dispute.subject}</Text>
            <Text style={styles.statusLabel}>
              {dispute.type_label} · Déclaré le {formatRequestDate(dispute.created_at)}
            </Text>
          </View>
          <StatusBadge label={dispute.status_label} tone={statusTone[dispute.status]} />
        </View>

        <View style={styles.card}>
          <Text style={styles.cardTitle}>Demande concernée</Text>
          <Text style={styles.bodyText}>{dispute.repair_request.reference}</Text>
          {dispute.repair_request.title ? <Text style={styles.bodyText}>{dispute.repair_request.title}</Text> : null}
        </View>

        <View style={styles.card}>
          <Text style={styles.cardTitle}>Description</Text>
          <Text style={styles.bodyText}>{dispute.description}</Text>
        </View>

        {dispute.resolution_notes ? (
          <View style={[styles.card, styles.resolvedCard]}>
            <Text style={styles.cardTitle}>Résolution</Text>
            <Text style={styles.bodyText}>{dispute.resolution_notes}</Text>
            {dispute.resolved_at ? <Text style={styles.metaText}>Résolu le {formatRequestDate(dispute.resolved_at)}</Text> : null}
          </View>
        ) : null}
      </ScrollView>
    </ScreenContainer>
  );
}

const styles = StyleSheet.create({
  header: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingVertical: 14, borderBottomWidth: 1, borderBottomColor: colors.border },
  topBar: { flexDirection: 'row', alignItems: 'center', paddingVertical: 14, borderBottomWidth: 1, borderBottomColor: colors.border },
  backButton: { width: 40, height: 40, borderRadius: 12, backgroundColor: colors.white, borderWidth: 1, borderColor: colors.border, alignItems: 'center', justifyContent: 'center' },
  headerTitle: { color: colors.text, fontSize: 20, fontWeight: '700' },
  content: { paddingVertical: 20, paddingBottom: 84, gap: 14 },
  statusCard: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: colors.white, borderRadius: 12, borderWidth: 1, borderColor: colors.border, padding: 16 },
  statusIcon: { width: 44, height: 44, borderRadius: 12, backgroundColor: colors.primaryLight, alignItems: 'center', justifyContent: 'center' },
  statusText: { flex: 1 },
  subject: { color: colors.text, fontSize: 17, fontWeight: '700' },
  statusLabel: { color: colors.muted, fontSize: 12, marginTop: 3 },
  card: { backgroundColor: colors.white, borderRadius: 12, borderWidth: 1, borderColor: colors.border, padding: 16, gap: 8 },
  resolvedCard: { borderColor: '#12B76A' },
  cardTitle: { color: colors.muted, fontSize: 12, fontWeight: '700', textTransform: 'uppercase' },
  bodyText: { color: colors.text, fontSize: 15, lineHeight: 22 },
  metaText: { color: colors.muted, fontSize: 13 },
  center: { flex: 1, justifyContent: 'center', paddingHorizontal: 20 },
});
