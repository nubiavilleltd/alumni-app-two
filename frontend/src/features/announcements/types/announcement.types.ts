// features/announcements/types/announcement.types.ts

export type AnnouncementType = 'info' | 'event';

export interface Announcement {
  id: string | number;
  slug: string;
  title: string;
  content?: string;
  excerpt?: string;
  image: string;
  date: string;
  type: AnnouncementType;
  tag?: string;
  createdBy?: string;
  createdByName?: string;
  createdAt?: string;
  chapterId?: string;
  year?: number;
  startsAt?: string;
  endsAt?: string;
  featured?: boolean;
  source?: 'announcement' | 'event' | 'project';
  sourceId?: string;
  href?: string;
}

export type NewsItem = Announcement;

export interface GetAnnouncementsParams {
  id?: string | number;
  createdBy?: string | number;
  type?: AnnouncementType;
  year?: string | number;
}

export interface AnnouncementMutationInput {
  title: string;
  content: string;
  type?: AnnouncementType;
  chapterId?: string;
  year?: string;
  startsAt?: string;
  endsAt?: string;
  image?: File | null;
}

export interface BirthdayResponse {
  user_id: string;
  fullname: string;
  name_in_school: string;
  avatar: string;
  class_label: string;
}
export interface Birthday {
  userId: string;
  fullName: string;
  nameInSchool: string;
  avatar: string;
  classLabel: string;
}
