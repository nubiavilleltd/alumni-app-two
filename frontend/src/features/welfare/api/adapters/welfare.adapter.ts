// features/welfare/api/adapters/welfare.adapter.ts
//
// Maps raw GET /get_zones response → frontend WelfareZone objects.
// Mirrors the adapter pattern used throughout the codebase.

import { WelfareZone, ZoneCoordinator } from '../../types/welfare.type';

const safeNum = (v: unknown, fallback = 0): number => {
  const n = Number(v);
  return Number.isFinite(n) ? n : fallback;
};

const safeStr = (v: unknown, fallback = ''): string =>
  v != null && String(v).trim() !== '' ? String(v).trim() : fallback;

function mapCoordinator(raw: unknown): ZoneCoordinator | null {
  if (raw == null) return null;
  const d = raw as Record<string, any>;

  // Guard: must have at least a name or user_id to be a valid coordinator
  if (!d.user_id && !d.name) return null;

  return {
    userId: safeNum(d.user_id),
    name: safeStr(d.name),
    firstName: safeStr(d.first_name),
    lastName: safeStr(d.last_name),
    phone: safeStr(d.phone),
    avatar: safeStr(d.avatar) || null,
  };
}

export function mapBackendZoneToFrontend(raw: unknown): WelfareZone {
  const d = raw as Record<string, any>;

  return {
    zoneId: safeNum(d.zone_id),
    zone: safeStr(d.zone),
    chapterId: safeNum(d.chapter_id),
    coordinator: mapCoordinator(d.coordinator),
    cities: Array.isArray(d.cities) ? d.cities.map((c) => safeStr(c.city)).join(', ') : '',
  };
}

export function mapBackendZoneList(raw: unknown): WelfareZone[] {
  if (!Array.isArray(raw)) return [];

  return raw
    .map((item) => {
      try {
        return mapBackendZoneToFrontend(item);
      } catch (err) {
        console.error('[welfare.adapter] Failed to map zone item:', item, err);
        return null;
      }
    })
    .filter((z): z is WelfareZone => z !== null);
}
