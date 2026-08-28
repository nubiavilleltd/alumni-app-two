import {
  ArrowLeft,
  Copy,
  Ellipsis,
  LoaderCircle,
  MessageSquare,
  MessageSquareWarning,
  MessageSquareX,
  MessagesSquare,
  Mic,
  MicOff,
  Paperclip,
  RefreshCw,
  Reply,
  Search,
  SendHorizontal,
  Trash2,
} from 'lucide-react';
import { useQueryClient } from '@tanstack/react-query';
import {
  Fragment,
  useDeferredValue,
  useEffect,
  useMemo,
  useRef,
  useState,
  type CSSProperties,
  type ChangeEvent,
  type DragEvent as ReactDragEvent,
  type KeyboardEvent,
  type PointerEvent as ReactPointerEvent,
} from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { SEO } from '@/shared/common/SEO';
import { Breadcrumbs } from '@/shared/components/ui/Breadcrumbs';
import EmptyState from '@/shared/components/ui/EmptyState';
import { toast } from '@/shared/components/ui/Toast';
import {
  MESSAGE_ATTACHMENT_FILE_INPUT_ACCEPT,
  buildRecordedVoiceNoteUploadRequest,
  buildSendMessageRequest,
  buildVoiceNoteUploadRequest,
  filterMessageThreads,
  isGraduationYearGroupThread,
  MESSAGE_MAX_BODY_LENGTH,
} from '../api/adapters/messages.adapter';
import { useDraftComposerAttachments } from '../hooks/useDraftComposerAttachments';
import { usePullToRefresh } from '../hooks/usePullToRefresh';
import {
  messageKeys,
  useCreateDirectMessageThread,
  useDeleteMessage,
  useMarkMessageThreadRead,
  useMessageThread,
  useMessagesInbox,
  useSendMessage,
  useUploadMessageAttachment,
} from '../hooks/useMessages';
import { registerMessageAttachmentPreview } from '../lib/messageAttachmentPreviewRegistry';
import {
  recordMarketplaceDraftPrefill,
  shouldPrefillMarketplaceDraft,
} from '../lib/marketplaceDraftPrefillStorage';
import type {
  MessageAttachment,
  MessageItem,
  MessageReplyPreview,
  MessageThreadFilter,
  MessageThreadSummary,
} from '../types/messages.types';
import { useIdentityStore } from '@/features/authentication/stores/useIdentityStore';
import {
  DraftComposerAttachments,
  GroupParticipantsModal,
  ImageAttachmentLightbox,
  MessageDeliveryIndicator,
  MessageAttachments,
  ReplyPreviewCard,
  ThreadAvatar,
} from './MessagesPage.components';
import type { OpenMessageActionsMenu, ReplaceMessagesSearchOptions } from './messagesPage.types';
import {
  buildCopyTextFromMessage,
  buildOptimisticAttachmentsFromDraftAttachments,
  buildOptimisticMessage,
  buildReplyPreviewFromMessage,
  createClientGeneratedMessageId,
  formatConversationDay,
  formatMemberCount,
  formatMessageTime,
  formatRecordingDuration,
  getAttachmentIcon,
  formatThreadHeaderSubtitle,
  formatThreadTimestamp,
  getPreferredRecorderMimeType,
  getThreadPreviewParts,
  mergeThreadMessagesWithOptimistic,
  sortGroupParticipants,
} from './messagesPage.utils';
import { renderIcon } from '@/shared/utils/renderIcon';

const breadcrumbItems = [{ label: 'Home', href: '/' }, { label: 'Messages' }];
const MIN_VOICE_NOTE_DURATION_MS = 600;
const RECORDING_TIMER_INTERVAL_MS = 200;

// Only All and Unread filters, matching Figma design
const inboxFilters: { key: MessageThreadFilter; label: string }[] = [
  { key: 'all', label: 'All' },
  { key: 'unread', label: 'Unread' },
];

export function MessagesPage() {
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const queryClient = useQueryClient();
  const currentUser = useIdentityStore((state) => state.user);
  const viewerMemberId = currentUser?.memberId ?? '';
  const [selectedThreadId, setSelectedThreadId] = useState<string | null>(null);
  const [filter, setFilter] = useState<MessageThreadFilter>('all');
  const [query, setQuery] = useState('');
  const [draftMessage, setDraftMessage] = useState('');
  const [replyTarget, setReplyTarget] = useState<MessageReplyPreview | null>(null);
  const [openMessageActions, setOpenMessageActions] = useState<OpenMessageActionsMenu | null>(null);
  const [participantsModalOpen, setParticipantsModalOpen] = useState(false);
  const [activeImageAttachment, setActiveImageAttachment] = useState<MessageAttachment | null>(
    null,
  );
  const [highlightedMessageId, setHighlightedMessageId] = useState<string | null>(null);
  const [isAttachmentDropActive, setIsAttachmentDropActive] = useState(false);
  const [voiceRecordingState, setVoiceRecordingState] = useState<
    'idle' | 'starting' | 'recording' | 'finishing'
  >('idle');
  const [voiceRecordingDurationMs, setVoiceRecordingDurationMs] = useState(0);
  const [optimisticMessagesByThreadId, setOptimisticMessagesByThreadId] = useState<
    Record<string, MessageItem[]>
  >({});
  const [persistedUnreadDividerMessageId, setPersistedUnreadDividerMessageId] = useState<
    string | null
  >(null);
  const fileInputRef = useRef<HTMLInputElement | null>(null);
  const voiceRecordButtonRef = useRef<HTMLButtonElement | null>(null);
  const messagePaneRef = useRef<HTMLDivElement | null>(null);
  const lastOpenedThreadIdRef = useRef<string | null>(null);
  const unreadDividerThreadIdRef = useRef<string | null>(null);
  const previousRequestedThreadIdRef = useRef<string | null>(null);
  const pendingDirectThreadIntentRef = useRef<string | null>(null);
  const pendingInitialMessageIntentRef = useRef<string | null>(null);
  const activeVoicePointerIdRef = useRef<number | null>(null);
  const mediaRecorderRef = useRef<MediaRecorder | null>(null);
  const mediaStreamRef = useRef<MediaStream | null>(null);
  const recordingChunksRef = useRef<Blob[]>([]);
  const recordingContextRef = useRef<{
    viewerMemberId: string;
    threadId: string;
  } | null>(null);
  const recordingStartedAtRef = useRef<number | null>(null);
  const stopVoiceRecordingAfterStartRef = useRef(false);
  const hasShownRecordingFallbackToastRef = useRef(false);
  const voiceRecordingModeRef = useRef<'live' | 'simulated' | null>(null);
  const sendInFlightRef = useRef(false);
  const deferredQuery = useDeferredValue(query);
  const requestedThreadId = searchParams.get('threadId');
  const requestedRecipientId = searchParams.get('recipient');
  const requestedTopic = searchParams.get('topic') ?? undefined;
  const requestedInitialMessage = searchParams.get('initialMessage')?.trim() ?? undefined;
  const requestedDraftMessage = searchParams.get('draftMessage')?.trim() ?? undefined;
  const requestedMarketplaceBusinessId =
    searchParams.get('marketplaceBusinessId')?.trim() ?? undefined;

  const inboxQuery = useMessagesInbox();
  const createDirectThread = useCreateDirectMessageThread();
  const deleteMessage = useDeleteMessage();
  const sendMessage = useSendMessage();
  const uploadAttachment = useUploadMessageAttachment();
  const markThreadRead = useMarkMessageThreadRead();
  const inboxThreads = inboxQuery.data?.threads ?? [];
  const {
    draftAttachments,
    addFilesToDraft,
    clearDraftAttachments,
    removeDraftAttachment,
    restoreDraftAttachments,
    stageDraftVoiceNote,
  } = useDraftComposerAttachments();

  const visibleThreads = useMemo(
    () => filterMessageThreads(inboxThreads, filter, deferredQuery, selectedThreadId),
    [deferredQuery, filter, inboxThreads, selectedThreadId],
  );

  useEffect(() => {
    const previousRequestedThreadId = previousRequestedThreadIdRef.current;
    previousRequestedThreadIdRef.current = requestedThreadId;

    if (requestedThreadId) {
      setSelectedThreadId((current) =>
        current === requestedThreadId ? current : requestedThreadId,
      );
      return;
    }

    if (previousRequestedThreadId) {
      setSelectedThreadId(null);
      return;
    }

    if (!inboxThreads.length) {
      setSelectedThreadId(null);
      return;
    }

    if (selectedThreadId && !inboxThreads.some((thread) => thread.id === selectedThreadId)) {
      setSelectedThreadId(null);
    }
  }, [inboxThreads, requestedThreadId, selectedThreadId]);

  const activeThreadSummary = selectedThreadId
    ? (inboxThreads.find((thread) => thread.id === selectedThreadId) ?? null)
    : null;
  const resolvedThreadSummary = activeThreadSummary;
  const activeThreadId = requestedThreadId ?? selectedThreadId ?? null;
  const threadQuery = useMessageThread(activeThreadId);
  const activeThread = threadQuery.data ?? null;
  const activeOptimisticMessages = activeThreadId
    ? (optimisticMessagesByThreadId[activeThreadId] ?? [])
    : [];
  const activeThreadWithOptimisticMessages =
    activeThread && activeOptimisticMessages.length > 0
      ? {
          ...activeThread,
          messages: mergeThreadMessagesWithOptimistic(
            activeThread.messages,
            activeOptimisticMessages,
          ),
        }
      : activeThread;
  const unreadDividerMessageId = useMemo(() => {
    if (!activeThreadWithOptimisticMessages?.messages.length) return null;

    const unreadCount =
      activeThreadSummary?.unreadCount ??
      activeThread?.unreadCount ??
      resolvedThreadSummary?.unreadCount ??
      0;

    if (unreadCount <= 0) return null;

    const incomingMessages = activeThreadWithOptimisticMessages.messages.filter(
      (message) => !message.isOwn,
    );

    const unreadIncomingMessages = incomingMessages.slice(-unreadCount);

    return unreadIncomingMessages[0]?.id ?? null;
  }, [
    activeThread?.unreadCount,
    activeThreadSummary?.unreadCount,
    activeThreadWithOptimisticMessages?.messages,
    resolvedThreadSummary?.unreadCount,
  ]);
  const visibleUnreadDividerMessageId =
    unreadDividerMessageId ??
    (unreadDividerThreadIdRef.current === activeThreadId ? persistedUnreadDividerMessageId : null);
  const threadShell = activeThreadWithOptimisticMessages ?? resolvedThreadSummary;
  const unreadThreadCount = inboxQuery.data?.unreadThreadCount ?? 0;
  const groupParticipants = useMemo(
    () =>
      threadShell?.type === 'group' ? sortGroupParticipants(threadShell.participants ?? []) : [],
    [threadShell],
  );
  const openMessageActionsMessage = useMemo(
    () =>
      openMessageActions
        ? (activeThreadWithOptimisticMessages?.messages.find(
            (message) => message.id === openMessageActions.messageId,
          ) ?? null)
        : null,
    [activeThreadWithOptimisticMessages?.messages, openMessageActions],
  );
  const canCopyOpenMessage =
    !!openMessageActionsMessage && !openMessageActionsMessage.deletedAt
      ? openMessageActionsMessage.body.trim().length > 0
      : false;

  function replaceMessagesSearch(nextThreadId?: string, options?: ReplaceMessagesSearchOptions) {
    const nextSearch = new URLSearchParams();

    if (nextThreadId) {
      nextSearch.set('threadId', nextThreadId);
    }

    if (options?.initialMessage?.trim()) {
      nextSearch.set('initialMessage', options.initialMessage.trim());
    }

    if (options?.draftMessage?.trim()) {
      nextSearch.set('draftMessage', options.draftMessage.trim());
    }

    if (options?.marketplaceBusinessId?.trim()) {
      nextSearch.set('marketplaceBusinessId', options.marketplaceBusinessId.trim());
    }

    navigate(
      {
        pathname: '/messages',
        search: nextSearch.toString() ? `?${nextSearch.toString()}` : '',
      },
      { replace: true },
    );
  }

  async function refreshAll() {
    await Promise.all([
      inboxQuery.refetch(),
      activeThreadId ? threadQuery.refetch() : Promise.resolve(),
    ]);
  }

  function discardDraftComposer(options?: {
    preservePreviewUrls?: Set<string>;
    preserveUploadedAttachmentIds?: Set<string>;
  }) {
    setDraftMessage('');
    setReplyTarget(null);
    clearDraftAttachments(options);
  }

  function addOptimisticMessage(threadId: string, message: MessageItem) {
    setOptimisticMessagesByThreadId((previous) => ({
      ...previous,
      [threadId]: [...(previous[threadId] ?? []), message],
    }));
  }

  function removeOptimisticMessage(threadId: string, messageId: string) {
    setOptimisticMessagesByThreadId((previous) => {
      const existingMessages = previous[threadId] ?? [];
      const nextMessages = existingMessages.filter((message) => message.id !== messageId);

      if (nextMessages.length === existingMessages.length) {
        return previous;
      }

      if (nextMessages.length === 0) {
        const { [threadId]: _removed, ...rest } = previous;
        return rest;
      }

      return {
        ...previous,
        [threadId]: nextMessages,
      };
    });
  }

  function handleJumpToMessage(messageId: string) {
    const messageElement = messagePaneRef.current?.querySelector<HTMLElement>(
      `[data-message-id="${CSS.escape(messageId)}"]`,
    );

    if (!messageElement) {
      toast.info('We could not find that original message in this view yet.');
      return;
    }

    messageElement.scrollIntoView({
      behavior: 'smooth',
      block: 'center',
    });
    setHighlightedMessageId(messageId);
  }

  function resetVoiceRecordingSession(shouldResetTimer = true) {
    if (mediaStreamRef.current) {
      mediaStreamRef.current.getTracks().forEach((track) => track.stop());
    }

    if (mediaRecorderRef.current) {
      mediaRecorderRef.current.ondataavailable = null;
      mediaRecorderRef.current.onstop = null;
    }

    mediaRecorderRef.current = null;
    mediaStreamRef.current = null;
    recordingChunksRef.current = [];
    recordingContextRef.current = null;
    recordingStartedAtRef.current = null;
    stopVoiceRecordingAfterStartRef.current = false;
    voiceRecordingModeRef.current = null;
    activeVoicePointerIdRef.current = null;
    if (shouldResetTimer) {
      setVoiceRecordingDurationMs(0);
    }
  }

  async function finalizeSimulatedVoiceRecording() {
    const context = recordingContextRef.current;
    const startedAt = recordingStartedAtRef.current;
    const durationMs = startedAt ? Date.now() - startedAt : 0;

    if (!context) {
      resetVoiceRecordingSession();
      setVoiceRecordingState('idle');
      return;
    }

    if (durationMs < MIN_VOICE_NOTE_DURATION_MS) {
      resetVoiceRecordingSession();
      setVoiceRecordingState('idle');
      toast.info('Hold a little longer before releasing to send a voice note.');
      return;
    }

    setVoiceRecordingState('finishing');

    try {
      const attachmentRequest = buildVoiceNoteUploadRequest(
        context.viewerMemberId,
        Math.max(1, Math.round(durationMs / 1000)),
      );
      const previewUrl = attachmentRequest.binary
        ? URL.createObjectURL(attachmentRequest.binary)
        : undefined;

      stageDraftVoiceNote(attachmentRequest, previewUrl);
    } finally {
      resetVoiceRecordingSession();
      setVoiceRecordingState('idle');
    }
  }

  async function finalizeLiveVoiceRecording() {
    const context = recordingContextRef.current;
    const startedAt = recordingStartedAtRef.current;
    const durationMs = startedAt ? Date.now() - startedAt : 0;
    const mimeType =
      mediaRecorderRef.current?.mimeType || recordingChunksRef.current[0]?.type || 'audio/webm';
    const blob = new Blob(recordingChunksRef.current, { type: mimeType });

    if (!context) {
      resetVoiceRecordingSession();
      setVoiceRecordingState('idle');
      return;
    }

    if (durationMs < MIN_VOICE_NOTE_DURATION_MS || blob.size === 0) {
      resetVoiceRecordingSession();
      setVoiceRecordingState('idle');
      toast.info('Hold a little longer before releasing to send a voice note.');
      return;
    }

    try {
      const attachmentRequest = await buildRecordedVoiceNoteUploadRequest({
        viewerMemberId: context.viewerMemberId,
        blob,
        durationSeconds: Math.max(1, Math.round(durationMs / 1000)),
      });

      stageDraftVoiceNote(attachmentRequest, URL.createObjectURL(blob));
    } finally {
      resetVoiceRecordingSession();
      setVoiceRecordingState('idle');
    }
  }

  async function startVoiceRecording() {
    if (!viewerMemberId || !activeThread || voiceRecordingState !== 'idle') return;

    recordingContextRef.current = {
      viewerMemberId,
      threadId: activeThread.id,
    };
    recordingChunksRef.current = [];
    stopVoiceRecordingAfterStartRef.current = false;
    setVoiceRecordingDurationMs(0);

    if (
      typeof navigator === 'undefined' ||
      !navigator.mediaDevices?.getUserMedia ||
      typeof MediaRecorder === 'undefined'
    ) {
      voiceRecordingModeRef.current = 'simulated';
      recordingStartedAtRef.current = Date.now();
      setVoiceRecordingState('recording');

      if (!hasShownRecordingFallbackToastRef.current) {
        toast.info(
          'Live microphone capture is unavailable here, so a placeholder voice note will be generated from your hold duration.',
        );
        hasShownRecordingFallbackToastRef.current = true;
      }

      return;
    }

    setVoiceRecordingState('starting');

    try {
      const stream = await navigator.mediaDevices.getUserMedia({ audio: true });

      if (stopVoiceRecordingAfterStartRef.current) {
        stream.getTracks().forEach((track) => track.stop());
        resetVoiceRecordingSession();
        setVoiceRecordingState('idle');
        return;
      }

      const mimeType = getPreferredRecorderMimeType();
      const recorder = mimeType
        ? new MediaRecorder(stream, { mimeType })
        : new MediaRecorder(stream);

      mediaStreamRef.current = stream;
      mediaRecorderRef.current = recorder;
      recordingStartedAtRef.current = Date.now();
      voiceRecordingModeRef.current = 'live';

      recorder.ondataavailable = (event) => {
        if (event.data.size > 0) {
          recordingChunksRef.current.push(event.data);
        }
      };

      recorder.onstop = () => {
        void finalizeLiveVoiceRecording();
      };

      recorder.start();
      setVoiceRecordingState('recording');
    } catch {
      resetVoiceRecordingSession();
      setVoiceRecordingState('idle');
      toast.error('Microphone access is required to record voice notes.');
    }
  }

  function stopVoiceRecording() {
    if (voiceRecordingState === 'starting') {
      stopVoiceRecordingAfterStartRef.current = true;
      return;
    }

    if (voiceRecordingState !== 'recording') return;

    if (voiceRecordingModeRef.current === 'simulated') {
      void finalizeSimulatedVoiceRecording();
      return;
    }

    const recorder = mediaRecorderRef.current;
    if (!recorder || recorder.state === 'inactive') {
      void finalizeLiveVoiceRecording();
      return;
    }

    setVoiceRecordingState('finishing');
    recorder.stop();
  }

  function handleVoiceRecordPointerDown(event: ReactPointerEvent<HTMLButtonElement>) {
    if (voiceRecordingState !== 'idle') return;

    activeVoicePointerIdRef.current = event.pointerId;
    event.currentTarget.setPointerCapture(event.pointerId);
    void startVoiceRecording();
  }

  function handleVoiceRecordPointerUp(event: ReactPointerEvent<HTMLButtonElement>) {
    if (
      activeVoicePointerIdRef.current !== null &&
      event.pointerId !== activeVoicePointerIdRef.current
    ) {
      return;
    }

    if (event.currentTarget.hasPointerCapture(event.pointerId)) {
      event.currentTarget.releasePointerCapture(event.pointerId);
    }

    activeVoicePointerIdRef.current = null;
    stopVoiceRecording();
  }

  function handleVoiceRecordKeyDown(event: KeyboardEvent<HTMLButtonElement>) {
    if ((event.key !== ' ' && event.key !== 'Enter') || event.repeat) return;

    event.preventDefault();
    void startVoiceRecording();
  }

  function handleVoiceRecordKeyUp(event: KeyboardEvent<HTMLButtonElement>) {
    if (event.key !== ' ' && event.key !== 'Enter') return;

    event.preventDefault();
    stopVoiceRecording();
  }

  const pullToRefresh = usePullToRefresh({
    onRefresh: refreshAll,
    disabled: !viewerMemberId,
  });

  useEffect(() => {
    if (voiceRecordingState !== 'recording') {
      if (voiceRecordingState === 'idle') {
        setVoiceRecordingDurationMs(0);
      }
      return undefined;
    }

    const timer = window.setInterval(() => {
      if (!recordingStartedAtRef.current) return;
      setVoiceRecordingDurationMs(Date.now() - recordingStartedAtRef.current);
    }, RECORDING_TIMER_INTERVAL_MS);

    return () => {
      window.clearInterval(timer);
    };
  }, [voiceRecordingState]);

  useEffect(() => {
    return () => {
      resetVoiceRecordingSession(false);
    };
  }, []);

  useEffect(() => {
    if (!highlightedMessageId) return undefined;

    const timer = window.setTimeout(() => {
      setHighlightedMessageId((current) => (current === highlightedMessageId ? null : current));
    }, 1800);

    return () => {
      window.clearTimeout(timer);
    };
  }, [highlightedMessageId]);

  useEffect(() => {
    function handlePointerDown(event: PointerEvent) {
      if (!(event.target instanceof Element)) return;
      if (event.target.closest('[data-message-actions-root="true"]')) return;
      setOpenMessageActions(null);
    }

    document.addEventListener('pointerdown', handlePointerDown);
    return () => {
      document.removeEventListener('pointerdown', handlePointerDown);
    };
  }, []);

  useEffect(() => {
    if (!openMessageActions) {
      return undefined;
    }

    function handleCloseActionsMenu() {
      setOpenMessageActions(null);
    }

    const messagePane = messagePaneRef.current;
    window.addEventListener('resize', handleCloseActionsMenu);
    messagePane?.addEventListener('scroll', handleCloseActionsMenu);

    return () => {
      window.removeEventListener('resize', handleCloseActionsMenu);
      messagePane?.removeEventListener('scroll', handleCloseActionsMenu);
    };
  }, [openMessageActions]);

  useEffect(() => {
    setOpenMessageActions(null);
  }, [activeThreadId]);

  useEffect(() => {
    setParticipantsModalOpen(false);
  }, [activeThreadId]);

  useEffect(() => {
    if (!requestedRecipientId) {
      pendingDirectThreadIntentRef.current = null;
    }
  }, [requestedRecipientId]);

  useEffect(() => {
    if (!requestedInitialMessage) {
      pendingInitialMessageIntentRef.current = null;
    }
  }, [requestedInitialMessage]);

  useEffect(() => {
    if (!currentUser?.memberId || !requestedRecipientId) return;

    if (requestedRecipientId === currentUser.memberId) {
      replaceMessagesSearch();
      toast.info('Your inbox is ready whenever you want to follow up.');
      return;
    }

    const intentKey = `${requestedRecipientId}:${requestedTopic ?? ''}:${requestedInitialMessage ?? ''}:${requestedDraftMessage ?? ''}:${requestedMarketplaceBusinessId ?? ''}`;
    if (pendingDirectThreadIntentRef.current === intentKey) return;

    pendingDirectThreadIntentRef.current = intentKey;

    void createDirectThread
      .mutateAsync({
        viewerMemberId: currentUser.memberId,
        participantMemberId: requestedRecipientId,
        topic: requestedTopic,
      })
      .then((response) => {
        setSelectedThreadId(response.thread.id);
        replaceMessagesSearch(response.thread.id, {
          initialMessage: requestedInitialMessage,
          draftMessage: requestedDraftMessage,
          marketplaceBusinessId: requestedMarketplaceBusinessId,
        });
      })
      .catch(() => {
        pendingDirectThreadIntentRef.current = null;
      });
  }, [
    createDirectThread,
    currentUser,
    requestedDraftMessage,
    requestedInitialMessage,
    requestedMarketplaceBusinessId,
    requestedRecipientId,
    requestedTopic,
  ]);

  useEffect(() => {
    if (!viewerMemberId || !activeThread || !requestedInitialMessage || sendMessage.isPending) {
      return;
    }

    const initialMessage = requestedInitialMessage.trim();
    if (!initialMessage) {
      replaceMessagesSearch(activeThread.id);
      return;
    }

    const intentKey = `${activeThread.id}:${initialMessage}`;
    if (pendingInitialMessageIntentRef.current === intentKey) {
      return;
    }

    pendingInitialMessageIntentRef.current = intentKey;

    if (activeThread.messages.length > 0) {
      replaceMessagesSearch(activeThread.id);
      return;
    }

    sendInFlightRef.current = true;

    void sendMessage
      .mutateAsync(
        buildSendMessageRequest({
          viewerMemberId,
          threadId: activeThread.id,
          body: initialMessage,
          attachments: [],
        }),
      )
      .then((response) => {
        queryClient.setQueryData(
          messageKeys.thread(viewerMemberId, response.thread.id),
          response.thread,
        );
        setSelectedThreadId(response.thread.id);
        replaceMessagesSearch(response.thread.id);
      })
      .catch(() => {
        pendingInitialMessageIntentRef.current = null;
        setDraftMessage(initialMessage);
        replaceMessagesSearch(activeThread.id);
      })
      .finally(() => {
        sendInFlightRef.current = false;
      });
  }, [activeThread, queryClient, requestedInitialMessage, sendMessage, viewerMemberId]);

  useEffect(() => {
    discardDraftComposer();
  }, [activeThreadId]);

  useEffect(() => {
    if (
      !viewerMemberId ||
      !activeThread ||
      !requestedDraftMessage ||
      !requestedMarketplaceBusinessId
    ) {
      return;
    }

    if (draftMessage.trim().length > 0 || draftAttachments.length > 0) {
      replaceMessagesSearch(activeThread.id);
      return;
    }

    if (
      !shouldPrefillMarketplaceDraft({
        buyerMemberId: viewerMemberId,
        businessId: requestedMarketplaceBusinessId,
      })
    ) {
      replaceMessagesSearch(activeThread.id);
      return;
    }

    setDraftMessage(requestedDraftMessage);
    recordMarketplaceDraftPrefill({
      buyerMemberId: viewerMemberId,
      businessId: requestedMarketplaceBusinessId,
    });
    replaceMessagesSearch(activeThread.id);
  }, [
    activeThread,
    draftAttachments.length,
    draftMessage,
    requestedDraftMessage,
    requestedMarketplaceBusinessId,
    viewerMemberId,
  ]);

  useEffect(() => {
    if (
      !viewerMemberId ||
      !activeThread ||
      activeThread.unreadCount === 0 ||
      markThreadRead.isPending
    ) {
      return;
    }

    markThreadRead.mutate({
      viewerMemberId,
      threadId: activeThread.id,
    });
  }, [activeThread, markThreadRead, viewerMemberId]);

  useEffect(() => {
    if (!activeThreadId) {
      unreadDividerThreadIdRef.current = null;
      setPersistedUnreadDividerMessageId(null);
      return;
    }

    if (unreadDividerThreadIdRef.current !== activeThreadId) {
      unreadDividerThreadIdRef.current = activeThreadId;
      setPersistedUnreadDividerMessageId(null);
    }
  }, [activeThreadId]);

  useEffect(() => {
    if (!activeThreadId || !unreadDividerMessageId) {
      return;
    }

    unreadDividerThreadIdRef.current = activeThreadId;
    setPersistedUnreadDividerMessageId((current) =>
      current === unreadDividerMessageId ? current : unreadDividerMessageId,
    );
  }, [activeThreadId, unreadDividerMessageId]);

  useEffect(() => {
    if (!activeThreadWithOptimisticMessages?.id) {
      lastOpenedThreadIdRef.current = null;
      return;
    }

    const container = messagePaneRef.current;
    if (!container || lastOpenedThreadIdRef.current === activeThreadWithOptimisticMessages.id) {
      return;
    }

    lastOpenedThreadIdRef.current = activeThreadWithOptimisticMessages.id;

    window.requestAnimationFrame(() => {
      container.scrollTo({
        top: container.scrollHeight,
        behavior: 'auto',
      });
    });
  }, [activeThreadWithOptimisticMessages?.id, activeThreadWithOptimisticMessages?.messages.length]);

  useEffect(() => {
    const container = messagePaneRef.current;
    if (!container || !activeThreadWithOptimisticMessages?.messages.length) return;

    const lastMessage =
      activeThreadWithOptimisticMessages.messages[
        activeThreadWithOptimisticMessages.messages.length - 1
      ];
    const distanceFromBottom =
      container.scrollHeight - container.scrollTop - container.clientHeight;

    if (lastMessage.isOwn || distanceFromBottom < 160) {
      window.requestAnimationFrame(() => {
        container.scrollTo({
          top: container.scrollHeight,
          behavior: 'smooth',
        });
      });
    }
  }, [activeThreadWithOptimisticMessages?.id, activeThreadWithOptimisticMessages?.messages]);

  async function handleFileSelection(event: ChangeEvent<HTMLInputElement>) {
    const files = Array.from(event.target.files ?? []);
    event.target.value = '';

    handleSelectedFiles(files);
  }

  function handleSelectedFiles(files: File[]) {
    if (!viewerMemberId || !activeThread || files.length === 0) return;

    addFilesToDraft(files, viewerMemberId);
  }

  function isDraggingFiles(event: ReactDragEvent<HTMLDivElement>) {
    return Array.from(event.dataTransfer.types).includes('Files');
  }

  function handleAttachmentDragEnter(event: ReactDragEvent<HTMLDivElement>) {
    if (attachmentsDisabled || !isDraggingFiles(event)) {
      return;
    }

    event.preventDefault();
    setIsAttachmentDropActive(true);
  }

  function handleAttachmentDragOver(event: ReactDragEvent<HTMLDivElement>) {
    if (attachmentsDisabled || !isDraggingFiles(event)) {
      return;
    }

    event.preventDefault();
    event.dataTransfer.dropEffect = 'copy';
    if (!isAttachmentDropActive) {
      setIsAttachmentDropActive(true);
    }
  }

  function handleAttachmentDragLeave(event: ReactDragEvent<HTMLDivElement>) {
    if (!isDraggingFiles(event)) {
      return;
    }

    event.preventDefault();
    const nextTarget = event.relatedTarget;
    if (nextTarget instanceof Node && event.currentTarget.contains(nextTarget)) {
      return;
    }

    setIsAttachmentDropActive(false);
  }

  function handleAttachmentDrop(event: ReactDragEvent<HTMLDivElement>) {
    if (!isDraggingFiles(event)) {
      return;
    }

    event.preventDefault();
    setIsAttachmentDropActive(false);

    if (attachmentsDisabled) {
      return;
    }

    handleSelectedFiles(Array.from(event.dataTransfer.files ?? []));
  }

  async function handleCopyMessage(message: MessageItem) {
    const textToCopy = buildCopyTextFromMessage(message);

    if (!textToCopy) {
      toast.info('There is nothing to copy from this message.');
      setOpenMessageActions(null);
      return;
    }

    if (!navigator.clipboard?.writeText) {
      toast.error('Copy is not supported in this browser.');
      setOpenMessageActions(null);
      return;
    }

    await navigator.clipboard.writeText(textToCopy);
    toast.success('Message copied.');
    setOpenMessageActions(null);
  }

  function handleReplyToMessage() {
    if (!openMessageActionsMessage) {
      return;
    }

    setReplyTarget(buildReplyPreviewFromMessage(openMessageActionsMessage));
    setOpenMessageActions(null);
  }

  async function handleDeleteMessage() {
    if (!viewerMemberId || !activeThread || !openMessageActionsMessage) {
      setOpenMessageActions(null);
      return;
    }

    if (!openMessageActionsMessage.isOwn || openMessageActionsMessage.deletedAt) {
      setOpenMessageActions(null);
      return;
    }

    const shouldDelete = window.confirm('Delete this message?');
    if (!shouldDelete) {
      setOpenMessageActions(null);
      return;
    }

    setOpenMessageActions(null);

    await deleteMessage.mutateAsync({
      viewerMemberId,
      threadId: activeThread.id,
      messageId: openMessageActionsMessage.id,
    });

    if (replyTarget?.messageId === openMessageActionsMessage.id) {
      setReplyTarget(null);
    }
  }

  function handleToggleMessageActions(message: MessageItem, trigger: HTMLButtonElement) {
    if (openMessageActions?.messageId === message.id) {
      setOpenMessageActions(null);
      return;
    }

    const rect = trigger.getBoundingClientRect();
    const viewportPadding = 16;
    const menuWidth = 160;
    const menuStyle: CSSProperties = {
      position: 'fixed',
      bottom: `${Math.max(viewportPadding, window.innerHeight - rect.top + 8)}px`,
    };

    if (message.isOwn) {
      menuStyle.left = `${Math.min(
        Math.max(viewportPadding, rect.left),
        window.innerWidth - menuWidth - viewportPadding,
      )}px`;
    } else {
      menuStyle.right = `${Math.max(viewportPadding, window.innerWidth - rect.right)}px`;
    }

    setOpenMessageActions({
      messageId: message.id,
      style: menuStyle,
    });
  }

  async function handleSendMessage() {
    if (sendInFlightRef.current || !viewerMemberId || !activeThread) return;

    const currentThreadId = activeThread.id;
    const originalDraftMessage = draftMessage;
    const originalDraftAttachments = draftAttachments;
    const originalReplyTarget = replyTarget;
    const body = originalDraftMessage.trim();
    if (!body && originalDraftAttachments.length === 0) return;

    sendInFlightRef.current = true;

    const uploadedByDraftId = new Map<string, MessageAttachment>();
    const preservePreviewUrls = new Set(
      originalDraftAttachments
        .map((attachment) => attachment.previewUrl)
        .filter((value): value is string => typeof value === 'string' && value.length > 0),
    );
    const preserveUploadedAttachmentIds = new Set(
      originalDraftAttachments
        .map((attachment) => attachment.uploadedAttachment?.id)
        .filter((value): value is string => typeof value === 'string' && value.length > 0),
    );
    const clientGeneratedId = createClientGeneratedMessageId();
    const currentViewerParticipant =
      activeThread.participants.find((participant) => participant.memberId === viewerMemberId) ??
      null;
    const optimisticAttachments =
      buildOptimisticAttachmentsFromDraftAttachments(originalDraftAttachments);
    const optimisticMessage = buildOptimisticMessage({
      viewerMemberId,
      threadId: currentThreadId,
      body,
      attachments: optimisticAttachments,
      clientGeneratedId,
      currentUserName: currentViewerParticipant?.fullName,
      currentUserAvatar: currentViewerParticipant?.avatar,
      replyTo: originalReplyTarget,
    });

    discardDraftComposer({
      preservePreviewUrls,
      preserveUploadedAttachmentIds,
    });
    addOptimisticMessage(currentThreadId, optimisticMessage);

    try {
      const resolvedAttachments: MessageAttachment[] = [];

      for (const draftAttachment of originalDraftAttachments) {
        const uploadedAttachment =
          draftAttachment.uploadedAttachment ??
          (await uploadAttachment.mutateAsync({
            ...draftAttachment.uploadRequest,
            threadId: currentThreadId,
          }));

        uploadedByDraftId.set(draftAttachment.id, uploadedAttachment);
        resolvedAttachments.push(uploadedAttachment);

        if (draftAttachment.previewUrl) {
          registerMessageAttachmentPreview(uploadedAttachment.id, draftAttachment.previewUrl);
          preservePreviewUrls.add(draftAttachment.previewUrl);
          preserveUploadedAttachmentIds.add(uploadedAttachment.id);
        }
      }

      const request = buildSendMessageRequest({
        viewerMemberId,
        threadId: currentThreadId,
        body,
        attachments: resolvedAttachments,
        replyToMessageId: originalReplyTarget?.messageId,
        clientGeneratedId,
      });
      const response = await sendMessage.mutateAsync(request);

      removeOptimisticMessage(currentThreadId, optimisticMessage.id);
      queryClient.setQueryData(
        messageKeys.thread(viewerMemberId, response.thread.id),
        response.thread,
      );

      if (response.thread.id !== currentThreadId) {
        queryClient.removeQueries({
          queryKey: messageKeys.thread(viewerMemberId, currentThreadId),
          exact: true,
        });
        setSelectedThreadId(response.thread.id);
        replaceMessagesSearch(response.thread.id);
      }
    } catch (error) {
      removeOptimisticMessage(currentThreadId, optimisticMessage.id);
      setDraftMessage(originalDraftMessage);
      setReplyTarget(originalReplyTarget);
      restoreDraftAttachments(
        originalDraftAttachments.map((attachment) => ({
          ...attachment,
          uploadedAttachment: uploadedByDraftId.get(attachment.id) ?? attachment.uploadedAttachment,
        })),
      );
    } finally {
      sendInFlightRef.current = false;
    }
  }

  function handleComposerKeyDown(event: KeyboardEvent<HTMLTextAreaElement>) {
    if (event.key !== 'Enter' || event.shiftKey) return;

    event.preventDefault();
    void handleSendMessage();
  }

  const refreshIndicatorVisible = pullToRefresh.pullDistance > 0 || pullToRefresh.isRefreshing;
  const refreshLabel = pullToRefresh.isRefreshing
    ? 'Refreshing conversations'
    : pullToRefresh.isArmed
      ? 'Release to refresh'
      : 'Pull to refresh';
  const voiceRecordingActive = voiceRecordingState === 'recording';
  const voiceRecordingBusy = voiceRecordingState !== 'idle';
  const voiceRecordingLabel =
    voiceRecordingState === 'starting'
      ? 'Preparing microphone'
      : voiceRecordingState === 'finishing'
        ? 'Preparing voice note preview'
        : 'Recording voice note';
  const voiceRecordingHint =
    voiceRecordingState === 'starting'
      ? 'Grant microphone access to begin.'
      : voiceRecordingState === 'finishing'
        ? 'Release complete. Building the preview.'
        : 'Keep holding the mic button. Release to preview.';
  const composerDisabled = !activeThread || sendMessage.isPending || voiceRecordingBusy;
  const attachmentsDisabled =
    composerDisabled || !activeThread?.attachmentsEnabled || uploadAttachment.isPending;
  const audioDisabled =
    !activeThread ||
    !activeThread.audioEnabled ||
    uploadAttachment.isPending ||
    sendMessage.isPending ||
    voiceRecordingState === 'finishing';
  const voiceRecordTooltip = !activeThread
    ? 'Select a conversation first.'
    : !activeThread.audioEnabled
      ? 'Voice notes are not available in this conversation.'
      : voiceRecordingBusy
        ? 'Release to finish recording.'
        : 'Hold to record a voice note.';
  const draftMessageLength = draftMessage.length;
  const isDraftMessageNearLimit = draftMessageLength / MESSAGE_MAX_BODY_LENGTH >= 0.85;
  const canSend =
    !!activeThread &&
    !sendMessage.isPending &&
    !voiceRecordingBusy &&
    (draftMessage.trim().length > 0 || draftAttachments.length > 0);

  useEffect(() => {
    if (attachmentsDisabled || !activeThread) {
      setIsAttachmentDropActive(false);
    }
  }, [activeThread, attachmentsDisabled]);

  function getSidebarDeliveryState(thread: MessageThreadSummary) {
    if (
      activeThreadWithOptimisticMessages?.id === thread.id &&
      activeOptimisticMessages.length > 0
    ) {
      const lastMessage =
        activeThreadWithOptimisticMessages.messages[
          activeThreadWithOptimisticMessages.messages.length - 1
        ];

      if (lastMessage) {
        return {
          isOwn: lastMessage.isOwn,
          status: lastMessage.status,
        };
      }
    }

    return {
      isOwn: Boolean(thread.lastMessageIsOwn),
      status: thread.lastMessageStatus,
    };
  }

  const isMobileThreadOpen = Boolean(activeThreadId);

  function handleBackToInbox() {
    replaceMessagesSearch();
  }

  return (
    <>
      <SEO
        title="Messages"
        description="Stay in touch with alumni conversations and follow-ups."
      />
      {/* <div className={isMobileThreadOpen ? 'hidden' : 'lg:hidden'}>
        <Breadcrumbs items={breadcrumbItems} />
      </div> */}

      {/* Keep the page shell transparent so the shared animated background remains visible. */}
      <section
        {...pullToRefresh.bind}
        className={`relative bg-transparent ${
          isMobileThreadOpen
            ? 'h-[calc(100dvh-5.25rem)] overflow-hidden sm:h-[calc(100dvh-5.5rem)]'
            : ''
        } lg:h-[calc(100dvh-4.75rem)] lg:overflow-hidden py-2 lg:py-4`}
      >
        <div
          className={`container-custom space-y-4 ${
            isMobileThreadOpen ? 'flex h-full min-h-0 flex-col space-y-0' : ''
          } lg:flex lg:h-full lg:min-h-0 lg:max-w-[1560px] lg:flex-col lg:space-y-0`}
        >
          {/* Pull-to-refresh indicator */}
          <div className="pointer-events-none absolute left-1/2 top-2 z-20 w-fit -translate-x-1/2">
            <div
              className={`flex items-center gap-2 rounded-full border border-gray-200 bg-white px-4 py-2 text-xs font-medium text-gray-600 shadow-sm transition-all duration-200 ${
                refreshIndicatorVisible ? 'opacity-100' : 'pointer-events-none opacity-0'
              }`}
              style={{
                transform: `translateY(${Math.min(pullToRefresh.pullDistance, 18)}px)`,
              }}
            >
              {pullToRefresh.isRefreshing ? (
                <LoaderCircle className="h-4 w-4 animate-spin" strokeWidth={2.2} />
              ) : (
                <RefreshCw className="h-4 w-4" strokeWidth={2.2} />
              )}
              <span>{refreshLabel}</span>
            </div>
          </div>

          {/* Page title */}
          <div
            className={`shrink-0 space-y-1 pt-0 ${
              isMobileThreadOpen ? 'hidden lg:block' : ''
            } lg:pb-3`}
          >
            {/* <h1 className="text-3xl font-bold leading-tight tracking-tight text-gray-950 sm:text-4xl lg:text-[2.5rem] xl:text-[2.75rem]"> */}
            <h1 className="type-section-title mb-3 text-gray-950">Message Centre</h1>
          </div>

          {/* Two-column layout */}
          <section
            className={`grid gap-4 ${
              isMobileThreadOpen ? 'min-h-0 flex-1' : ''
            } lg:min-h-0 lg:flex-1 lg:grid-cols-[minmax(20rem,26rem)_minmax(0,1fr)] lg:gap-4 xl:grid-cols-[24rem_minmax(0,1fr)] 2xl:grid-cols-[26rem_minmax(0,1fr)]`}
          >
            {/* ─── Inbox pane ─── */}
            <aside
              className={`min-h-[calc(100dvh-9rem)] flex-col overflow-hidden rounded-2xl bg-white shadow-sm lg:flex lg:h-full lg:min-h-0 ${
                isMobileThreadOpen ? 'hidden' : 'flex'
              }`}
            >
              {/* Search + filters */}
              <div className="px-4 pb-3 pt-4">
                <label className="relative block">
                  <Search
                    className="pointer-events-none absolute left-3.5 top-1/2 h-4.5 w-4.5 -translate-y-1/2 text-gray-400"
                    strokeWidth={2.2}
                  />
                  <input
                    type="text"
                    value={query}
                    onChange={(event) => setQuery(event.target.value)}
                    placeholder="Search chats"
                    className="w-full rounded-full border-0 bg-gray-100 py-3 pl-10 pr-4 text-sm text-gray-900 outline-none placeholder:text-gray-400 focus:ring-2 focus:ring-blue-100"
                  />
                </label>

                {/* Filters — only All + Unread per Figma */}
                <div className="mt-3 flex flex-wrap gap-2">
                  {inboxFilters.map((item) => {
                    const active = filter === item.key;
                    const label =
                      item.key === 'unread' ? `${item.label} (${unreadThreadCount})` : item.label;

                    return (
                      <button
                        key={item.key}
                        type="button"
                        onClick={() => setFilter(item.key)}
                        className={`rounded-full px-4 py-1.5 text-sm font-medium transition-colors ${
                          active
                            ? 'bg-blue-100 text-blue-700'
                            : 'border border-gray-200 bg-white text-gray-600 hover:bg-gray-50'
                        }`}
                      >
                        {label}
                      </button>
                    );
                  })}
                </div>
              </div>

              {/* Thread list */}
              <div className="flex-1 overflow-y-auto">
                {inboxQuery.isLoading && !inboxQuery.data ? (
                  <div className="space-y-px px-2 py-1">
                    {Array.from({ length: 5 }).map((_, index) => (
                      <div key={index} className="animate-pulse rounded-xl px-3 py-3">
                        <div className="flex items-center gap-3">
                          <div className="h-11 w-11 flex-shrink-0 rounded-full bg-gray-100" />
                          <div className="flex-1 space-y-2">
                            <div className="h-3.5 w-1/2 rounded-full bg-gray-100" />
                            <div className="h-3 w-3/4 rounded-full bg-gray-50" />
                          </div>
                        </div>
                      </div>
                    ))}
                  </div>
                ) : inboxQuery.error && !inboxQuery.data ? (
                  <EmptyState
                    icon={MessageSquareWarning}
                    title="Could not load inbox"
                    description="Pull down to retry."
                    actionLabel="Refresh"
                    onAction={() => void refreshAll()}
                  />
                ) : inboxThreads.length === 0 ? (
                  <EmptyState
                    icon={MessageSquare}
                    title="No messages yet"
                    description="Your conversations will appear here."
                  />
                ) : visibleThreads.length === 0 ? (
                  <EmptyState
                    icon={MessagesSquare}
                    title="No results"
                    description="Try a different filter or search term."
                  />
                ) : (
                  <div className="py-1">
                    {visibleThreads.map((thread) => {
                      const isActive = activeThreadId === thread.id;
                      const deliveryState = getSidebarDeliveryState(thread);
                      const preview = getThreadPreviewParts(thread);

                      return (
                        <button
                          key={thread.id}
                          type="button"
                          onClick={() => {
                            replaceMessagesSearch(thread.id);
                          }}
                          className={`relative w-full px-4 py-3 text-left transition-colors ${
                            isActive ? 'bg-gray-50' : 'hover:bg-gray-50'
                          }`}
                        >
                          {/* Active indicator: blue left border like Figma */}
                          {isActive ? (
                            <span className="absolute inset-y-0 left-0 w-0.5 rounded-full bg-blue-600" />
                          ) : null}

                          <div className="flex items-center gap-3">
                            <ThreadAvatar thread={thread} size="sm" />

                            <div className="min-w-0 flex-1">
                              <div className="flex items-start justify-between gap-2">
                                <p className="truncate text-sm font-semibold text-gray-900">
                                  {thread.title}
                                </p>
                                <span className="flex-shrink-0 text-xs text-gray-400">
                                  {formatThreadTimestamp(thread.lastActivityAt)}
                                </span>
                              </div>

                              <div className="mt-0.5 flex items-center justify-between gap-2">
                                <div className="flex min-w-0 flex-1 items-center gap-1.5">
                                  {deliveryState.isOwn && deliveryState.status ? (
                                    <MessageDeliveryIndicator
                                      status={deliveryState.status}
                                      className="shrink-0"
                                    />
                                  ) : null}
                                  <p className="line-clamp-1 min-w-0 flex-1 text-sm text-gray-500">
                                    {preview.senderPrefix ? (
                                      <span>{preview.senderPrefix}</span>
                                    ) : null}
                                    {preview.attachmentKind
                                      ? renderIcon(
                                          getAttachmentIcon(preview.attachmentKind),
                                          'mb-0.5 mr-1 inline-block h-4 w-4 align-text-bottom text-gray-400',
                                        )
                                      : null}
                                    <span>{preview.text}</span>
                                  </p>
                                </div>
                                {thread.unreadCount > 0 ? (
                                  <span className="flex-shrink-0 inline-flex min-w-5 items-center justify-center rounded-full bg-blue-600 px-1.5 py-0.5 text-[11px] font-semibold text-white">
                                    {thread.unreadCount}
                                  </span>
                                ) : null}
                              </div>
                            </div>
                          </div>
                        </button>
                      );
                    })}
                  </div>
                )}
              </div>
            </aside>

            {/* ─── Active thread pane ─── */}
            <article
              className={`min-h-[calc(100dvh-9rem)] flex-col overflow-hidden rounded-2xl bg-white shadow-sm ${
                isMobileThreadOpen ? 'h-full min-h-0' : ''
              } lg:flex lg:h-full lg:min-h-0 ${isMobileThreadOpen ? 'flex' : 'hidden'}`}
            >
              {threadShell || (activeThreadId && threadQuery.isLoading) ? (
                <>
                  {/* Thread header */}
                  {threadShell ? (
                    <header className="flex items-center gap-3 border-b border-gray-100 px-4 py-2 lg:px-5">
                      <button
                        type="button"
                        onClick={handleBackToInbox}
                        className="inline-flex h-10 w-10 items-center justify-center rounded-full text-gray-600 transition-colors hover:bg-gray-100 lg:hidden"
                        aria-label="Back to messages"
                      >
                        <ArrowLeft className="h-5 w-5" strokeWidth={2.2} />
                      </button>

                      <ThreadAvatar thread={threadShell} />

                      <div className="min-w-0 flex-1">
                        <h2 className="truncate text-base font-semibold text-gray-900">
                          {threadShell.title}
                        </h2>
                        {threadShell.type === 'group' && groupParticipants.length > 0 ? (
                          <button
                            type="button"
                            onClick={() => setParticipantsModalOpen(true)}
                            className="mt-0.5 block truncate text-left text-sm text-gray-500 underline decoration-gray-300 underline-offset-4 transition-colors hover:text-blue-700 hover:decoration-blue-300"
                          >
                            {formatMemberCount(groupParticipants.length)}
                          </button>
                        ) : (
                          <p className="mt-0.5 truncate text-sm text-gray-500">
                            {threadShell.subtitle || formatThreadHeaderSubtitle(threadShell)}
                          </p>
                        )}
                      </div>
                    </header>
                  ) : (
                    <header className="border-b border-gray-100 px-5 py-4">
                      <div className="flex animate-pulse items-center gap-3">
                        <div className="h-12 w-12 rounded-full bg-gray-100" />
                        <div className="flex-1 space-y-2">
                          <div className="h-4 w-36 rounded-full bg-gray-100" />
                          <div className="h-3 w-48 rounded-full bg-gray-50" />
                        </div>
                      </div>
                    </header>
                  )}

                  {/* Message pane */}
                  <div
                    ref={messagePaneRef}
                    className="min-h-0 flex-1 overflow-y-auto bg-white px-4 py-4 sm:px-5 sm:py-5"
                  >
                    {threadQuery.isLoading && !activeThread ? (
                      <div className="space-y-5">
                        {Array.from({ length: 3 }).map((_, index) => (
                          <div
                            key={index}
                            className={`flex ${index % 2 === 0 ? 'justify-start' : 'justify-end'}`}
                          >
                            <div className="w-64 animate-pulse rounded-2xl bg-gray-100 px-4 py-5" />
                          </div>
                        ))}
                      </div>
                    ) : threadQuery.error ? (
                      <EmptyState
                        icon={MessageSquareX}
                        title="Conversation unavailable"
                        description="Refresh to reload."
                        actionLabel="Refresh"
                        onAction={() => void refreshAll()}
                      />
                    ) : activeThreadWithOptimisticMessages ? (
                      <div className="space-y-1">
                        {activeThreadWithOptimisticMessages.messages.map((message, index) => {
                          const previousMessage =
                            activeThreadWithOptimisticMessages.messages[index - 1];

                          const currentDate = new Date(message.createdAt);
                          const previousDate = previousMessage
                            ? new Date(previousMessage.createdAt)
                            : null;

                          const showDayDivider =
                            !previousMessage ||
                            previousDate?.toDateString() !== currentDate.toDateString();

                          const currentMinuteKey = `${currentDate.getFullYear()}-${currentDate.getMonth()}-${currentDate.getDate()}-${currentDate.getHours()}-${currentDate.getMinutes()}`;

                          const previousMinuteKey = previousDate
                            ? `${previousDate.getFullYear()}-${previousDate.getMonth()}-${previousDate.getDate()}-${previousDate.getHours()}-${previousDate.getMinutes()}`
                            : null;

                          const showTimestampHeader =
                            !previousMessage ||
                            showDayDivider ||
                            previousMessage.senderMemberId !== message.senderMemberId ||
                            previousMinuteKey !== currentMinuteKey;

                          return (
                            <Fragment key={message.id}>
                              {showDayDivider ? (
                                <div className="py-4 text-center">
                                  <span className="text-xs text-gray-400">
                                    {formatConversationDay(message.createdAt)}
                                  </span>
                                </div>
                              ) : null}

                              {message.id === visibleUnreadDividerMessageId ? (
                                <div className="my-4 flex items-center gap-3">
                                  <div className="h-px flex-1 bg-blue-100" />
                                  <span className="text-sm font-medium text-gray-500">
                                    Unread messages
                                  </span>
                                  <div className="h-px flex-1 bg-blue-100" />
                                </div>
                              ) : null}

                              {showTimestampHeader ? (
                                <div
                                  className={`pb-1 pt-3 text-xs text-gray-400 ${
                                    message.isOwn ? 'text-right' : 'text-left'
                                  }`}
                                >
                                  {!message.isOwn &&
                                  activeThreadWithOptimisticMessages.type === 'group' ? (
                                    <span className="font-medium text-gray-500">
                                      {message.senderDisplayName}{' '}
                                    </span>
                                  ) : null}

                                  <span>
                                    {formatConversationDay(message.createdAt)}{' '}
                                    {formatMessageTime(message.createdAt)}
                                  </span>
                                </div>
                              ) : null}

                              <div
                                className={`flex ${message.isOwn ? 'justify-end' : 'justify-start'}`}
                              >
                                <div
                                  data-message-id={message.id}
                                  className={`group relative w-fit max-w-[75%] transition-all duration-300 sm:max-w-[60%] ${
                                    highlightedMessageId === message.id
                                      ? 'rounded-2xl ring-2 ring-blue-300 ring-offset-2'
                                      : ''
                                  }`}
                                >
                                  {/* Actions button */}
                                  {message.status !== 'sending' && !message.deletedAt ? (
                                    <div
                                      data-message-actions-root="true"
                                      className={`absolute top-2 z-10 ${message.isOwn ? 'left-2' : 'right-2'}`}
                                    >
                                      <button
                                        type="button"
                                        onClick={(event) => {
                                          event.stopPropagation();
                                          handleToggleMessageActions(message, event.currentTarget);
                                        }}
                                        className={`inline-flex h-7 w-7 items-center justify-center rounded-full border border-gray-200 bg-white shadow-sm transition-all ${
                                          openMessageActions?.messageId === message.id
                                            ? 'text-gray-700 opacity-100'
                                            : 'text-gray-400 opacity-0 group-hover:opacity-100'
                                        }`}
                                        aria-label="Message actions"
                                      >
                                        <Ellipsis className="h-3.5 w-3.5" strokeWidth={2.2} />
                                      </button>
                                    </div>
                                  ) : null}

                                  <div
                                    className={`rounded-2xl px-4 py-2.5 ${
                                      message.isOwn
                                        ? 'bg-[#dbeafe] text-gray-900'
                                        : 'bg-[#f3f4f6] text-gray-900'
                                    }`}
                                  >
                                    {message.replyTo && !message.deletedAt ? (
                                      <ReplyPreviewCard
                                        replyTo={message.replyTo}
                                        variant="bubble"
                                        onOpenOriginal={handleJumpToMessage}
                                      />
                                    ) : null}

                                    {message.body ? (
                                      <p className="whitespace-pre-wrap text-[14.5px] leading-6">
                                        {message.body}
                                      </p>
                                    ) : null}

                                    {message.attachments.length > 0 ? (
                                      <MessageAttachments
                                        attachments={message.attachments}
                                        isOwn={message.isOwn}
                                        onOpenImage={setActiveImageAttachment}
                                      />
                                    ) : null}
                                  </div>

                                  {message.isOwn ? (
                                    <MessageDeliveryIndicator
                                      status={message.status}
                                      showLabel
                                      className="mt-1 justify-end px-1 text-[11px]"
                                    />
                                  ) : null}
                                </div>
                              </div>
                            </Fragment>
                          );
                        })}
                      </div>
                    ) : (
                      <div className="flex h-full min-h-[28rem] items-center justify-center">
                        <div className="rounded-full bg-blue-50 px-6 py-3 text-sm font-medium text-blue-600">
                          Select a chat to start messaging
                        </div>
                      </div>
                    )}
                  </div>

                  {/* Composer footer */}
                  <footer className="sticky bottom-0 z-10 shrink-0 border-t border-gray-100 bg-white/95 px-4 py-1.5 backdrop-blur lg:static lg:bg-white lg:backdrop-blur-0">
                    <input
                      ref={fileInputRef}
                      type="file"
                      multiple
                      accept={MESSAGE_ATTACHMENT_FILE_INPUT_ACCEPT}
                      className="hidden"
                      onChange={handleFileSelection}
                    />

                    <div
                      onDragEnter={handleAttachmentDragEnter}
                      onDragOver={handleAttachmentDragOver}
                      onDragLeave={handleAttachmentDragLeave}
                      onDrop={handleAttachmentDrop}
                      className={`flex max-h-[min(12.5rem,24vh)] flex-col rounded-2xl border bg-gray-50 transition-colors ${
                        isAttachmentDropActive
                          ? 'border-primary-400 bg-primary-50/70 ring-2 ring-primary-100'
                          : 'border-gray-200'
                      }`}
                    >
                      {isAttachmentDropActive ? (
                        <div className="shrink-0 border-b border-primary-100 px-4 py-2 text-xs font-semibold tracking-[0.01em] text-primary-600">
                          Drop files here to attach them
                        </div>
                      ) : null}
                      <div className="min-h-0 overflow-y-auto px-4 pt-2">
                        {replyTarget ? (
                          <ReplyPreviewCard
                            replyTo={replyTarget}
                            variant="composer"
                            onClear={() => setReplyTarget(null)}
                          />
                        ) : null}

                        <DraftComposerAttachments
                          attachments={draftAttachments}
                          onRemove={removeDraftAttachment}
                        />
                      </div>

                      <div
                        className={`shrink-0 px-4 py-2 ${
                          draftAttachments.length > 0 ? 'border-t border-gray-200/80' : ''
                        }`}
                      >
                        {voiceRecordingBusy ? (
                          <div className="mb-3 flex items-center justify-between gap-3 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2.5 text-rose-900">
                            <div className="flex min-w-0 items-center gap-2.5">
                              <span
                                className={`h-2.5 w-2.5 flex-shrink-0 rounded-full ${
                                  voiceRecordingActive ? 'animate-pulse bg-rose-500' : 'bg-rose-400'
                                }`}
                              />
                              <div className="min-w-0">
                                <p className="text-sm font-semibold">{voiceRecordingLabel}</p>
                                <p className="text-xs text-rose-700">{voiceRecordingHint}</p>
                              </div>
                            </div>

                            <span className="rounded-full bg-white px-2.5 py-1 text-sm font-semibold text-rose-700 shadow-sm">
                              {formatRecordingDuration(voiceRecordingDurationMs)}
                            </span>
                          </div>
                        ) : null}

                        <textarea
                          value={draftMessage}
                          onChange={(event) => setDraftMessage(event.target.value)}
                          onKeyDown={handleComposerKeyDown}
                          disabled={composerDisabled}
                          maxLength={MESSAGE_MAX_BODY_LENGTH}
                          rows={1}
                          placeholder={
                            activeThread
                              ? 'Write a message...'
                              : 'Select a conversation to start typing'
                          }
                          className="w-full resize-none border-0 bg-transparent text-[14.5px] text-gray-900 outline-none placeholder:text-gray-400 disabled:cursor-not-allowed disabled:opacity-50"
                        />

                        <div className="mt-1.5 flex items-center justify-between gap-3">
                          <div className="flex items-center gap-1.5">
                            {/* Attach file */}
                            <button
                              type="button"
                              onClick={() => fileInputRef.current?.click()}
                              disabled={attachmentsDisabled}
                              className="inline-flex h-9 w-9 items-center justify-center rounded-full text-gray-400 transition-colors hover:bg-gray-200 hover:text-gray-600 disabled:cursor-not-allowed disabled:opacity-50"
                              aria-label="Attach file"
                            >
                              <Paperclip className="h-4.5 w-4.5" strokeWidth={2.2} />
                            </button>

                            {/* Voice note */}
                            <div className="group relative">
                              <button
                                type="button"
                                ref={voiceRecordButtonRef}
                                onPointerDown={handleVoiceRecordPointerDown}
                                onPointerUp={handleVoiceRecordPointerUp}
                                onPointerCancel={handleVoiceRecordPointerUp}
                                onKeyDown={handleVoiceRecordKeyDown}
                                onKeyUp={handleVoiceRecordKeyUp}
                                disabled={audioDisabled}
                                title={voiceRecordTooltip}
                                className={`inline-flex h-9 w-9 items-center justify-center rounded-full transition-colors disabled:cursor-not-allowed disabled:opacity-50 ${
                                  voiceRecordingBusy
                                    ? 'bg-rose-100 text-rose-700'
                                    : 'text-gray-400 hover:bg-gray-200 hover:text-gray-600'
                                }`}
                                aria-label="Hold to record a voice note"
                              >
                                {voiceRecordingState === 'starting' ||
                                voiceRecordingState === 'finishing' ? (
                                  <LoaderCircle
                                    className="h-4.5 w-4.5 animate-spin"
                                    strokeWidth={2.2}
                                  />
                                ) : voiceRecordingActive ? (
                                  <Mic className="h-4.5 w-4.5" strokeWidth={2.2} />
                                ) : (
                                  <MicOff className="h-4.5 w-4.5" strokeWidth={2.2} />
                                )}
                              </button>

                              <span className="pointer-events-none absolute -top-9 left-1/2 hidden -translate-x-1/2 whitespace-nowrap rounded-lg bg-gray-800 px-2.5 py-1.5 text-xs text-white shadow-sm group-hover:inline-flex">
                                {voiceRecordTooltip}
                              </span>
                            </div>
                          </div>

                          <div className="flex items-center gap-3">
                            <span
                              className={`text-[11px] font-medium ${
                                draftMessageLength >= MESSAGE_MAX_BODY_LENGTH
                                  ? 'text-red-500'
                                  : isDraftMessageNearLimit
                                    ? 'text-amber-500'
                                    : 'text-gray-400'
                              }`}
                            >
                              {draftMessageLength}/{MESSAGE_MAX_BODY_LENGTH}
                            </span>

                            {/* Send button */}
                            <button
                              type="button"
                              onClick={() => void handleSendMessage()}
                              disabled={!canSend}
                              className="inline-flex h-9 w-9 items-center justify-center rounded-full bg-blue-600 text-white shadow-sm transition-colors hover:bg-blue-700 disabled:cursor-not-allowed disabled:bg-blue-200"
                              aria-label="Send message"
                            >
                              {sendMessage.isPending ? (
                                <LoaderCircle className="h-4 w-4 animate-spin" strokeWidth={2.2} />
                              ) : (
                                <SendHorizontal className="h-4 w-4" strokeWidth={2.2} />
                              )}
                            </button>
                          </div>
                        </div>
                      </div>
                    </div>
                  </footer>
                </>
              ) : (
                <div className="flex h-full min-h-[42rem] items-center justify-center">
                  <div className="rounded-full bg-blue-50 px-6 py-3 text-sm font-medium text-blue-600 shadow-sm">
                    Select a chat to start messaging
                  </div>
                </div>
              )}
            </article>

            {/* Message actions context menu */}
            {openMessageActions && openMessageActionsMessage ? (
              <div
                data-message-actions-root="true"
                style={openMessageActions.style}
                className="z-50 w-40 rounded-xl border border-gray-100 bg-white p-1.5 shadow-xl"
              >
                <button
                  type="button"
                  onClick={() => void handleReplyToMessage()}
                  className="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm text-gray-700 transition-colors hover:bg-gray-50"
                >
                  <Reply className="h-4 w-4" strokeWidth={2.2} />
                  Reply
                </button>
                {canCopyOpenMessage ? (
                  <button
                    type="button"
                    onClick={() => void handleCopyMessage(openMessageActionsMessage)}
                    className="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm text-gray-700 transition-colors hover:bg-gray-50"
                  >
                    <Copy className="h-4 w-4" strokeWidth={2.2} />
                    Copy
                  </button>
                ) : null}
                {openMessageActionsMessage.isOwn ? (
                  <button
                    type="button"
                    onClick={() => void handleDeleteMessage()}
                    className="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm text-rose-600 transition-colors hover:bg-rose-50"
                  >
                    <Trash2 className="h-4 w-4" strokeWidth={2.2} />
                    Delete
                  </button>
                ) : null}
              </div>
            ) : null}
          </section>
        </div>
      </section>

      <ImageAttachmentLightbox
        attachment={activeImageAttachment}
        onClose={() => setActiveImageAttachment(null)}
      />
      <GroupParticipantsModal
        isOpen={participantsModalOpen}
        onClose={() => setParticipantsModalOpen(false)}
        threadTitle={threadShell?.title ?? 'Group members'}
        participants={groupParticipants}
        viewerMemberId={viewerMemberId}
      />
    </>
  );
}
