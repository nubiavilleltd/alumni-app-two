/**
 * Proposed fetch_locations response item:
 * { state: "Lagos", cities: ["Ikeja", "Lagos Mainland"] }
 *
 * The fallback currently uses LGA names as the city values.
 */
export interface LocationGroup {
  state: string;
  cities: readonly string[];
}
