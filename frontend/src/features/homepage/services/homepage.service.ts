import { contentApiClient } from '@/lib/api/contentClient';
import { API_ENDPOINTS } from '@/lib/api/endpoints';
import { mapCarouselImage, mapHomepageContent } from '../api/adapters/homepage.adapter';
import type { HomepageCarouselImage, HomepageContent } from '../types/homepage.types';
import { buildHeroTitleWithAnimation } from '../utils/heroTitleAnimation';

export type UpdateHomepageTextInput = {
  greetingTitle: string;
  greetingMessage: string;
};

export type CreateCarouselImageInput = {
  image: File;
  altText?: string;
  sortOrder?: number;
  isHidden?: boolean;
};

export type UpdateCarouselImageInput = {
  id: string;
  image?: File;
  altText?: string;
  isHidden?: boolean;
  showGreetingMessage?: boolean;
};

export type ReorderCarouselImageInput = {
  id: string;
  sortOrder: number;
};

function visibilityValue(isHidden?: boolean) {
  return isHidden ? '1' : '0';
}

function greetingVisibilityValue(showGreetingMessage?: boolean) {
  return showGreetingMessage ? '1' : '0';
}

const fallbackHomepageContent: HomepageContent = {
  greetingTitle: buildHeroTitleWithAnimation('Welcome Home', 'Everyone'),
  greetingMessage:
    'A global community of alumni connected by shared memories, driven by purpose, and committed to lifting the next generation.',
  carouselImages: [
    'Property 1=Frame 713.png',
    'Property 1=Frame 714.png',
    'Property 1=Frame 715.png',
    'Property 1=Frame 716.png',
    'Property 1=Frame 717.png',
  ].map((fileName, index) => ({
    id: `fallback-home-${index + 1}`,
    imageUrl: `/home/${encodeURIComponent(fileName)}`,
    fileName,
    altText: `Homepage image ${index + 1}`,
    sortOrder: index + 1,
    isHidden: false,
    showGreetingMessage: true,
  })),
};

function withHomepageFallbacks(homepage: HomepageContent): HomepageContent {
  return {
    greetingTitle: homepage.greetingTitle || fallbackHomepageContent.greetingTitle,
    greetingMessage: homepage.greetingMessage || fallbackHomepageContent.greetingMessage,
    carouselImages:
      homepage.carouselImages.length > 0
        ? homepage.carouselImages
        : fallbackHomepageContent.carouselImages,
  };
}

function forceCarouselImagesVisible(homepage: HomepageContent): HomepageContent {
  return {
    ...homepage,
    carouselImages: homepage.carouselImages.map((image) => ({
      ...image,
      isHidden: false,
      showGreetingMessage: true,
    })),
  };
}

export const homepageService = {
  async getHomepage(options?: { admin?: boolean }): Promise<HomepageContent> {
    try {
      const { data } = await contentApiClient.get(API_ENDPOINTS.CONTENT.HOMEPAGE, {
        headers: options?.admin ? undefined : { 'X-Skip-Bearer': '1' },
      });
      const homepage = forceCarouselImagesVisible(withHomepageFallbacks(mapHomepageContent(data)));

      if (options?.admin) {
        return homepage;
      }

      return {
        ...homepage,
        carouselImages: homepage.carouselImages.filter((image) => !image.isHidden),
      };
    } catch (error) {
      if (options?.admin) {
        throw error;
      }

      return forceCarouselImagesVisible(fallbackHomepageContent);
    }
  },

  async updateHomepageText(input: UpdateHomepageTextInput): Promise<UpdateHomepageTextInput> {
    const { data } = await contentApiClient.post(API_ENDPOINTS.CONTENT.UPDATE_HOMEPAGE_TEXT, {
      greeting_title: input.greetingTitle,
      greeting_message: input.greetingMessage,
    });
    const responseData = (data?.data ?? data ?? {}) as Record<string, unknown>;

    return {
      greetingTitle: String(responseData.greeting_title ?? input.greetingTitle),
      greetingMessage: String(responseData.greeting_message ?? input.greetingMessage),
    };
  },

  async createCarouselImage(input: CreateCarouselImageInput): Promise<HomepageCarouselImage> {
    const formData = new FormData();
    formData.append('image', input.image);
    if (input.altText !== undefined) formData.append('alt_text', input.altText);
    if (input.sortOrder !== undefined) formData.append('sort_order', String(input.sortOrder));
    if (input.isHidden !== undefined) formData.append('is_hidden', visibilityValue(input.isHidden));

    const { data } = await contentApiClient.post(
      API_ENDPOINTS.CONTENT.CREATE_CAROUSEL_IMAGE,
      formData,
    );

    return mapCarouselImage(data?.image);
  },

  async updateCarouselImage(input: UpdateCarouselImageInput): Promise<HomepageCarouselImage> {
    if (!input.id) {
      throw new Error('Unable to update carousel image.');
    }

    if (input.image) {
      const formData = new FormData();
      formData.append('id', input.id);
      formData.append('image', input.image);
      if (input.altText !== undefined) formData.append('alt_text', input.altText);
      if (input.isHidden !== undefined) {
        formData.append('is_hidden', visibilityValue(input.isHidden));
      }
      if (input.showGreetingMessage !== undefined) {
        formData.append('show_greeting', greetingVisibilityValue(input.showGreetingMessage));
      }

      console.log('updateCarouselImage multipart payload:', Object.fromEntries(formData.entries()));
      const { data } = await contentApiClient.post(
        API_ENDPOINTS.CONTENT.UPDATE_CAROUSEL_IMAGE,
        formData,
      );
      console.log('updateCarouselImage response:', data);
      return mapCarouselImage(data?.image);
    }

    const payload = {
      id: input.id,
      ...(input.altText !== undefined ? { alt_text: input.altText } : {}),
      ...(input.isHidden !== undefined ? { is_hidden: visibilityValue(input.isHidden) } : {}),
      ...(input.showGreetingMessage !== undefined
        ? { show_greeting: greetingVisibilityValue(input.showGreetingMessage) }
        : {}),
    };
    console.log('updateCarouselImage JSON payload:', payload);
    const { data } = await contentApiClient.post(API_ENDPOINTS.CONTENT.UPDATE_CAROUSEL_IMAGE, payload);
    console.log('updateCarouselImage response:', data);
    return mapCarouselImage(data?.image);
  },

  async reorderCarousel(images: ReorderCarouselImageInput[]): Promise<HomepageCarouselImage[]> {
    if (images.some((image) => !image.id)) {
      throw new Error('Unable to update carousel order.');
    }

    const { data } = await contentApiClient.post(API_ENDPOINTS.CONTENT.REORDER_CAROUSEL, {
      images: images.map((image) => ({
        id: image.id,
        sort_order: image.sortOrder,
      })),
    });
    const rawImages = Array.isArray(data?.carousel_images) ? data.carousel_images : [];
    return rawImages.map(mapCarouselImage);
  },

  async deleteCarouselImage(id: string): Promise<void> {
    await contentApiClient.post(API_ENDPOINTS.CONTENT.DELETE_CAROUSEL_IMAGE, { id });
  },
};
