import { apiClient } from '@/lib/api/client';
import { API_ENDPOINTS } from '@/lib/api/endpoints';
import {
  extractList,
  extractObject,
  generateSlug,
  safeParseDate,
  safeParseInt,
  stringToBoolean,
} from '@/lib/utils/adapters';
import {
  MESSAGE_POLLING_INTERVAL_MS,
  type CreateDirectThreadRequest,
  type CreateDirectThreadResponse,
  type CreateGroupThreadRequest,
  type CreateGroupThreadResponse,
  type DeleteMessageRequest,
  type DeleteMessageResponse,
  type GetMessageThreadRequest,
  type GetMessageThreadResponse,
  type ListMessageThreadsRequest,
  type ListMessageThreadsResponse,
  type MarkMessageThreadReadRequest,
  type MarkMessageThreadReadResponse,
  type MessagesTransport,
  type SendMessageRequest,
  type SendMessageResponse,
  type UploadMessageAttachmentRequest,
  type UploadMessageAttachmentResponse,
} from '../api/messages.contract';
import {
  describeAttachmentForPreview,
  formatBytes,
  isGraduationYearGroupTitle,
  normalizeMessageAttachmentMimeType,
} from '../api/adapters/messages.adapter';
import type { FieldVisibility, PrivacySettings } from '@/features/authentication/types/auth.types';
import { getRegisteredMessageRecipient } from './messageRecipientRegistry';
import {
  normalizeFieldVisibility,
  parseFieldVisibility,
  resolveProfilePhoto,
} from '@/features/user/utils/profileUtils';
import type {
  MessageAttachment,
  MessageDeliveryStatus,
  MessageParticipant,
  MessageThreadCategory,
  MessageThreadDetail,
  MessageThreadSummary,
} from '../types/messages.types';
import { useIdentityStore } from '@/features/authentication/stores/useIdentityStore';

type BackendEnvelope = Record<string, unknown> & {
  status?: number;
  message?: string;
  data?: unknown;
};

type BackendThreadParticipant = Record<string, unknown>;
type BackendThreadLike = Record<string, unknown>;
type BackendMessageLike = Record<string, unknown>;
type DirectDraftThreadEntry = {
  participantMemberId: string;
  topic?: string;
  thread: MessageThreadDetail;
};
type SessionUserSnapshot = {
  id: string;
  memberId: string;
  role: string;
  slug?: string;
  fullName?: string;
  otherNames?: string;
  graduationYear?: number;
  city?: string;
  photo?: string;
  privacy?: PrivacySettings;
  avatarInitials?: string;
  profileHref?: string;
};

const DRAFT_DIRECT_THREAD_PREFIX = 'draft-direct__';
const directDraftThreads = new Map<string, DirectDraftThreadEntry>();

function nowIso() {
  return new Date().toISOString();
}

function createLocalAttachmentId() {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return `staged-${crypto.randomUUID()}`;
  }

  return `staged-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
}

function sortThreadsForInbox(threads: MessageThreadSummary[]) {
  return [...threads].sort((left, right) => {
    if (left.isPinned !== right.isPinned) {
      return left.isPinned ? -1 : 1;
    }

    return Date.parse(right.lastActivityAt) - Date.parse(left.lastActivityAt);
  });
}

function sortMessagesChronologically(messages: MessageThreadDetail['messages']) {
  return [...messages].sort(
    (left, right) => Date.parse(left.createdAt) - Date.parse(right.createdAt),
  );
}

function normalizeBackendTimestamp(value: unknown) {
  if (typeof value === 'string' && value.includes(' ') && !value.includes('T')) {
    return safeParseDate(value.replace(' ', 'T'));
  }

  return safeParseDate(value);
}

function normalizeBackendIdentifierValue(value: string) {
  const trimmedValue = value.trim();

  if (/^\d+$/.test(trimmedValue)) {
    return Number(trimmedValue);
  }

  return trimmedValue;
}

function normalizeRecipientRegistryLookupId(memberId: string) {
  const trimmedValue = memberId.trim();
  const normalizedValue = trimmedValue.toLowerCase();
  const prefixedNumericMatch = normalizedValue.match(/^(?:member|user)[-_ ]?(\d+)$/);

  if (prefixedNumericMatch?.[1]) {
    return prefixedNumericMatch[1];
  }

  return trimmedValue;
}

function getRegisteredRecipientForMemberId(memberId: string) {
  const exactMatch = getRegisteredMessageRecipient(memberId);
  if (exactMatch) {
    return exactMatch;
  }

  const normalizedLookupId = normalizeRecipientRegistryLookupId(memberId);
  if (normalizedLookupId !== memberId) {
    return getRegisteredMessageRecipient(normalizedLookupId);
  }

  return undefined;
}

function extractBackendParticipantFullName(rawParticipant: BackendThreadParticipant) {
  const firstName = String(rawParticipant.first_name ?? '').trim();
  const lastName = String(rawParticipant.last_name ?? '').trim();
  const combinedName = `${firstName} ${lastName}`.trim();

  return (
    String(
      rawParticipant.fullname ?? rawParticipant.full_name ?? rawParticipant.name ?? combinedName,
    ).trim() || undefined
  );
}

function isPlaceholderParticipantName(value: string, memberId: string) {
  const normalizedValue = value.trim().toLowerCase();
  const normalizedMemberId = memberId.trim().toLowerCase();

  if (!normalizedValue) {
    return true;
  }

  if (normalizedValue === normalizedMemberId) {
    return true;
  }

  if (/^(?:member|user)[-_ ]?\d+$/.test(normalizedValue)) {
    return true;
  }

  if (normalizedMemberId && normalizedValue === `member${normalizedMemberId}`) {
    return true;
  }

  if (normalizedMemberId && normalizedValue === `user${normalizedMemberId}`) {
    return true;
  }

  return normalizedValue === 'unknown' || normalizedValue === 'unknown member';
}

function deriveInitials(value: string) {
  return (
    value
      .split(/\s+/)
      .filter(Boolean)
      .slice(0, 2)
      .map((part) => part[0]?.toUpperCase() ?? '')
      .join('') || 'MB'
  );
}

function normalizeThreadCategory(value: unknown): MessageThreadCategory {
  const normalizedValue = String(value ?? '')
    .trim()
    .toLowerCase();

  if (normalizedValue === 'mentorship') return 'Mentorship';
  if (normalizedValue === 'events') return 'Events';
  if (normalizedValue === 'marketplace') return 'Marketplace';
  if (normalizedValue === 'community') return 'Community';

  return 'Community';
}

function normalizeThreadType(value: unknown): MessageThreadSummary['type'] {
  return value === 'group' ? 'group' : 'direct';
}

function normalizeDeliveryStatus(value: unknown): MessageDeliveryStatus | undefined {
  const normalizedValue = String(value ?? '')
    .trim()
    .toLowerCase();

  if (
    normalizedValue === 'sending' ||
    normalizedValue === 'sent' ||
    normalizedValue === 'delivered' ||
    normalizedValue === 'seen' ||
    normalizedValue === 'failed'
  ) {
    return normalizedValue;
  }

  if (normalizedValue === 'read') return 'seen';
  if (normalizedValue === 'received') return 'delivered';

  return undefined;
}

function normalizeParticipantRole(value: unknown): MessageParticipant['roleInThread'] {
  if (value === 'admin' || value === 'moderator' || value === 'member') {
    return value;
  }

  return 'member';
}

function getCurrentSessionUser(): SessionUserSnapshot | null {
  return useIdentityStore.getState().user as SessionUserSnapshot | null;
}

function buildViewerParticipantFromSessionUser(
  currentUser: SessionUserSnapshot,
): MessageParticipant {
  const fullName = currentUser.fullName?.trim() || 'You';
  const firstName = currentUser.otherNames?.trim() || fullName.split(/\s+/)[0] || fullName;
  const graduationYear = safeParseInt(currentUser.graduationYear) ?? 0;

  return {
    memberId: currentUser.memberId,
    slug: currentUser.slug ?? generateSlug(fullName, currentUser.memberId, 'user'),
    fullName,
    firstName,
    headline: graduationYear ? `Class of ${graduationYear}` : 'alumni member',
    location: currentUser.city || 'Nigeria',
    graduationYear,
    avatar: resolveProfilePhoto({
      photoUrl: currentUser.photo,
      privacy: currentUser.privacy,
      isOwner: true,
      isSignedIn: true,
    }),
    photoVisibility: currentUser.privacy?.photo,
    initials: currentUser.avatarInitials ?? deriveInitials(fullName),
    profileHref: currentUser.profileHref ?? `/alumni/profiles/${currentUser.memberId}`,
    presence: 'offline',
    roleInThread: 'member',
  };
}

function extractParticipantPhotoVisibility(
  rawParticipant: BackendThreadParticipant,
): FieldVisibility | undefined {
  const rawProfile =
    rawParticipant.profile && typeof rawParticipant.profile === 'object'
      ? (rawParticipant.profile as Record<string, unknown>)
      : undefined;
  const participantFieldVisibility = parseFieldVisibility(rawParticipant.field_visibility);
  const profileFieldVisibility = parseFieldVisibility(rawProfile?.field_visibility);
  const rawVisibility =
    rawParticipant.avatar_visible ??
    rawParticipant.photo_visible ??
    rawParticipant.image_visible ??
    participantFieldVisibility.avatar ??
    participantFieldVisibility.photo ??
    profileFieldVisibility.avatar ??
    profileFieldVisibility.photo;

  if (rawVisibility === undefined) {
    return undefined;
  }

  return normalizeFieldVisibility(rawVisibility, 'private');
}

function getEnvelopeData(responseData: unknown) {
  const envelope = extractObject(responseData, ['data']) as BackendEnvelope | null;
  if (envelope) {
    return envelope;
  }

  return (responseData as BackendEnvelope | null) ?? null;
}

function buildParticipantFromBackend(
  rawParticipant: BackendThreadParticipant,
  viewerMemberId: string,
): MessageParticipant {
  const rawUserId =
    rawParticipant.user_id ??
    rawParticipant.member_id ??
    rawParticipant.id ??
    rawParticipant.recipient_id ??
    '';
  const memberId = String(rawUserId || '');
  const currentUser = getCurrentSessionUser();
  const recipientRegistryEntry = getRegisteredRecipientForMemberId(memberId);
  const backendFullName = extractBackendParticipantFullName(rawParticipant);
  const preferredBackendFullName =
    backendFullName && !isPlaceholderParticipantName(backendFullName, memberId)
      ? backendFullName
      : undefined;
  const fullName =
    (memberId === viewerMemberId ? currentUser?.fullName : undefined) ??
    preferredBackendFullName ??
    recipientRegistryEntry?.fullName ??
    backendFullName ??
    `Member ${memberId}`;
  const firstName = fullName.split(/\s+/)[0] ?? fullName;
  const isViewer = memberId === viewerMemberId;
  const photoVisibility =
    (isViewer ? currentUser?.privacy?.photo : undefined) ??
    recipientRegistryEntry?.photoVisibility ??
    extractParticipantPhotoVisibility(rawParticipant);
  const rawAvatar =
    (isViewer ? currentUser?.photo : undefined) ??
    (typeof rawParticipant.avatar === 'string' ? rawParticipant.avatar : undefined) ??
    (typeof rawParticipant.image === 'string' ? rawParticipant.image : undefined) ??
    recipientRegistryEntry?.avatar;
  const avatar = resolveProfilePhoto({
    photoUrl: rawAvatar,
    photoVisibility,
    privacy: isViewer ? currentUser?.privacy : undefined,
    isOwner: isViewer,
    isSignedIn: Boolean(currentUser?.memberId),
  });
  const graduationYear =
    safeParseInt(rawParticipant.graduation_year ?? rawParticipant.year) ??
    (isViewer ? currentUser?.graduationYear : undefined) ??
    recipientRegistryEntry?.graduationYear ??
    0;
  const slug =
    (isViewer ? currentUser?.slug : undefined) ??
    recipientRegistryEntry?.slug ??
    generateSlug(fullName, memberId || firstName, 'member');

  return {
    memberId,
    slug,
    fullName,
    firstName,
    headline: graduationYear ? `Class of ${graduationYear}` : 'alumni member',
    location: 'Nigeria',
    graduationYear,
    avatar,
    photoVisibility,
    initials: (isViewer ? currentUser?.avatarInitials : undefined) ?? deriveInitials(fullName),
    profileHref:
      (isViewer ? currentUser?.profileHref : undefined) ??
      recipientRegistryEntry?.profileHref ??
      `/alumni/profiles/${memberId}`,
    presence: 'offline',
    roleInThread: normalizeParticipantRole(rawParticipant.role),
    lastReadMessageId:
      typeof rawParticipant.last_read_message_id === 'string' ||
      typeof rawParticipant.last_read_message_id === 'number'
        ? String(rawParticipant.last_read_message_id)
        : undefined,
    lastDeliveredMessageId:
      typeof rawParticipant.last_delivered_message_id === 'string' ||
      typeof rawParticipant.last_delivered_message_id === 'number'
        ? String(rawParticipant.last_delivered_message_id)
        : undefined,
  };
}

function getAttachmentKind(value: unknown): MessageAttachment['kind'] {
  if (value === 'audio') return 'audio';
  if (value === 'image') return 'image';
  return 'file';
}

function guessMimeTypeFromAttachmentKind(kind: MessageAttachment['kind']) {
  if (kind === 'audio') return 'audio/mpeg';
  if (kind === 'image') return 'image/jpeg';
  return 'application/octet-stream';
}

function buildAudioWaveform() {
  return [18, 28, 46, 34, 54, 23, 39, 58, 31, 43, 27, 48];
}

function describeAttachmentFallback(kind: MessageAttachment['kind']) {
  if (kind === 'audio') return 'Audio';
  if (kind === 'image') return 'Image';
  return 'File';
}

function normalizeAttachmentPreviewKind(value: string): MessageAttachment['kind'] | undefined {
  const normalized = value.trim().toLowerCase().replace(/\s+/g, ' ');

  if (
    normalized === '[audio]' ||
    normalized === 'audio attachment' ||
    normalized === '[voice note]'
  ) {
    return 'audio';
  }

  if (normalized === '[image]' || normalized === 'image attachment') {
    return 'image';
  }

  if (
    normalized === '[attachment]' ||
    normalized === '[file]' ||
    normalized === 'attachment' ||
    normalized === 'file attachment'
  ) {
    return 'file';
  }

  return undefined;
}

function getThreadPreviewPayload(
  rawPreview: unknown,
  rawLastMessage: BackendMessageLike | null | undefined,
): {
  text: string;
  attachmentKind?: MessageAttachment['kind'];
} {
  const trimmedPreview = String(rawPreview ?? '').trim();
  const explicitAttachmentKind = trimmedPreview
    ? normalizeAttachmentPreviewKind(trimmedPreview)
    : undefined;
  const attachmentKind = explicitAttachmentKind
    ? explicitAttachmentKind
    : !trimmedPreview && rawLastMessage
      ? buildAttachmentsFromBackendMessage(rawLastMessage)[0]?.kind
      : undefined;

  if (attachmentKind) {
    return {
      text: describeAttachmentFallback(attachmentKind),
      attachmentKind,
    };
  }

  if (trimmedPreview) {
    return {
      text: trimmedPreview,
    };
  }

  return {
    text: 'No messages yet.',
  };
}

function extractAttachmentUrl(rawMessage: BackendMessageLike | null | undefined) {
  if (!rawMessage || typeof rawMessage !== 'object') {
    return undefined;
  }

  const attachment =
    rawMessage.public_url ??
    rawMessage.url ??
    rawMessage.attachment ??
    rawMessage.attachment_url ??
    rawMessage.file_url ??
    rawMessage.media_url;

  return typeof attachment === 'string' && attachment.trim() ? attachment : undefined;
}

function buildAttachmentIdsPayload(attachments: MessageAttachment[]) {
  return attachments.map((attachment) => normalizeBackendIdentifierValue(attachment.id));
}

function isDeletedBackendMessage(rawMessage: BackendMessageLike) {
  return (
    stringToBoolean(rawMessage.is_deleted) === true ||
    stringToBoolean(rawMessage.deleted) === true ||
    typeof rawMessage.deleted_at === 'string'
  );
}

function buildAttachmentFromBackendItem(
  rawAttachment: BackendMessageLike | null | undefined,
  fallbackId: string,
): MessageAttachment | null {
  if (!rawAttachment || typeof rawAttachment !== 'object') {
    return null;
  }

  const attachmentUrl = extractAttachmentUrl(rawAttachment);
  if (!attachmentUrl) {
    return null;
  }

  const kind = getAttachmentKind(rawAttachment.kind ?? rawAttachment.attachment_type);
  const fileName =
    String(rawAttachment.file_name ?? rawAttachment.filename ?? '').trim() ||
    attachmentUrl.split('/').pop()?.split('?')[0] ||
    describeAttachmentFallback(kind);
  const sizeInBytes =
    safeParseInt(
      rawAttachment.size_in_bytes ?? rawAttachment.attachment_size ?? rawAttachment.file_size,
    ) ?? 0;

  return {
    id: String(rawAttachment.attachment_id ?? rawAttachment.id ?? fallbackId),
    kind,
    fileName,
    mimeType:
      (typeof rawAttachment.mime_type === 'string' && rawAttachment.mime_type) ||
      guessMimeTypeFromAttachmentKind(kind),
    sizeInBytes,
    sizeLabel: formatBytes(sizeInBytes),
    durationSeconds: safeParseInt(rawAttachment.duration_seconds),
    uploadState: 'uploaded',
    url: attachmentUrl,
    waveform: kind === 'audio' ? buildAudioWaveform() : undefined,
  };
}

function buildAttachmentsFromBackendMessage(rawMessage: BackendMessageLike): MessageAttachment[] {
  const nestedAttachments = extractList(rawMessage, ['attachments']).map(
    (attachment) => attachment as BackendMessageLike | null,
  );
  if (nestedAttachments.length > 0) {
    return nestedAttachments
      .map((attachment, index) =>
        buildAttachmentFromBackendItem(
          attachment,
          `${rawMessage.id ?? rawMessage.message_id ?? 'attachment'}-${index}`,
        ),
      )
      .filter((attachment): attachment is MessageAttachment => attachment !== null);
  }

  const attachmentUrl = extractAttachmentUrl(rawMessage);
  if (!attachmentUrl) {
    return [];
  }

  const fallbackAttachment = buildAttachmentFromBackendItem(
    rawMessage,
    `${rawMessage.id ?? rawMessage.message_id ?? 'attachment'}-0`,
  );

  return fallbackAttachment ? [fallbackAttachment] : [];
}

function buildReplyPreviewFromBackendMessage(params: {
  rawMessage: BackendMessageLike;
  rawMessages: BackendMessageLike[];
  participants: MessageParticipant[];
  viewerMemberId: string;
}) {
  const replyToMessageId = String(
    params.rawMessage.reply_to_message_id ?? params.rawMessage.reply_to_id ?? '',
  ).trim();

  if (!replyToMessageId) {
    return undefined;
  }

  const referencedMessage =
    params.rawMessages.find(
      (message) => String(message.id ?? message.message_id ?? '').trim() === replyToMessageId,
    ) ?? null;

  if (!referencedMessage) {
    const fallbackPreview = String(params.rawMessage.reply_preview ?? '').trim();
    const senderMemberId = String(
      params.rawMessage.reply_sender_member_id ?? params.rawMessage.reply_sender_id ?? '',
    ).trim();

    return {
      messageId: replyToMessageId,
      senderMemberId,
      senderDisplayName: String(params.rawMessage.reply_sender_name ?? 'Original message'),
      bodyPreview: fallbackPreview || 'Message',
      attachments: [],
      isOwn: senderMemberId === params.viewerMemberId,
      isDeleted: false,
    };
  }

  const senderMemberId = String(
    referencedMessage.sender_member_id ??
      referencedMessage.sender_id ??
      referencedMessage.sender_user_id ??
      referencedMessage.user_id ??
      referencedMessage.member_id ??
      '',
  );
  const sender =
    params.participants.find((participant) => participant.memberId === senderMemberId) ?? null;
  const attachments = buildAttachmentsFromBackendMessage(referencedMessage);
  const isDeleted = isDeletedBackendMessage(referencedMessage);
  const fallbackPreview = String(params.rawMessage.reply_preview ?? '').trim();
  const bodyPreview = isDeleted
    ? 'Message removed'
    : String(referencedMessage.message ?? referencedMessage.body ?? '').trim() ||
      attachments.map((attachment) => describeAttachmentForPreview(attachment)).join(', ') ||
      fallbackPreview ||
      'Message';

  return {
    messageId: replyToMessageId,
    senderMemberId,
    senderDisplayName:
      sender?.fullName ??
      String(referencedMessage.sender_name ?? referencedMessage.sender ?? 'Member'),
    bodyPreview,
    attachments: attachments.map((attachment) => ({
      kind: attachment.kind,
      fileName: attachment.fileName,
    })),
    isOwn: senderMemberId === params.viewerMemberId,
    isDeleted,
  };
}

function buildLastMessagePreview(rawLastMessage: BackendMessageLike | null | undefined) {
  if (!rawLastMessage) {
    return 'No messages yet.';
  }

  const messageBody = String(rawLastMessage.message ?? rawLastMessage.body ?? '').trim();
  if (messageBody) {
    return messageBody;
  }

  const attachments = buildAttachmentsFromBackendMessage(rawLastMessage);
  if (attachments.length) {
    return describeAttachmentFallback(attachments[0].kind);
  }

  return 'No messages yet.';
}

function getBackendMessageSenderMemberId(rawMessage: BackendMessageLike | null | undefined) {
  if (!rawMessage) return '';

  return String(
    rawMessage.sender_member_id ??
      rawMessage.sender_id ??
      rawMessage.sender_user_id ??
      rawMessage.user_id ??
      rawMessage.member_id ??
      '',
  ).trim();
}

function buildThreadSummaryFromBackend(params: {
  rawThread: BackendThreadLike;
  viewerMemberId: string;
}): MessageThreadSummary {
  const type = normalizeThreadType(params.rawThread.type);
  const rawParticipants = extractList(params.rawThread.participants, []).map(
    (participant) => participant as BackendThreadParticipant,
  );
  const participants = rawParticipants.map((participant) =>
    buildParticipantFromBackend(participant, params.viewerMemberId),
  );
  const currentUser = getCurrentSessionUser();
  const participantsWithViewer =
    currentUser &&
    !participants.some((participant) => participant.memberId === params.viewerMemberId)
      ? [buildViewerParticipantFromSessionUser(currentUser), ...participants]
      : participants;
  const otherParticipant =
    participantsWithViewer.find((participant) => participant.memberId !== params.viewerMemberId) ??
    participantsWithViewer[0];
  const title =
    type === 'direct'
      ? otherParticipant?.fullName ||
        String(params.rawThread.title ?? params.rawThread.name ?? '').trim() ||
        'Direct conversation'
      : String(params.rawThread.title ?? params.rawThread.name ?? '').trim() ||
        'Group conversation';
  const isYearGroup = type === 'group' && isGraduationYearGroupTitle(title);
  const lastMessage = extractObject(params.rawThread.last_message, []) as BackendMessageLike | null;
  const lastMessagePreviewPayload = getThreadPreviewPayload(
    String(params.rawThread.last_message_preview ?? '').trim() ||
      buildLastMessagePreview(lastMessage),
    lastMessage,
  );
  const lastMessageSenderMemberId =
    getBackendMessageSenderMemberId(lastMessage) ||
    String(
      params.rawThread.last_message_sender_member_id ??
        params.rawThread.last_message_sender_id ??
        params.rawThread.last_message_sender_user_id ??
        '',
    ).trim();
  const lastMessageSenderName =
    String(
      params.rawThread.last_message_sender_name ??
        (typeof lastMessage?.sender_name === 'string' ? lastMessage.sender_name : '') ??
        '',
    ).trim() || undefined;
  const explicitLastMessageStatus = normalizeDeliveryStatus(
    params.rawThread.last_message_status ??
      params.rawThread.last_message_delivery_status ??
      lastMessage?.status ??
      lastMessage?.delivery_status ??
      lastMessage?.message_status,
  );
  const normalizedCurrentUserName = currentUser?.fullName?.trim().toLowerCase() ?? '';
  const normalizedLastMessageSenderName = lastMessageSenderName?.toLowerCase() ?? '';
  const isOwnBySenderId =
    Boolean(lastMessageSenderMemberId) &&
    (lastMessageSenderMemberId === params.viewerMemberId ||
      (currentUser?.id ? lastMessageSenderMemberId === currentUser.id : false));
  const isOwnBySenderName =
    Boolean(normalizedLastMessageSenderName) &&
    Boolean(normalizedCurrentUserName) &&
    normalizedLastMessageSenderName === normalizedCurrentUserName;
  const lastMessageIsOwn =
    isOwnBySenderId ||
    isOwnBySenderName ||
    (!lastMessageSenderMemberId && !lastMessageSenderName && Boolean(explicitLastMessageStatus));
  const lastMessageStatus = lastMessageIsOwn
    ? (explicitLastMessageStatus ??
      (lastMessage
        ? deriveOutgoingMessageStatus({
            rawMessage: lastMessage,
            rawMessages: [lastMessage],
            participants: participantsWithViewer,
            viewerMemberId: params.viewerMemberId,
            threadType: type,
          })
        : 'sent'))
    : undefined;

  return {
    id: String(
      params.rawThread.thread_id ?? params.rawThread.group_id ?? params.rawThread.id ?? title,
    ),
    type,
    category: normalizeThreadCategory(params.rawThread.category),
    title,
    subtitle:
      type === 'direct'
        ? (otherParticipant?.headline ?? 'alumni member')
        : `${participantsWithViewer.length} members`,
    topic:
      String(params.rawThread.description ?? params.rawThread.topic ?? '').trim() ||
      (type === 'direct' ? 'Direct conversation' : title),
    avatar:
      type === 'direct'
        ? otherParticipant?.avatar
        : typeof params.rawThread.cover_image === 'string'
          ? params.rawThread.cover_image
          : undefined,
    initials:
      type === 'direct'
        ? (otherParticipant?.initials ?? deriveInitials(title))
        : deriveInitials(title),
    unreadCount: safeParseInt(params.rawThread.unread_count) ?? 0,
    isPinned: (stringToBoolean(params.rawThread.is_pinned) ?? false) || isYearGroup,
    lastActivityAt: normalizeBackendTimestamp(
      params.rawThread.last_message_at ??
        lastMessage?.created_at ??
        params.rawThread.updated_at ??
        params.rawThread.created_at,
    ),
    lastMessagePreview: lastMessagePreviewPayload.text,
    lastMessagePreviewAttachmentKind: lastMessagePreviewPayload.attachmentKind,
    lastMessageSenderName,
    lastMessageStatus,
    lastMessageIsOwn,
    presence: undefined,
    memberCount: participantsWithViewer.length,
    participants: participantsWithViewer,
  };
}

function getMessageIndex(messages: BackendMessageLike[], messageId: string | undefined) {
  if (!messageId) {
    return -1;
  }

  return messages.findIndex(
    (message) => String(message.id ?? message.message_id ?? '') === messageId,
  );
}

function deriveOutgoingMessageStatus(params: {
  rawMessage: BackendMessageLike;
  rawMessages: BackendMessageLike[];
  participants: MessageParticipant[];
  viewerMemberId: string;
  threadType: MessageThreadSummary['type'];
}) {
  const senderMemberId = getBackendMessageSenderMemberId(params.rawMessage);

  if (senderMemberId !== params.viewerMemberId) {
    return 'seen' as const;
  }

  if (params.threadType !== 'direct') {
    return 'sent' as const;
  }

  const recipientParticipant =
    params.participants.find((participant) => participant.memberId !== params.viewerMemberId) ??
    null;
  if (!recipientParticipant) {
    return 'sent' as const;
  }

  const currentMessageId = String(params.rawMessage.id ?? params.rawMessage.message_id ?? '');
  const currentIndex = getMessageIndex(params.rawMessages, currentMessageId);
  const deliveredIndex = getMessageIndex(
    params.rawMessages,
    recipientParticipant.lastDeliveredMessageId,
  );
  const readIndex = getMessageIndex(params.rawMessages, recipientParticipant.lastReadMessageId);

  if (readIndex >= currentIndex && currentIndex >= 0) {
    return 'seen' as const;
  }

  if (deliveredIndex >= currentIndex && currentIndex >= 0) {
    return 'delivered' as const;
  }

  return 'sent' as const;
}

function buildMessageItemsFromBackend(params: {
  rawMessages: BackendMessageLike[];
  participants: MessageParticipant[];
  viewerMemberId: string;
  threadId: string;
  threadType: MessageThreadSummary['type'];
}) {
  return sortMessagesChronologically(
    params.rawMessages.map((rawMessage) => {
      const senderMemberId = getBackendMessageSenderMemberId(rawMessage);
      const sender =
        params.participants.find((participant) => participant.memberId === senderMemberId) ?? null;
      const body = String(rawMessage.message ?? rawMessage.body ?? '').trim();
      const isDeleted = isDeletedBackendMessage(rawMessage);
      const deletedAt =
        typeof rawMessage.deleted_at === 'string'
          ? normalizeBackendTimestamp(rawMessage.deleted_at)
          : undefined;

      return {
        id: String(
          rawMessage.id ?? rawMessage.message_id ?? rawMessage.created_at ?? Math.random(),
        ),
        clientGeneratedId:
          typeof rawMessage.client_generated_id === 'string'
            ? rawMessage.client_generated_id
            : undefined,
        threadId: params.threadId,
        senderMemberId,
        senderDisplayName: sender?.fullName ?? String(rawMessage.sender_name ?? 'Member'),
        senderAvatar: sender?.avatar,
        body: isDeleted ? 'Message removed' : body || '',
        createdAt: normalizeBackendTimestamp(rawMessage.created_at),
        status: deriveOutgoingMessageStatus({
          rawMessage,
          rawMessages: params.rawMessages,
          participants: params.participants,
          viewerMemberId: params.viewerMemberId,
          threadType: params.threadType,
        }),
        attachments: isDeleted ? [] : buildAttachmentsFromBackendMessage(rawMessage),
        isOwn: senderMemberId === params.viewerMemberId,
        replyTo: buildReplyPreviewFromBackendMessage({
          rawMessage,
          rawMessages: params.rawMessages,
          participants: params.participants,
          viewerMemberId: params.viewerMemberId,
        }),
        deletedAt,
      };
    }),
  );
}

async function fetchThreadsEnvelope() {
  const response = await apiClient.post(API_ENDPOINTS.MESSAGES.THREADS, {});
  console.log('[messages] listThreads response', response.data);
  return getEnvelopeData(response.data);
}

async function fetchThreadDetailEnvelope(threadId: string, limit = 50, offset = 0) {
  const response = await apiClient.post(API_ENDPOINTS.MESSAGES.THREAD_DETAIL, {
    thread_id: normalizeBackendIdentifierValue(threadId),
    limit,
    offset,
  });
  console.log('[messages] getThread response', {
    threadId,
    limit,
    offset,
    response: response.data,
  });

  return getEnvelopeData(response.data);
}

function buildThreadPayloadForDetail(rawPayload: Record<string, unknown>) {
  const nestedThread =
    (extractObject(rawPayload, ['thread']) as BackendThreadLike | null) ??
    (extractObject(rawPayload, ['group']) as BackendThreadLike | null);
  const messages = extractList(rawPayload, ['messages']).map((message) => message);
  const participants = extractList(rawPayload, ['participants']).map((participant) => participant);

  if (!nestedThread) {
    return {
      ...rawPayload,
      messages,
      participants,
    } as BackendThreadLike;
  }

  return {
    ...rawPayload,
    ...nestedThread,
    messages:
      messages.length > 0
        ? messages
        : extractList(nestedThread, ['messages']).map((message) => message),
    participants:
      participants.length > 0
        ? participants
        : extractList(nestedThread, ['participants']).map((participant) => participant),
  } as BackendThreadLike;
}

function buildThreadDetailFromBackend(params: {
  rawThreadPayload: BackendThreadLike;
  viewerMemberId: string;
}) {
  const summaryBase = buildThreadSummaryFromBackend({
    rawThread: params.rawThreadPayload,
    viewerMemberId: params.viewerMemberId,
  });
  const rawMessages = extractList(params.rawThreadPayload.messages, []).map(
    (message) => message as BackendMessageLike,
  );
  const participants = summaryBase.participants;
  const summary = {
    ...summaryBase,
    participants,
    presence: undefined,
  };

  return {
    ...summary,
    unreadCount: summary.unreadCount,
    description:
      typeof params.rawThreadPayload.description === 'string'
        ? params.rawThreadPayload.description
        : undefined,
    attachmentsEnabled: stringToBoolean(params.rawThreadPayload.attachment_enabled) ?? true,
    audioEnabled: true,
    messages: buildMessageItemsFromBackend({
      rawMessages,
      participants: summary.participants,
      viewerMemberId: params.viewerMemberId,
      threadId: summary.id,
      threadType: summary.type,
    }),
    syncToken: `backend-${nowIso()}`,
    pollingIntervalMs: MESSAGE_POLLING_INTERVAL_MS,
  } satisfies MessageThreadDetail;
}

function isDraftDirectThreadId(threadId: string) {
  return threadId.startsWith(DRAFT_DIRECT_THREAD_PREFIX);
}

function buildDirectDraftThreadId(viewerMemberId: string, participantMemberId: string) {
  return `${DRAFT_DIRECT_THREAD_PREFIX}${encodeURIComponent(viewerMemberId)}__${encodeURIComponent(
    participantMemberId,
  )}`;
}

function parseDirectDraftThreadId(threadId: string) {
  if (!isDraftDirectThreadId(threadId)) {
    return null;
  }

  const encodedParts = threadId.slice(DRAFT_DIRECT_THREAD_PREFIX.length).split('__');
  if (encodedParts.length !== 2) {
    return null;
  }

  return {
    viewerMemberId: decodeURIComponent(encodedParts[0] ?? ''),
    participantMemberId: decodeURIComponent(encodedParts[1] ?? ''),
  };
}

function buildViewerParticipant(viewerMemberId: string) {
  const currentUser = getCurrentSessionUser();

  return buildParticipantFromBackend(
    {
      user_id: viewerMemberId,
      fullname: currentUser?.fullName,
      image: currentUser?.photo,
      role: 'member',
      graduation_year: currentUser?.graduationYear,
    },
    viewerMemberId,
  );
}

function buildDirectDraftThread(params: {
  viewerMemberId: string;
  participantMemberId: string;
  topic?: string;
}) {
  const serverTime = nowIso();
  const recipientEntry = getRegisteredMessageRecipient(params.participantMemberId);
  const viewerParticipant = buildViewerParticipant(params.viewerMemberId);
  const recipientParticipant = buildParticipantFromBackend(
    {
      user_id: params.participantMemberId,
      fullname: recipientEntry?.fullName ?? `Member ${params.participantMemberId}`,
      image: recipientEntry?.avatar,
      graduation_year: recipientEntry?.graduationYear,
      role: 'member',
    },
    params.viewerMemberId,
  );
  const threadId = buildDirectDraftThreadId(params.viewerMemberId, params.participantMemberId);

  const thread: MessageThreadDetail = {
    id: threadId,
    type: 'direct',
    category: 'Community',
    title: recipientParticipant.fullName,
    subtitle: recipientParticipant.headline ?? 'alumni member',
    topic: params.topic?.trim() || 'Direct conversation',
    avatar: recipientParticipant.avatar,
    initials: recipientParticipant.initials,
    unreadCount: 0,
    isPinned: false,
    lastActivityAt: serverTime,
    lastMessagePreview: 'No messages yet.',
    presence: undefined,
    memberCount: 2,
    participants: [viewerParticipant, recipientParticipant],
    attachmentsEnabled: true,
    audioEnabled: true,
    messages: [],
    syncToken: `draft-${serverTime}`,
    pollingIntervalMs: MESSAGE_POLLING_INTERVAL_MS,
  };

  directDraftThreads.set(threadId, {
    participantMemberId: params.participantMemberId,
    topic: params.topic,
    thread,
  });

  return {
    thread,
    serverTime,
  } satisfies CreateDirectThreadResponse;
}

function getStoredDirectDraftThread(threadId: string) {
  const storedEntry = directDraftThreads.get(threadId);
  if (storedEntry) {
    return storedEntry.thread;
  }

  const parsed = parseDirectDraftThreadId(threadId);
  if (!parsed) {
    return null;
  }

  return buildDirectDraftThread({
    viewerMemberId: parsed.viewerMemberId,
    participantMemberId: parsed.participantMemberId,
  }).thread;
}

function findExistingDirectThreadSummary(
  threads: MessageThreadSummary[],
  participantMemberId: string,
) {
  return (
    threads.find(
      (thread) =>
        thread.type === 'direct' &&
        thread.participants.some((participant) => participant.memberId === participantMemberId),
    ) ?? null
  );
}

async function resolveDirectThreadIdAfterFirstSend(params: {
  viewerMemberId: string;
  participantMemberId: string;
  payload: Record<string, unknown>;
}) {
  const threadPayload = extractObject(params.payload, ['thread']) as BackendThreadLike | null;
  const candidateId = String(
    params.payload.group_id ??
      params.payload.thread_id ??
      params.payload.id ??
      threadPayload?.group_id ??
      threadPayload?.id ??
      '',
  ).trim();

  if (candidateId) {
    return candidateId;
  }

  const threadsResponse = await backendMessagesTransport.listThreads({
    viewerMemberId: params.viewerMemberId,
  });
  const directThread = findExistingDirectThreadSummary(
    threadsResponse.threads,
    params.participantMemberId,
  );

  return directThread?.id ?? '';
}

export const backendMessagesTransport: MessagesTransport = {
  async listThreads(request: ListMessageThreadsRequest): Promise<ListMessageThreadsResponse> {
    const envelope = await fetchThreadsEnvelope();
    const payload = extractObject(envelope, ['data']) ?? envelope ?? {};
    const rawThreads = extractList(payload, ['threads']).map(
      (thread) => thread as BackendThreadLike,
    );
    const threads = sortThreadsForInbox(
      rawThreads.map((rawThread) =>
        buildThreadSummaryFromBackend({
          rawThread,
          viewerMemberId: request.viewerMemberId,
        }),
      ),
    );
    const serverTime = normalizeBackendTimestamp(
      (payload as Record<string, unknown>).server_time ?? envelope?.server_time ?? nowIso(),
    );

    return {
      threads: typeof request.limit === 'number' ? threads.slice(0, request.limit) : threads,
      unreadCount:
        safeParseInt((payload as Record<string, unknown>).unread_count) ??
        threads.reduce((total, thread) => total + thread.unreadCount, 0),
      unreadThreadCount:
        safeParseInt((payload as Record<string, unknown>).unread_thread_count) ??
        threads.filter((thread) => thread.unreadCount > 0).length,
      syncToken: String((payload as Record<string, unknown>).sync_token ?? `backend-${serverTime}`),
      pollingIntervalMs:
        safeParseInt((payload as Record<string, unknown>).polling_interval_ms) ??
        MESSAGE_POLLING_INTERVAL_MS,
      serverTime,
    };
  },

  async getThread(request: GetMessageThreadRequest): Promise<GetMessageThreadResponse> {
    if (isDraftDirectThreadId(request.threadId)) {
      const thread = getStoredDirectDraftThread(request.threadId);

      if (!thread) {
        throw new Error('This direct conversation could not be restored.');
      }

      return {
        thread,
        syncToken: thread.syncToken,
        serverTime: nowIso(),
      };
    }

    const envelope = await fetchThreadDetailEnvelope(request.threadId, request.limit ?? 50, 0);
    const payload = extractObject(envelope, ['data']) ?? envelope ?? {};
    const threadPayload = buildThreadPayloadForDetail(payload);
    const thread = buildThreadDetailFromBackend({
      rawThreadPayload: threadPayload,
      viewerMemberId: request.viewerMemberId,
    });
    const serverTime = normalizeBackendTimestamp(
      (payload as Record<string, unknown>).server_time ?? envelope?.server_time ?? nowIso(),
    );

    return {
      thread,
      syncToken: thread.syncToken,
      serverTime,
    };
  },

  async uploadAttachment(
    request: UploadMessageAttachmentRequest,
  ): Promise<UploadMessageAttachmentResponse> {
    if (!request.binary) {
      throw new Error('Attachment data is missing. Please choose the file again.');
    }

    if (!request.threadId) {
      throw new Error('Choose a conversation before attaching a file.');
    }

    const isDraftDirectThread = isDraftDirectThreadId(request.threadId);
    const draftEntry = isDraftDirectThread ? directDraftThreads.get(request.threadId) : undefined;
    const parsedDraftThread = isDraftDirectThread
      ? parseDirectDraftThreadId(request.threadId)
      : null;
    const participantMemberId =
      draftEntry?.participantMemberId ?? parsedDraftThread?.participantMemberId;

    if (isDraftDirectThread && !participantMemberId) {
      throw new Error('This direct conversation could not be resolved for attachments yet.');
    }

    const formData = new FormData();
    const normalizedMimeType =
      normalizeMessageAttachmentMimeType(
        request.mimeType || request.binary.type || 'application/octet-stream',
      ) || 'application/octet-stream';
    const uploadFile = new File([request.binary], request.fileName, {
      type: normalizedMimeType,
    });

    if (isDraftDirectThread) {
      formData.append(
        'recipient_id',
        String(normalizeBackendIdentifierValue(participantMemberId!)),
      );
    } else {
      formData.append('thread_id', String(normalizeBackendIdentifierValue(request.threadId)));
    }
    formData.append('file', uploadFile);

    const response = await apiClient.post(API_ENDPOINTS.MESSAGES.ATTACHMENTS, formData);
    const envelope = getEnvelopeData(response.data) ?? {};
    const payload = extractObject(envelope, ['data']) ?? envelope ?? {};
    const attachmentPayload = payload as Record<string, unknown>;
    const attachmentId = String(
      attachmentPayload.attachment_id ?? attachmentPayload.id ?? createLocalAttachmentId(),
    );
    const kind = getAttachmentKind(attachmentPayload.kind ?? request.kind);
    const uploadedUrl =
      extractAttachmentUrl(attachmentPayload) ??
      buildAttachmentFromBackendItem(attachmentPayload, attachmentId)?.url;

    return {
      attachment: {
        id: attachmentId,
        kind,
        fileName: request.fileName,
        mimeType: request.mimeType || guessMimeTypeFromAttachmentKind(kind),
        sizeInBytes: request.sizeInBytes,
        sizeLabel: formatBytes(request.sizeInBytes),
        durationSeconds: request.durationSeconds,
        uploadState: 'uploaded',
        url: uploadedUrl,
        waveform: kind === 'audio' ? buildAudioWaveform() : undefined,
      },
      serverTime: normalizeBackendTimestamp(
        attachmentPayload.server_time ?? envelope?.server_time ?? nowIso(),
      ),
    };
  },

  async sendMessage(request: SendMessageRequest): Promise<SendMessageResponse> {
    const messageBody = request.body?.trim() ?? '';
    const bodyPayload = messageBody || undefined;
    const safeAttachments = request.attachments ?? [];

    if (!messageBody && safeAttachments.length === 0) {
      throw new Error('Write a message before sending.');
    }

    const isDraftDirectThread = isDraftDirectThreadId(request.threadId);
    const draftEntry = isDraftDirectThread ? directDraftThreads.get(request.threadId) : undefined;
    const parsedDraftThread = isDraftDirectThread
      ? parseDirectDraftThreadId(request.threadId)
      : null;
    const participantMemberId =
      draftEntry?.participantMemberId ?? parsedDraftThread?.participantMemberId;

    if (isDraftDirectThread && !participantMemberId) {
      throw new Error('This direct conversation could not be resolved for sending yet.');
    }

    const response = await apiClient.post(
      isDraftDirectThread
        ? API_ENDPOINTS.MESSAGES.DIRECT_THREAD
        : API_ENDPOINTS.MESSAGES.SEND_MESSAGE,
      isDraftDirectThread
        ? {
            recipient_id: normalizeBackendIdentifierValue(participantMemberId!),
            body: bodyPayload,
            attachment_ids: safeAttachments.length
              ? buildAttachmentIdsPayload(safeAttachments)
              : undefined,
            reply_to_message_id: request.replyToMessageId
              ? normalizeBackendIdentifierValue(request.replyToMessageId)
              : undefined,
            client_generated_id: request.clientGeneratedId,
          }
        : {
            thread_id: normalizeBackendIdentifierValue(request.threadId),
            body: bodyPayload,
            attachment_ids: safeAttachments.length
              ? buildAttachmentIdsPayload(safeAttachments)
              : undefined,
            reply_to_message_id: request.replyToMessageId
              ? normalizeBackendIdentifierValue(request.replyToMessageId)
              : undefined,
            client_generated_id: request.clientGeneratedId,
          },
    );
    const envelope = getEnvelopeData(response.data) ?? {};
    const payload = extractObject(envelope, ['data']) ?? envelope ?? {};
    const returnedThreadId = isDraftDirectThread
      ? await resolveDirectThreadIdAfterFirstSend({
          viewerMemberId: request.viewerMemberId,
          participantMemberId: participantMemberId ?? '',
          payload: payload as Record<string, unknown>,
        })
      : String(
          (payload as Record<string, unknown>).thread_id ??
            (payload as Record<string, unknown>).group_id ??
            (payload as Record<string, unknown>).id ??
            request.threadId,
        );

    if (!returnedThreadId) {
      throw new Error('The direct message was sent, but the thread could not be resolved.');
    }

    const threadResponse = await backendMessagesTransport.getThread({
      viewerMemberId: request.viewerMemberId,
      threadId: returnedThreadId,
    });

    if (isDraftDirectThread) {
      directDraftThreads.delete(request.threadId);
    }

    return {
      thread: threadResponse.thread,
      messageId: String(
        (extractObject(payload, ['message']) as Record<string, unknown> | null)?.id ??
          (payload as Record<string, unknown>).message_id ??
          nowIso(),
      ),
      serverTime: threadResponse.serverTime,
    };
  },

  async deleteMessage(request: DeleteMessageRequest): Promise<DeleteMessageResponse> {
    const response = await apiClient.post(API_ENDPOINTS.MESSAGES.DELETE_MESSAGE, {
      message_id: normalizeBackendIdentifierValue(request.messageId),
    });
    const envelope = getEnvelopeData(response.data) ?? {};
    const payload = extractObject(envelope, ['data']) ?? envelope ?? {};
    const payloadRecord = payload as Record<string, unknown>;

    return {
      threadId: request.threadId,
      messageId: String(payloadRecord.message_id ?? request.messageId),
      serverTime: normalizeBackendTimestamp(
        payloadRecord.server_time ?? payloadRecord.deleted_at ?? envelope?.server_time ?? nowIso(),
      ),
    };
  },

  async markThreadRead(
    request: MarkMessageThreadReadRequest,
  ): Promise<MarkMessageThreadReadResponse> {
    const response = await apiClient.post(API_ENDPOINTS.MESSAGES.MARK_READ, {
      thread_id: normalizeBackendIdentifierValue(request.threadId),
    });
    const envelope = getEnvelopeData(response.data) ?? {};
    const payload = extractObject(envelope, ['data']) ?? envelope ?? {};
    const payloadRecord = payload as Record<string, unknown>;

    return {
      threadId: String(
        payloadRecord.thread_id ?? payloadRecord.group_id ?? payloadRecord.id ?? request.threadId,
      ),
      unreadCount: safeParseInt(payloadRecord.unread_count) ?? 0,
      serverTime: normalizeBackendTimestamp(
        payloadRecord.server_time ?? envelope?.server_time ?? nowIso(),
      ),
    };
  },

  async createDirectThread(
    request: CreateDirectThreadRequest,
  ): Promise<CreateDirectThreadResponse> {
    const threadsResponse = await backendMessagesTransport.listThreads({
      viewerMemberId: request.viewerMemberId,
    });
    const existingThread = findExistingDirectThreadSummary(
      threadsResponse.threads,
      request.participantMemberId,
    );

    if (existingThread) {
      const threadResponse = await backendMessagesTransport.getThread({
        viewerMemberId: request.viewerMemberId,
        threadId: existingThread.id,
      });

      return {
        thread: threadResponse.thread,
        serverTime: threadResponse.serverTime,
      };
    }

    return buildDirectDraftThread(request);
  },

  async createGroupThread(request: CreateGroupThreadRequest): Promise<CreateGroupThreadResponse> {
    const response = await apiClient.post(API_ENDPOINTS.MESSAGES.GROUP_THREAD, {
      title: request.title.trim(),
      member_ids: request.memberIds.map((memberId) => normalizeBackendIdentifierValue(memberId)),
      category: request.category?.toLowerCase(),
    });
    const envelope = getEnvelopeData(response.data) ?? {};
    const payload = extractObject(envelope, ['data']) ?? envelope ?? {};
    const payloadRecord = payload as Record<string, unknown>;
    const threadId = String(
      payloadRecord.thread_id ?? payloadRecord.group_id ?? payloadRecord.id ?? '',
    ).trim();

    if (!threadId) {
      throw new Error('The group was created, but the thread could not be resolved.');
    }

    const threadResponse = await backendMessagesTransport.getThread({
      viewerMemberId: request.viewerMemberId,
      threadId,
    });

    return {
      thread: threadResponse.thread,
      serverTime: threadResponse.serverTime,
    };
  },
};
