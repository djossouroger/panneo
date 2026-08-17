import React, { useState } from 'react';
import { ActivityIndicator, Pressable, StyleSheet, Text, TextInput, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Check, Eye, EyeOff, LockKeyhole, Star, UserRound, Wrench } from 'lucide-react-native';
import { colors } from './theme';

export function Logo({ compact = false }: { compact?: boolean }) {
  return (
    <View style={styles.logoRow}>
      <LogoSymbol small={compact} />
      <Text style={[styles.logoText, compact && styles.logoTextCompact]}>Pannéo</Text>
    </View>
  );
}

export function LogoSymbol({ small = false }: { small?: boolean }) {
  return (
    <View style={[styles.logoMark, small && styles.logoMarkSmall]}>
      <View style={[styles.logoLine, styles.logoLineLeft]} />
      <View style={[styles.logoLine, styles.logoLineRight]} />
      <View style={styles.logoRepairDot} />
      <Wrench size={small ? 14 : 18} color={colors.white} strokeWidth={2.6} style={styles.logoWrench} />
    </View>
  );
}

export function ScreenContainer({ children, style }: { children: React.ReactNode; style?: any }) {
  return <SafeAreaView style={[styles.screen, style]}>{children}</SafeAreaView>;
}

export function AppButton({ title, onPress, variant = 'primary', loading = false, disabled = false, icon }: {
  title: string;
  onPress?: () => void;
  variant?: 'primary' | 'secondary' | 'danger';
  loading?: boolean;
  disabled?: boolean;
  icon?: React.ReactNode;
}) {
  const isPrimary = variant === 'primary';
  const isDanger = variant === 'danger';

  return (
    <Pressable
      disabled={disabled || loading}
      onPress={onPress}
      style={({ pressed }) => [
        styles.button,
        isPrimary && styles.primaryButton,
        variant === 'secondary' && styles.secondaryButton,
        isDanger && styles.dangerButton,
        (disabled || loading) && styles.buttonDisabled,
        pressed && !disabled && !loading && styles.buttonPressed,
      ]}
    >
      {loading ? (
        <ActivityIndicator color={isPrimary || isDanger ? colors.white : colors.primary} />
      ) : (
        <View style={styles.buttonContent}>
          {icon ? <View style={styles.buttonIcon}>{icon}</View> : null}
          <Text style={[styles.buttonText, variant === 'secondary' && styles.secondaryButtonText]}>{title}</Text>
        </View>
      )}
    </Pressable>
  );
}

export function AppInput({ label, value, onChangeText, placeholder, keyboardType = 'default', icon, error }: {
  label: string;
  value: string;
  onChangeText: (text: string) => void;
  placeholder?: string;
  keyboardType?: 'default' | 'email-address' | 'phone-pad';
  icon?: React.ReactNode;
  error?: string;
}) {
  return (
    <View style={styles.fieldWrap}>
      <Text style={styles.label}>{label}</Text>
      <View style={[styles.inputWrap, error ? styles.inputError : null]}>
        {icon ? <View style={styles.inputIcon}>{icon}</View> : null}
        <TextInput
          value={value}
          onChangeText={onChangeText}
          placeholder={placeholder}
          keyboardType={keyboardType}
          autoCapitalize={keyboardType === 'email-address' ? 'none' : 'sentences'}
          autoCorrect={keyboardType !== 'email-address'}
          placeholderTextColor={colors.muted}
          style={styles.input}
        />
      </View>
      {error ? <Text style={styles.errorText}>{error}</Text> : null}
    </View>
  );
}

export function PasswordInput({ label, value, onChangeText, placeholder, error }: {
  label: string;
  value: string;
  onChangeText: (text: string) => void;
  placeholder?: string;
  error?: string;
}) {
  const [show, setShow] = useState(false);

  return (
    <View style={styles.fieldWrap}>
      <Text style={styles.label}>{label}</Text>
      <View style={[styles.inputWrap, error ? styles.inputError : null]}>
        <View style={styles.inputIcon}><LockKeyhole size={18} color={colors.muted} /></View>
        <TextInput
          value={value}
          onChangeText={onChangeText}
          placeholder={placeholder}
          secureTextEntry={!show}
          autoCapitalize="none"
          autoCorrect={false}
          placeholderTextColor={colors.muted}
          style={styles.input}
        />
        <Pressable onPress={() => setShow((prev) => !prev)} style={styles.inputIconRight}>
          {show ? <EyeOff size={18} color={colors.muted} /> : <Eye size={18} color={colors.muted} />}
        </Pressable>
      </View>
      {error ? <Text style={styles.errorText}>{error}</Text> : null}
    </View>
  );
}

export function RoleCard({ title, description, icon, active, onPress }: {
  title: string;
  description: string;
  icon: 'user' | 'wrench';
  active: boolean;
  onPress: () => void;
}) {
  const Icon = icon === 'user' ? UserRound : Wrench;

  return (
    <Pressable onPress={onPress} style={[styles.roleCard, active && styles.roleCardActive]}>
      <View style={[styles.roleIcon, active && styles.roleIconActive]}>
        <Icon size={22} color={active ? colors.primary : colors.text} strokeWidth={2.2} />
      </View>
      <View style={styles.roleContent}>
        <Text style={styles.roleTitle}>{title}</Text>
        <Text style={styles.roleDescription}>{description}</Text>
      </View>
      {active ? (
        <View style={styles.selectionIndicator}>
          <Check size={12} color={colors.white} strokeWidth={3} />
        </View>
      ) : null}
    </Pressable>
  );
}

export function StatusBadge({ label, tone = 'success' }: { label: string; tone?: 'success' | 'warning' | 'danger' | 'neutral' }) {
  const map = {
    success: { bg: '#E7F9F0', color: colors.success },
    warning: { bg: '#FFF3E2', color: colors.urgent },
    danger: { bg: '#FEE4E2', color: colors.danger },
    neutral: { bg: '#F2F4F7', color: colors.muted },
  };
  const toneStyle = map[tone];

  return (
    <View style={[styles.badge, { backgroundColor: toneStyle.bg }]}>
      <Text style={[styles.badgeText, { color: toneStyle.color }]}>{label}</Text>
    </View>
  );
}

export function VerifiedBadge({ label = 'Vérifié', size = 12 }: { label?: string; size?: number }) {
  return (
    <View style={styles.verifiedBadge}>
      <Check size={size} color={colors.success} strokeWidth={2.5} />
      <Text style={styles.verifiedBadgeText}>{label}</Text>
    </View>
  );
}

export function LoadingState({ label = 'Chargement...' }: { label?: string }) {
  return (
    <View style={styles.stateBox}>
      <ActivityIndicator color={colors.primary} />
      <Text style={styles.stateText}>{label}</Text>
    </View>
  );
}

export function EmptyState({ icon, title, text }: { icon: React.ReactNode; title: string; text: string }) {
  return (
    <View style={styles.emptyBox}>
      <View style={styles.emptyIcon}>{icon}</View>
      <Text style={styles.emptyTitle}>{title}</Text>
      <Text style={styles.emptyText}>{text}</Text>
    </View>
  );
}

export function ErrorMessage({ message }: { message?: string | null }) {
  if (!message) return null;
  return <Text style={styles.formError}>{message}</Text>;
}

export function LoadMore({ loading, onPress }: { loading: boolean; onPress: () => void }) {
  if (loading) {
    return <ActivityIndicator color={colors.primary} style={styles.loadMoreSpinner} />;
  }
  return (
    <Pressable onPress={onPress} style={({ pressed }) => [styles.loadMoreButton, pressed && styles.loadMorePressed]}>
      <Text style={styles.loadMoreText}>Charger plus</Text>
    </Pressable>
  );
}

export function StarRating({ rating, size = 14, showValue = true }: { rating: number | string | null; size?: number; showValue?: boolean }) {
  const value = rating !== null && rating !== '' && !Number.isNaN(Number(rating)) ? Number(rating) : null;
  const rounded = Math.round(value ?? 0);
  return (
    <View style={styles.starRow}>
      {Array.from({ length: 5 }).map((_, i) => (
        <Star
          key={i}
          size={size}
          color={i < rounded ? '#F59E0B' : colors.border}
          fill={i < rounded ? '#F59E0B' : 'transparent'}
          strokeWidth={1.5}
        />
      ))}
      {showValue && value !== null ? <Text style={[styles.starText, { fontSize: size - 2 }]}>{value.toFixed(1)}</Text> : null}
    </View>
  );
}

export function RatingSelector({ rating, onPress, size = 24 }: { rating: number; onPress: (rating: number) => void; size?: number }) {
  return (
    <View style={styles.ratingSelectorRow}>
      {Array.from({ length: 5 }).map((_, i) => (
        <Pressable key={i} onPress={() => onPress(i + 1)} style={styles.ratingButton}>
          <Star
            size={size}
            color={i < rating ? '#F59E0B' : colors.border}
            fill={i < rating ? '#F59E0B' : 'transparent'}
            strokeWidth={1.5}
          />
        </Pressable>
      ))}
    </View>
  );
}

export function Textarea({ label, value, onChangeText, placeholder, error, maxLength }: {
  label: string;
  value: string;
  onChangeText: (text: string) => void;
  placeholder?: string;
  error?: string;
  maxLength?: number;
}) {
  return (
    <View style={styles.fieldWrap}>
      <Text style={styles.label}>{label}</Text>
      <View style={[styles.textareaWrap, error ? styles.inputError : null]}>
        <TextInput
          value={value}
          onChangeText={onChangeText}
          placeholder={placeholder}
          placeholderTextColor={colors.muted}
          style={styles.textarea}
          multiline
          maxLength={maxLength}
          textAlignVertical="top"
        />
      </View>
      {maxLength ? <Text style={styles.charCount}>{value.length}/{maxLength}</Text> : null}
      {error ? <Text style={styles.errorText}>{error}</Text> : null}
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.background, paddingHorizontal: 20 },
  logoRow: { flexDirection: 'row', alignItems: 'center', gap: 10 },
  logoMark: { width: 40, height: 40, borderRadius: 12, backgroundColor: colors.primary, alignItems: 'center', justifyContent: 'center', overflow: 'hidden' },
  logoMarkSmall: { width: 32, height: 32, borderRadius: 10 },
  logoLine: { position: 'absolute', height: 4, borderRadius: 4, backgroundColor: colors.white },
  logoLineLeft: { left: 5, top: 14, width: 15 },
  logoLineRight: { right: 5, bottom: 14, width: 15 },
  logoRepairDot: { position: 'absolute', width: 7, height: 7, borderRadius: 4, backgroundColor: colors.urgent, left: 17, top: 17 },
  logoWrench: { transform: [{ rotate: '-34deg' }] },
  logoText: { fontSize: 28, fontWeight: '700', color: colors.text },
  logoTextCompact: { fontSize: 20 },
  button: { borderRadius: 999, minHeight: 52, alignItems: 'center', justifyContent: 'center', paddingHorizontal: 18 },
  primaryButton: { backgroundColor: colors.primary },
  secondaryButton: { backgroundColor: colors.white, borderWidth: 1, borderColor: colors.border },
  dangerButton: { backgroundColor: colors.danger },
  buttonContent: { flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 8 },
  buttonIcon: { width: 20, height: 20, alignItems: 'center', justifyContent: 'center' },
  buttonText: { color: colors.white, fontSize: 16, fontWeight: '700' },
  secondaryButtonText: { color: colors.text },
  buttonDisabled: { opacity: 0.6 },
  buttonPressed: { opacity: 0.9 },
  fieldWrap: { gap: 8, marginBottom: 16 },
  label: { fontSize: 14, fontWeight: '600', color: colors.text },
  inputWrap: { flexDirection: 'row', alignItems: 'center', borderWidth: 1, borderColor: colors.border, backgroundColor: colors.white, borderRadius: 12, minHeight: 52, paddingHorizontal: 12 },
  inputError: { borderColor: colors.danger },
  inputIcon: { marginRight: 8 },
  inputIconRight: { marginLeft: 8, width: 36, height: 36, alignItems: 'center', justifyContent: 'center' },
  input: { flex: 1, color: colors.text, fontSize: 16 },
  errorText: { color: colors.danger, fontSize: 12, marginTop: -4 },
  formError: { color: colors.danger, backgroundColor: '#FEE4E2', borderRadius: 10, paddingHorizontal: 12, paddingVertical: 10, marginBottom: 14, fontSize: 13 },
  loadMoreButton: { alignSelf: 'center', borderRadius: 999, borderWidth: 1, borderColor: colors.primary, paddingHorizontal: 22, paddingVertical: 10, backgroundColor: colors.white },
  loadMoreText: { color: colors.primary, fontSize: 14, fontWeight: '700' },
  loadMorePressed: { opacity: 0.7 },
  loadMoreSpinner: { alignSelf: 'center', paddingVertical: 8 },
  roleCard: { flexDirection: 'row', alignItems: 'center', gap: 12, borderRadius: 12, borderWidth: 1, borderColor: colors.border, backgroundColor: colors.white, padding: 16, marginBottom: 12 },
  roleCardActive: { borderColor: colors.primary, backgroundColor: colors.primaryLight },
  roleIcon: { width: 46, height: 46, borderRadius: 12, backgroundColor: '#F2F4F7', alignItems: 'center', justifyContent: 'center' },
  roleIconActive: { backgroundColor: colors.white },
  roleContent: { flex: 1 },
  roleTitle: { color: colors.text, fontSize: 18, fontWeight: '700' },
  roleDescription: { color: colors.muted, fontSize: 14, marginTop: 4, lineHeight: 20 },
  selectionIndicator: { width: 22, height: 22, borderRadius: 11, backgroundColor: colors.primary, alignItems: 'center', justifyContent: 'center' },
  badge: { alignSelf: 'flex-start', paddingHorizontal: 10, paddingVertical: 6, borderRadius: 999 },
  badgeText: { fontSize: 12, fontWeight: '700' },
  stateBox: { flex: 1, alignItems: 'center', justifyContent: 'center', gap: 12 },
  stateText: { color: colors.muted, fontSize: 14 },
  emptyBox: { backgroundColor: colors.white, borderRadius: 12, borderWidth: 1, borderColor: colors.border, padding: 20, alignItems: 'center', gap: 8 },
  emptyIcon: { width: 44, height: 44, borderRadius: 12, alignItems: 'center', justifyContent: 'center', backgroundColor: colors.primaryLight },
  emptyTitle: { color: colors.text, fontSize: 17, fontWeight: '700', textAlign: 'center' },
  emptyText: { color: colors.muted, fontSize: 14, textAlign: 'center', lineHeight: 20 },
  ratingSelectorRow: { flexDirection: 'row', alignItems: 'center', gap: 4, justifyContent: 'center', paddingVertical: 8 },
  ratingButton: { width: 36, height: 36, alignItems: 'center', justifyContent: 'center' },
  textareaWrap: { borderWidth: 1, borderColor: colors.border, backgroundColor: colors.white, borderRadius: 12, minHeight: 100, paddingHorizontal: 12, paddingVertical: 10 },
  textarea: { color: colors.text, fontSize: 16, minHeight: 80, textAlignVertical: 'top' },
  charCount: { color: colors.muted, fontSize: 12, textAlign: 'right', marginTop: 2 },
  starRow: { flexDirection: 'row', alignItems: 'center', gap: 2 },
  starText: { color: colors.muted, fontWeight: '600', marginLeft: 6 },
  verifiedBadge: { flexDirection: 'row', alignItems: 'center', gap: 4, backgroundColor: '#E7F9F0', borderRadius: 999, paddingHorizontal: 8, paddingVertical: 3, alignSelf: 'flex-start' },
  verifiedBadgeText: { color: colors.success, fontSize: 11, fontWeight: '700' },
});

