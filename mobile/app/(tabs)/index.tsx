import { useEffect, useState } from 'react';
import { useRouter } from 'expo-router';
import { LoadingState, ScreenContainer } from '../../components/ui';
import { restoreSession } from '../../lib/session';
import ClientHomeScreen from '../home/client';
import ArtisanHomeScreen from '../home/artisan';

export default function HomeTab() {
  const router = useRouter();
  const [role, setRole] = useState<'client' | 'artisan' | null>(null);

  useEffect(() => {
    let mounted = true;

    restoreSession().then((session) => {
      if (!mounted) return;
      if (!session) {
        router.replace('/welcome');
        return;
      }
      if (!session.user.email_verified_at) {
        router.replace('/verify-email');
        return;
      }
      setRole(session.user.role === 'artisan' ? 'artisan' : 'client');
    });

    return () => {
      mounted = false;
    };
  }, [router]);

  if (!role) {
    return <ScreenContainer><LoadingState label="Ouverture de Pannéo..." /></ScreenContainer>;
  }

  return role === 'artisan' ? <ArtisanHomeScreen /> : <ClientHomeScreen />;
}
