import { contentApiClient } from '@/lib/api/contentClient';
import { API_ENDPOINTS } from '@/lib/api/endpoints';
import {
  mapBlogCategories,
  mapBlogCategory,
  mapBlogPostDetail,
  mapBlogPosts,
  toBooleanActive,
} from '../api/adapters/blog.adapter';
import type {
  BlogCategory,
  BlogPostDetail,
  BlogPostStatus,
  BlogPostsResult,
  BlogSection,
  SaveBlogCategoryInput,
} from '../types/blog.types';

export type GetBlogPostsInput = {
  status?: BlogPostStatus | 'all';
  category?: string;
  search?: string;
  page?: number;
  limit?: number;
  admin?: boolean;
};

export type SaveBlogPostInput = {
  id?: string;
  title: string;
  categoryId: string;
  excerpt: string;
  status: BlogPostStatus;
  sections: BlogSection[];
  images?: File[];
  mainImageIndex?: number;
  mainImageUrl?: string;
};

export type ReorderBlogCategoryInput = {
  id: string;
  sortOrder: number;
};

function activeValue(value: number | boolean | undefined, fallback = true): 1 | 0 {
  const finalValue = value ?? fallback;

  if (typeof finalValue === 'number') {
    return finalValue === 1 ? 1 : 0;
  }

  return finalValue ? 1 : 0;
}

function summarizeBlogPostInput(input: SaveBlogPostInput) {
  return {
    ...input,
    images: input.images?.map((image) => ({
      name: image.name,
      type: image.type,
      size: image.size,
    })),
  };
}

function summarizeBlogFormData(formData: FormData) {
  return Array.from(formData.entries()).map(([key, value]) => {
    if (value instanceof File) {
      return {
        key,
        file: {
          name: value.name,
          type: value.type,
          size: value.size,
        },
      };
    }

    return { key, value };
  });
}

function appendBlogPostFields(formData: FormData, input: SaveBlogPostInput) {
  const uploadedImages = input.images ?? [];

  if (input.id) formData.append('id', input.id);
  formData.append('title', input.title);
  formData.append('category_id', input.categoryId);
  formData.append('excerpt', input.excerpt);
  formData.append('status', input.status);
  formData.append(
    'sections',
    JSON.stringify(
      input.sections.map((section, index) => ({
        ...(section.id ? { id: section.id } : {}),
        heading: section.heading,
        body: section.body,
        sort_order: section.sortOrder ?? index,
      })),
    ),
  );
  uploadedImages.forEach((image) => formData.append('images', image));
  if (
    input.mainImageIndex !== undefined &&
    input.mainImageIndex >= 0 &&
    input.mainImageIndex < uploadedImages.length
  ) {
    formData.append('main_image_index', String(input.mainImageIndex));
  }
  if (input.mainImageUrl) {
    formData.append('main_image_url', input.mainImageUrl);
  }
}



export const blogService = {
  async getCategories(): Promise<BlogCategory[]> {
    const { data } = await contentApiClient.get(API_ENDPOINTS.CONTENT.BLOG_CATEGORIES, {
      headers: { 'X-Skip-Bearer': '1' },
    });
    const categories = mapBlogCategories(data).filter((category) => category.isActive);

    console.log('[Blog Categories] get public categories:', {
      response: data,
      categories,
    });

    return categories;
  },

  async getAdminCategories(): Promise<BlogCategory[]> {
    const { data } = await contentApiClient.get(API_ENDPOINTS.CONTENT.BLOG_CATEGORIES);
    const categories = mapBlogCategories(data);

    console.log('[Blog Categories] get admin categories:', {
      response: data,
      categories,
    });

    return categories;
  },

  async createCategory(input: SaveBlogCategoryInput): Promise<BlogCategory> {
    const payload = {
      name: input.name,
      ...(input.slug !== undefined ? { slug: input.slug } : {}),
      is_active: activeValue(input.isActive ?? true),
      sort_order: input.sortOrder ?? 0,
    };
    const { data } = await contentApiClient.post(
      API_ENDPOINTS.CONTENT.CREATE_BLOG_CATEGORY,
      payload,
    );
    const category = mapBlogCategory(data?.category ?? data?.data ?? data);

    console.log('[Blog Categories] create category:', {
      input,
      payload,
      response: data,
      category,
    });

    return category;
  },

  async updateCategory(input: SaveBlogCategoryInput): Promise<BlogCategory> {
    const payload = {
      id: input.id,
      ...(input.name !== undefined ? { name: input.name } : {}),
      ...(input.slug !== undefined ? { slug: input.slug } : {}),
      ...(input.isActive !== undefined ? { is_active: activeValue(input.isActive) } : {}),
      ...(input.sortOrder !== undefined ? { sort_order: input.sortOrder } : {}),
    };
    const { data } = await contentApiClient.post(
      API_ENDPOINTS.CONTENT.UPDATE_BLOG_CATEGORY,
      payload,
    );

    const category = mapBlogCategory(data?.category ?? data?.data ?? data);
    const baseCategory = category.id
      ? category
      : {
          id: String(input.id ?? ''),
          name: '',
          slug: '',
          sortOrder: 0,
          isActive: true,
        };

    const mergedCategory = {
      ...baseCategory,
      name: input.name ?? baseCategory.name,
      slug: input.slug ?? baseCategory.slug,
      sortOrder: input.sortOrder ?? baseCategory.sortOrder,
      isActive: toBooleanActive(input.isActive, baseCategory.isActive),

    };

    console.log('[Blog Categories] update category:', {
      input,
      payload,
      response: data,
      mappedCategory: category,
      mergedCategory,
    });

    try {
      const { data: verifyData } = await contentApiClient.get(API_ENDPOINTS.CONTENT.BLOG_CATEGORIES);
      const persistedCategories = mapBlogCategories(verifyData);
      const persistedCategory = persistedCategories.find(
        (item) => String(item.id) === String(mergedCategory.id),
      );

      console.log('[Blog Categories] update category persisted check:', {
        requestedCategory: mergedCategory,
        response: verifyData,
        persistedCategories,
        persistedCategory,
      });

      return persistedCategory ?? mergedCategory;
    } catch (error) {
      console.log('[Blog Categories] update category persisted check failed:', error);
      return mergedCategory;
    }
  },

  async deleteCategory(id: string): Promise<void> {
    const payload = { id };
    const { data } = await contentApiClient.post(
      API_ENDPOINTS.CONTENT.DELETE_BLOG_CATEGORY,
      payload,
    );

    console.log('[Blog Categories] delete category:', {
      payload,
      response: data,
    });
  },

  async reorderCategories(categories: ReorderBlogCategoryInput[]): Promise<BlogCategory[]> {
    const payload = {
      categories: categories.map((category) => ({
        id: category.id,
        sort_order: category.sortOrder,
      })),
    };
    const { data } = await contentApiClient.post(
      API_ENDPOINTS.CONTENT.REORDER_BLOG_CATEGORIES,
      payload,
    );
    const reorderedCategories = mapBlogCategories(data);

    console.log('[Blog Categories] reorder categories:', {
      categories,
      payload,
      response: data,
      reorderedCategories,
    });

    return reorderedCategories;
  },

  async getPosts(input: GetBlogPostsInput = {}): Promise<BlogPostsResult> {
    const { data } = await contentApiClient.get(API_ENDPOINTS.CONTENT.BLOG_POSTS, {
      params: {
        ...(input.status ? { status: input.status } : {}),
        ...(input.category ? { category: input.category } : {}),
        ...(input.search ? { search: input.search } : {}),
        page: input.page ?? 1,
        limit: input.limit ?? 10,
      },
      headers: input.admin ? undefined : { 'X-Skip-Bearer': '1' },
    });

    return mapBlogPosts(data);
  },

  async getPostDetail(idOrSlug: string, options?: { admin?: boolean }): Promise<BlogPostDetail> {
    const { data } = await contentApiClient.get(API_ENDPOINTS.CONTENT.BLOG_POST_DETAIL(idOrSlug), {
      headers: options?.admin ? undefined : { 'X-Skip-Bearer': '1' },
    });

    return mapBlogPostDetail(data);
  },

  async createPost(input: SaveBlogPostInput): Promise<BlogPostDetail> {
    const formData = new FormData();
    appendBlogPostFields(formData, input);
    console.log('Create blog payload:', summarizeBlogPostInput(input));
    console.log('Create blog FormData:', summarizeBlogFormData(formData));

    const { data } = await contentApiClient.post(API_ENDPOINTS.CONTENT.CREATE_BLOG_POST, formData);
    console.log('Create blog response:', data);
    return mapBlogPostDetail(data);
  },

  async updatePost(input: SaveBlogPostInput): Promise<BlogPostDetail> {
    const formData = new FormData();
    appendBlogPostFields(formData, input);
    console.log('Update blog payload:', summarizeBlogPostInput(input));
    console.log('Update blog FormData:', summarizeBlogFormData(formData));

    const { data } = await contentApiClient.post(API_ENDPOINTS.CONTENT.UPDATE_BLOG_POST, formData);
    console.log('Update blog response:', data);
    return mapBlogPostDetail(data);
  },

  async deletePost(id: string): Promise<void> {
    await contentApiClient.post(API_ENDPOINTS.CONTENT.DELETE_BLOG_POST, { id });
  },
};
