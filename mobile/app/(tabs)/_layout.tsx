import { useEffect, useState } from 'react';
import { Tabs } from 'expo-router';
import { Clock, Home, Inbox, User } from 'lucide-react-native';
import { colors } from '../../components/theme';
import { restoreSession } from '../../lib/session';

export default function TabsLayout() {
  const [role, setRole] = useState<'client' | 'artisan'>('client');

  useEffect(() => {
    let mounted = true;
    restoreSession().then((session) => {
      if (!mounted) return;
      setRole(session?.user.role === 'artisan' ? 'artisan' : 'client');
    });
    return () => {
      mounted = false;
    };
  }, []);

  const RequestsIcon = role === 'artisan' ? Inbox : Clock;

  return (
    <Tabs
      screenOptions={{
        headerShown: false,
        tabBarActiveTintColor: colors.primary,
        tabBarInactiveTintColor: colors.muted,
        tabBarLabelStyle: { fontSize: 12, fontWeight: '600' },
        tabBarStyle: { borderTopWidth: 1, borderTopColor: colors.border, backgroundColor: colors.background },
      }}
    >
      <Tabs.Screen
        name="index"
        options={{
          title: 'Accueil',
          tabBarIcon: ({ color, size }) => <Home size={size} color={color} strokeWidth={2.2} />,
        }}
      />
      <Tabs.Screen
        name="requests"
        options={{
          title: 'Demandes',
          tabBarIcon: ({ color, size }) => <RequestsIcon size={size} color={color} strokeWidth={2.2} />,
        }}
      />
      <Tabs.Screen
        name="profile"
        options={{
          title: 'Profil',
          tabBarIcon: ({ color, size }) => <User size={size} color={color} strokeWidth={2.2} />,
        }}
      />
    </Tabs>
  );
}
