import type { NigeriaState } from './nigerianStates';

// ─── All delivery areas per state ─────────────────────────────────────────────
// Add more areas here as the admin sets them up.
// These are used both for the Area dropdown and for shipping rate lookups.

export const NIGERIA_AREAS: Record<NigeriaState, readonly string[]> = {
  Abia: ['Aba', 'Umuahia', 'Ohafia'],
  Adamawa: ['Yola', 'Mubi', 'Jimeta'],
  'Akwa Ibom': ['Uyo', 'Eket', 'Ikot Ekpene'],
  Anambra: ['Awka', 'Onitsha', 'Nnewi'],
  Bauchi: ['Bauchi', 'Azare', 'Misau'],
  Bayelsa: ['Yenagoa', 'Sagbama', 'Brass'],
  Benue: ['Makurdi', 'Gboko', 'Katsina-Ala'],
  Borno: ['Maiduguri', 'Biu', 'Konduga'],
  'Cross River': ['Calabar', 'Ogoja', 'Ikom'],
  Delta: ['Asaba', 'Warri', 'Sapele'],
  Ebonyi: ['Abakaliki', 'Onueke', 'Afikpo'],
  Edo: ['Benin City', 'Auchi', 'Ekpoma'],
  Ekiti: ['Ado-Ekiti', 'Ikere', 'Ijero'],
  Enugu: ['Enugu North', 'Enugu South', 'Nsukka'],
  'Federal Capital Territory': ['Wuse', 'Garki', 'Gwarinpa', 'Maitama', 'Asokoro', 'Kubwa'],
  Gombe: ['Gombe', 'Bajoga', 'Kaltungo'],
  Imo: ['Imo Municipal', 'Imo North', 'Orlu', 'Okigwe'],
  Jigawa: ['Dutse', 'Hadejia', 'Gumel'],
  Kaduna: ['Kaduna North', 'Kaduna South', 'Zaria'],
  Kano: ['Kano Municipal', 'Nasarawa', 'Fagge', 'Gwale'],
  Katsina: ['Katsina', 'Daura', 'Funtua'],
  Kebbi: ['Birnin Kebbi', 'Argungu', 'Yauri'],
  Kogi: ['Lokoja', 'Ankpa', 'Idah'],
  Kwara: ['Ilorin', 'Offa', 'Share'],
  Lagos: ['Ikeja', 'Victoria Island', 'Lekki', 'Surulere', 'Yaba', 'Ajah', 'Ikorodu', 'Badagry'],
  Nasarawa: ['Lafia', 'Keffi', 'Akwanga'],
  Niger: ['Minna', 'Suleja', 'Bida'],
  Ogun: ['Abeokuta', 'Sagamu', 'Ijebu-Ode'],
  Ondo: ['Akure', 'Ondo', 'Owo'],
  Osun: ['Osogbo', 'Ile-Ife', 'Ilesa'],
  Oyo: ['Ibadan North', 'Ibadan South', 'Ogbomoso', 'Oyo'],
  Plateau: ['Jos North', 'Jos South', 'Pankshin'],
  Rivers: ['Port Harcourt GRA', 'Rumuola', 'Rumuigbo', 'Obio-Akpor'],
  Sokoto: ['Sokoto', 'Wamako', 'Dange-Shuni'],
  Taraba: ['Jalingo', 'Wukari', 'Bali'],
  Yobe: ['Damaturu', 'Potiskum', 'Gashua'],
  Zamfara: ['Gusau', 'Kaura Namoda', 'Talata Mafara'],
};

export type NigeriaArea<S extends NigeriaState> = (typeof NIGERIA_AREAS)[S][number];