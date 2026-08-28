import {
  useEffect,
  useMemo,
  useRef,
  useState,
  type ChangeEvent,
  type ClipboardEvent,
  type DragEvent,
} from 'react';
import {
  Grid2X2,
  Image as ImageIcon,
  Info,
  List,
  Pencil,
  Plus,
  RotateCcw,
  Trash2,
} from 'lucide-react';
import { useQueryClient } from '@tanstack/react-query';
import { TextareaInput } from '@/shared/components/ui/TextAreaInput';
import { BaseInput } from '@/shared/components/ui/input/BaseInput';
import { DeleteConfirmModal } from '@/features/events/components/DeleteConfirmModal';
import { toast } from '@/shared/components/ui/Toast';
import {
  homepageContentKeys,
  useAdminHomepageContent,
  useCreateCarouselImage,
  useDeleteCarouselImage,
  useReorderCarousel,
  useUpdateCarouselImage,
  useUpdateHomepageText,
} from '@/features/homepage/hooks/useHomepageContent';
import {
  buildHeroTitleWithAnimation,
  parseHeroTitleAnimation,
} from '@/features/homepage/utils/heroTitleAnimation';
import { DragHandle } from './DragHandle';
import type { HomepageImage, PagesContentTab } from './types';

type ImageUploadIntent = { type: 'add' } | { type: 'replace'; imageId: string };
type CarouselViewMode = 'grid' | 'list';

const HERO_CAROUSEL_IMAGE_ACCEPT = 'image/png,image/jpeg,image/jpg,image/webp';
const HERO_CAROUSEL_ALLOWED_MIME_TYPES = new Set([
  'image/png',
  'image/jpeg',
  'image/jpg',
  'image/webp',
]);
const HERO_CAROUSEL_RECOMMENDED_WIDTH = 1920;
const HERO_CAROUSEL_RECOMMENDED_HEIGHT = 900;
const HERO_CAROUSEL_MIN_WIDTH = 1440;
const HERO_CAROUSEL_MIN_HEIGHT = 675;
const HERO_CAROUSEL_TARGET_SIZE_MB = 2;
const HERO_CAROUSEL_MAX_SIZE_MB = 5;
const HERO_CAROUSEL_MAX_SIZE_BYTES = HERO_CAROUSEL_MAX_SIZE_MB * 1024 * 1024;
const DEFAULT_HERO_GREETING_TITLE = 'Welcome home';
const DEFAULT_HERO_GREETING_ALTERNATE_WORD = 'members';
const DEFAULT_HERO_GREETING_MESSAGE =
  'A global community of alumni connected by shared memories, driven by purpose, and committed to lifting the next generation.';

function normalizeAlternateLastWord(value: string) {
  return value.trim().split(/\s+/)[0] ?? '';
}

const heroCarouselImageSpecs = [
  {
    label: 'Recommended',
    value: `${HERO_CAROUSEL_RECOMMENDED_WIDTH} x ${HERO_CAROUSEL_RECOMMENDED_HEIGHT} px`,
    detail: `Minimum upload: ${HERO_CAROUSEL_MIN_WIDTH} x ${HERO_CAROUSEL_MIN_HEIGHT} px.`,
  },
  {
    label: 'Aspect ratio',
    value: 'Wide landscape',
    detail: 'Keep faces, logos, and key detail near the center.',
  },
  {
    label: 'File type',
    value: 'JPG or WebP',
    detail: 'PNG is also accepted for artwork that needs it.',
  },
  {
    label: 'File size',
    value: `${HERO_CAROUSEL_TARGET_SIZE_MB} MB target`,
    detail: `${HERO_CAROUSEL_MAX_SIZE_MB} MB maximum upload size.`,
  },
];

type AdminHomepageImage = HomepageImage & {
  isNew?: boolean;
  localFile?: File;
  replacementFile?: File;
};

function normalizeCarouselOrder<T extends HomepageImage>(images: T[]): T[] {
  return images.map((image, index) => ({
    ...image,
    sortOrder: index + 1,
  }));
}

function fileToDataUrl(file: File) {
  return new Promise<string>((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = () => resolve(String(reader.result ?? ''));
    reader.onerror = () => reject(reader.error ?? new Error('Unable to read selected image.'));
    reader.readAsDataURL(file);
  });
}

function getImageDimensions(file: File) {
  return new Promise<{ width: number; height: number }>((resolve, reject) => {
    const objectUrl = URL.createObjectURL(file);
    const image = new Image();

    image.onload = () => {
      URL.revokeObjectURL(objectUrl);
      resolve({ width: image.naturalWidth, height: image.naturalHeight });
    };

    image.onerror = () => {
      URL.revokeObjectURL(objectUrl);
      reject(new Error('Unable to read selected image dimensions.'));
    };

    image.src = objectUrl;
  });
}

function createLocalCarouselImage(file: File, src: string, sortOrder: number): AdminHomepageImage {
  return {
    id: `carousel-local-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
    src,
    fileName: file.name,
    altText: file.name,
    isHidden: false,
    showGreetingMessage: true,
    isNew: true,
    localFile: file,
    sortOrder,
  };
}

function getComparableHomepageImages(images: AdminHomepageImage[]) {
  return normalizeCarouselOrder(images).map((image) => ({
    id: image.id,
    isHidden: image.isHidden,
    showGreetingMessage: image.showGreetingMessage,
    sortOrder: image.sortOrder,
    hasLocalChange: Boolean(image.isNew || image.localFile || image.replacementFile),
  }));
}

function areComparableValuesEqual(left: unknown, right: unknown) {
  return JSON.stringify(left) === JSON.stringify(right);
}

function getImageAltText(image: Pick<AdminHomepageImage, 'altText' | 'fileName'>) {
  return image.altText || image.fileName || '';
}

function clampPosition(position: number, total: number) {
  return Math.min(Math.max(position, 1), Math.max(total, 1));
}

function moveImageToIndex<T extends HomepageImage>(
  images: T[],
  imageId: string,
  targetIndex: number,
) {
  const fromIndex = images.findIndex((image) => image.id === imageId);

  if (
    fromIndex < 0 ||
    targetIndex < 0 ||
    targetIndex >= images.length ||
    fromIndex === targetIndex
  ) {
    return images;
  }

  const nextImages = [...images];
  const [movedImage] = nextImages.splice(fromIndex, 1);
  nextImages.splice(targetIndex, 0, movedImage);
  return normalizeCarouselOrder(nextImages);
}

function PositionInput({
  imageId,
  position,
  total,
  onCommit,
}: {
  imageId: string;
  position: number;
  total: number;
  onCommit: (imageId: string, position: number) => void;
}) {
  const [draftPosition, setDraftPosition] = useState(String(position));

  useEffect(() => {
    setDraftPosition(String(position));
  }, [position]);

  const revertPosition = () => setDraftPosition(String(position));

  const commitPosition = () => {
    if (!/^\d+$/.test(draftPosition)) {
      revertPosition();
      return;
    }

    const nextPosition = clampPosition(Number(draftPosition), total);
    setDraftPosition(String(nextPosition));
    onCommit(imageId, nextPosition);
  };

  return (
    <input
      type="text"
      inputMode="numeric"
      pattern="[0-9]*"
      aria-label={`Position, editable, currently ${position} of ${total}`}
      value={draftPosition}
      onChange={(event) => {
        const nextValue = event.target.value;
        if (/^\d*$/.test(nextValue)) {
          setDraftPosition(nextValue);
        }
      }}
      onBlur={commitPosition}
      onKeyDown={(event) => {
        if (event.key === 'Enter') {
          event.preventDefault();
          event.currentTarget.blur();
        }

        if (event.key === 'Escape') {
          event.preventDefault();
          revertPosition();
          event.currentTarget.blur();
        }
      }}
      className="h-10 w-14 rounded-full border border-cms-tab-active/25 bg-white text-center text-lg font-semibold text-gray-950 shadow-sm transition focus:border-cms-tab-active focus:outline-none focus:ring-4 focus:ring-primary-500/15"
    />
  );
}

function ImageCardActions({
  isHidden,
  onDelete,
  onEdit,
  onToggleHidden,
  className = '',
}: {
  isHidden: boolean;
  onDelete: () => void;
  onEdit: () => void;
  onToggleHidden: () => void;
  className?: string;
}) {
  return (
    <div
      className={[
        'flex items-center justify-end gap-5 border-t border-gray-200 pt-4 text-gray-500 lg:absolute lg:left-[411px] lg:top-[448px] lg:h-6 lg:w-[173px] lg:gap-6 lg:border-t-0 lg:pt-0',
        className,
      ].join(' ')}
    >
      <button
        type="button"
        onClick={onToggleHidden}
        className="inline-flex items-center gap-2 text-sm font-semibold text-gray-500 transition-colors hover:text-cms-tab-active"
        aria-pressed={isHidden}
      >
        <span
          className={[
            'relative h-6 w-10 rounded-full transition-colors',
            isHidden ? 'bg-cms-tab-active/70' : 'bg-[#BDBDBD]',
          ].join(' ')}
        >
          <span
            className={[
              'absolute top-1/2 h-7 w-7 -translate-y-1/2 rounded-full bg-white shadow-[0_3px_8px_rgba(0,0,0,0.18)] ring-1 ring-black/5 transition-transform',
              isHidden ? 'left-[14px]' : '-left-1',
            ].join(' ')}
          />
        </span>
        Hide
      </button>
      <button
        type="button"
        onClick={onEdit}
        className="text-gray-500 transition-colors hover:text-cms-tab-active"
        aria-label="Edit carousel image"
      >
        <Pencil className="h-[22px] w-[22px]" />
      </button>
      <button
        type="button"
        onClick={onDelete}
        className="text-gray-500 transition-colors hover:text-red-500"
        aria-label="Delete carousel image"
      >
        <Trash2 className="h-[22px] w-[22px]" />
      </button>
    </div>
  );
}

function GreetingVisibilityCheckbox({
  checked,
  onChange,
  className = '',
}: {
  checked: boolean;
  onChange: (checked: boolean) => void;
  className?: string;
}) {
  return (
    <label
      className={[
        'inline-flex cursor-pointer items-center gap-2.5 text-sm font-semibold text-gray-500 transition-colors hover:text-cms-tab-active',
        className,
      ].join(' ')}
    >
      <input
        type="checkbox"
        checked={checked}
        onChange={(event) => {
          console.log('Show greeting checkbox checked:', event.target.checked);
          onChange(event.target.checked);
        }}
        className="h-5 w-5 rounded border-gray-300 text-cms-tab-active accent-cms-tab-active focus:ring-cms-tab-active/25"
      />
      <span>Show the greeting message on this image</span>
    </label>
  );
}

export function HomeContentPanel({ activeTab }: { activeTab: PagesContentTab }) {
  const queryClient = useQueryClient();
  const fileInputRef = useRef<HTMLInputElement>(null);
  const carouselScrollRef = useRef<HTMLDivElement>(null);
  const autoScrollFrameRef = useRef<number | null>(null);
  const autoScrollSpeedRef = useRef(0);
  const { data: homepageContent, isLoading, isError, error } = useAdminHomepageContent();
  const updateHomepageText = useUpdateHomepageText();
  const createCarouselImage = useCreateCarouselImage();
  const updateCarouselImage = useUpdateCarouselImage();
  const reorderCarousel = useReorderCarousel();
  const deleteCarouselImage = useDeleteCarouselImage();
  const [orderedImages, setOrderedImages] = useState<AdminHomepageImage[]>([]);
  const [draggedImageId, setDraggedImageId] = useState<string | null>(null);
  const [dropTargetImageId, setDropTargetImageId] = useState<string | null>(null);
  const [imageUploadIntent, setImageUploadIntent] = useState<ImageUploadIntent | null>(null);
  const [greetingTitle, setGreetingTitle] = useState('');
  const [greetingTitleAlternateWord, setGreetingTitleAlternateWord] = useState('');
  const [greetingMessage, setGreetingMessage] = useState('');
  const [saveStatus, setSaveStatus] = useState('');
  const [deletedImageIds, setDeletedImageIds] = useState<string[]>([]);
  const [pendingDeleteImage, setPendingDeleteImage] = useState<HomepageImage | null>(null);
  const [carouselViewMode, setCarouselViewMode] = useState<CarouselViewMode>(() => {
    if (typeof window === 'undefined') return 'list';
    return window.sessionStorage.getItem('home-carousel-view') === 'grid' ? 'grid' : 'list';
  });

  const stopAutoScroll = () => {
    autoScrollSpeedRef.current = 0;
    if (autoScrollFrameRef.current !== null) {
      window.cancelAnimationFrame(autoScrollFrameRef.current);
      autoScrollFrameRef.current = null;
    }
  };

  const continueAutoScroll = () => {
    const container = carouselScrollRef.current;
    const speed = autoScrollSpeedRef.current;

    if (!container || speed === 0) {
      stopAutoScroll();
      return;
    }

    container.scrollLeft += speed;
    autoScrollFrameRef.current = window.requestAnimationFrame(continueAutoScroll);
  };

  const updateAutoScroll = (event: DragEvent<HTMLElement>) => {
    const container = carouselScrollRef.current;
    if (!container) return;

    const triggerZone = 60;
    const rect = container.getBoundingClientRect();
    const distanceFromLeft = event.clientX - rect.left;
    const distanceFromRight = rect.right - event.clientX;
    let nextSpeed = 0;

    if (distanceFromLeft < triggerZone) {
      nextSpeed = -Math.max(4, ((triggerZone - distanceFromLeft) / triggerZone) * 22);
    } else if (distanceFromRight < triggerZone) {
      nextSpeed = Math.max(4, ((triggerZone - distanceFromRight) / triggerZone) * 22);
    }

    autoScrollSpeedRef.current = nextSpeed;
    if (nextSpeed !== 0 && autoScrollFrameRef.current === null) {
      autoScrollFrameRef.current = window.requestAnimationFrame(continueAutoScroll);
    } else if (nextSpeed === 0) {
      stopAutoScroll();
    }
  };

  useEffect(() => {
    if (!homepageContent) return;

    setOrderedImages(
      normalizeCarouselOrder(
        homepageContent.carouselImages.map((image) => ({
          id: image.id,
          src: image.imageUrl,
          fileName: image.fileName,
          altText: image.altText,
          isHidden: image.isHidden,
          showGreetingMessage: image.showGreetingMessage,
          sortOrder: image.sortOrder + 1,
        })),
      ),
    );
    const parsedGreetingTitle = parseHeroTitleAnimation(homepageContent.greetingTitle);
    setGreetingTitle(parsedGreetingTitle.displayTitle);
    setGreetingTitleAlternateWord(
      parsedGreetingTitle.hasStoredAlternate ? parsedGreetingTitle.alternateLastWord : '',
    );
    setGreetingMessage(homepageContent.greetingMessage);
    setDeletedImageIds([]);
    setSaveStatus('');
  }, [homepageContent]);

  useEffect(() => stopAutoScroll, []);

  useEffect(() => {
    window.sessionStorage.setItem('home-carousel-view', carouselViewMode);
  }, [carouselViewMode]);

  const moveImage = (fromId: string, toId: string) => {
    if (fromId === toId) return;

    setOrderedImages((currentImages) => {
      const fromIndex = currentImages.findIndex((image) => image.id === fromId);
      const toIndex = currentImages.findIndex((image) => image.id === toId);

      if (fromIndex < 0 || toIndex < 0) return currentImages;

      return moveImageToIndex(currentImages, fromId, toIndex);
    });
    setSaveStatus('');
  };

  const moveImageToPosition = (imageId: string, position: number) => {
    setOrderedImages((currentImages) =>
      moveImageToIndex(currentImages, imageId, clampPosition(position, currentImages.length) - 1),
    );
    setSaveStatus('');
  };

  const startDrag = (event: DragEvent<HTMLElement>, imageId: string) => {
    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData('text/plain', imageId);
    setDraggedImageId(imageId);
  };

  const dragOverImage = (event: DragEvent<HTMLElement>, imageId: string) => {
    event.preventDefault();
    event.dataTransfer.dropEffect = 'move';
    updateAutoScroll(event);
    setDropTargetImageId(imageId);
  };

  const dropOnImage = (event: DragEvent<HTMLElement>, imageId: string) => {
    event.preventDefault();
    const sourceImageId = event.dataTransfer.getData('text/plain') || draggedImageId;
    if (sourceImageId) moveImage(sourceImageId, imageId);
    setDraggedImageId(null);
    setDropTargetImageId(null);
    stopAutoScroll();
  };

  const endDrag = () => {
    setDraggedImageId(null);
    setDropTargetImageId(null);
    stopAutoScroll();
  };

  const openImagePicker = (intent: ImageUploadIntent) => {
    setImageUploadIntent(intent);
    fileInputRef.current?.click();
  };

  const handleImageFileChange = async (event: ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0];
    event.target.value = '';

    if (!file || !imageUploadIntent) return;

    if (!HERO_CAROUSEL_ALLOWED_MIME_TYPES.has(file.type.toLowerCase())) {
      toast.error('Use a JPG, PNG, or WebP image for the homepage carousel.');
      setImageUploadIntent(null);
      return;
    }

    if (file.size > HERO_CAROUSEL_MAX_SIZE_BYTES) {
      toast.error(`Homepage carousel images must be ${HERO_CAROUSEL_MAX_SIZE_MB} MB or smaller.`);
      setImageUploadIntent(null);
      return;
    }

    let dimensions;
    try {
      dimensions = await getImageDimensions(file);
    } catch {
      toast.error('We could not read this image. Please choose a different file.');
      setImageUploadIntent(null);
      return;
    }

    if (
      dimensions.width < HERO_CAROUSEL_MIN_WIDTH ||
      dimensions.height < HERO_CAROUSEL_MIN_HEIGHT
    ) {
      toast.error(
        `Homepage carousel images must be at least ${HERO_CAROUSEL_MIN_WIDTH} x ${HERO_CAROUSEL_MIN_HEIGHT} px. Selected image is ${dimensions.width} x ${dimensions.height} px.`,
      );
      setImageUploadIntent(null);
      return;
    }

    const src = await fileToDataUrl(file);

    if (imageUploadIntent.type === 'add') {
      setOrderedImages((currentImages) =>
        normalizeCarouselOrder([
          ...currentImages,
          createLocalCarouselImage(file, src, currentImages.length + 1),
        ]),
      );
    } else {
      setOrderedImages((currentImages) =>
        normalizeCarouselOrder(
          currentImages.map((image) =>
            image.id === imageUploadIntent.imageId
              ? {
                  ...image,
                  src,
                  fileName: file.name,
                  altText: image.altText || file.name,
                  replacementFile: file,
                }
              : image,
          ),
        ),
      );
    }

    setImageUploadIntent(null);
    setSaveStatus('');
  };

  const deleteImage = (imageId: string) => {
    setOrderedImages((currentImages) => {
      const imageToDelete = currentImages.find((image) => image.id === imageId);
      if (imageToDelete && !imageToDelete.isNew) {
        setDeletedImageIds((currentIds) =>
          currentIds.includes(imageToDelete.id) ? currentIds : [...currentIds, imageToDelete.id],
        );
      }

      return normalizeCarouselOrder(currentImages.filter((image) => image.id !== imageId));
    });
    setPendingDeleteImage(null);
    setSaveStatus('');
  };

  const toggleImageHidden = (imageId: string) => {
    setOrderedImages((currentImages) =>
      currentImages.map((image) =>
        image.id === imageId ? { ...image, isHidden: !image.isHidden } : image,
      ),
    );
    setSaveStatus('');
  };

  const setImageGreetingMessageVisibility = (imageId: string, showGreetingMessage: boolean) => {
    let preventedLastVisibleGreeting = false;

    setOrderedImages((currentImages) => {
      const visibleGreetingCount = currentImages.filter(
        (image) => image.showGreetingMessage,
      ).length;

      if (!showGreetingMessage && visibleGreetingCount <= 1) {
        preventedLastVisibleGreeting = true;
        return currentImages;
      }

      return currentImages.map((image) =>
        image.id === imageId ? { ...image, showGreetingMessage } : image,
      );
    });

    if (preventedLastVisibleGreeting) {
      toast.error('At least one carousel image must show the greeting message.');
      return;
    }

    setSaveStatus('');
  };

  const applyDefaultHeroGreeting = () => {
    setGreetingTitle(DEFAULT_HERO_GREETING_TITLE);
    setGreetingTitleAlternateWord(DEFAULT_HERO_GREETING_ALTERNATE_WORD);
    setGreetingMessage(DEFAULT_HERO_GREETING_MESSAGE);
    setSaveStatus('');
  };

  const handleAlternateWordPaste = (event: ClipboardEvent<HTMLInputElement>) => {
    event.preventDefault();
    setGreetingTitleAlternateWord(normalizeAlternateLastWord(event.clipboardData.getData('text')));
    setSaveStatus('');
  };

  const isSaving =
    updateHomepageText.isPending ||
    createCarouselImage.isPending ||
    updateCarouselImage.isPending ||
    reorderCarousel.isPending ||
    deleteCarouselImage.isPending;

  const hasHomepageChanges = useMemo(() => {
    const savedGreetingTitle = buildHeroTitleWithAnimation(
      greetingTitle,
      greetingTitleAlternateWord,
    );

    if (!homepageContent) {
      return Boolean(
        savedGreetingTitle ||
        greetingMessage.trim() ||
        orderedImages.length > 0 ||
        deletedImageIds.length > 0,
      );
    }

    const hasTextChanges =
      savedGreetingTitle !== homepageContent.greetingTitle.trim() ||
      greetingMessage.trim() !== homepageContent.greetingMessage.trim();
    const hasDeletedImages = deletedImageIds.length > 0;
    const currentImages = getComparableHomepageImages(orderedImages);
    const originalImages = getComparableHomepageImages(
      homepageContent.carouselImages.map((image) => ({
        id: image.id,
        src: image.imageUrl,
        fileName: image.fileName,
        altText: image.altText,
        isHidden: image.isHidden,
        showGreetingMessage: image.showGreetingMessage,
        sortOrder: image.sortOrder + 1,
      })),
    );
    const hasImageChanges =
      currentImages.some((image) => image.hasLocalChange) ||
      !areComparableValuesEqual(
        currentImages.map(({ hasLocalChange, ...image }) => image),
        originalImages.map(({ hasLocalChange, ...image }) => image),
      );

    return hasTextChanges || hasDeletedImages || hasImageChanges;
  }, [
    deletedImageIds.length,
    greetingMessage,
    greetingTitle,
    greetingTitleAlternateWord,
    homepageContent,
    orderedImages,
  ]);

  const saveHomepageContent = async () => {
    if (!hasHomepageChanges) return;

    try {
      setSaveStatus('');

      await updateHomepageText.mutateAsync({
        greetingTitle: buildHeroTitleWithAnimation(greetingTitle, greetingTitleAlternateWord),
        greetingMessage: greetingMessage.trim(),
      });

      await Promise.all(deletedImageIds.map((imageId) => deleteCarouselImage.mutateAsync(imageId)));

      const persistedImages = [];
      const originalImagesById = new Map(
        homepageContent?.carouselImages.map((image) => [image.id, image]) ?? [],
      );
      const imagesToPersist = normalizeCarouselOrder(orderedImages).map((image, index) => ({
        image,
        index,
      }));

      for (const { image, index } of imagesToPersist) {
        if (image.isNew && image.localFile) {
          const createdImage = await createCarouselImage.mutateAsync({
            image: image.localFile,
            altText: image.altText || image.fileName,
            sortOrder: index,
            isHidden: image.isHidden,
          });

          await updateCarouselImage.mutateAsync({
            id: createdImage.id,
            altText: image.altText || image.fileName,
            isHidden: image.isHidden,
            showGreetingMessage: image.showGreetingMessage,
          });

          persistedImages.push({ id: createdImage.id, sortOrder: index });
          continue;
        }

        const originalImage = originalImagesById.get(image.id);
        const hasMetadataChanges =
          !originalImage ||
          getImageAltText(image) !==
            getImageAltText({
              altText: originalImage.altText,
              fileName: originalImage.fileName,
            }) ||
          image.isHidden !== originalImage.isHidden ||
          image.showGreetingMessage !== originalImage.showGreetingMessage;

        if (image.replacementFile) {
          const updatedImage = await updateCarouselImage.mutateAsync({
            id: image.id,
            image: image.replacementFile,
            altText: getImageAltText(image),
            isHidden: image.isHidden,
            showGreetingMessage: image.showGreetingMessage,
          });
          persistedImages.push({
            id: updatedImage.id || image.id,
            sortOrder: index,
          });
          continue;
        }

        if (hasMetadataChanges) {
          await updateCarouselImage.mutateAsync({
            id: image.id,
            altText: getImageAltText(image),
            isHidden: image.isHidden,
            showGreetingMessage: image.showGreetingMessage,
          });
        }
        persistedImages.push({ id: image.id, sortOrder: index });
      }

      if (persistedImages.length > 0) {
        await reorderCarousel.mutateAsync(persistedImages);
      }

      await queryClient.invalidateQueries({
        queryKey: homepageContentKeys.all,
      });
      setDeletedImageIds([]);
      setSaveStatus('Homepage content updated successfully.');
      toast.success('Homepage content updated successfully.');
    } catch (saveError) {
      console.error('Homepage content update failed:', saveError);
      setSaveStatus('');
      toast.error('We could not update homepage content. Please try again.');
    }
  };

  return (
    <>
      <div
        className={[
          'animate-slide-up space-y-10 rounded-[1.75rem] bg-white px-5 py-7 shadow-sm sm:px-6 lg:min-h-[1038px] lg:px-6',
          activeTab === 'home' ? 'rounded-tl-none' : '',
        ].join(' ')}
      >
        <section>
          <div className="mb-6 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <p className="text-[20px] font-medium leading-none tracking-[0.03em] text-cms-tab-inactive">
              Upload and organise the carousel images displayed on the homepage.
            </p>

            <div className="flex flex-wrap items-center gap-3">
              <div
                className="inline-flex rounded-full border border-cms-tab-active/20 bg-cms-surface p-1"
                aria-label="Carousel view"
              >
                {(['grid', 'list'] as const).map((mode) => {
                  const isActive = carouselViewMode === mode;
                  const Icon = mode === 'grid' ? Grid2X2 : List;

                  return (
                    <button
                      key={mode}
                      type="button"
                      onClick={() => setCarouselViewMode(mode)}
                      className={[
                        'inline-flex h-9 items-center gap-2 rounded-full px-3 text-sm font-semibold transition-colors',
                        isActive
                          ? 'bg-white text-cms-tab-active shadow-sm'
                          : 'text-gray-500 hover:text-cms-tab-active',
                      ].join(' ')}
                      aria-pressed={isActive}
                    >
                      <Icon className="h-4 w-4" />
                      {mode === 'grid' ? 'Grid' : 'List'}
                    </button>
                  );
                })}
              </div>

              <button
                type="button"
                disabled={isSaving}
                onClick={() => openImagePicker({ type: 'add' })}
                className="inline-flex w-fit items-center gap-2 rounded-full border-2 border-primary-500 px-4 py-[5px] text-base font-semibold text-primary-500 transition-all duration-200 hover:bg-primary-50 disabled:cursor-not-allowed disabled:opacity-60"
              >
                <Plus className="h-5 w-5" />
                Add new image
              </button>
            </div>
          </div>

          {isLoading ? (
            <p className="mb-4 text-sm font-medium text-gray-500">Loading homepage content...</p>
          ) : null}

          {isError ? (
            <p className="mb-4 text-sm font-medium text-red-600">
              Homepage content could not be loaded. You can still add content and try saving.
              {error instanceof Error ? ` ${error.message}` : ''}
            </p>
          ) : null}

          <input
            ref={fileInputRef}
            type="file"
            accept={HERO_CAROUSEL_IMAGE_ACCEPT}
            className="hidden"
            onChange={handleImageFileChange}
          />

          <aside className="mb-6 rounded-[1.25rem] border border-cms-tab-active/10 bg-cms-surface px-4 py-4 sm:px-5">
            <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
              <div className="flex gap-3">
                <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white text-cms-tab-active shadow-sm">
                  <ImageIcon className="h-5 w-5" aria-hidden="true" />
                </span>
                <div>
                  <h3 className="text-base font-semibold text-cms-tab-active">
                    Hero carousel image specs
                  </h3>
                  <p className="mt-1 max-w-2xl text-sm leading-6 text-gray-500">
                    These images fill the homepage hero on desktop and mobile, so upload a clean
                    landscape image with important detail away from the edges.
                  </p>
                </div>
              </div>

              <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                {heroCarouselImageSpecs.map((spec) => (
                  <div
                    key={spec.label}
                    className="rounded-2xl bg-white px-4 py-3 shadow-sm ring-1 ring-cms-tab-active/5"
                  >
                    <p className="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-[0.08em] text-gray-400">
                      <Info className="h-3.5 w-3.5" aria-hidden="true" />
                      {spec.label}
                    </p>
                    <p className="mt-1 text-sm font-bold text-gray-900">{spec.value}</p>
                    <p className="mt-1 text-xs leading-5 text-gray-500">{spec.detail}</p>
                  </div>
                ))}
              </div>
            </div>
          </aside>

          <div
            ref={carouselScrollRef}
            onDragOver={updateAutoScroll}
            onDragLeave={stopAutoScroll}
            className={[
              'scrollbar-hide overflow-x-auto scroll-smooth pb-3 [-webkit-overflow-scrolling:touch]',
              carouselViewMode === 'grid' ? 'flex snap-x gap-4' : 'space-y-3',
            ].join(' ')}
          >
            {!isLoading && orderedImages.length === 0 ? (
              <div className="flex min-h-[12rem] w-full items-center justify-center rounded-xl border border-dashed border-cms-tab-active/35 bg-white text-sm font-medium text-gray-500">
                No carousel images yet. Add an image to start building the homepage carousel.
              </div>
            ) : null}

            {orderedImages.map((image) => {
              const isDragging = draggedImageId === image.id;
              const isDropTarget = dropTargetImageId === image.id && draggedImageId !== image.id;

              return carouselViewMode === 'grid' ? (
                <article
                  key={image.id}
                  draggable
                  onDragStart={(event) => startDrag(event, image.id)}
                  onDragOver={(event) => dragOverImage(event, image.id)}
                  onDragLeave={() => {
                    setDropTargetImageId((currentId) =>
                      currentId === image.id ? null : currentId,
                    );
                  }}
                  onDrop={(event) => dropOnImage(event, image.id)}
                  onDragEnd={endDrag}
                  className={[
                    'relative flex min-h-[21.6rem] w-full shrink-0 snap-start flex-col rounded-xl border border-cms-tab-active/25 bg-white p-5 transition-all duration-300 hover:-translate-y-1 hover:shadow-lg sm:max-w-[34.2rem] lg:h-[439px] lg:w-[547px] lg:max-w-none lg:p-0',
                    isDragging ? 'scale-[0.98] opacity-60' : '',
                    isDropTarget
                      ? 'border-cms-tab-active shadow-lg ring-2 ring-cms-tab-active/20'
                      : '',
                  ].join(' ')}
                >
                  <div className="mx-auto lg:absolute lg:left-1/2 lg:top-2.5 lg:-translate-x-1/2">
                    <DragHandle />
                  </div>
                  <div className="mt-5 lg:absolute lg:left-[22px] lg:top-[32px] lg:mt-0">
                    <PositionInput
                      imageId={image.id}
                      position={image.sortOrder}
                      total={orderedImages.length}
                      onCommit={moveImageToPosition}
                    />
                  </div>
                  <img
                    src={image.src}
                    alt=""
                    className={[
                      'mt-7 aspect-[7/4] w-full rounded-[6px] object-cover transition-opacity lg:absolute lg:left-[22px] lg:top-[79px] lg:mt-0 lg:h-[288px] lg:w-[504px]',
                      image.isHidden ? 'opacity-45 grayscale' : '',
                    ].join(' ')}
                  />
                  <GreetingVisibilityCheckbox
                    checked={image.showGreetingMessage}
                    onChange={(checked) => setImageGreetingMessageVisibility(image.id, checked)}
                    className="mt-5 lg:absolute lg:left-[22px] lg:top-[403px] lg:mt-0 lg:max-w-[20rem]"
                  />
                  <ImageCardActions
                    isHidden={image.isHidden}
                    onDelete={() => setPendingDeleteImage(image)}
                    onEdit={() => openImagePicker({ type: 'replace', imageId: image.id })}
                    onToggleHidden={() => toggleImageHidden(image.id)}
                    className="lg:left-[370px] lg:top-[403px] lg:w-[156px]"
                  />
                </article>
              ) : (
                <article
                  key={image.id}
                  draggable
                  onDragStart={(event) => startDrag(event, image.id)}
                  onDragOver={(event) => dragOverImage(event, image.id)}
                  onDragLeave={() => {
                    setDropTargetImageId((currentId) =>
                      currentId === image.id ? null : currentId,
                    );
                  }}
                  onDrop={(event) => dropOnImage(event, image.id)}
                  onDragEnd={endDrag}
                  className={[
                    'grid min-w-[66rem] grid-cols-[2.5rem_4.5rem_5rem_minmax(12rem,1fr)_17rem_7rem_8rem] items-center gap-3 rounded-xl border border-cms-tab-active/20 bg-white px-4 py-3 transition-all',
                    isDragging ? 'opacity-60' : '',
                    isDropTarget
                      ? 'border-cms-tab-active shadow-md ring-2 ring-cms-tab-active/20'
                      : 'hover:border-primary-200 hover:shadow-sm',
                  ].join(' ')}
                >
                  <DragHandle />
                  <PositionInput
                    imageId={image.id}
                    position={image.sortOrder}
                    total={orderedImages.length}
                    onCommit={moveImageToPosition}
                  />
                  <div className="h-14 w-20 overflow-hidden rounded-md border border-gray-100 bg-cms-surface">
                    {image.src ? (
                      <img
                        src={image.src}
                        alt=""
                        className={[
                          'h-full w-full object-cover',
                          image.isHidden ? 'opacity-45 grayscale' : '',
                        ].join(' ')}
                      />
                    ) : null}
                  </div>
                  <p
                    className={[
                      'truncate text-sm font-semibold text-gray-800',
                      image.src ? '' : 'text-gray-400',
                    ].join(' ')}
                    title={image.fileName || image.altText || `Carousel image ${image.sortOrder}`}
                  >
                    {image.fileName || image.altText || `Carousel image ${image.sortOrder}`}
                  </p>
                  <GreetingVisibilityCheckbox
                    checked={image.showGreetingMessage}
                    onChange={(checked) => setImageGreetingMessageVisibility(image.id, checked)}
                    className="text-xs"
                  />
                  <button
                    type="button"
                    onClick={() => toggleImageHidden(image.id)}
                    className={[
                      'w-fit rounded-full px-3 py-1 text-xs font-semibold',
                      image.isHidden
                        ? 'bg-gray-100 text-gray-500'
                        : 'bg-primary-50 text-cms-tab-active',
                    ].join(' ')}
                    aria-pressed={image.isHidden}
                  >
                    {image.isHidden ? 'Hidden' : 'Visible'}
                  </button>
                  <div className="flex items-center justify-end gap-4">
                    <button
                      type="button"
                      onClick={() => openImagePicker({ type: 'replace', imageId: image.id })}
                      className="text-gray-500 transition-colors hover:text-cms-tab-active"
                      aria-label="Edit carousel image"
                    >
                      <Pencil className="h-5 w-5" />
                    </button>
                    <button
                      type="button"
                      onClick={() => setPendingDeleteImage(image)}
                      className="text-gray-500 transition-colors hover:text-red-500"
                      aria-label="Delete carousel image"
                    >
                      <Trash2 className="h-5 w-5" />
                    </button>
                  </div>
                </article>
              );
            })}
          </div>
        </section>

        <section className="space-y-7">
          <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <p className="text-[20px] font-medium leading-snug tracking-[0.03em] text-cms-tab-inactive">
              Edit the greeting message displayed on the homepage.
            </p>
            <button
              type="button"
              onClick={applyDefaultHeroGreeting}
              disabled={isSaving}
              className="inline-flex w-fit items-center gap-2 rounded-full border border-primary-500/20 bg-white px-4 py-2 text-sm font-bold text-primary-500 shadow-sm transition-all duration-200 hover:border-primary-500/35 hover:bg-primary-50 disabled:cursor-not-allowed disabled:opacity-60"
            >
              <RotateCcw className="h-4 w-4" aria-hidden="true" />
              Use default message
            </button>
          </div>

          <BaseInput
            id="greeting-title"
            label="Greeting Title"
            placeholder="Welcome home"
            value={greetingTitle}
            required
            onValueChange={(value) => {
              setGreetingTitle(value);
              setSaveStatus('');
            }}
            className="max-w-3xl gap-3"
            labelClassName="!text-base !font-medium !leading-none !text-[#858585]"
            controlClassName="!min-h-12 !rounded-full !border-0 !bg-cms-surface !shadow-none focus-within:!border-transparent focus-within:!outline focus-within:!outline-3 focus-within:!outline-primary-500/20"
            inputClassName="!h-12 !px-4 !text-base !font-normal !text-gray-900 placeholder:!text-[#858585] placeholder:!opacity-100"
          />

          <div className="max-w-3xl space-y-2">
            <BaseInput
              id="greeting-title-alternate-word"
              label="Alternate Last Word"
              placeholder="members"
              value={greetingTitleAlternateWord}
              onValueChange={(value) => {
                setGreetingTitleAlternateWord(normalizeAlternateLastWord(value));
                setSaveStatus('');
              }}
              onKeyDown={(event) => {
                if (/\s/.test(event.key)) {
                  event.preventDefault();
                }
              }}
              onPaste={handleAlternateWordPaste}
              className="gap-3"
              labelClassName="!text-base !font-medium !leading-none !text-[#858585]"
              controlClassName="!min-h-12 !rounded-full !border-0 !bg-cms-surface !shadow-none focus-within:!border-transparent focus-within:!outline focus-within:!outline-3 focus-within:!outline-primary-500/20"
              inputClassName="!h-12 !px-4 !text-base !font-normal !text-gray-900 placeholder:!text-[#858585] placeholder:!opacity-100"
            />
            <p className="px-1 text-sm font-medium leading-relaxed text-[#858585]">
              Optional: the title's last word rotates with this word. Leave blank for no animation;
              use one word only. The default word is members.
            </p>
          </div>

          <TextareaInput
            id="greeting-message"
            label="Greeting Message"
            placeholder="Enter the greeting message"
            required
            value={greetingMessage}
            onChange={(event) => {
              setGreetingMessage(event.target.value);
              setSaveStatus('');
            }}
            rows={4}
            showCounter={false}
            className="gap-3"
            labelClassName="!text-base !font-medium !leading-none !text-[#858585]"
            textareaClassName="!min-h-24 !resize-none !rounded-[1.5rem] !border-0 !bg-cms-surface !px-4 !py-4 !text-base !font-normal !leading-snug !text-gray-900 !shadow-none placeholder:!text-[#858585] placeholder:!opacity-100 focus:!border-transparent focus:!outline focus:!outline-3 focus:!outline-primary-500/20"
          />

          <div className="flex justify-center pt-8">
            <button
              type="button"
              disabled={isSaving || !hasHomepageChanges}
              onClick={saveHomepageContent}
              className="rounded-full bg-primary-500 tracking-[3%] px-8 py-2 text-base font-bold text-white shadow-sm transition-all duration-200 hover:bg-primary-600 hover:shadow-lg disabled:cursor-not-allowed disabled:opacity-60"
            >
              {isSaving ? 'Updating...' : 'Update Homepage'}
            </button>
          </div>
          {saveStatus ? (
            <p className="text-center text-sm font-medium text-success-700">{saveStatus}</p>
          ) : null}
        </section>
      </div>

      {pendingDeleteImage ? (
        <DeleteConfirmModal
          title={`carousel image ${pendingDeleteImage.sortOrder}`}
          heading="Delete Homepage Image?"
          isDeleting={false}
          onConfirm={() => deleteImage(pendingDeleteImage.id)}
          onCancel={() => setPendingDeleteImage(null)}
          description={`Delete carousel image ${pendingDeleteImage.sortOrder}? This removes it from the homepage carousel draft. Use Hide instead if you only want to temporarily remove it from the public homepage.`}
        />
      ) : null}
    </>
  );
}
