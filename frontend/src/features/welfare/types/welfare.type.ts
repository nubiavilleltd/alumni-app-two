// features/welfare/types/welfare.types.ts

export interface ZoneCoordinator {
  userId: number;
  name: string;
  firstName: string;
  lastName: string;
  phone: string;
  avatar: string | null;
}

export interface WelfareZone {
  zoneId: number;
  zone: string; // e.g. "Zone 1", "Zone 5a"
  chapterId: number;
  coordinator: ZoneCoordinator | null;
  cities: string;
}
