import { useCallback, useState } from 'react';
import { useRouter } from 'expo-router';
import { useFocusEffect } from '@react-navigation/native';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { BadgeCheck, Bell, CheckCheck, CircleCheck, CircleX, Inbox, Play, Star } from 'lucide-react-native';
import { colors } from './theme';
import { AppNotification, NotificationType, getUnreadNotificationCount } from '../lib/api';
import { formatRelativeDate } from '../lib/dates';

export function NotificationBell({ token }: { token: string | null }) {
  const router = useRouter();
  const [count, setCount] = useState(0);

  const refresh = useCallback(() => {
    if (!token) {
      setCount(0);
      return;
    }
    getUnreadNotificationCount(token).then(setCount).catch(() => undefined);
  }, [token]);

  useFocusEffect(useCallback(() => {
    refresh();
  }, [refresh]));

  return (
    <Pressable onPress={() => router.push('/notifications')} style={styles.bellButton} hitSlop={8}>
      <Bell size={21} color={colors.primary} strokeWidth={2.2} />
      {count > 0 ? (
        <View style={styles.badge}>
          <Text style={styles.badgeText}>{count > 99 ? '99+' : count}</Text>
        </View>
      ) : null}
    </Pressable>
  );
}

export function notificationIcon(type: NotificationType) {
  switch (type) {
    case 'repair_request_received':
      return Inbox;
    case 'repair_request_accepted':
      return CircleCheck;
    case 'repair_request_rejected':
      return CircleX;
    case 'repair_request_started':
      return Play;
    case 'repair_request_completed':
      return CheckCheck;
    case 'review_received':
      return Star;
    case 'account_verified':
      return BadgeCheck;
    default:
      return Inbox;
  }
}

export function notificationTone(type: NotificationType) {
  switch (type) {
    case 'repair_request_rejected':
      return colors.danger;
    case 'review_received':
      return '#F59E0B';
    case 'repair_request_completed':
    case 'repair_request_accepted':
    case 'account_verified':
      return colors.success;
    default:
      return colors.primary;
  }
}

export function NotificationItem({ notification, onPress }: { notification: AppNotification; onPress: () => void }) {
  const Icon = notificationIcon(notification.type);
  const tone = notificationTone(notification.type);
  const unread = notification.read_at === null;

  return (
    <Pressable
      onPress={onPress}
      style={[styles.item, unread ? styles.itemUnread : styles.itemRead]}
    >
      <View style={[styles.iconBox, { backgroundColor: unread ? '#EFF4FF' : colors.border }]}>
        <Icon size={20} color={unread ? tone : colors.muted} strokeWidth={2.2} />
      </View>
      <View style={styles.body}>
        <View style={styles.titleRow}>
          <Text style={[styles.title, unread ? styles.titleUnread : styles.titleRead]} numberOfLines={1}>
            {notification.title}
          </Text>
          {unread ? <View style={styles.dot} /> : null}
        </View>
        <Text style={styles.message} numberOfLines={2}>{notification.message}</Text>
        <Text style={styles.date}>{formatRelativeDate(notification.created_at)}</Text>
      </View>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  item: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: 12,
    padding: 14,
    borderRadius: 14,
    borderWidth: 1,
  },
  itemUnread: {
    backgroundColor: '#EFF4FF',
    borderColor: colors.primaryLight,
  },
  itemRead: {
    backgroundColor: colors.white,
    borderColor: colors.border,
  },
  iconBox: {
    width: 38,
    height: 38,
    borderRadius: 11,
    alignItems: 'center',
    justifyContent: 'center',
  },
  body: { flex: 1, gap: 3 },
  titleRow: { flexDirection: 'row', alignItems: 'center', gap: 6 },
  title: { flex: 1, fontSize: 15, fontWeight: '700' },
  titleUnread: { color: colors.text },
  titleRead: { color: colors.text, opacity: 0.8 },
  message: { color: colors.muted, fontSize: 13, lineHeight: 18 },
  date: { color: colors.muted, fontSize: 12, marginTop: 2, opacity: 0.8 },
  dot: { width: 8, height: 8, borderRadius: 4, backgroundColor: colors.primary },
  bellButton: { width: 42, height: 42, borderRadius: 12, backgroundColor: colors.primaryLight, alignItems: 'center', justifyContent: 'center' },
  badge: { position: 'absolute', top: -4, right: -4, minWidth: 18, height: 18, borderRadius: 9, backgroundColor: colors.danger, alignItems: 'center', justifyContent: 'center', paddingHorizontal: 4 },
  badgeText: { color: colors.white, fontSize: 10, fontWeight: '800' },
});
