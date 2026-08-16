import { useRouter } from 'expo-router';
import { useState } from 'react';
import { View, Text, StyleSheet } from 'react-native';
import { colors } from '../../components/theme';
import { AppButton, RoleCard, ScreenContainer } from '../../components/ui';

export default function RoleSelectionScreen() {
  const router = useRouter();
  const [selected, setSelected] = useState<'client' | 'artisan'>('client');

  return (
    <ScreenContainer style={styles.container}>
      <View style={styles.header}>
        <Text style={styles.title}>Comment souhaitez-vous utiliser Pannéo ?</Text>
        <Text style={styles.subtitle}>Choisissez le profil qui correspond à votre besoin.</Text>
      </View>

      <RoleCard
        title="J'ai besoin d'un dépannage"
        description="Signalez une panne et trouvez un professionnel disponible."
        icon="user"
        active={selected === 'client'}
        onPress={() => setSelected('client')}
      />
      <RoleCard
        title="Je suis artisan"
        description="Recevez et gérez des demandes de dépannage."
        icon="wrench"
        active={selected === 'artisan'}
        onPress={() => setSelected('artisan')}
      />

      <View style={styles.actions}>
        <AppButton title="Continuer" onPress={() => router.push(`/signup/form?role=${selected}`)} />
        <AppButton title="Retour" variant="secondary" onPress={() => router.back()} />
      </View>
    </ScreenContainer>
  );
}

const styles = StyleSheet.create({
  container: { justifyContent: 'center', paddingVertical: 20 },
  header: { marginBottom: 28 },
  title: { fontSize: 28, lineHeight: 36, fontWeight: '700', color: colors.text, marginBottom: 8 },
  subtitle: { fontSize: 15, lineHeight: 22, color: colors.muted },
  actions: { gap: 12, marginTop: 28 },
});