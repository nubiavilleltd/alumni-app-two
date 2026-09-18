import {
  Check,
  CheckCheck,
  CircleAlert,
  Clock3,
  ExternalLink,
  Image,
  LoaderCircle,
  MessageSquare,
  Users,
  X,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { apiClient } from '@/lib/api/client';
import { renderIcon } from '@/shared/utils/renderIcon';
import { toast } from '@/shared/components/ui/Toast';
import {
  MESSAGE_MAX_ATTACHMENTS_PER_MESSAGE,
  describeAttachmentForPreview,
} from '../api/adapters/messages.adapter';
import { useStartDirectConversation } from '../hooks/useStartDirectConversation';
import {
  getMessageAttachmentPreviewUrl,
  registerMessageAttachmentPreview,
} from '../lib/messageAttachmentPreviewRegistry';
import type {
  MessageAttachment,
  MessageDeliveryStatus,
  MessageParticipant,
  MessageReplyPreview,
  MessageThreadDetail,
  MessageThreadSummary,
} from '../types/messages.types';
import type { DraftComposerAttachment } from './messagesPage.types';
import {
  formatAudioDuration,
  deliveryLabel,
  formatMemberCount,
  getAttachmentIcon,
  presenceClasses,
} from './messagesPage.utils';

export function ParticipantAvatar({
  participant,
  size = 'md',
}: {
  participant: MessageParticipant;
  size?: 'sm' | 'md';
}) {
  const [hasImageError, setHasImageError] = useState(false);
  const sizeClasses =
    size === 'sm' ? 'h-9 w-9 rounded-full text-xs' : 'h-11 w-11 rounded-full text-sm';

  useEffect(() => {
    setHasImageError(false);
  }, [participant.avatar]);

  return participant.avatar && !hasImageError ? (
    <img
      src={participant.avatar}
      alt={participant.fullName}
      className={`${sizeClasses} object-cover`}
      onError={() => setHasImageError(true)}
    />
  ) : (
    <div
      className={`flex ${sizeClasses} items-center justify-center bg-blue-100 font-semibold text-blue-700`}
    >
      {participant.initials}
    </div>
  );
}

export function MessageDeliveryIndicator({
  status,
  showLabel = false,
  className = '',
}: {
  status: MessageDeliveryStatus;
  showLabel?: boolean;
  className?: string;
}) {
  const isSeen = status === 'seen';
  const isDelivered = status === 'delivered';
  const icon =
    status === 'failed'
      ? CircleAlert
      : status === 'sending'
        ? Clock3
        : isSeen || isDelivered
          ? CheckCheck
          : Check;
  const colorClass =
    status === 'failed' ? 'text-red-500' : isSeen ? 'text-blue-500' : 'text-gray-400';
  const label = deliveryLabel(status);

  return (
    <span
      className={`inline-flex items-center gap-1 ${colorClass} ${className}`}
      title={label}
      aria-label={label}
    >
      {renderIcon(icon, 'h-3.5 w-3.5 flex-shrink-0')}
      {showLabel ? <span>{label}</span> : null}
    </span>
  );
}

export function GroupParticipantsModal({
  isOpen,
  onClose,
  threadTitle,
  participants,
  viewerMemberId,
}: {
  isOpen: boolean;
  onClose: () => void;
  threadTitle: string;
  participants: MessageParticipant[];
  viewerMemberId?: string;
}) {
  const navigate = useNavigate();
  const { startDirectConversation, isPending: isStartingConversation } =
    useStartDirectConversation();
  const [pendingConversationMemberId, setPendingConversationMemberId] = useState<string | null>(
    null,
  );

  useEffect(() => {
    if (!isOpen) return undefined;

    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';

    function handleEscape(event: globalThis.KeyboardEvent) {
      if (event.key === 'Escape') {
        onClose();
      }
    }

    window.addEventListener('keydown', handleEscape);

    return () => {
      document.body.style.overflow = previousOverflow;
      window.removeEventListener('keydown', handleEscape);
    };
  }, [isOpen, onClose]);

  if (!isOpen) return null;

  function handleOpenProfile(participant: MessageParticipant) {
    const profileHref = participant.profileHref || `/alumni/profiles/${participant.memberId}`;
    onClose();
    navigate(profileHref);
  }

  async function handleStartConversation(participant: MessageParticipant) {
    if (!participant.memberId || participant.memberId === viewerMemberId) {
      return;
    }

    setPendingConversationMemberId(participant.memberId);
    onClose();

    try {
      await startDirectConversation({
        participantMemberId: participant.memberId,
        recipientProfile: {
          fullName: participant.fullName,
          avatar: participant.avatar,
          photoVisibility: participant.photoVisibility,
          headline: participant.headline,
          location: participant.location,
          graduationYear: participant.graduationYear || undefined,
          slug: participant.slug,
          profileHref: participant.profileHref,
        },
      });
    } finally {
      setPendingConversationMemberId((current) =>
        current === participant.memberId ? null : current,
      );
    }
  }

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4 py-6 backdrop-blur-sm"
      onClick={onClose}
      role="dialog"
      aria-modal="true"
      aria-label={`${threadTitle} members`}
    >
      <div
        className="w-full max-w-2xl overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-2xl"
        onClick={(event) => event.stopPropagation()}
      >
        <div className="flex items-start justify-between gap-4 border-b border-gray-100 px-6 py-5">
          <div className="min-w-0">
            <p className="text-xs font-semibold uppercase tracking-wider text-blue-600">
              Group Members
            </p>
            <h3 className="mt-1.5 truncate text-xl font-semibold text-gray-900">{threadTitle}</h3>
            <p className="mt-0.5 text-sm text-gray-500">{formatMemberCount(participants.length)}</p>
          </div>

          <button
            type="button"
            onClick={onClose}
            className="inline-flex h-10 w-10 items-center justify-center rounded-full text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600"
            aria-label="Close members list"
          >
            <X className="h-5 w-5" strokeWidth={2.2} />
          </button>
        </div>

        <div className="max-h-[70vh] overflow-y-auto px-6 py-4">
          <div className="space-y-2">
            {participants.map((participant) => {
              const isViewer = participant.memberId === viewerMemberId;

              return (
                <div
                  key={participant.memberId}
                  className="flex flex-col gap-3 rounded-xl border border-gray-100 bg-gray-50 px-4 py-3 sm:flex-row sm:items-center"
                >
                  <div className="flex items-center gap-3 sm:flex-1 sm:px-2 sm:py-1.5">
                    <ParticipantAvatar participant={participant} />

                    <div className="min-w-0 flex-1">
                      <div className="flex flex-wrap items-center gap-2">
                        <button
                          type="button"
                          onClick={() => handleOpenProfile(participant)}
                          className="truncate text-left text-sm font-semibold text-gray-900 underline decoration-gray-300 underline-offset-4 transition-colors hover:text-blue-700 hover:decoration-blue-300"
                        >
                          {participant.fullName}
                        </button>
                        {isViewer ? (
                          <span className="rounded-full bg-white px-2 py-0.5 text-[11px] font-semibold text-gray-500">
                            You
                          </span>
                        ) : null}
                        {participant.roleInThread === 'admin' ? (
                          <span className="rounded-full bg-blue-50 px-2 py-0.5 text-[11px] font-semibold text-blue-600">
                            Admin
                          </span>
                        ) : participant.roleInThread === 'moderator' ? (
                          <span className="rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-semibold text-amber-700">
                            Moderator
                          </span>
                        ) : null}
                      </div>
                      <p className="mt-0.5 truncate text-sm text-gray-500">
                        {participant.headline}
                      </p>
                    </div>
                  </div>

                  {!isViewer ? (
                    <button
                      type="button"
                      onClick={() => void handleStartConversation(participant)}
                      disabled={isStartingConversation}
                      className="inline-flex w-full items-center justify-center gap-2 rounded-full bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-blue-700 disabled:cursor-not-allowed disabled:bg-blue-200 sm:w-auto"
                    >
                      {isStartingConversation &&
                      pendingConversationMemberId === participant.memberId ? (
                        <LoaderCircle className="h-4 w-4 animate-spin" strokeWidth={2.2} />
                      ) : (
                        <MessageSquare className="h-4 w-4" strokeWidth={2.2} />
                      )}
                      {isStartingConversation &&
                      pendingConversationMemberId === participant.memberId
                        ? 'Opening...'
                        : 'Message'}
                    </button>
                  ) : null}
                </div>
              );
            })}
          </div>
        </div>
      </div>
    </div>
  );
}

export function ThreadAvatar({
  thread,
  size = 'md',
}: {
  thread: MessageThreadSummary | MessageThreadDetail;
  size?: 'sm' | 'md';
}) {
  const [hasImageError, setHasImageError] = useState(false);
  const sizeClasses = size === 'sm' ? 'h-11 w-11 rounded-full' : 'h-12 w-12 rounded-full';

  useEffect(() => {
    setHasImageError(false);
  }, [thread.avatar]);

  if (thread.type === 'group') {
    return (
      <div
        className={`flex ${sizeClasses} flex-shrink-0 items-center justify-center bg-blue-100 text-blue-600`}
      >
        <Users className="h-6 w-6" strokeWidth={2.2} />
      </div>
    );
  }

  return (
    <div className="relative flex-shrink-0">
      {thread.avatar && !hasImageError ? (
        <img
          src={thread.avatar}
          alt={thread.title}
          className={`${sizeClasses} object-cover`}
          onError={() => setHasImageError(true)}
        />
      ) : (
        <div
          className={`flex ${sizeClasses} items-center justify-center bg-blue-100 text-sm font-semibold text-blue-700`}
        >
          {thread.initials}
        </div>
      )}
      {thread.presence ? (
        <span
          className={`absolute -bottom-0.5 -right-0.5 h-3 w-3 rounded-full border-2 border-white ${presenceClasses(
            thread.presence,
          )}`}
        />
      ) : null}
    </div>
  );
}

export function ReplyPreviewCard({
  replyTo,
  variant,
  onClear,
  onOpenOriginal,
}: {
  replyTo: MessageReplyPreview;
  variant: 'composer' | 'bubble';
  onClear?: () => void;
  onOpenOriginal?: (messageId: string) => void;
}) {
  const isComposer = variant === 'composer';
  const attachmentSummary =
    replyTo.attachments.length > 0
      ? replyTo.attachments.length === 1
        ? describeAttachmentForPreview({
            id: replyTo.messageId,
            kind: replyTo.attachments[0].kind,
            fileName: replyTo.attachments[0].fileName,
            mimeType: '',
            sizeInBytes: 0,
            sizeLabel: '',
            uploadState: 'uploaded',
          })
        : `${replyTo.attachments.length} attachments`
      : null;

  return (
    <div
      className={`mb-3 rounded-xl border-l-4 px-3 py-2.5 ${
        isComposer
          ? 'border-blue-500 bg-blue-50 text-gray-800'
          : 'border-blue-300 bg-black/10 text-inherit'
      }`}
    >
      <div className="flex items-start justify-between gap-3">
        {onOpenOriginal ? (
          <button
            type="button"
            onClick={() => onOpenOriginal(replyTo.messageId)}
            className="min-w-0 flex-1 text-left"
          >
            <p
              className={`truncate text-xs font-semibold ${
                isComposer ? 'text-blue-700' : 'text-current/80'
              }`}
            >
              {replyTo.senderDisplayName}
            </p>
            <p
              className={`mt-0.5 line-clamp-2 text-sm ${
                isComposer ? 'text-gray-600' : 'text-current/85'
              }`}
            >
              {replyTo.bodyPreview}
            </p>
            {attachmentSummary ? (
              <p className={`mt-0.5 text-xs ${isComposer ? 'text-gray-500' : 'text-current/70'}`}>
                {attachmentSummary}
              </p>
            ) : null}
          </button>
        ) : (
          <div className="min-w-0">
            <p
              className={`truncate text-xs font-semibold ${
                isComposer ? 'text-blue-700' : 'text-current/80'
              }`}
            >
              {replyTo.senderDisplayName}
            </p>
            <p
              className={`mt-0.5 line-clamp-2 text-sm ${
                isComposer ? 'text-gray-600' : 'text-current/85'
              }`}
            >
              {replyTo.bodyPreview}
            </p>
            {attachmentSummary ? (
              <p className={`mt-0.5 text-xs ${isComposer ? 'text-gray-500' : 'text-current/70'}`}>
                {attachmentSummary}
              </p>
            ) : null}
          </div>
        )}

        {onClear ? (
          <button
            type="button"
            onClick={onClear}
            className="inline-flex h-7 w-7 items-center justify-center rounded-full text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-700"
            aria-label="Clear reply target"
          >
            <X className="h-3.5 w-3.5" strokeWidth={2.2} />
          </button>
        ) : null}
      </div>
    </div>
  );
}

function MessageImageGrid({
  images,
  onOpenImage,
}: {
  images: MessageAttachment[];
  onOpenImage: (attachment: MessageAttachment, allImages: MessageAttachment[]) => void;
}) {
  const MAX_VISIBLE = 4;
  const visible = images.slice(0, MAX_VISIBLE);
  const overflow = images.length - MAX_VISIBLE;
  const count = visible.length;
  const gridClass = count === 1 ? 'grid-cols-1' : 'grid-cols-2';

  return (
    <div className={`mt-2.5 grid gap-0.5 overflow-hidden rounded-xl ${gridClass}`}>
      {visible.map((attachment, index) => {
        const previewUrl = attachment.url ?? getMessageAttachmentPreviewUrl(attachment.id);
        const isLast = index === MAX_VISIBLE - 1;
        const showOverlay = isLast && overflow > 0;
        const spanFull = count === 3 && index === 0;

        return (
          <button
            key={attachment.id}
            type="button"
            onClick={() => onOpenImage(attachment, images)}
            className={`relative overflow-hidden ${spanFull ? 'col-span-2' : ''}`}
            style={{ aspectRatio: spanFull ? '16/8' : '1' }}
          >
            {previewUrl ? (
              <img
                src={previewUrl}
                alt={attachment.fileName}
                className="h-full w-full object-cover"
              />
            ) : (
              <div className="flex h-full w-full items-center justify-center bg-gray-200">
                <Image className="h-6 w-6 text-gray-400" strokeWidth={2.2} />
              </div>
            )}

            {showOverlay ? (
              <div className="absolute inset-0 flex items-center justify-center bg-black/55">
                <span className="text-xl font-medium text-white">+{overflow}</span>
              </div>
            ) : null}
          </button>
        );
      })}
    </div>
  );
}

function usePrivateAttachmentPreviews(attachments: MessageAttachment[]) {
  const [revision, setRevision] = useState(0);
  const previewKey = attachments
    .map((attachment) => `${attachment.id}:${attachment.downloadPath ?? ''}`)
    .join('|');

  useEffect(() => {
    let mounted = true;
    const protectedAttachments = attachments.filter(
      (attachment) =>
        attachment.downloadPath && !attachment.url && !getMessageAttachmentPreviewUrl(attachment.id),
    );
    if (protectedAttachments.length === 0) return undefined;

    void Promise.all(
      protectedAttachments.map(async (attachment) => {
        try {
          const response = await apiClient.get<Blob>(attachment.downloadPath!, {
            responseType: 'blob',
          });
          registerMessageAttachmentPreview(attachment.id, URL.createObjectURL(response.data));
        } catch {
          // Keep the attachment tile visible; the existing empty-preview state
          // is safer than exposing a private URL without an authenticated fetch.
        }
      }),
    ).finally(() => {
      if (mounted) setRevision((current) => current + 1);
    });
    return () => {
      mounted = false;
    };
  }, [attachments, previewKey]);

  return revision;
}

export function MessageAttachments({
  attachments,
  isOwn,
  onOpenImage,
}: {
  attachments: MessageAttachment[];
  isOwn: boolean;
  onOpenImage: (attachment: MessageAttachment) => void;
}) {
  usePrivateAttachmentPreviews(attachments);
  const images = attachments.filter(
    (attachment) =>
      attachment.kind === 'image' &&
      (attachment.url ?? getMessageAttachmentPreviewUrl(attachment.id)),
  );
  const nonImages = attachments.filter((attachment) => attachment.kind !== 'image');

  return (
    <div className="mt-2.5 space-y-2">
      {images.length > 0 ? <MessageImageGrid images={images} onOpenImage={onOpenImage} /> : null}

      {nonImages.map((attachment) => {
        const previewUrl = attachment.url ?? getMessageAttachmentPreviewUrl(attachment.id);

        return (
          <div
            key={attachment.id}
            className={`rounded-xl border px-3 py-2.5 ${
              isOwn
                ? 'border-blue-200/60 bg-white/20 text-gray-900'
                : 'border-gray-200 bg-white text-gray-800'
            }`}
          >
            <div className="flex items-center gap-2.5">
              <div
                className={`flex h-9 w-9 items-center justify-center rounded-xl ${
                  isOwn ? 'bg-white/30' : 'bg-blue-50 text-blue-600'
                }`}
              >
                {renderIcon(getAttachmentIcon(attachment.kind), 'h-4.5 w-4.5')}
              </div>

              <div className="min-w-0 flex-1">
                <p className="truncate text-sm font-medium">{attachment.fileName}</p>
                <p className={`text-xs ${isOwn ? 'text-gray-600' : 'text-gray-500'}`}>
                  {attachment.kind === 'audio' && attachment.durationSeconds
                    ? `${formatAudioDuration(attachment.durationSeconds)} • ${attachment.sizeLabel}`
                    : attachment.sizeLabel}
                </p>
              </div>
            </div>

            {attachment.kind === 'audio' && attachment.waveform ? (
              <div className="mt-2.5 flex h-8 items-end gap-0.5">
                {attachment.waveform.map((barHeight, index) => (
                  <span
                    key={`${attachment.id}-${index}`}
                    className={`block w-1 rounded-full ${isOwn ? 'bg-blue-400' : 'bg-blue-200'}`}
                    style={{
                      height: `${Math.max(8, Math.round(barHeight * 0.4))}px`,
                    }}
                  />
                ))}
              </div>
            ) : null}

            {attachment.kind === 'audio' ? (
              previewUrl ? (
                <audio controls preload="metadata" src={previewUrl} className="mt-2.5 w-full" />
              ) : (
                <p className={`mt-2.5 text-xs ${isOwn ? 'text-gray-500' : 'text-gray-400'}`}>
                  Audio playback is unavailable for this message right now.
                </p>
              )
            ) : null}

            {attachment.kind === 'file' && previewUrl ? (
              <div className="mt-2.5">
                <a
                  href={previewUrl}
                  target="_blank"
                  rel="noopener noreferrer"
                  className={`inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold transition-colors ${
                    isOwn
                      ? 'bg-white/20 text-gray-700 hover:bg-white/30'
                      : 'bg-blue-50 text-blue-700 hover:bg-blue-100'
                  }`}
                >
                  <ExternalLink className="h-3.5 w-3.5" strokeWidth={2.2} />
                  Open file
                </a>
              </div>
            ) : null}
          </div>
        );
      })}
    </div>
  );
}

export function DraftComposerAttachments({
  attachments,
  onRemove,
}: {
  attachments: DraftComposerAttachment[];
  onRemove: (attachmentId: string) => void;
}) {
  if (attachments.length === 0) return null;

  return (
    <div className="mb-3">
      {attachments.length >= MESSAGE_MAX_ATTACHMENTS_PER_MESSAGE ? (
        <p className="mb-1.5 text-[11px] text-gray-400">
          {attachments.length} / {MESSAGE_MAX_ATTACHMENTS_PER_MESSAGE} attachments
        </p>
      ) : null}

      <div className="flex gap-2 overflow-x-auto pb-1 [scrollbar-width:thin]">
        {attachments.map((attachment) => (
          <div
            key={attachment.id}
            className="relative h-[72px] w-[72px] flex-shrink-0 overflow-hidden rounded-xl border border-gray-200 bg-gray-100"
          >
            {attachment.kind === 'image' && attachment.previewUrl ? (
              <img
                src={attachment.previewUrl}
                alt={attachment.fileName}
                className="h-full w-full object-cover"
              />
            ) : (
              <div className="flex h-full w-full flex-col items-center justify-center gap-1 px-1.5">
                {renderIcon(
                  getAttachmentIcon(attachment.kind),
                  'h-5 w-5 flex-shrink-0 text-gray-400',
                )}
                <p className="line-clamp-2 w-full text-center text-[10px] leading-tight text-gray-500">
                  {attachment.fileName}
                </p>
              </div>
            )}

            {attachment.kind === 'audio' && attachment.durationSeconds ? (
              <span className="absolute bottom-1 left-1 rounded bg-white/90 px-1 py-px text-[9px] font-medium leading-tight text-gray-600">
                {formatAudioDuration(attachment.durationSeconds)}
              </span>
            ) : null}

            <button
              type="button"
              onClick={() => onRemove(attachment.id)}
              className="absolute right-1 top-1 inline-flex h-[18px] w-[18px] items-center justify-center rounded-full bg-black/55 text-white transition-colors hover:bg-black/75"
              aria-label={`Remove ${attachment.fileName}`}
            >
              <X className="h-2.5 w-2.5" strokeWidth={2.2} />
            </button>
          </div>
        ))}
      </div>
    </div>
  );
}

export function ImageAttachmentLightbox({
  attachment,
  onClose,
}: {
  attachment: MessageAttachment | null;
  onClose: () => void;
}) {
  const previewUrl = attachment
    ? (attachment.url ?? getMessageAttachmentPreviewUrl(attachment.id))
    : undefined;

  useEffect(() => {
    if (!attachment) return undefined;

    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';

    function handleEscape(event: globalThis.KeyboardEvent) {
      if (event.key === 'Escape') onClose();
    }

    window.addEventListener('keydown', handleEscape);

    return () => {
      document.body.style.overflow = previousOverflow;
      window.removeEventListener('keydown', handleEscape);
    };
  }, [attachment, onClose]);

  if (!attachment || !previewUrl) return null;

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center bg-black/85 px-4 py-6 backdrop-blur-sm"
      onClick={onClose}
      role="dialog"
      aria-modal="true"
      aria-label={attachment.fileName}
    >
      <button
        type="button"
        onClick={onClose}
        className="absolute right-5 top-5 inline-flex h-10 w-10 items-center justify-center rounded-full bg-white/10 text-white transition-colors hover:bg-white/20"
        aria-label="Close image preview"
      >
        <X className="h-5 w-5" strokeWidth={2.2} />
      </button>

      <div
        className="w-full max-w-5xl overflow-hidden rounded-2xl border border-white/10 bg-gray-900/90 shadow-2xl"
        onClick={(event) => event.stopPropagation()}
      >
        <div className="flex items-center justify-between gap-4 border-b border-white/10 px-5 py-4 text-white">
          <div className="min-w-0">
            <p className="truncate text-sm font-semibold">{attachment.fileName}</p>
            <p className="text-xs text-white/60">{attachment.sizeLabel}</p>
          </div>

          <button
            type="button"
            onClick={() => {
              const nextPreviewUrl =
                attachment.url ?? getMessageAttachmentPreviewUrl(attachment.id);
              if (!nextPreviewUrl) {
                toast.info('This preview is not available right now.');
                return;
              }

              window.open(nextPreviewUrl, '_blank', 'noopener,noreferrer');
            }}
            className="inline-flex items-center gap-1.5 rounded-full border border-white/15 px-3 py-1.5 text-xs font-semibold text-white/80 transition-colors hover:border-white/30 hover:text-white"
          >
            <ExternalLink className="h-3.5 w-3.5" strokeWidth={2.2} />
            Open
          </button>
        </div>

        <div className="flex max-h-[80vh] items-center justify-center bg-black/50 p-4 sm:p-6">
          <img
            src={previewUrl}
            alt={attachment.fileName}
            className="max-h-[72vh] w-auto max-w-full rounded-xl object-contain"
          />
        </div>
      </div>
    </div>
  );
}
