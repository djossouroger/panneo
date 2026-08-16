import { useRouter } from 'expo-router';
import { View, Text, StyleSheet } from 'react-native';
import { Wrench } from 'lucide-react-native';
import { colors } from '../components/theme';
import { AppButton, Logo, ScreenContainer } from '../components/ui';

export default function WelcomeScreen() {
  const router = useRouter();

  return (
    <ScreenContainer style={styles.container}>
      <View style={styles.header}>
        <Logo />
      </View>

      <View style={styles.illustration}>
        <View style={styles.brokenLineLeft} />
        <View style={styles.brokenLineRight} />
        <View style={styles.toolCircle}>
          <Wrench size={42} color={colors.primary} strokeWidth={2.2} />
        </View>
      </View>

      <View style={styles.copy}>
        <Text style={styles.title}>Un dépannage quand vous en avez besoin.</Text>
        <Text style={styles.subtitle}>Trouvez rapidement un professionnel disponible pour résoudre votre panne.</Text>
      </View>

      <View style={styles.actions}>
        <AppButton title="Créer un compte" onPress={() => router.push('/signup/role')} />
        <AppButton title="Se connecter" variant="secondary" onPress={() => router.push('/login')} />
      </View>

      <Text style={styles.note}>Vous êtes artisan ? Vous pourrez créer votre profil professionnel pendant l'inscription.</Text>
    </ScreenContainer>
  );
}

const styles = StyleSheet.create({
  container: { justifyContent: 'center', paddingTop: 40 },
  header: { alignItems: 'center', marginBottom: 28 },
  illustration: { height: 142, alignItems: 'center', justifyContent: 'center', marginBottom: 28 },
  brokenLineLeft: { position: 'absolute', left: 16, width: '38%', height: 8, borderRadius: 8, backgroundColor: colors.primary },
  brokenLineRight: { position: 'absolute', right: 16, width: '38%', height: 8, borderRadius: 8, backgroundColor: colors.primary },
  toolCircle: { width: 104, height: 104, borderRadius: 52, alignItems: 'center', justifyContent: 'center', backgroundColor: colors.primaryLight, borderWidth: 1, borderColor: colors.border },
  copy: { marginBottom: 28 },
  title: { color: colors.text, fontSize: 30, fontWeight: '700', lineHeight: 38, textAlign: 'center' },
  subtitle: { color: colors.muted, fontSize: 16, lineHeight: 24, textAlign: 'center', marginTop: 12 },
  actions: { gap: 12 },
  note: { color: colors.muted, fontSize: 13, textAlign: 'center', lineHeight: 19, marginTop: 16 },
});