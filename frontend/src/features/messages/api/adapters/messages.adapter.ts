import type { SendMessageRequest, UploadMessageAttachmentRequest } from '../messages.contract';
import { SHARED_UPLOAD_MAX_SIZE_BYTES } from '@/shared/utils/fileValidation';
import type {
  MessageAttachment,
  MessageThreadDetail,
  MessageThreadFilter,
  MessageThreadSummary,
} from '../../types/messages.types';

export const MESSAGE_ATTACHMENT_MAX_FILE_SIZE_BYTES = SHARED_UPLOAD_MAX_SIZE_BYTES;
export const MESSAGE_IMAGE_ATTACHMENT_MAX_FILE_SIZE_BYTES = 2 * 1024 * 1024;
export const MESSAGE_AUDIO_ATTACHMENT_MAX_FILE_SIZE_BYTES = SHARED_UPLOAD_MAX_SIZE_BYTES;
export const MESSAGE_MAX_ATTACHMENTS_PER_MESSAGE = 6;
export const MESSAGE_MAX_BODY_LENGTH = 4_000;
export const MESSAGE_ATTACHMENT_ALLOWED_MIME_TYPES = [
  'image/jpeg',
  'image/png',
  'image/webp',
  'image/gif',
  'application/pdf',
  'application/msword',
  'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
  'text/plain',
  'text/csv',
  'audio/mp4',
  'audio/mpeg',
  'audio/ogg',
  'audio/ogg;codecs=opus',
  'audio/wav',
  'audio/webm',
  'audio/webm;codecs=opus',
] as const;
export const MESSAGE_ATTACHMENT_FILE_INPUT_ACCEPT = MESSAGE_ATTACHMENT_ALLOWED_MIME_TYPES.join(',');

export function normalizeMessageAttachmentMimeType(mimeType: string) {
  const normalizedMimeType = mimeType.trim().toLowerCase();
  if (!normalizedMimeType) {
    return '';
  }

  const baseMimeType = normalizedMimeType.split(';')[0]?.trim() ?? normalizedMimeType;

  if (baseMimeType === 'audio/x-wav') {
    return 'audio/wav';
  }

  return baseMimeType;
}

const allowedMessageAttachmentMimeTypes = new Set<string>(
  MESSAGE_ATTACHMENT_ALLOWED_MIME_TYPES.map((mimeType) =>
    normalizeMessageAttachmentMimeType(mimeType),
  ),
);

export function formatBytes(sizeInBytes: number) {
  if (sizeInBytes < 1024) return `${sizeInBytes} B`;
  if (sizeInBytes < 1024 * 1024) return `${(sizeInBytes / 1024).toFixed(1)} KB`;
  return `${(sizeInBytes / (1024 * 1024)).toFixed(1)} MB`;
}

function resolveAttachmentKindFromMime(mimeType: string): MessageAttachment['kind'] {
  if (mimeType.startsWith('audio/')) return 'audio';
  if (mimeType.startsWith('image/')) return 'image';
  return 'file';
}

function resolveAudioFileExtension(mimeType: string) {
  if (mimeType.includes('mp4')) return 'm4a';
  if (mimeType.includes('ogg')) return 'ogg';
  if (mimeType.includes('wav')) return 'wav';
  return 'webm';
}

export function isAllowedMessageAttachmentMimeType(mimeType: string) {
  return allowedMessageAttachmentMimeTypes.has(normalizeMessageAttachmentMimeType(mimeType));
}

export function assertValidMessageBody(body?: string) {
  if (!body) return;

  const trimmedBody = body.trim();
  if (!trimmedBody) return;

  if (trimmedBody.length > MESSAGE_MAX_BODY_LENGTH) {
    throw new Error(
      `Messages must stay under ${MESSAGE_MAX_BODY_LENGTH.toLocaleString()} characters.`,
    );
  }
}

export function assertValidMessageAttachments(attachments?: MessageAttachment[]) {
  const safeAttachments = attachments ?? [];

  if (safeAttachments.length > MESSAGE_MAX_ATTACHMENTS_PER_MESSAGE) {
    throw new Error(
      `You can attach up to ${MESSAGE_MAX_ATTACHMENTS_PER_MESSAGE} files per message.`,
    );
  }
}

export function assertValidMessageAttachmentUploadRequest(
  request: Pick<UploadMessageAttachmentRequest, 'fileName' | 'mimeType' | 'sizeInBytes' | 'kind'>,
) {
  const trimmedFileName = request.fileName.trim();
  const normalizedMimeType = normalizeMessageAttachmentMimeType(request.mimeType);

  if (!trimmedFileName) {
    throw new Error('Attachments must have a valid file name.');
  }

  if (!normalizedMimeType || !isAllowedMessageAttachmentMimeType(normalizedMimeType)) {
    throw new Error('That file type is not supported in chat yet.');
  }

  if (!Number.isFinite(request.sizeInBytes) || request.sizeInBytes <= 0) {
    throw new Error('Attachments must include a valid file size.');
  }

  const maxSizeBytes =
    request.kind === 'image'
      ? MESSAGE_IMAGE_ATTACHMENT_MAX_FILE_SIZE_BYTES
      : request.kind === 'audio'
        ? MESSAGE_AUDIO_ATTACHMENT_MAX_FILE_SIZE_BYTES
        : MESSAGE_ATTACHMENT_MAX_FILE_SIZE_BYTES;

  if (request.sizeInBytes > maxSizeBytes) {
    throw new Error(`Attachments must be ${formatBytes(maxSizeBytes)} or smaller.`);
  }

  const expectedKind = resolveAttachmentKindFromMime(normalizedMimeType);
  if (request.kind !== expectedKind) {
    throw new Error('Attachment type does not match the selected file.');
  }
}

function writeAsciiToDataView(view: DataView, offset: number, value: string) {
  for (let index = 0; index < value.length; index += 1) {
    view.setUint8(offset + index, value.charCodeAt(index));
  }
}

function buildSimulatedVoiceNoteBlob(durationSeconds: number) {
  const safeDurationSeconds = Math.max(1, Math.round(durationSeconds));
  const sampleRate = 8_000;
  const bitsPerSample = 16;
  const channelCount = 1;
  const bytesPerSample = bitsPerSample / 8;
  const frameCount = safeDurationSeconds * sampleRate;
  const dataSize = frameCount * channelCount * bytesPerSample;
  const buffer = new ArrayBuffer(44 + dataSize);
  const view = new DataView(buffer);

  writeAsciiToDataView(view, 0, 'RIFF');
  view.setUint32(4, 36 + dataSize, true);
  writeAsciiToDataView(view, 8, 'WAVE');
  writeAsciiToDataView(view, 12, 'fmt ');
  view.setUint32(16, 16, true);
  view.setUint16(20, 1, true);
  view.setUint16(22, channelCount, true);
  view.setUint32(24, sampleRate, true);
  view.setUint32(28, sampleRate * channelCount * bytesPerSample, true);
  view.setUint16(32, channelCount * bytesPerSample, true);
  view.setUint16(34, bitsPerSample, true);
  writeAsciiToDataView(view, 36, 'data');
  view.setUint32(40, dataSize, true);

  return new Blob([buffer], { type: 'audio/wav' });
}

function buildWavBlobFromAudioBuffer(audioBuffer: AudioBuffer) {
  const channelCount = audioBuffer.numberOfChannels;
  const sampleRate = audioBuffer.sampleRate;
  const bitsPerSample = 16;
  const bytesPerSample = bitsPerSample / 8;
  const dataSize = audioBuffer.length * channelCount * bytesPerSample;
  const buffer = new ArrayBuffer(44 + dataSize);
  const view = new DataView(buffer);

  writeAsciiToDataView(view, 0, 'RIFF');
  view.setUint32(4, 36 + dataSize, true);
  writeAsciiToDataView(view, 8, 'WAVE');
  writeAsciiToDataView(view, 12, 'fmt ');
  view.setUint32(16, 16, true);
  view.setUint16(20, 1, true);
  view.setUint16(22, channelCount, true);
  view.setUint32(24, sampleRate, true);
  view.setUint32(28, sampleRate * channelCount * bytesPerSample, true);
  view.setUint16(32, channelCount * bytesPerSample, true);
  view.setUint16(34, bitsPerSample, true);
  writeAsciiToDataView(view, 36, 'data');
  view.setUint32(40, dataSize, true);

  let offset = 44;

  for (let sampleIndex = 0; sampleIndex < audioBuffer.length; sampleIndex += 1) {
    for (let channelIndex = 0; channelIndex < channelCount; channelIndex += 1) {
      const sample = audioBuffer.getChannelData(channelIndex)[sampleIndex] ?? 0;
      const clampedSample = Math.max(-1, Math.min(1, sample));
      const pcmValue = clampedSample < 0 ? clampedSample * 0x8000 : clampedSample * 0x7fff;

      view.setInt16(offset, Math.round(pcmValue), true);
      offset += bytesPerSample;
    }
  }

  return new Blob([buffer], { type: 'audio/wav' });
}

async function convertRecordedAudioBlobToWav(blob: Blob) {
  if (typeof window === 'undefined' || typeof window.AudioContext === 'undefined') {
    return blob;
  }

  const audioContext = new window.AudioContext();

  try {
    const arrayBuffer = await blob.arrayBuffer();
    const audioBuffer = await audioContext.decodeAudioData(arrayBuffer.slice(0));

    return buildWavBlobFromAudioBuffer(audioBuffer);
  } finally {
    await audioContext.close().catch(() => undefined);
  }
}

export function buildFileAttachmentUploadRequest(
  file: File,
  viewerMemberId: string,
): UploadMessageAttachmentRequest {
  const normalizedMimeType = normalizeMessageAttachmentMimeType(
    file.type || 'application/octet-stream',
  );

  return {
    viewerMemberId,
    fileName: file.name,
    mimeType: normalizedMimeType || 'application/octet-stream',
    sizeInBytes: file.size,
    binary: file,
    kind: resolveAttachmentKindFromMime(normalizedMimeType || 'application/octet-stream'),
    source: 'device',
  };
}

export function buildVoiceNoteUploadRequest(
  viewerMemberId: string,
  durationSeconds = 18 + Math.floor(Math.random() * 45),
): UploadMessageAttachmentRequest {
  const binary = buildSimulatedVoiceNoteBlob(durationSeconds);
  const normalizedMimeType = normalizeMessageAttachmentMimeType(binary.type || 'audio/wav');

  return {
    viewerMemberId,
    fileName: `voice-note-${Date.now()}.wav`,
    mimeType: normalizedMimeType || 'audio/wav',
    sizeInBytes: binary.size,
    binary,
    kind: 'audio',
    durationSeconds,
    source: 'microphone',
  };
}

export async function buildRecordedVoiceNoteUploadRequest(params: {
  viewerMemberId: string;
  blob: Blob;
  durationSeconds: number;
}): Promise<UploadMessageAttachmentRequest> {
  const processedBlob = await convertRecordedAudioBlobToWav(params.blob).catch(() => params.blob);
  const normalizedMimeType = normalizeMessageAttachmentMimeType(processedBlob.type || 'audio/wav');
  const fileExtension = resolveAudioFileExtension(normalizedMimeType || 'audio/wav');

  return {
    viewerMemberId: params.viewerMemberId,
    fileName: `voice-note-${Date.now()}.${fileExtension}`,
    mimeType: normalizedMimeType || 'audio/wav',
    sizeInBytes: processedBlob.size,
    binary: processedBlob,
    kind: 'audio',
    durationSeconds: params.durationSeconds,
    source: 'microphone',
  };
}

export function buildSendMessageRequest(params: {
  viewerMemberId: string;
  threadId: string;
  body?: string;
  attachments?: MessageAttachment[];
  replyToMessageId?: string;
  clientGeneratedId?: string;
}): SendMessageRequest {
  return {
    viewerMemberId: params.viewerMemberId,
    threadId: params.threadId,
    body: params.body?.trim() || undefined,
    attachments: params.attachments ?? [],
    replyToMessageId: params.replyToMessageId,
    clientGeneratedId:
      params.clientGeneratedId ?? `client-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
  };
}

export function isGraduationYearGroupTitle(title: string) {
  return /^class of \d{4}$/i.test(title.trim());
}

export function isGraduationYearGroupThread(
  thread: Pick<MessageThreadSummary | MessageThreadDetail, 'type' | 'title'>,
) {
  return thread.type === 'group' && isGraduationYearGroupTitle(thread.title);
}

export function sortMessageThreads(threads: MessageThreadSummary[]) {
  return [...threads].sort((left, right) => {
    if (left.isPinned !== right.isPinned) {
      return left.isPinned ? -1 : 1;
    }

    return new Date(right.lastActivityAt).getTime() - new Date(left.lastActivityAt).getTime();
  });
}

export function filterMessageThreads(
  threads: MessageThreadSummary[],
  filter: MessageThreadFilter,
  query: string,
  selectedThreadId: string | null,
) {
  const normalizedQuery = query.trim().toLowerCase();

  return sortMessageThreads(threads).filter((thread) => {
    const matchesFilter =
      filter === 'all' ||
      (filter === 'unread' && thread.unreadCount > 0) ||
      (filter === 'pinned' && thread.isPinned);

    if (!matchesFilter) return false;
    if (!normalizedQuery) return true;

    const searchTarget = [
      thread.title,
      thread.subtitle,
      thread.topic,
      thread.lastMessagePreview,
      thread.category,
      thread.participants.map((participant) => participant.fullName).join(' '),
    ]
      .join(' ')
      .toLowerCase();

    return searchTarget.includes(normalizedQuery);
  });
}

export function describeAttachmentForPreview(attachment: MessageAttachment) {
  if (attachment.kind === 'audio') return `Audio · ${attachment.fileName}`;
  if (attachment.kind === 'image') return `Image · ${attachment.fileName}`;
  return `File · ${attachment.fileName}`;
}
