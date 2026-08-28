// src/data/chapters.ts
//
// Chapter definitions for the Alumni Portal.
// Currently Lagos-only. Add new chapters here as the association grows.
// chapterId format: CHR-{LOCATION} — short, readable, stable.

export interface Chapter {
  chapterId: string;
  name: string;
  shortName: string;
  city: string;
  country: string;
  established: string; // ISO date
  isActive: boolean;
}

export const chapters: Chapter[] = [
  {
    chapterId: 'CHR-LAGOS',
    name: 'Lagos Chapter',
    shortName: 'Lagos',
    city: 'Lagos',
    country: 'Nigeria',
    established: '2001-01-01',
    isActive: true,
  },
  // 🔴 TODO: Add more chapters as the association grows
  // {
  //   chapterId:   'CHR-ABUJA',
  //   name:        'Abuja Chapter',
  //   shortName:   'Abuja',
  //   city:        'Abuja',
  //   country:     'Nigeria',
  //   established: '2005-01-01',
  //   isActive:    true,
  // },
];

export const DEFAULT_CHAPTER_ID = 'CHR-LAGOS';

export function getChapterById(chapterId: string): Chapter | undefined {
  return chapters.find((c) => c.chapterId === chapterId);
}

export function getChapterName(chapterId: string | undefined): string {
  if (!chapterId) return 'Not yet assigned';
  return getChapterById(chapterId)?.name ?? chapterId;
}

export function getActiveChapters(): Chapter[] {
  return chapters.filter((c) => c.isActive);
}
