import { useEffect, useMemo, useState } from 'react';
import { useLocalSearchParams, useRouter } from 'expo-router';
import * as ImagePicker from 'expo-image-picker';
import { Image, Keyboard, KeyboardAvoidingView, Platform, Pressable, ScrollView, StyleSheet, Text, TextInput, View } from 'react-native';
import { ChevronLeft, CircleCheck, ImagePlus, Mail, MapPin, Send, X } from 'lucide-react-native';
import { colors } from '../components/theme';
import { AppButton, ErrorMessage, LoadingState, Logo, ScreenContainer } from '../components/ui';
import { CategoryCard, getCategoryIcon, ProgressIndicator, RequestStatusBadge } from '../components/repairRequests';
import { ApiError, Category, createRepairRequest, getCategories, RepairRequest } from '../lib/api';
import { getToken, restoreSession } from '../lib/session';

type Step = 1 | 2 | 3 | 4 | 5;

type FieldErrors = {
  category_id?: string;
  description?: string;
  city?: string;
  district?: string;
  images?: string;
};

const helpBySlug: Record<string, string> = {
  plomberie: 'Exemples : fuite d’eau, robinet cassé, canalisation bouchée...',
  electricite: 'Exemples : prise en panne, coupure locale, interrupteur défectueux...',
  serrurerie: 'Exemples : clé cassée, serrure bloquée, porte impossible à ouvrir...',
  climatisation: 'Exemples : climatisation qui ne refroidit plus, fuite, bruit inhabituel...',
  electromenager: 'Exemples : machine en panne, appareil qui ne démarre plus, bruit anormal...',
};

export default function ReportScreen() {
  const router = useRouter();
  const { category: categoryParam } = useLocalSearchParams<{ category?: string }>();
  const [step, setStep] = useState<Step>(1);
  const [preselected, setPreselected] = useState(false);
  const [token, setToken] = useState<string | null>(null);
  const [categories, setCategories] = useState<Category[]>([]);
  const [selectedCategory, setSelectedCategory] = useState<Category | null>(null);
  const [title, setTitle] = useState('');
  const [description, setDescription] = useState('');
  const [city, setCity] = useState('');
  const [district, setDistrict] = useState('');
  const [addressDetails, setAddressDetails] = useState('');
  const [images, setImages] = useState<string[]>([]);
  const [createdRequest, setCreatedRequest] = useState<RepairRequest | null>(null);
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<FieldErrors>({});

  const descriptionHelp = useMemo(() => selectedCategory ? helpBySlug[selectedCategory.slug] : null, [selectedCategory]);

  const load = async () => {
    setLoading(true);
    setError(null);
    try {
      const session = await restoreSession();
      if (!session) {
        router.replace('/login');
        return;
      }
      if (session.user.role !== 'client') {
        router.replace('/');
        return;
      }
      setToken(session.token);
      const nextCategories = await getCategories();
      setCategories(nextCategories);
      const slug = typeof categoryParam === 'string' ? categoryParam : null;
      const match = slug ? nextCategories.find((cat) => cat.slug === slug) : undefined;
      if (match) {
        setSelectedCategory(match);
        setStep(2);
        setPreselected(true);
      }
    } catch {
      setError('Impossible de charger les catégories. Vérifiez que le backend est lancé.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    load();
  }, [categoryParam]);

  const goBack = () => {
    if (step === 5 || step === 1 || (step === 2 && preselected)) {
      router.back();
      return;
    }
    setStep((current) => (current - 1) as Step);
  };

  const goNext = () => {
    Keyboard.dismiss();
    setError(null);
    if (step === 1 && !selectedCategory) {
      setFieldErrors({ category_id: 'Choisissez un type de dépannage.' });
      return;
    }
    if (step === 2) {
      const nextErrors: FieldErrors = {};
      if (description.trim().length < 12) nextErrors.description = 'Décrivez la panne en quelques mots.';
      setFieldErrors(nextErrors);
      if (Object.keys(nextErrors).length > 0) return;
    }
    if (step === 3) {
      const nextErrors: FieldErrors = {};
      if (!city.trim()) nextErrors.city = 'La ville est requise.';
      if (!district.trim()) nextErrors.district = 'Le quartier est requis.';
      setFieldErrors(nextErrors);
      if (Object.keys(nextErrors).length > 0) return;
    }
    setFieldErrors({});
    setStep((current) => Math.min(current + 1, 4) as Step);
  };

  const addImage = async () => {
    if (images.length >= 2) return;
    try {
      const permission = await ImagePicker.requestMediaLibraryPermissionsAsync();
      if (!permission.granted) {
        setError('Autorisez l’accès à vos photos pour joindre une image.');
        return;
      }
      const result = await ImagePicker.launchImageLibraryAsync({
        mediaTypes: ['images'],
        quality: 0.7,
      });
      if (result.canceled || !result.assets?.length) return;
      const uri = result.assets[0].uri;
      setImages((prev) => (prev.length >= 2 ? prev : [...prev, uri]));
      setError(null);
    } catch {
      setError('Impossible d’accéder à vos photos.');
    }
  };

  const removeImage = (index: number) => {
    setImages((prev) => prev.filter((_, i) => i !== index));
  };

  const submit = async () => {
    if (!selectedCategory || submitting) return;
    const currentToken = token || await getToken();
    if (!currentToken) {
      router.replace('/login');
      return;
    }

    setSubmitting(true);
    setError(null);
    try {
      const result = await createRepairRequest(currentToken, {
        category_id: selectedCategory.id,
        title: title.trim() || null,
        description: description.trim(),
        city: city.trim(),
        district: district.trim(),
        address_details: addressDetails.trim() || null,
        images: images.length > 0 ? images : undefined,
      });
      setCreatedRequest(result);
      setStep(5);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Votre demande n’a pas pu être envoyée.');
      if (err instanceof ApiError) {
        setFieldErrors({
          category_id: err.errors.category_id?.[0],
          description: err.errors.description?.[0],
          city: err.errors.city?.[0],
          district: err.errors.district?.[0],
        });
      }
    } finally {
      setSubmitting(false);
    }
  };

  if (loading) {
    return <ScreenContainer><LoadingState label="Chargement du signalement..." /></ScreenContainer>;
  }

  if (step === 5 && createdRequest) {
    return <SuccessView repairRequest={createdRequest} onFind={() => router.replace(`/available-artisans/${createdRequest.id}` as never)} onView={() => router.replace(`/repair-request/${createdRequest.id}` as never)} />;
  }

  return (
    <ScreenContainer>
      <View style={styles.topBar}>
        <Pressable onPress={goBack} style={styles.backButton}>
          <ChevronLeft size={22} color={colors.text} />
        </Pressable>
        <Logo compact />
      </View>

      <KeyboardAvoidingView behavior={Platform.OS === 'ios' ? 'padding' : undefined} style={styles.flex}>
        <ScrollView contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled" showsVerticalScrollIndicator={false}>
          <ProgressIndicator step={step} />
          <ErrorMessage message={error} />

          {step === 1 ? (
            <View>
              <Text style={styles.title}>Quel problème rencontrez-vous ?</Text>
              <Text style={styles.subtitle}>Choisissez le type de dépannage dont vous avez besoin.</Text>
              {fieldErrors.category_id ? <Text style={styles.fieldError}>{fieldErrors.category_id}</Text> : null}
              <View style={styles.categoryGrid}>
                {categories.map((category) => (
                  <View key={category.id} style={styles.categoryCell}>
                    <CategoryCard category={category} selected={selectedCategory?.id === category.id} onPress={() => setSelectedCategory(category)} />
                  </View>
                ))}
              </View>
              <AppButton title="Continuer" onPress={goNext} disabled={!selectedCategory} />
            </View>
          ) : null}

          {step === 2 ? (
            <View>
              <Text style={styles.title}>Que se passe-t-il ?</Text>
              <Text style={styles.subtitle}>Donnez quelques détails pour mieux comprendre votre problème.</Text>
              <Text style={styles.label}>Titre court</Text>
              <TextInput value={title} onChangeText={setTitle} placeholder="Ex. Fuite sous l’évier" placeholderTextColor={colors.muted} style={styles.input} returnKeyType="next" />
              <View style={styles.textareaHeader}>
                <Text style={styles.label}>Description</Text>
                <Text style={styles.counter}>{description.length}/1200</Text>
              </View>
              <TextInput
                value={description}
                onChangeText={setDescription}
                placeholder="Décrivez brièvement ce qui s’est passé..."
                placeholderTextColor={colors.muted}
                style={[styles.input, styles.textarea, fieldErrors.description && styles.inputError]}
                multiline
                maxLength={1200}
                textAlignVertical="top"
                returnKeyType="done"
              />
              {fieldErrors.description ? <Text style={styles.fieldError}>{fieldErrors.description}</Text> : null}
              {descriptionHelp ? <Text style={styles.helpText}>{descriptionHelp}</Text> : null}

              <View style={styles.photoSection}>
                <Text style={styles.label}>Photos (optionnelles)</Text>
                <Text style={styles.helpText}>Ajoutez 1 ou 2 photos pour aider le dépanneur à comprendre la panne.</Text>
                <View style={styles.photoRow}>
                  {images.map((uri, index) => (
                    <View key={uri} style={styles.photoCell}>
                      <Image source={{ uri }} style={styles.photoThumb} resizeMode="cover" />
                      <Pressable onPress={() => removeImage(index)} style={styles.photoRemove} hitSlop={8}>
                        <X size={13} color={colors.white} strokeWidth={2.6} />
                      </Pressable>
                    </View>
                  ))}
                  {images.length < 2 ? (
                    <Pressable onPress={addImage} style={styles.photoAdd}>
                      <ImagePlus size={22} color={colors.primary} strokeWidth={2.2} />
                      <Text style={styles.photoAddText}>Ajouter</Text>
                    </Pressable>
                  ) : null}
                </View>
                {fieldErrors.images ? <Text style={styles.fieldError}>{fieldErrors.images}</Text> : null}
              </View>

              <View style={styles.actions}>
                <AppButton title="Continuer" onPress={goNext} disabled={description.trim().length < 12} />
              </View>
            </View>
          ) : null}

          {step === 3 ? (
            <View>
              <Text style={styles.title}>Où avez-vous besoin d’aide ?</Text>
              <Text style={styles.subtitle}>Indiquez simplement la ville, le quartier et quelques repères utiles.</Text>
              <Text style={styles.label}>Ville</Text>
              <TextInput value={city} onChangeText={setCity} placeholder="Ex. Cotonou" placeholderTextColor={colors.muted} style={[styles.input, fieldErrors.city && styles.inputError]} returnKeyType="next" />
              {fieldErrors.city ? <Text style={styles.fieldError}>{fieldErrors.city}</Text> : null}
              <Text style={styles.label}>Quartier</Text>
              <TextInput value={district} onChangeText={setDistrict} placeholder="Ex. Akpakpa" placeholderTextColor={colors.muted} style={[styles.input, fieldErrors.district && styles.inputError]} returnKeyType="next" />
              {fieldErrors.district ? <Text style={styles.fieldError}>{fieldErrors.district}</Text> : null}
              <Text style={styles.label}>Indications complémentaires</Text>
              <TextInput
                value={addressDetails}
                onChangeText={setAddressDetails}
                placeholder="Ex. Rue derrière la pharmacie, portail bleu..."
                placeholderTextColor={colors.muted}
                style={[styles.input, styles.textareaSmall]}
                multiline
                textAlignVertical="top"
              />
              <View style={styles.actions}>
                <AppButton title="Continuer" onPress={goNext} disabled={!city.trim() || !district.trim()} />
              </View>
            </View>
          ) : null}

          {step === 4 && selectedCategory ? (
            <SummaryView
              category={selectedCategory}
              title={title}
              description={description}
              city={city}
              district={district}
              addressDetails={addressDetails}
              images={images}
              submitting={submitting}
              onEdit={setStep}
              onSubmit={submit}
            />
          ) : null}
        </ScrollView>
      </KeyboardAvoidingView>
    </ScreenContainer>
  );
}

function SummaryView({ category, title, description, city, district, addressDetails, images, submitting, onEdit, onSubmit }: {
  category: Category;
  title: string;
  description: string;
  city: string;
  district: string;
  addressDetails: string;
  images: string[];
  submitting: boolean;
  onEdit: (step: Step) => void;
  onSubmit: () => void;
}) {
  const Icon = getCategoryIcon(category.icon);
  return (
    <View>
      <Text style={styles.title}>Vérifiez votre demande</Text>
      <Text style={styles.subtitle}>Relisez les informations avant l’envoi.</Text>
      <View style={styles.summaryCard}>
        <SummarySection label="Type de dépannage" onEdit={() => onEdit(1)}>
          <View style={styles.summaryRow}>
            <View style={styles.summaryIcon}><Icon size={20} color={colors.primary} /></View>
            <Text style={styles.summaryStrong}>{category.name}</Text>
          </View>
        </SummarySection>
        <SummarySection label="Problème" onEdit={() => onEdit(2)}>
          {title.trim() ? <Text style={styles.summaryStrong}>{title.trim()}</Text> : null}
          <Text style={styles.summaryText}>{description.trim()}</Text>
          {images.length > 0 ? (
            <View style={styles.summaryPhotos}>
              {images.map((uri) => (
                <Image key={uri} source={{ uri }} style={styles.summaryPhoto} resizeMode="cover" />
              ))}
            </View>
          ) : null}
        </SummarySection>
        <SummarySection label="Localisation" onEdit={() => onEdit(3)}>
          <View style={styles.summaryRow}>
            <MapPin size={18} color={colors.muted} />
            <Text style={styles.summaryStrong}>{city.trim()} — {district.trim()}</Text>
          </View>
          {addressDetails.trim() ? <Text style={styles.summaryText}>{addressDetails.trim()}</Text> : null}
        </SummarySection>
      </View>
      <View style={styles.actions}>
        <AppButton title="Envoyer ma demande" onPress={onSubmit} loading={submitting} disabled={submitting} />
      </View>
    </View>
  );
}

function SummarySection({ label, children, onEdit }: { label: string; children: React.ReactNode; onEdit: () => void }) {
  return (
    <View style={styles.summarySection}>
      <View style={styles.summaryHeader}>
        <Text style={styles.summaryLabel}>{label}</Text>
        <Pressable onPress={onEdit}><Text style={styles.editText}>Modifier</Text></Pressable>
      </View>
      {children}
    </View>
  );
}

function SuccessView({ repairRequest, onFind, onView }: { repairRequest: RepairRequest; onFind: () => void; onView: () => void }) {
  return (
    <ScreenContainer style={styles.successScreen}>
      <View style={styles.successIcon}>
        <CircleCheck size={54} color={colors.success} strokeWidth={2.2} />
      </View>
      <Text style={styles.successTitle}>Demande envoyée</Text>
      <Text style={styles.successText}>Votre demande de dépannage a bien été enregistrée.</Text>
      <View style={styles.successCard}>
        <Text style={styles.successLabel}>Référence</Text>
        <Text style={styles.reference}>{repairRequest.reference}</Text>
        <View style={styles.successDivider} />
        <Text style={styles.summaryStrong}>{repairRequest.category.name}</Text>
        <Text style={styles.summaryText}>{repairRequest.location.city} — {repairRequest.location.district}</Text>
        <View style={styles.statusLine}>
          <Text style={styles.successLabel}>Statut</Text>
          <RequestStatusBadge status={repairRequest.status} />
        </View>
      </View>
      <View style={styles.actions}>
        <AppButton title="Trouver un dépanneur" onPress={onFind} />
        <AppButton title="Voir ma demande" variant="secondary" onPress={onView} />
      </View>
    </ScreenContainer>
  );
}

const styles = StyleSheet.create({
  flex: { flex: 1 },
  topBar: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingVertical: 14, borderBottomWidth: 1, borderBottomColor: colors.border },
  backButton: { width: 40, height: 40, borderRadius: 12, backgroundColor: colors.white, borderWidth: 1, borderColor: colors.border, alignItems: 'center', justifyContent: 'center' },
  content: { paddingVertical: 20, paddingBottom: 34 },
  title: { color: colors.text, fontSize: 26, lineHeight: 34, fontWeight: '700', marginBottom: 8 },
  subtitle: { color: colors.muted, fontSize: 15, lineHeight: 22, marginBottom: 20 },
  categoryGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: 12, marginBottom: 22 },
  categoryCell: { width: '47.8%' },
  label: { color: colors.text, fontSize: 14, fontWeight: '700', marginBottom: 8, marginTop: 8 },
  input: { minHeight: 52, borderRadius: 12, borderWidth: 1, borderColor: colors.border, backgroundColor: colors.white, color: colors.text, fontSize: 16, paddingHorizontal: 12, paddingVertical: 12, marginBottom: 12 },
  inputError: { borderColor: colors.danger },
  textarea: { minHeight: 146, lineHeight: 22 },
  textareaSmall: { minHeight: 108, lineHeight: 22 },
  textareaHeader: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' },
  counter: { color: colors.muted, fontSize: 12, fontWeight: '600' },
  helpText: { color: colors.muted, fontSize: 13, lineHeight: 19, marginBottom: 8 },
  fieldError: { color: colors.danger, fontSize: 12, marginTop: -6, marginBottom: 10 },
  actions: { gap: 12, marginTop: 18 },
  photoSection: { marginTop: 6, marginBottom: 4 },
  photoRow: { flexDirection: 'row', gap: 10, flexWrap: 'wrap' },
  photoCell: { position: 'relative' },
  photoThumb: { width: 92, height: 92, borderRadius: 12, backgroundColor: colors.border },
  photoRemove: { position: 'absolute', top: 6, right: 6, width: 22, height: 22, borderRadius: 11, backgroundColor: 'rgba(16, 24, 40, 0.72)', alignItems: 'center', justifyContent: 'center' },
  photoAdd: { width: 92, height: 92, borderRadius: 12, borderWidth: 1, borderColor: colors.border, borderStyle: 'dashed', backgroundColor: colors.white, alignItems: 'center', justifyContent: 'center', gap: 4 },
  photoAddText: { color: colors.primary, fontSize: 12, fontWeight: '700' },
  summaryPhotos: { flexDirection: 'row', gap: 8 },
  summaryPhoto: { width: 68, height: 68, borderRadius: 10, backgroundColor: colors.border },
  summaryCard: { backgroundColor: colors.white, borderRadius: 12, borderWidth: 1, borderColor: colors.border, paddingHorizontal: 16 },
  summarySection: { paddingVertical: 16, borderBottomWidth: 1, borderBottomColor: colors.border, gap: 8 },
  summaryHeader: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 12 },
  summaryLabel: { color: colors.muted, fontSize: 13, fontWeight: '700' },
  editText: { color: colors.primary, fontSize: 13, fontWeight: '700' },
  summaryRow: { flexDirection: 'row', alignItems: 'center', gap: 10 },
  summaryIcon: { width: 36, height: 36, borderRadius: 10, backgroundColor: colors.primaryLight, alignItems: 'center', justifyContent: 'center' },
  summaryStrong: { color: colors.text, fontSize: 16, fontWeight: '700', lineHeight: 22 },
  summaryText: { color: colors.muted, fontSize: 14, lineHeight: 21 },
  successScreen: { justifyContent: 'center', gap: 16 },
  successIcon: { alignSelf: 'center', width: 96, height: 96, borderRadius: 26, backgroundColor: '#E7F9F0', alignItems: 'center', justifyContent: 'center' },
  successTitle: { color: colors.text, fontSize: 28, lineHeight: 36, fontWeight: '700', textAlign: 'center' },
  successText: { color: colors.muted, fontSize: 15, lineHeight: 22, textAlign: 'center' },
  successCard: { backgroundColor: colors.white, borderRadius: 12, borderWidth: 1, borderColor: colors.border, padding: 16, gap: 8 },
  successLabel: { color: colors.muted, fontSize: 13, fontWeight: '700' },
  reference: { color: colors.primary, fontSize: 22, fontWeight: '700' },
  successDivider: { height: 1, backgroundColor: colors.border, marginVertical: 4 },
  statusLine: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginTop: 4 },
});