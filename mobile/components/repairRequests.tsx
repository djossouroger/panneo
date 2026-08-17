import React from 'react';
import { Image, Modal, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { CalendarDays, Check, CircleCheck, ClipboardList, Clock, Clock3, Droplets, Fan, KeyRound, MapPin, Phone, PlugZap, RefreshCw, SearchX, Send, Star, Wrench, X } from 'lucide-react-native';
import { AvailableArtisan, Category, PublicArtisan, RepairRequest, RepairRequestOffer, RepairRequestOfferStatus, RepairRequestOfferSummary, RepairRequestStatus } from '../lib/api';
import { colors } from './theme';
import { AppButton, StarRating, VerifiedBadge } from './ui';

const iconMap = {
  plumbing: Droplets,
  electricity: PlugZap,
  locksmith: KeyRound,
  air_conditioning: Fan,
  appliance: PlugZap,
};

const requestStatusMap: Record<RepairRequestStatus, { label: string; badge: object; text: object }> = {
  pending: { label: 'En recherche', badge: { backgroundColor: '#EFF4FF' }, text: { color: colors.primary } },
  awaiting_artisan: { label: 'Réponse en attente', badge: { backgroundColor: '#FFF3E2' }, text: { color: colors.urgent } },
  accepted: { label: 'Artisan trouvé', badge: { backgroundColor: '#E7F9F0' }, text: { color: colors.success } },
  in_progress: { label: 'En cours', badge: { backgroundColor: '#EFF4FF' }, text: { color: colors.primary } },
  completed: { label: 'Terminée', badge: { backgroundColor: '#E7F9F0' }, text: { color: colors.success } },
  cancelled: { label: 'Annulée', badge: { backgroundColor: '#F2F4F7' }, text: { color: colors.muted } },
};

const interventionStatusMap: Partial<Record<RepairRequestStatus, { label: string; badge: object; text: object }>> = {
  accepted: { label: 'À démarrer', badge: { backgroundColor: '#E7F9F0' }, text: { color: colors.success } },
  in_progress: { label: 'En cours', badge: { backgroundColor: '#EFF4FF' }, text: { color: colors.primary } },
  completed: { label: 'Terminée', badge: { backgroundColor: '#E7F9F0' }, text: { color: colors.success } },
};

const offerStatusMap: Record<RepairRequestOfferStatus, { label: string; badge: object; text: object }> = {
  pending: { label: 'À répondre', badge: { backgroundColor: '#FFF3E2' }, text: { color: colors.urgent } },
  accepted: { label: 'Acceptée', badge: { backgroundColor: '#E7F9F0' }, text: { color: colors.success } },
  rejected: { label: 'Refusée', badge: { backgroundColor: '#FEE4E2' }, text: { color: colors.danger } },
  cancelled: { label: 'Annulée', badge: { backgroundColor: '#F2F4F7' }, text: { color: colors.muted } },
};

export function getCategoryIcon(icon?: string | null) {
  return iconMap[icon as keyof typeof iconMap] || Wrench;
}

export function getStatusLabel(status: RepairRequestStatus) {
  return requestStatusMap[status]?.label || status;
}

export function getOfferStatusLabel(status: RepairRequestOfferStatus) {
  return offerStatusMap[status]?.label || status;
}

export function RequestStatusBadge({ status, label }: { status: RepairRequestStatus; label?: string }) {
  const style = requestStatusMap[status] || requestStatusMap.pending;
  return (
    <View style={[styles.statusBadge, style.badge]}>
      <Text style={[styles.statusText, style.text]}>{label || style.label}</Text>
    </View>
  );
}

export function InterventionStatusBadge({ status }: { status: RepairRequestStatus }) {
  const style = interventionStatusMap[status] || requestStatusMap[status] || requestStatusMap.pending;
  return (
    <View style={[styles.statusBadge, style.badge]}>
      <Text style={[styles.statusText, style.text]}>{style.label}</Text>
    </View>
  );
}

export function OfferStatusBadge({ status }: { status: RepairRequestOfferStatus }) {
  const style = offerStatusMap[status] || offerStatusMap.pending;
  return (
    <View style={[styles.statusBadge, style.badge]}>
      <Text style={[styles.statusText, style.text]}>{style.label}</Text>
    </View>
  );
}

export function ProgressIndicator({ step, total = 4 }: { step: number; total?: number }) {
  return (
    <View style={styles.progressWrap}>
      <Text style={styles.stepText}>Étape {step} sur {total}</Text>
      <View style={styles.progressTrack}>
        {Array.from({ length: total }).map((_, index) => (
          <View key={index} style={[styles.progressSegment, index < step && styles.progressSegmentActive]} />
        ))}
      </View>
    </View>
  );
}

export function CategoryCard({ category, selected, onPress }: { category: Category; selected: boolean; onPress: () => void }) {
  const Icon = getCategoryIcon(category.icon);
  return (
    <Pressable onPress={onPress} style={[styles.categoryCard, selected && styles.categoryCardSelected]}>
      <View style={[styles.categoryIcon, selected && styles.categoryIconSelected]}>
        <Icon size={24} color={selected ? colors.primary : colors.text} strokeWidth={2.2} />
      </View>
      <Text style={[styles.categoryName, selected && styles.categoryNameSelected]}>{category.name}</Text>
      {selected ? (
        <View style={styles.categoryCheck}>
          <Check size={13} color={colors.white} strokeWidth={3} />
        </View>
      ) : null}
    </Pressable>
  );
}

export function RepairRequestCard({ repairRequest, onPress }: { repairRequest: RepairRequest; onPress: () => void }) {
  const Icon = getCategoryIcon(repairRequest.category.icon);
  const title = repairRequest.title || repairRequest.description;
  const relationText = getRequestRelationText(repairRequest);
  const review = repairRequest.review;

  return (
    <Pressable onPress={onPress} style={styles.requestCard}>
      <View style={styles.requestTopRow}>
        <View style={styles.requestCategoryWrap}>
          <View style={styles.requestIconBox}>
            <Icon size={20} color={colors.primary} />
          </View>
          <Text style={styles.requestCategory}>{repairRequest.category.name}</Text>
        </View>
        <RequestStatusBadge status={repairRequest.status} />
      </View>

      <Text style={styles.requestTitle} numberOfLines={2}>{title}</Text>
      {relationText ? <Text style={styles.relationText}>{relationText}</Text> : null}
      {repairRequest.status === 'completed' && review ? (
        <View style={styles.reviewRow}>
          <StarRating rating={review.rating} size={14} showValue={false} />
          <Text style={styles.reviewValue}>{review.rating}/5</Text>
        </View>
      ) : null}
      <Text style={styles.requestMeta}>{repairRequest.reference}</Text>
      <View style={styles.metaRow}>
        <MapPin size={14} color={colors.muted} />
        <Text style={styles.requestMeta}>{formatLocation(repairRequest.location.district, repairRequest.location.city)}</Text>
      </View>
      <View style={styles.metaRow}>
        <CalendarDays size={14} color={colors.muted} />
        <Text style={styles.requestMeta}>{formatRequestDate(repairRequest.created_at)}</Text>
      </View>
    </Pressable>
  );
}

export function InterventionCard({ repairRequest, onPress }: { repairRequest: RepairRequest; onPress: () => void }) {
  const Icon = getCategoryIcon(repairRequest.category.icon);
  const title = repairRequest.title || repairRequest.description;
  const clientName = repairRequest.client?.name || 'Client';
  const date = repairRequest.status === 'completed' ? repairRequest.completed_at : repairRequest.started_at || repairRequest.accepted_at || repairRequest.created_at;
  const review = repairRequest.review;
  const hasReview = Boolean(review);

  return (
    <Pressable onPress={onPress} style={styles.interventionCard}>
      <View style={styles.requestTopRow}>
        <View style={styles.requestCategoryWrap}>
          <View style={styles.requestIconBox}><Icon size={20} color={colors.primary} /></View>
          <Text style={styles.requestCategory}>{repairRequest.category.name}</Text>
        </View>
        <InterventionStatusBadge status={repairRequest.status} />
      </View>
      <Text style={styles.requestTitle} numberOfLines={2}>{title}</Text>
      <Text style={styles.relationText}>{clientName}</Text>
      <View style={styles.metaRow}>
        <MapPin size={14} color={colors.muted} />
        <Text style={styles.requestMeta}>{formatLocation(repairRequest.location.district, repairRequest.location.city)}</Text>
      </View>
      {repairRequest.status === 'completed' && hasReview ? (
        <View style={styles.reviewRow}>
          <StarRating rating={review!.rating} size={14} showValue={false} />
          <Text style={styles.reviewValue}>{review!.rating}/5</Text>
          {review!.comment ? <Text style={styles.reviewComment} numberOfLines={2}>{review!.comment}</Text> : null}
        </View>
      ) : null}
      <View style={styles.incomingFooter}>
        <Text style={styles.requestMeta}>{repairRequest.reference}</Text>
        <Text style={styles.requestMeta}>{formatRequestDate(date)}</Text>
      </View>
    </Pressable>
  );
}

export function ArtisanCard({ artisan, onChoose, onViewProfile, loading = false, disabled = false }: {
  artisan: AvailableArtisan;
  onChoose: () => void;
  onViewProfile?: () => void;
  loading?: boolean;
  disabled?: boolean;
}) {
  const stats = artisan.stats;
  const rating = stats?.average_rating != null && !Number.isNaN(Number(stats.average_rating)) ? Number(stats.average_rating) : null;
  const reviewsCount = stats?.reviews_count ?? 0;
  const completed = stats?.completed_interventions ?? 0;

  return (
    <View style={styles.artisanCard}>
      <View style={styles.artisanHeader}>
        <View style={styles.avatar}><Text style={styles.avatarText}>{initialsFor(artisan.name)}</Text></View>
        <View style={styles.artisanTitleWrap}>
          <View style={styles.artisanNameRow}>
            <Text style={styles.artisanName} numberOfLines={1}>{artisan.name}</Text>
            {artisan.verification_status === 'verified' ? <VerifiedBadge /> : null}
          </View>
          {onViewProfile ? (
            <Pressable onPress={onViewProfile} style={styles.profileLink}>
              <Text style={styles.profileLinkText}>Voir le profil</Text>
            </Pressable>
          ) : null}
        </View>
      </View>
      <View style={styles.artisanInfoRow}>
        <Wrench size={16} color={colors.muted} />
        <Text style={styles.artisanInfo}>{categoryName(artisan)}</Text>
      </View>
      <View style={styles.artisanInfoRow}>
        <MapPin size={16} color={colors.muted} />
        <Text style={styles.artisanInfo}>{formatLocation(artisan.district, artisan.city)}</Text>
      </View>
      {rating !== null && (
        <View style={styles.artisanStatsRow}>
          <StarRating rating={rating} size={14} showValue={false} />
          <Text style={styles.artisanRatingText}>{rating.toFixed(1)} ({reviewsCount} avis)</Text>
        </View>
      )}
      {rating === null ? (
        <Text style={styles.artisanNoRating}>{reviewsCount === 0 ? 'Aucun avis pour le moment' : ''}</Text>
      ) : null}
      <View style={styles.artisanStatsRow}>
        <Text style={styles.artisanCompleted}>{completed} intervention{completed !== 1 ? 's' : ''}</Text>
      </View>
      {artisan.description ? <Text style={styles.artisanDescription} numberOfLines={3}>{artisan.description}</Text> : null}
      <AppButton title="Choisir cet artisan" onPress={onChoose} loading={loading} disabled={disabled || loading} />
    </View>
  );
}

export function IncomingRequestCard({ offer, onPress }: { offer: RepairRequestOffer; onPress: () => void }) {
  const request = offer.request;
  const Icon = getCategoryIcon(request?.category.icon);
  const title = request?.title || request?.description || 'Demande de dépannage';

  return (
    <Pressable onPress={onPress} style={styles.incomingCard}>
      <View style={styles.requestTopRow}>
        <View style={styles.requestCategoryWrap}>
          <View style={styles.requestIconBox}><Icon size={20} color={colors.primary} /></View>
          <Text style={styles.requestCategory}>{request?.category.name || 'Dépannage'}</Text>
        </View>
        <OfferStatusBadge status={offer.status} />
      </View>
      <Text style={styles.requestTitle} numberOfLines={2}>{title}</Text>
      {request ? <Text style={styles.bodyText} numberOfLines={3}>{request.description}</Text> : null}
      {request ? (
        <View style={styles.metaRow}>
          <MapPin size={14} color={colors.muted} />
          <Text style={styles.requestMeta}>{formatLocation(request.location.district, request.location.city)}</Text>
        </View>
      ) : null}
      <View style={styles.incomingFooter}>
        <Text style={styles.requestMeta}>{request?.reference || '—'}</Text>
        <Text style={styles.viewLink}>Voir la demande</Text>
      </View>
    </Pressable>
  );
}

export function ContactActions({ onCall, onWhatsApp, callDisabled = false, whatsappDisabled = false }: {
  onCall: () => void;
  onWhatsApp: () => void;
  callDisabled?: boolean;
  whatsappDisabled?: boolean;
}) {
  return (
    <View style={styles.contactActions}>
      <AppButton title="Appeler" onPress={onCall} disabled={callDisabled} icon={<Phone size={18} color={colors.white} />} />
      <AppButton title="WhatsApp" variant="secondary" onPress={onWhatsApp} disabled={whatsappDisabled} icon={<Send size={18} color={colors.text} />} />
    </View>
  );
}

export function ConfirmationModal({ visible, title, text, confirmLabel, cancelLabel, loading = false, confirmVariant = 'danger', onConfirm, onCancel, children }: {
  visible: boolean;
  title: string;
  text: string;
  confirmLabel: string;
  cancelLabel: string;
  loading?: boolean;
  confirmVariant?: 'primary' | 'danger';
  onConfirm: () => void;
  onCancel: () => void;
  children?: React.ReactNode;
}) {
  return (
    <Modal visible={visible} transparent animationType="fade" onRequestClose={onCancel}>
      <View style={styles.modalBackdrop}>
        <View style={styles.modalCard}>
          <Pressable onPress={onCancel} style={styles.modalClose} disabled={loading}>
            <X size={18} color={colors.muted} />
          </Pressable>
          <Text style={styles.modalTitle}>{title}</Text>
          <Text style={styles.modalText}>{text}</Text>
          {children}
          <View style={styles.modalActions}>
            <AppButton title={cancelLabel} variant="secondary" onPress={onCancel} disabled={loading} />
            <AppButton title={confirmLabel} variant={confirmVariant} onPress={onConfirm} loading={loading} disabled={loading} />
          </View>
        </View>
      </View>
    </Modal>
  );
}

export function RequestsEmptyState({ onPress }: { onPress: () => void }) {
  return (
    <View style={styles.emptyCard}>
      <View style={styles.emptyIcon}>
        <ClipboardList size={24} color={colors.primary} />
      </View>
      <Text style={styles.emptyTitle}>Aucune demande</Text>
      <Text style={styles.emptyText}>Vous n’avez encore signalé aucune panne.</Text>
      <AppButton title="Signaler une panne" onPress={onPress} />
    </View>
  );
}

type TimelineStep = {
  title: string;
  text: string;
  date?: string | null;
  done?: boolean;
  pending?: boolean;
  muted?: boolean;
  danger?: boolean;
};

export function Timeline({ status, repairRequest }: { status?: RepairRequestStatus; repairRequest?: RepairRequest }) {
  const effectiveStatus = repairRequest?.status || status || 'pending';
  const latestOffer = repairRequest?.current_offer || repairRequest?.last_offer || repairRequest?.offers?.[repairRequest.offers.length - 1];
  const acceptedDone = ['accepted', 'in_progress', 'completed'].includes(effectiveStatus);
  const startedDone = ['in_progress', 'completed'].includes(effectiveStatus);
  const completedDone = effectiveStatus === 'completed';

  const steps: TimelineStep[] = [
    {
      title: 'Demande créée',
      text: 'Votre demande a été enregistrée.',
      date: repairRequest?.created_at,
      done: true,
    },
    {
      title: latestOffer?.artisan ? `Envoyée à ${latestOffer.artisan.name}` : 'Recherche d’un dépanneur',
      text: latestOffer ? 'La proposition a été transmise au dépanneur.' : 'Vous pouvez choisir un dépanneur disponible dans votre ville.',
      date: latestOffer?.created_at,
      done: Boolean(latestOffer),
      pending: effectiveStatus === 'pending' && !latestOffer,
    },
    {
      title: repairRequest?.artisan ? `${repairRequest.artisan.name} a accepté` : 'Demande acceptée',
      text: acceptedDone ? 'Les coordonnées sont disponibles.' : 'En attente de confirmation.',
      date: repairRequest?.accepted_at || latestOffer?.responded_at,
      done: acceptedDone,
      pending: effectiveStatus === 'awaiting_artisan',
    },
    {
      title: 'Intervention commencée',
      text: startedDone ? 'La prise en charge est en cours.' : 'Non encore effectuée.',
      date: repairRequest?.started_at,
      done: startedDone,
      muted: !startedDone,
    },
    {
      title: 'Intervention terminée',
      text: completedDone ? 'Le dépannage est clôturé.' : 'Non encore effectuée.',
      date: repairRequest?.completed_at,
      done: completedDone,
      muted: !completedDone,
    },
  ];

  if (effectiveStatus === 'cancelled') {
    steps.push({
      title: 'Demande annulée',
      text: 'Cette demande ne sera plus proposée.',
      date: null,
      done: false,
      danger: true,
    });
  }

  return (
    <View style={styles.timelineCard}>
      <Text style={styles.timelineTitle}>Suivi</Text>
      {steps.map((step, index) => (
        <React.Fragment key={`${step.title}-${index}`}>
          {index > 0 ? <View style={styles.timelineLine} /> : null}
          <TimelineItem {...step} />
        </React.Fragment>
      ))}
    </View>
  );
}

export function RequestImages({ images }: { images?: string[] | null }) {
  if (!images || images.length === 0) return null;
  return (
    <View style={styles.requestImagesBlock}>
      <Text style={styles.requestImagesLabel}>{images.length > 1 ? `${images.length} photos jointes` : '1 photo jointe'}</Text>
      <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.requestImagesRow}>
        {images.map((uri) => (
          <Image key={uri} source={{ uri }} style={styles.requestImage} resizeMode="cover" />
        ))}
      </ScrollView>
    </View>
  );
}

function TimelineItem({ title, text, date, done = false, pending = false, muted = false, danger = false }: {
  title: string;
  text: string;
  date?: string | null;
  done?: boolean;
  pending?: boolean;
  muted?: boolean;
  danger?: boolean;
}) {
  const dotStyle = done ? styles.timelineDotDone : pending ? styles.timelineDotPending : danger ? styles.timelineDotDanger : muted ? styles.timelineDotMuted : styles.timelineDot;
  const iconColor = done ? colors.white : danger ? colors.danger : pending ? colors.urgent : colors.muted;
  const Icon = done ? Check : pending ? Clock : danger ? X : Clock3;

  return (
    <View style={styles.timelineItem}>
      <View style={[styles.timelineDot, dotStyle]}><Icon size={12} color={iconColor} strokeWidth={2.7} /></View>
      <View style={styles.timelineTextWrap}>
        <Text style={styles.timelineItemTitle}>{title}</Text>
        {date ? <Text style={styles.timelineItemDate}>{formatRequestDate(date)}</Text> : null}
        <Text style={styles.timelineItemText}>{text}</Text>
      </View>
    </View>
  );
}

export function formatRequestDate(value?: string | null) {
  if (!value) return '—';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  const today = new Date();
  const sameDay = date.toDateString() === today.toDateString();
  const time = date.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
  return sameDay ? `Aujourd’hui à ${time}` : date.toLocaleDateString('fr-FR', { day: '2-digit', month: 'long', year: 'numeric' });
}

export function formatTime(value?: string | null) {
  if (!value) return '—';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return date.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
}

export function formatLocation(district?: string | null, city?: string | null) {
  if (district && city) return `${district}, ${city}`;
  return district || city || 'Localisation non renseignée';
}

export function initialsFor(name: string) {
  const parts = name.trim().split(/\s+/).filter(Boolean);
  if (parts.length === 0) return '??';
  return parts.slice(0, 2).map((part) => part[0]?.toUpperCase()).join('');
}

export function categoryName(artisan?: AvailableArtisan | PublicArtisan | null) {
  if (artisan && 'categories' in artisan && Array.isArray(artisan.categories)) {
    return artisan.categories.find((cat) => cat.is_primary)?.name || artisan.categories[0]?.name || 'Métier non renseigné';
  }
  if (!artisan || !('category' in artisan) || !artisan.category) return 'Métier non renseigné';
  return typeof artisan.category === 'string' ? artisan.category : artisan.category.name || 'Métier non renseigné';
}

function RequestAvailabilityBadge() {
  return (
    <View style={styles.availableBadge}>
      <Text style={styles.availableText}>Disponible</Text>
    </View>
  );
}

function getRequestRelationText(repairRequest: RepairRequest) {
  if (repairRequest.status === 'awaiting_artisan' && repairRequest.current_offer?.artisan) {
    return `Envoyée à ${repairRequest.current_offer.artisan.name}`;
  }
  if (repairRequest.status === 'accepted' && repairRequest.artisan) {
    return `${repairRequest.artisan.name} a accepté`;
  }
  if (repairRequest.status === 'in_progress' && repairRequest.artisan) {
    return `${repairRequest.artisan.name} a commencé l’intervention`;
  }
  if (repairRequest.status === 'completed') {
    return 'Dépannage terminé';
  }
  if (repairRequest.status === 'pending' && repairRequest.last_offer?.status === 'rejected' && repairRequest.last_offer.artisan) {
    return `${repairRequest.last_offer.artisan.name} n’est pas disponible`;
  }
  return null;
}

const styles = StyleSheet.create({
  progressWrap: { gap: 8, marginBottom: 18 },
  stepText: { color: colors.primary, fontSize: 13, fontWeight: '700' },
  progressTrack: { flexDirection: 'row', gap: 6 },
  progressSegment: { flex: 1, height: 4, borderRadius: 4, backgroundColor: colors.border },
  progressSegmentActive: { backgroundColor: colors.primary },
  categoryCard: { minHeight: 104, borderRadius: 12, borderWidth: 1, borderColor: colors.border, backgroundColor: colors.white, padding: 14, gap: 12, position: 'relative' },
  categoryCardSelected: { borderColor: colors.primary, backgroundColor: colors.primaryLight },
  categoryIcon: { width: 44, height: 44, borderRadius: 12, alignItems: 'center', justifyContent: 'center', backgroundColor: '#F2F4F7' },
  categoryIconSelected: { backgroundColor: colors.white },
  categoryName: { color: colors.text, fontSize: 15, fontWeight: '700', lineHeight: 20 },
  categoryNameSelected: { color: colors.primary },
  categoryCheck: { position: 'absolute', top: 12, right: 12, width: 22, height: 22, borderRadius: 11, backgroundColor: colors.primary, alignItems: 'center', justifyContent: 'center' },
  statusBadge: { alignSelf: 'flex-start', borderRadius: 999, paddingHorizontal: 10, paddingVertical: 6 },
  statusText: { fontSize: 12, fontWeight: '700' },
  requestCard: { backgroundColor: colors.white, borderRadius: 12, borderWidth: 1, borderColor: colors.border, padding: 15, gap: 9 },
  interventionCard: { backgroundColor: colors.white, borderRadius: 12, borderWidth: 1, borderColor: colors.border, padding: 15, gap: 9 },
  requestTopRow: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 12 },
  requestCategoryWrap: { flexDirection: 'row', alignItems: 'center', gap: 9, flex: 1 },
  requestIconBox: { width: 34, height: 34, borderRadius: 10, backgroundColor: colors.primaryLight, alignItems: 'center', justifyContent: 'center' },
  requestCategory: { color: colors.text, fontSize: 14, fontWeight: '700' },
  requestTitle: { color: colors.text, fontSize: 17, fontWeight: '700', lineHeight: 23 },
  relationText: { color: colors.primary, fontSize: 13, fontWeight: '700' },
  requestMeta: { color: colors.muted, fontSize: 13, fontWeight: '500' },
  metaRow: { flexDirection: 'row', alignItems: 'center', gap: 6 },
  artisanCard: { backgroundColor: colors.white, borderRadius: 12, borderWidth: 1, borderColor: colors.border, padding: 16, gap: 12 },
  artisanHeader: { flexDirection: 'row', alignItems: 'center', gap: 12 },
  avatar: { width: 50, height: 50, borderRadius: 14, backgroundColor: colors.primaryLight, alignItems: 'center', justifyContent: 'center' },
  avatarText: { color: colors.primary, fontSize: 17, fontWeight: '800' },
  artisanTitleWrap: { flex: 1, gap: 6 },
  artisanName: { color: colors.text, fontSize: 18, fontWeight: '700' },
  artisanNameRow: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  profileLink: { alignSelf: 'flex-start' },
  profileLinkText: { color: colors.primary, fontSize: 12, fontWeight: '700' },
  artisanRatingText: { color: colors.muted, fontSize: 12, fontWeight: '600', marginLeft: 4 },
  artisanNoRating: { color: colors.muted, fontSize: 12, fontStyle: 'italic', marginBottom: 4 },
  artisanCompleted: { color: colors.muted, fontSize: 13, fontWeight: '600' },
  artisanStatsRow: { flexDirection: 'row', alignItems: 'center', gap: 4, marginBottom: 4 },
  artisanDescription: { color: colors.muted, fontSize: 14, lineHeight: 20 },
  reviewRow: { flexDirection: 'row', alignItems: 'center', gap: 6, marginBottom: 4 },
  reviewValue: { color: colors.muted, fontSize: 12, fontWeight: '600' },
  reviewComment: { color: colors.muted, fontSize: 12, lineHeight: 18, opacity: 0.7, flex: 1 },
  artisanInfoRow: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  artisanInfo: { color: colors.text, fontSize: 14, fontWeight: '600' },
  availableBadge: { alignSelf: 'flex-start', borderRadius: 999, paddingHorizontal: 9, paddingVertical: 5, backgroundColor: '#E7F9F0' },
  availableText: { color: colors.success, fontSize: 12, fontWeight: '700' },
  incomingCard: { backgroundColor: colors.white, borderRadius: 12, borderWidth: 1, borderColor: colors.border, padding: 15, gap: 9 },
  incomingFooter: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', gap: 12 },
  viewLink: { color: colors.primary, fontSize: 13, fontWeight: '700' },
  bodyText: { color: colors.muted, fontSize: 14, lineHeight: 21 },
  contactActions: { gap: 10 },
  modalBackdrop: { flex: 1, backgroundColor: 'rgba(16, 24, 40, 0.42)', alignItems: 'center', justifyContent: 'center', padding: 22 },
  modalCard: { width: '100%', borderRadius: 12, backgroundColor: colors.white, padding: 18, gap: 12 },
  modalClose: { position: 'absolute', top: 12, right: 12, width: 32, height: 32, alignItems: 'center', justifyContent: 'center' },
  modalTitle: { color: colors.text, fontSize: 21, fontWeight: '700', paddingRight: 34 },
  modalText: { color: colors.muted, fontSize: 15, lineHeight: 22 },
  modalActions: { gap: 10, marginTop: 4 },
  emptyCard: { backgroundColor: colors.white, borderRadius: 12, borderWidth: 1, borderColor: colors.border, padding: 20, alignItems: 'center', gap: 10 },
  emptyIcon: { width: 54, height: 54, borderRadius: 14, alignItems: 'center', justifyContent: 'center', backgroundColor: colors.primaryLight },
  emptyTitle: { color: colors.text, fontSize: 18, fontWeight: '700', textAlign: 'center' },
  emptyText: { color: colors.muted, fontSize: 14, textAlign: 'center', lineHeight: 20, marginBottom: 8 },
  timelineCard: { gap: 12 },
  timelineTitle: { color: colors.text, fontSize: 18, fontWeight: '700', marginBottom: 2 },
  timelineItem: { flexDirection: 'row', gap: 12, alignItems: 'flex-start' },
  timelineDot: { width: 26, height: 26, borderRadius: 13, backgroundColor: '#F2F4F7', alignItems: 'center', justifyContent: 'center' },
  timelineDotDone: { backgroundColor: colors.success },
  timelineDotPending: { backgroundColor: '#FFF3E2' },
  timelineDotMuted: { backgroundColor: '#F2F4F7' },
  timelineDotDanger: { backgroundColor: '#FEE4E2' },
  timelineTextWrap: { flex: 1, paddingTop: 3 },
  timelineItemTitle: { color: colors.text, fontSize: 15, fontWeight: '700' },
  timelineItemDate: { color: colors.primary, fontSize: 12, fontWeight: '700', marginTop: 3 },
  timelineItemText: { color: colors.muted, fontSize: 13, lineHeight: 19, marginTop: 3 },
  timelineLine: { width: 2, height: 20, backgroundColor: colors.border, marginLeft: 12, marginVertical: -6 },
  requestImagesBlock: { gap: 8 },
  requestImagesLabel: { color: colors.muted, fontSize: 12, fontWeight: '700', textTransform: 'uppercase' },
  requestImagesRow: { gap: 10, paddingVertical: 2 },
  requestImage: { width: 132, height: 132, borderRadius: 14, backgroundColor: colors.border },
});

