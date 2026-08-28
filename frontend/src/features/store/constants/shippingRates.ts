import type { NigeriaState } from './nigerianStates';
import { NIGERIA_AREAS } from './nigerianAreas';

// ─── Types ────────────────────────────────────────────────────────────────────

export interface AreaRate {
  area: string; // constrained at runtime via NIGERIA_AREAS
  fee: number;
}

export interface StateRate {
  state: NigeriaState;
  areas: AreaRate[];
}

// ─── Default fee applied to any area not explicitly listed ────────────────────
const DEFAULT_FEE = 5500;

// ─── Helper: build a full area list for a state with a given default fee ──────
// Any area explicitly listed in overrides gets its specific fee;
// the rest get the default.
function buildAreas(
  state: NigeriaState,
  overrides: Partial<Record<string, number>> = {},
  defaultFee = DEFAULT_FEE,
): AreaRate[] {
  return NIGERIA_AREAS[state].map((area) => ({
    area,
    fee: overrides[area] ?? defaultFee,
  }));
}

// ─── Shipping rates for all states ───────────────────────────────────────────
export const SHIPPING_RATES: StateRate[] = [
  {
    state: 'Lagos',
    areas: buildAreas('Lagos', {
      Ikeja: 2000,
      Yaba: 2000,
      Surulere: 2500,
      'Victoria Island': 3000,
      Lekki: 3500,
      Ajah: 4000,
      Ikorodu: 4500,
      Badagry: 5000,
    }),
  },
  {
    state: 'Federal Capital Territory',
    areas: buildAreas('Federal Capital Territory', {
      Wuse: 3000,
      Garki: 3000,
      Gwarinpa: 3500,
      Maitama: 3500,
      Asokoro: 3500,
      Kubwa: 4000,
    }),
  },
  {
    state: 'Rivers',
    areas: buildAreas('Rivers', {
      'Port Harcourt GRA': 4000,
      Rumuola: 4500,
      Rumuigbo: 4500,
      'Obio-Akpor': 4500,
    }),
  },
  {
    state: 'Imo',
    areas: buildAreas('Imo', {
      'Imo Municipal': 4000,
      'Imo North': 4500,
      Orlu: 5000,
      Okigwe: 5000,
    }),
  },
  {
    state: 'Anambra',
    areas: buildAreas('Anambra', {
      Awka: 4500,
      Onitsha: 4500,
      Nnewi: 5000,
    }),
  },
  {
    state: 'Oyo',
    areas: buildAreas('Oyo', {
      'Ibadan North': 3500,
      'Ibadan South': 3500,
      Ogbomoso: 4500,
      Oyo: 4500,
    }),
  },
  {
    state: 'Kano',
    areas: buildAreas('Kano', {
      'Kano Municipal': 5000,
      Nasarawa: 5000,
      Fagge: 5000,
      Gwale: 5000,
    }),
  },
  {
    state: 'Enugu',
    areas: buildAreas('Enugu', {
      'Enugu North': 4500,
      'Enugu South': 4500,
      Nsukka: 5000,
    }),
  },
  // All remaining states use DEFAULT_FEE for every area
  { state: 'Abia', areas: buildAreas('Abia') },
  { state: 'Adamawa', areas: buildAreas('Adamawa') },
  { state: 'Akwa Ibom', areas: buildAreas('Akwa Ibom') },
  { state: 'Bauchi', areas: buildAreas('Bauchi') },
  { state: 'Bayelsa', areas: buildAreas('Bayelsa') },
  { state: 'Benue', areas: buildAreas('Benue') },
  { state: 'Borno', areas: buildAreas('Borno') },
  { state: 'Cross River', areas: buildAreas('Cross River') },
  { state: 'Delta', areas: buildAreas('Delta') },
  { state: 'Ebonyi', areas: buildAreas('Ebonyi') },
  { state: 'Edo', areas: buildAreas('Edo') },
  { state: 'Ekiti', areas: buildAreas('Ekiti') },
  { state: 'Gombe', areas: buildAreas('Gombe') },
  { state: 'Jigawa', areas: buildAreas('Jigawa') },
  { state: 'Kaduna', areas: buildAreas('Kaduna') },
  { state: 'Katsina', areas: buildAreas('Katsina') },
  { state: 'Kebbi', areas: buildAreas('Kebbi') },
  { state: 'Kogi', areas: buildAreas('Kogi') },
  { state: 'Kwara', areas: buildAreas('Kwara') },
  { state: 'Nasarawa', areas: buildAreas('Nasarawa') },
  { state: 'Niger', areas: buildAreas('Niger') },
  { state: 'Ogun', areas: buildAreas('Ogun') },
  { state: 'Ondo', areas: buildAreas('Ondo') },
  { state: 'Osun', areas: buildAreas('Osun') },
  { state: 'Plateau', areas: buildAreas('Plateau') },
  { state: 'Sokoto', areas: buildAreas('Sokoto') },
  { state: 'Taraba', areas: buildAreas('Taraba') },
  { state: 'Yobe', areas: buildAreas('Yobe') },
  { state: 'Zamfara', areas: buildAreas('Zamfara') },
];

// ─── Lookup helpers ───────────────────────────────────────────────────────────

export function getAreasForState(state: NigeriaState): AreaRate[] {
  return SHIPPING_RATES.find((s) => s.state === state)?.areas ?? [];
}

export function getShippingFee(state: string, area: string): number {
  const stateRate = SHIPPING_RATES.find((s) => s.state === state);
  if (!stateRate) return DEFAULT_FEE;
  return stateRate.areas.find((a) => a.area === area)?.fee ?? DEFAULT_FEE;
}