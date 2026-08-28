// src/data/site-data.ts
//
// All relational keys now use memberId (MBR-{year}-{hex}).
// Leadership excos are linked to real MockAuthAccount memberIds.
// Businesses are linked to real member owners via ownerId (memberId).
// Events have createdBy + registrations[].
// Announcements have createdBy.

import { generateMemberId } from '@/features/authentication/constants/mockAccounts';
import { DEFAULT_CHAPTER_ID } from '@/data/chapters';

import type { Alumni } from '@/features/alumni/types/alumni.types';
import type { Event } from '@/features/events/types/event.types';
import type { LeadershipMember } from '@/features/leadership/types/leadership.types';
import type { Project } from '@/features/projects/types/project.types';
import type { NewsItem } from '@/features/announcements/types/announcement.types';

import Leadership1 from '/leadership-1.png';
import Leadership2 from '/leadership-2.png';
import Leadership3 from '/leadership-3.png';
import Leadership4 from '/leadership-4.png';
import Leadership5 from '/leadership-5.png';
import Leadership6 from '/leadership-6.png';
import Leadership7 from '/leadership-7.png';

// ─── Pre-computed memberIds for cross-referencing ─────────────────────────────
// These mirror the generateMemberId calls in mockAccounts.ts exactly.
// The function is deterministic so the output is always the same.

const MBR_ADAEZE = generateMemberId(1998, 'adaeze.okonkwo@email.com');
const MBR_NGOZI = generateMemberId(2002, 'ngozi.ibrahim@email.com');
const MBR_CHIDINMA = generateMemberId(2005, 'chidinma.eze@email.com');
const MBR_STELLA = generateMemberId(1985, 'stella.alochi@email.com');
const MBR_ABIGAL_VP = generateMemberId(1987, 'abigal.vp@email.com');
const MBR_JOSEPHINE = generateMemberId(1989, 'josephine.adeka@email.com');
const MBR_FAVOUR = generateMemberId(1991, 'favour.adah@email.com');
const MBR_LILIAN = generateMemberId(1992, 'lilian.ojo@email.com');
const MBR_GOODNESS = generateMemberId(1993, 'goodness.adeka@email.com');
const MBR_BELLA = generateMemberId(1994, 'bella.adah@email.com');

// Historical alumni memberIds
const MBR_ABIGAL_OJO = generateMemberId(1990, 'abigal.ojo@email.com');
const MBR_PRECIOUS = generateMemberId(1990, 'precious.ojeka@email.com');
const MBR_THERESA = generateMemberId(1990, 'theresa.ojo@email.com');
const MBR_ABIGAL_CEO = generateMemberId(1990, 'abigal.ceo@email.com');
const MBR_ABIGAL_DEV = generateMemberId(1990, 'abigal.dev@email.com');
const MBR_ABIGAL_CHEF = generateMemberId(1990, 'abigal.chef@email.com');
const MBR_ABIGAL_ART2 = generateMemberId(1990, 'abigal.artist2@email.com');
const MBR_ABIGAL_ART3 = generateMemberId(1990, 'abigal.artist3@email.com');
const MBR_ABIGAL_ART4 = generateMemberId(1990, 'abigal.artist4@email.com');
const MBR_ABIGAL_ART5 = generateMemberId(1990, 'abigal.artist5@email.com');
const MBR_ABIGAL_ART6 = generateMemberId(1990, 'abigal.artist6@email.com');
const MBR_ABIGAL_ART7 = generateMemberId(1990, 'abigal.artist7@email.com');

type LegacyWorkExperience = {
  company: string;
  position: string;
  duration: string;
  description: string;
};

type LegacyEducation = {
  degree: string;
  institution: string;
  year: number;
  gpa: string;
};

type LegacyAlumni = {
  memberId: string;
  name: string;
  slug: string;
  chapterId?: string;
  year: number;
  short_bio: string;
  long_bio: string;
  photo: string;
  email: string;
  location: string;
  company: string;
  position: string;
  skills: string[];
  projects: unknown[];
  work_experience: LegacyWorkExperience[];
  education: LegacyEducation[];
  achievements: string[];
  interests: string[];
  linkedin?: string;
  twitter?: string;
  instagram?: string;
};

type LegacyProject = {
  id: string;
  title: string;
  description: string;
  budget: string;
  image: string;
};

type LegacyEvent = Omit<Event, 'startDate'> & {
  date: string;
};

const parseCurrencyAmount = (value: string): number => {
  const amount = Number(value.replace(/[^\d.]/g, ''));
  return Number.isFinite(amount) ? amount : 0;
};

const toAlumni = (entry: LegacyAlumni): Alumni => ({
  id: entry.memberId,
  memberId: entry.memberId,
  chapterId: entry.chapterId,
  slug: entry.slug,
  name: entry.name,
  email: entry.email,
  graduationYear: entry.year,
  nameInSchool: entry.name,
  nickName: '',
  whatsappPhone: '',
  photo: entry.photo,
  bio: entry.long_bio || entry.short_bio,
  location: entry.location,
  position: entry.position,
  company: entry.company,
  occupations: entry.skills,
  industrySectors: entry.interests,
  linkedin: entry.linkedin,
  twitter: entry.twitter,
  instagram: entry.instagram,
  isCoordinator: false,
  isApproved: true,
  isEmailVerified: true,
  isActive: true,
  isVisible: true,
});

const toProject = (entry: LegacyProject, index: number): Project => ({
  id: entry.id,
  title: entry.title,
  description: entry.description,
  images: [entry.image],
  amountRaised: 0,
  targetAmount: parseCurrencyAmount(entry.budget),
  status: 'ongoing',
  conductedBy: 'Alumni Portal',
  location: 'Alumni Portal',
  startDate: '2025-01-01',
  sortOrder: index + 1,
  isFeatured: index === 0 ? 1 : 0,
});

const toEvent = ({ date, ...event }: LegacyEvent): Event => ({
  ...event,
  startDate: date,
});

// ─── Alumni directory ─────────────────────────────────────────────────────────
// Every approved active member appears here.
// Primary test accounts + leadership members + historical members.

const legacyAlumni: LegacyAlumni[] = [
  // ── Primary test accounts ──────────────────────────────────────────────────
  {
    memberId: MBR_ADAEZE,
    name: 'Adaeze Chioma Okonkwo',
    slug: 'adaeze-okonkwo',
    chapterId: DEFAULT_CHAPTER_ID,
    year: 1998,
    short_bio: 'Entrepreneur & Business Consultant',
    long_bio:
      'Adaeze is a seasoned entrepreneur and business consultant with over 20 years of experience spanning financial services and nonprofit management. She is a passionate advocate for community leadership and serves as President of the Lagos Chapter.',
    photo: 'https://images.unsplash.com/photo-1573497161161-c3e73707e25c?w=600&q=80',
    email: 'adaeze.okonkwo@email.com',
    location: 'Lagos, Nigeria',
    company: 'Adaeze Consulting Group',
    position: 'Founder & CEO',
    skills: [
      'Business Strategy',
      'Nonprofit Management',
      'Financial Advisory',
      'Leadership',
      'Mentorship',
    ],
    projects: [],
    work_experience: [
      {
        company: 'Adaeze Consulting Group',
        position: 'Founder & CEO',
        duration: '2004 - Present',
        description:
          'Strategic business consulting and financial advisory for organisations across Nigeria.',
      },
    ],
    education: [
      { degree: 'MBA', institution: 'University of Lagos', year: 2003, gpa: '' },
      {
        degree: 'B.Sc. Economics',
        institution: 'University of Nigeria, Nsukka',
        year: 1998,
        gpa: '',
      },
    ],
    achievements: [
      'Lagos Business Leader of the Year 2022',
      'Alumni Portal Alumni Award of Excellence 2020',
    ],
    interests: ['Entrepreneurship', 'Community Empowerment', 'Finance', 'Community Service'],
    linkedin: '',
  },

  {
    memberId: MBR_NGOZI,
    name: 'Ngozi Blessing Ibrahim',
    slug: 'ngozi-ibrahim',
    chapterId: DEFAULT_CHAPTER_ID,
    year: 2002,
    short_bio: 'Medical Doctor & Health Consultant',
    long_bio:
      'Ngozi is a practising medical doctor with over 16 years of experience in healthcare. She runs a medical consulting practice in Surulere and is passionate about accessible healthcare for all Nigerians.',
    photo: 'https://images.unsplash.com/photo-1531123897727-8f129e1688ce?w=600&q=80',
    email: 'ngozi.ibrahim@email.com',
    location: 'Lagos, Nigeria',
    company: 'Ngozi Medical Consulting',
    position: 'Medical Director',
    skills: [
      'General Medicine',
      'Health Advisory',
      'Wellness Coaching',
      'Patient Care',
      'Medical Consulting',
    ],
    projects: [],
    work_experience: [
      {
        company: 'Ngozi Medical Consulting',
        position: 'Medical Director',
        duration: '2012 - Present',
        description:
          'Medical consulting and wellness coaching for individuals and corporate clients.',
      },
      {
        company: 'Lagos Island General Hospital',
        position: 'Medical Officer',
        duration: '2006 - 2012',
        description: 'General medical practice and patient care.',
      },
    ],
    education: [
      {
        degree: 'MBBS',
        institution: 'University of Lagos College of Medicine',
        year: 2006,
        gpa: '',
      },
    ],
    achievements: [],
    interests: ['Healthcare', 'Public Health', 'Wellness', 'Community Medicine'],
    linkedin: '',
  },

  {
    memberId: MBR_CHIDINMA,
    name: 'Chidinma Sandra Eze',
    slug: 'chidinma-eze',
    chapterId: DEFAULT_CHAPTER_ID,
    year: 2005,
    short_bio: 'Software Developer & Tech Entrepreneur',
    long_bio:
      'Chidinma is a full-stack software developer and tech entrepreneur based in Abuja. She founded Chidinma Software Studio to build custom digital solutions for Nigerian businesses and has been a leading voice for the technology community across the FCT.',
    photo: 'https://images.unsplash.com/photo-1589156229687-496a31ad1d1f?w=600&q=80',
    email: 'chidinma.eze@email.com',
    location: 'Abuja, Nigeria',
    company: 'Chidinma Software Studio',
    position: 'Founder & Lead Developer',
    skills: ['React', 'Node.js', 'TypeScript', 'Mobile Development', 'Digital Transformation'],
    projects: [],
    work_experience: [
      {
        company: 'Chidinma Software Studio',
        position: 'Founder & Lead Developer',
        duration: '2015 - Present',
        description:
          'Custom software, mobile apps, and digital transformation for Nigerian businesses.',
      },
    ],
    education: [
      {
        degree: 'B.Sc. Computer Science',
        institution: 'University of Nigeria, Nsukka',
        year: 2005,
        gpa: '',
      },
    ],
    achievements: ['FCT Tech Leadership Award 2023'],
    interests: ['Technology', 'Open Source', 'STEM', 'Entrepreneurship'],
    linkedin: '',
    twitter: '',
  },

  // ── Leadership (excos) ─────────────────────────────────────────────────────
  {
    memberId: MBR_STELLA,
    name: 'Mrs. Stella Alochi',
    slug: 'stella-alochi',
    chapterId: DEFAULT_CHAPTER_ID,
    year: 1985,
    short_bio: 'President, Lagos Chapter · Legal Professional',
    long_bio:
      'Mrs. Stella Alochi is a distinguished legal professional and the President of the Alumni Portal, Lagos Chapter. She brings decades of leadership experience and an unwavering commitment to the advancement of alumni everywhere.',
    photo: 'https://images.unsplash.com/photo-1573497161161-c3e73707e25c?w=600&q=80',
    email: 'stella.alochi@email.com',
    location: 'Lagos, Nigeria',
    company: 'Alochi & Associates',
    position: 'Senior Partner',
    skills: ['Legal Practice', 'Leadership', 'Corporate Law', 'Community Development'],
    projects: [],
    work_experience: [
      {
        company: 'Alochi & Associates',
        position: 'Senior Partner',
        duration: '2000 - Present',
        description: 'Corporate and commercial legal practice.',
      },
    ],
    education: [
      { degree: 'LLB', institution: 'University of Nigeria, Nsukka', year: 1985, gpa: '' },
      { degree: 'BL', institution: 'Nigerian Law Academy', year: 1986, gpa: '' },
    ],
    achievements: ['Nigerian Bar Association Award of Excellence 2019'],
    interests: ['Law', 'Community Service', 'Community Empowerment', 'Education'],
    linkedin: '',
  },
  {
    memberId: MBR_ABIGAL_VP,
    name: 'Mrs. Abigal Ojo',
    slug: 'abigal-ojo-vp',
    chapterId: DEFAULT_CHAPTER_ID,
    year: 1987,
    short_bio: 'Vice President, Lagos Chapter · Business Owner',
    long_bio:
      'Mrs. Abigal Ojo serves as Vice President of the Lagos Chapter. She is a seasoned entrepreneur with interests in retail and consumer goods.',
    photo: 'https://images.unsplash.com/photo-1598346762291-aee88549193f?w=600&q=80',
    email: 'abigal.vp@email.com',
    location: 'Lagos, Nigeria',
    company: 'Ojo Ventures',
    position: 'CEO',
    skills: ['Entrepreneurship', 'Retail', 'Business Development'],
    projects: [],
    work_experience: [],
    education: [],
    achievements: [],
    interests: ['Business', 'Fashion', 'Community'],
    linkedin: '',
  },
  {
    memberId: MBR_JOSEPHINE,
    name: 'Mrs. Josephine Adeka',
    slug: 'josephine-adeka',
    chapterId: DEFAULT_CHAPTER_ID,
    year: 1989,
    short_bio: 'P.R.O, Lagos Chapter · Media Professional',
    long_bio:
      'Mrs. Josephine Adeka is the Public Relations Officer of the Lagos Chapter. She brings her media expertise to amplify the voice and mission of the association.',
    photo: 'https://images.unsplash.com/photo-1616003023074-a49a5c5d5d9d?w=600&q=80',
    email: 'josephine.adeka@email.com',
    location: 'Lagos, Nigeria',
    company: 'Impact Creatives',
    position: 'Creative Director',
    skills: ['Public Relations', 'Media', 'Communications', 'Journalism'],
    projects: [],
    work_experience: [],
    education: [],
    achievements: [],
    interests: ['Media', 'Communications', 'Community Service'],
    linkedin: '',
  },
  {
    memberId: MBR_FAVOUR,
    name: 'Mrs. Favour Adah',
    slug: 'favour-adah',
    chapterId: DEFAULT_CHAPTER_ID,
    year: 1991,
    short_bio: 'Secretary, Lagos Chapter · Civil Servant',
    long_bio:
      'Mrs. Favour Adah serves as Secretary of the Lagos Chapter. Her organisational skills and attention to detail keep the chapter running smoothly.',
    photo: 'https://images.unsplash.com/photo-1624561172888-ac93c696e10c?w=600&q=80',
    email: 'favour.adah@email.com',
    location: 'Lagos, Nigeria',
    company: 'Federal Civil Service',
    position: 'Senior Administrative Officer',
    skills: ['Administration', 'Record Keeping', 'Government', 'Organisation'],
    projects: [],
    work_experience: [],
    education: [],
    achievements: [],
    interests: ['Public Service', 'Administration', 'Community'],
    linkedin: '',
  },
  {
    memberId: MBR_LILIAN,
    name: 'Mrs. Lilian Ojo',
    slug: 'lilian-ojo',
    chapterId: DEFAULT_CHAPTER_ID,
    year: 1992,
    short_bio: 'Secretary General, Lagos Chapter · Accountant',
    long_bio:
      'Mrs. Lilian Ojo serves as Secretary General of the Lagos Chapter. Her financial acumen and secretarial precision ensure seamless chapter operations.',
    photo: 'https://images.unsplash.com/photo-1531746020798-e6953c6e8e04?w=600&q=80',
    email: 'lilian.ojo@email.com',
    location: 'Lagos, Nigeria',
    company: 'First Bank of Nigeria',
    position: 'Senior Accountant',
    skills: ['Accounting', 'Financial Reporting', 'Banking', 'Administration'],
    projects: [],
    work_experience: [],
    education: [],
    achievements: [],
    interests: ['Finance', 'Banking', 'Community Service'],
    linkedin: '',
  },
  {
    memberId: MBR_GOODNESS,
    name: 'Mrs. Goodness Adeka',
    slug: 'goodness-adeka',
    chapterId: DEFAULT_CHAPTER_ID,
    year: 1993,
    short_bio: 'Cashier, Lagos Chapter · Finance Professional',
    long_bio:
      'Mrs. Goodness Adeka manages the finances of the Lagos Chapter with transparency and diligence. She is a seasoned finance professional with extensive banking experience.',
    photo: 'https://images.unsplash.com/photo-1580894894513-541e068a3e2b?w=600&q=80',
    email: 'goodness.adeka@email.com',
    location: 'Lagos, Nigeria',
    company: 'UBA',
    position: 'Finance Manager',
    skills: ['Finance', 'Accounting', 'Cash Management', 'Banking'],
    projects: [],
    work_experience: [],
    education: [],
    achievements: [],
    interests: ['Finance', 'Investment', 'Community Service'],
    linkedin: '',
  },
  {
    memberId: MBR_BELLA,
    name: 'Mrs. Bella Adah',
    slug: 'bella-adah',
    chapterId: DEFAULT_CHAPTER_ID,
    year: 1994,
    short_bio: 'Event Planner, Lagos Chapter · Hospitality Professional',
    long_bio:
      'Mrs. Bella Adah brings energy and creativity to every chapter event. As Event Planner, she transforms association visions into memorable experiences.',
    photo: 'https://images.unsplash.com/photo-1609010697446-11f2155278f0?w=600&q=80',
    email: 'bella.adah@email.com',
    location: 'Lagos, Nigeria',
    company: 'Bella Events & Hospitality',
    position: 'CEO & Event Director',
    skills: ['Event Planning', 'Hospitality', 'Project Management', 'Catering'],
    projects: [],
    work_experience: [],
    education: [],
    achievements: [],
    interests: ['Events', 'Hospitality', 'Food', 'Community'],
    linkedin: '',
  },
  {
    memberId: MBR_ABIGAL_OJO,
    name: 'Abigal Ojo',
    slug: 'abigal-ojo',
    chapterId: DEFAULT_CHAPTER_ID,
    year: 1990,
    short_bio: 'Creative Director & Visual Artist',
    long_bio:
      'Abigal is a celebrated creative director and visual artist whose work spans brand identity, editorial design, and fine art exhibitions across Nigeria and beyond.',
    photo: 'https://images.unsplash.com/photo-1596944924616-7b38e7cfac36?w=600&q=80',
    email: 'abigal.ojo@email.com',
    location: 'Lagos, Nigeria',
    company: 'Studio Ojo Creative',
    position: 'Creative Director',
    skills: ['Brand Identity', 'Visual Art', 'Illustration', 'Art Direction', 'Typography'],
    projects: [],
    work_experience: [
      {
        company: 'Studio Ojo Creative',
        position: 'Creative Director',
        duration: '2015 - Present',
        description: 'Leading creative strategy and visual identity for top Nigerian brands.',
      },
    ],
    education: [
      {
        degree: 'Bachelor of Arts in Fine & Applied Arts',
        institution: 'University of Lagos',
        year: 1990,
        gpa: '',
      },
    ],
    achievements: [],
    interests: ['Visual Art', 'Design', 'Culture', 'Fashion'],
    linkedin: '',
    twitter: '',
  },
  {
    memberId: MBR_PRECIOUS,
    name: 'Precious Ojeka',
    slug: 'precious-ojeka',
    chapterId: DEFAULT_CHAPTER_ID,
    year: 1990,
    short_bio: 'Banker',
    long_bio:
      'Precious is a seasoned banking professional with over two decades of experience in commercial and retail banking across Nigeria.',
    photo: 'https://images.unsplash.com/photo-1612349317150-e413f6a5b16d?w=600&q=80',
    email: 'precious.ojeka@email.com',
    location: 'Lagos, Nigeria',
    company: 'First Bank of Nigeria',
    position: 'Senior Relationship Manager',
    skills: ['Banking', 'Finance', 'Customer Relations', 'Credit Analysis', 'Risk Management'],
    projects: [],
    work_experience: [
      {
        company: 'First Bank of Nigeria',
        position: 'Senior Relationship Manager',
        duration: '2010 - Present',
        description: 'Managing high-value client portfolios and corporate banking relationships.',
      },
    ],
    education: [
      {
        degree: 'Bachelor of Science in Banking & Finance',
        institution: 'University of Benin',
        year: 1990,
        gpa: '',
      },
    ],
    achievements: [],
    interests: ['Finance', 'Investment', 'Mentorship', 'Travel'],
    linkedin: '',
  },
  {
    memberId: MBR_THERESA,
    name: 'Theresa Ojo',
    slug: 'theresa-ojo',
    chapterId: DEFAULT_CHAPTER_ID,
    year: 1990,
    short_bio: 'Nurse',
    long_bio:
      'Theresa is a dedicated registered nurse specialising in maternal and child health, serving communities across Lagos State.',
    photo: 'https://images.unsplash.com/photo-1559839734-2b71ea197ec2?w=600&q=80',
    email: 'theresa.ojo@email.com',
    location: 'Lagos, Nigeria',
    company: 'Lagos University Teaching Hospital',
    position: 'Registered Nurse',
    skills: ['Patient Care', 'Maternal Health', 'Paediatrics', 'Community Health', 'First Aid'],
    projects: [],
    work_experience: [
      {
        company: 'Lagos University Teaching Hospital',
        position: 'Registered Nurse',
        duration: '2005 - Present',
        description: 'Providing specialist maternal and child healthcare services.',
      },
    ],
    education: [
      {
        degree: 'Bachelor of Nursing Science',
        institution: 'University of Lagos',
        year: 1990,
        gpa: '',
      },
    ],
    achievements: [],
    interests: ['Healthcare', 'Community Service', 'Public Health', 'Wellness'],
    linkedin: '',
  },
  {
    memberId: MBR_ABIGAL_CEO,
    name: 'Abigal Ojo',
    slug: 'abigal-ojo-ceo',
    chapterId: DEFAULT_CHAPTER_ID,
    year: 1990,
    short_bio: "C.E.O Abi's stitches",
    long_bio:
      "Founder and CEO of Abi's Stitches, a leading Nigerian fashion label known for contemporary African-inspired designs.",
    photo: 'https://images.unsplash.com/photo-1601412436009-d964bd02edbc?w=600&q=80',
    email: 'abigal.ceo@email.com',
    location: 'Lagos, Nigeria',
    company: "Abi's Stitches",
    position: 'CEO & Fashion Designer',
    skills: ['Fashion Design', 'Entrepreneurship', 'Textile Arts', 'Brand Building', 'Tailoring'],
    projects: [],
    work_experience: [
      {
        company: "Abi's Stitches",
        position: 'CEO & Fashion Designer',
        duration: '2012 - Present',
        description: 'Building a premium African fashion brand with nationwide reach.',
      },
    ],
    education: [
      {
        degree: 'Diploma in Fashion Design',
        institution: 'Yaba College of Technology',
        year: 1990,
        gpa: '',
      },
    ],
    achievements: [],
    interests: ['Fashion', 'Entrepreneurship', 'African Culture', 'Design'],
    linkedin: '',
  },
  {
    memberId: MBR_ABIGAL_DEV,
    name: 'Abigal Ojo',
    slug: 'abigal-ojo-dev',
    chapterId: DEFAULT_CHAPTER_ID,
    year: 1990,
    short_bio: 'Web developer',
    long_bio:
      'A skilled front-end web developer building digital products for Nigerian startups and SMEs.',
    photo: 'https://images.unsplash.com/photo-1607990281513-2c110a25bd8c?w=600&q=80',
    email: 'abigal.dev@email.com',
    location: 'Lagos, Nigeria',
    company: 'Freelance',
    position: 'Web Developer',
    skills: ['HTML', 'CSS', 'JavaScript', 'React', 'UI Design'],
    projects: [],
    work_experience: [
      {
        company: 'Freelance',
        position: 'Web Developer',
        duration: '2018 - Present',
        description: 'Building responsive websites and web applications for clients.',
      },
    ],
    education: [
      {
        degree: 'Bachelor of Science in Computer Science',
        institution: 'University of Lagos',
        year: 1990,
        gpa: '',
      },
    ],
    achievements: [],
    interests: ['Technology', 'Web Design', 'Open Source', 'Innovation'],
    linkedin: '',
    instagram: '',
  },
  {
    memberId: MBR_ABIGAL_CHEF,
    name: 'Abigal Ojo',
    slug: 'abigal-ojo-chef',
    chapterId: DEFAULT_CHAPTER_ID,
    year: 1990,
    short_bio: 'Chef',
    long_bio:
      'A renowned chef celebrated for her fusion of traditional Nigerian cuisine with contemporary cooking techniques.',
    photo: 'https://images.unsplash.com/photo-1556909114-f6e7ad7d3136?w=600&q=80',
    email: 'abigal.chef@email.com',
    location: 'Lagos, Nigeria',
    company: 'The Pepper Soup Kitchen',
    position: 'Executive Chef',
    skills: ['Culinary Arts', 'Menu Development', 'Food Styling', 'Catering', 'Nutrition'],
    projects: [],
    work_experience: [
      {
        company: 'The Pepper Soup Kitchen',
        position: 'Executive Chef',
        duration: '2016 - Present',
        description: 'Creating signature Nigerian fusion dishes and leading kitchen operations.',
      },
    ],
    education: [
      {
        degree: 'Diploma in Culinary Arts',
        institution: 'Lagos Culinary Academy',
        year: 1990,
        gpa: '',
      },
    ],
    achievements: [],
    interests: ['Cooking', 'Food Culture', 'Travel', 'Wellness'],
    linkedin: '',
  },
  {
    memberId: MBR_ABIGAL_ART2,
    name: 'Abigal Ojo',
    slug: 'abigal-ojo-artist-2',
    chapterId: DEFAULT_CHAPTER_ID,
    year: 1990,
    short_bio: 'Visual Artist',
    long_bio:
      'A multidisciplinary visual artist working across photography, painting, and digital media.',
    photo: 'https://images.unsplash.com/photo-1567532939604-b6b5b0db2604?w=600&q=80',
    email: 'abigal.artist2@email.com',
    location: 'Lagos, Nigeria',
    company: 'Self-employed',
    position: 'Visual Artist',
    skills: ['Photography', 'Painting', 'Digital Art', 'Creative Direction', 'Storytelling'],
    projects: [],
    work_experience: [],
    education: [],
    achievements: [],
    interests: ['Art', 'Photography', 'Culture', 'Travel'],
    linkedin: '',
  },
  {
    memberId: MBR_ABIGAL_ART3,
    name: 'Abigal Ojo',
    slug: 'abigal-ojo-artist-3',
    chapterId: DEFAULT_CHAPTER_ID,
    year: 1990,
    short_bio: 'Creative Director',
    long_bio:
      'Creative director with a focus on visual storytelling for NGOs and social impact organisations.',
    photo: 'https://images.unsplash.com/photo-1589156280159-27698a70f29e?w=600&q=80',
    email: 'abigal.artist3@email.com',
    location: 'Lagos, Nigeria',
    company: 'Impact Creatives',
    position: 'Creative Director',
    skills: [
      'Visual Storytelling',
      'NGO Communication',
      'Graphic Design',
      'Branding',
      'Video Production',
    ],
    projects: [],
    work_experience: [],
    education: [],
    achievements: [],
    interests: ['Social Impact', 'Design', 'Media', 'Community'],
    linkedin: '',
  },
  {
    memberId: MBR_ABIGAL_ART4,
    name: 'Abigal Ojo',
    slug: 'abigal-ojo-artist-4',
    chapterId: DEFAULT_CHAPTER_ID,
    year: 1990,
    short_bio: 'Creative Director',
    long_bio:
      'Award-winning creative director known for her bold visual identity work for fashion and lifestyle brands.',
    photo: 'https://images.unsplash.com/photo-1523419409543-a5e549c1faa8?w=600&q=80',
    email: 'abigal.artist4@email.com',
    location: 'Lagos, Nigeria',
    company: 'Bold Studio',
    position: 'Creative Director',
    skills: ['Art Direction', 'Brand Strategy', 'Photography', 'Fashion', 'Campaign Design'],
    projects: [],
    work_experience: [],
    education: [],
    achievements: [],
    interests: ['Fashion', 'Photography', 'Branding', 'Lifestyle'],
    linkedin: '',
  },
  {
    memberId: MBR_ABIGAL_ART5,
    name: 'Abigal Ojo',
    slug: 'abigal-ojo-artist-5',
    chapterId: DEFAULT_CHAPTER_ID,
    year: 1990,
    short_bio: 'Creative Director',
    long_bio:
      'A creative director with expertise in digital media, content strategy, and visual communications for tech companies.',
    photo: 'https://images.unsplash.com/photo-1611432579699-484f7990b127?w=600&q=80',
    email: 'abigal.artist5@email.com',
    location: 'Lagos, Nigeria',
    company: 'TechMedia Lagos',
    position: 'Creative Director',
    skills: [
      'Digital Media',
      'Content Strategy',
      'UX Design',
      'Visual Communication',
      'Social Media',
    ],
    projects: [],
    work_experience: [],
    education: [],
    achievements: [],
    interests: ['Tech', 'Media', 'Design', 'Innovation'],
    linkedin: '',
  },
  {
    memberId: MBR_ABIGAL_ART6,
    name: 'Abigal Ojo',
    slug: 'abigal-ojo-artist-6',
    chapterId: DEFAULT_CHAPTER_ID,
    year: 1990,
    short_bio: 'Art Educator',
    long_bio:
      'Passionate visual artist and creative educator dedicated to nurturing the next generation of Nigerian artists.',
    photo: 'https://images.unsplash.com/photo-1590650153855-d9e808231d41?w=600&q=80',
    email: 'abigal.artist6@email.com',
    location: 'Lagos, Nigeria',
    company: 'Lagos Art Academy',
    position: 'Art Educator & Creative Director',
    skills: ['Art Education', 'Creative Direction', 'Curriculum Design', 'Painting', 'Drawing'],
    projects: [],
    work_experience: [],
    education: [],
    achievements: [],
    interests: ['Education', 'Art', 'Youth Development', 'Culture'],
    linkedin: '',
  },
  {
    memberId: MBR_ABIGAL_ART7,
    name: 'Abigal Ojo',
    slug: 'abigal-ojo-artist-7',
    chapterId: DEFAULT_CHAPTER_ID,
    year: 1990,
    short_bio: 'Creative Consultant',
    long_bio:
      'A visual artist and creative consultant helping brands find their unique visual voice.',
    photo: 'https://images.unsplash.com/photo-1619441207978-3d326c46e2c9?w=600&q=80',
    email: 'abigal.artist7@email.com',
    location: 'Lagos, Nigeria',
    company: 'Freelance',
    position: 'Creative Consultant',
    skills: ['Consulting', 'Brand Design', 'Visual Arts', 'Strategy', 'Mentorship'],
    projects: [],
    work_experience: [],
    education: [],
    achievements: [],
    interests: ['Art', 'Strategy', 'Mentorship', 'Design'],
    linkedin: '',
  },
];

export const alumni: Alumni[] = legacyAlumni.map(toAlumni);

// ─── Leadership ───────────────────────────────────────────────────────────────
// Excos are real registered members — linked via memberId.

export const leadership: LeadershipMember[] = [
  {
    id: 1,
    memberId: MBR_STELLA,
    name: 'Mrs. Stella Alochi',
    role: 'President',
    chapterId: DEFAULT_CHAPTER_ID,
    image: Leadership1,
    featured: true,
    bio: `Welcome to the official website of the Alumni Portal. We are more than graduates—we are the fire forged in shared halls, the quiet strength that shatters ceilings, and the unstoppable force lifting the next generation.\n\nFrom boardrooms to classrooms, from startups to policy tables, our alumni prove every day: education here didn't just open doors—it built empires, healed communities, and changed nations.\n\nAs your Alumni President, I see you: the doctors saving lives, the entrepreneurs building legacies, the mothers raising revolutionaries, the leaders shaping tomorrow.`,
  },
  {
    id: 2,
    memberId: MBR_ABIGAL_VP,
    name: 'Mrs. Abigal Ojo',
    role: 'Vice President',
    chapterId: DEFAULT_CHAPTER_ID,
    image: Leadership2,
  },
  {
    id: 3,
    memberId: MBR_JOSEPHINE,
    name: 'Mrs. Josephine Adeka',
    role: 'P.R.O',
    chapterId: DEFAULT_CHAPTER_ID,
    image: Leadership3,
  },
  {
    id: 4,
    memberId: MBR_FAVOUR,
    name: 'Mrs. Favour Adah',
    role: 'Secretary',
    chapterId: DEFAULT_CHAPTER_ID,
    image: Leadership4,
  },
  {
    id: 5,
    memberId: MBR_LILIAN,
    name: 'Mrs. Lilian Ojo',
    role: 'Secretary Gen',
    chapterId: DEFAULT_CHAPTER_ID,
    image: Leadership5,
  },
  {
    id: 6,
    memberId: MBR_GOODNESS,
    name: 'Mrs. Goodness Adeka',
    role: 'Cashier',
    chapterId: DEFAULT_CHAPTER_ID,
    image: Leadership6,
  },
  {
    id: 7,
    memberId: MBR_BELLA,
    name: 'Mrs. Bella Adah',
    role: 'Event Planner',
    chapterId: DEFAULT_CHAPTER_ID,
    image: Leadership7,
  },
];

// ─── Projects ─────────────────────────────────────────────────────────────────
const legacyProjects: LegacyProject[] = [
  {
    id: '1',
    title: 'Computer Donation 2025',
    description: '87/88 Set donated computer sets to support digital learning',
    budget: '₦897,908.00',
    image: 'https://images.unsplash.com/photo-1518770660439-4636190af475?w=700&q=80',
  },
  {
    id: '2',
    title: 'Whiteboards & Markers',
    description: 'Complete classroom whiteboard installation for better learning',
    budget: '₦993,200.00',
    image: 'https://images.unsplash.com/photo-1580582932707-520aed937b7b?w=700&q=80',
  },
  {
    id: '3',
    title: 'Community Safety Initiative',
    description: 'Enhanced security through comprehensive perimeter fencing project',
    budget: '₦698,090.00',
    image: 'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=700&q=80',
  },
];

export const projects: Project[] = legacyProjects.map(toProject);

const legacyEvents: LegacyEvent[] = [
  // ═══════════════════════════════════════════════════════════════════════════
  // UPCOMING EVENTS
  // ═══════════════════════════════════════════════════════════════════════════

  {
    // Identity
    id: 'evt-001',
    slug: 'annual-homecoming-weekend-gala',
    title: 'Annual Homecoming Weekend & Grand Gala',

    // Timing
    date: '2026-12-12',
    startTime: '18:00',
    endTime: '23:00',

    // Content
    description:
      'A spectacular reunion bringing together alumni from every set and every corner of the world. Awards ceremony, cultural night, and gala dinner.',
    content: `# Annual Homecoming Weekend & Grand Gala\n\nA spectacular reunion bringing together alumni from every set and every corner of the world.`,
    image: 'https://images.unsplash.com/photo-1519671482749-fd09be7ccebf?w=700&q=80',

    // Location
    location: 'Transcorp Hilton, Abuja',
    isVirtual: false,
    attire: 'Formal Attire',

    // Classification
    category: 'Reunion',
    tags: ['reunion', 'gala', 'networking', 'homecoming'],
    featured: true,
    status: 'published',

    // Registration
    capacity: 200,
    allowGuests: true,

    // Relations
    createdBy: MBR_ADAEZE,
    registrations: [MBR_NGOZI, MBR_CHIDINMA, MBR_PRECIOUS, MBR_THERESA],

    // Timestamps
    createdAt: '2026-01-15T10:00:00Z',
    publishedAt: '2026-01-15T10:30:00Z',

    // Legacy
    type: 'upcoming', // @deprecated - computed from date
  },

  {
    // Identity
    id: 'evt-002',
    slug: 'diaspora-virtual-networking-night',
    title: 'Diaspora Virtual Networking Night',

    // Timing
    date: '2026-12-12',
    startTime: '19:00',
    endTime: '21:00',

    // Content
    description:
      'Connecting alumni in the diaspora for an evening of networking and shared stories.',
    content: '',
    image: 'https://images.unsplash.com/photo-1588196749597-9ff075ee6b5b?w=700&q=80',

    // Location
    location: 'Zoom, Global',
    isVirtual: true,
    virtualLink: 'https://zoom.us/j/1234567890?pwd=PORTAL2026',
    attire: '',

    // Classification
    category: 'Networking',
    tags: ['diaspora', 'virtual', 'networking', 'global'],
    featured: false,
    status: 'published',

    // Registration
    capacity: undefined, // Unlimited for virtual
    allowGuests: false,

    // Relations
    createdBy: MBR_ADAEZE,
    registrations: [MBR_ABIGAL_DEV, MBR_ABIGAL_ART5],

    // Timestamps
    createdAt: '2026-01-20T14:00:00Z',
    publishedAt: '2026-01-20T14:15:00Z',

    // Legacy
    type: 'upcoming',
  },

  {
    // Identity
    id: 'evt-003',
    slug: 'child-birth-of-one-us',
    title: 'Child Birth Of One Us',

    // Timing
    date: '2028-12-12',
    startTime: '14:00',
    endTime: '17:00',

    // Content
    description: 'A celebration of new life and community support for our members.',
    content: '',
    image: 'https://images.unsplash.com/photo-1519689680058-324335c77eba?w=700&q=80',

    // Location
    location: 'Transcorp Hilton, Abuja',
    isVirtual: false,
    attire: 'Formal Attire',

    // Classification
    category: 'Celebration',
    tags: ['celebration', 'community', 'maternal', 'support'],
    featured: false,
    status: 'published',

    // Registration
    capacity: 100,
    allowGuests: true,

    // Relations
    createdBy: MBR_STELLA,
    registrations: [MBR_NGOZI, MBR_ABIGAL_OJO],

    // Timestamps
    createdAt: '2028-10-01T09:00:00Z',
    publishedAt: '2028-10-01T09:30:00Z',

    // Legacy
    type: 'upcoming',
  },

  {
    // Identity
    id: 'evt-004',
    slug: 'donation-for-project',
    title: 'Donation For Project',

    // Timing
    date: '2026-12-12',
    startTime: '10:00',
    endTime: '12:00',

    // Content
    description: 'Virtual fundraising drive for a community improvement project.',
    content: '',
    image: 'https://images.unsplash.com/photo-1532629345422-7515f3d16bb6?w=700&q=80',

    // Location
    location: 'Zoom, Global',
    isVirtual: true,
    virtualLink: 'https://zoom.us/j/9876543210?pwd=DONATE2026',
    attire: '',

    // Classification
    category: 'Fundraising',
    tags: ['donation', 'fundraising', 'project', 'giveback'],
    featured: false,
    status: 'published',

    // Registration
    capacity: undefined, // Unlimited
    allowGuests: false,

    // Relations
    createdBy: MBR_STELLA,
    registrations: [MBR_ADAEZE, MBR_NGOZI, MBR_CHIDINMA, MBR_PRECIOUS, MBR_THERESA],

    // Timestamps
    createdAt: '2026-02-01T08:00:00Z',
    publishedAt: '2026-02-01T09:00:00Z',

    // Legacy
    type: 'upcoming',
  },

  {
    // Identity
    id: 'evt-005',
    slug: 'community-season-opening',
    title: 'Community Season Opening',

    // Timing
    date: '2026-12-12',
    startTime: '09:00',
    endTime: '13:00',

    // Content
    description: 'Community gathering to welcome a new season of events and initiatives.',
    content: '',
    image: 'https://images.unsplash.com/photo-1580582932707-520aed937b7b?w=700&q=80',

    // Location
    location: 'Alumni Portal, Imo State',
    isVirtual: false,
    attire: 'Formal Attire',

    // Classification
    category: 'Education',
    tags: ['community', 'opening', 'ceremony'],
    featured: false,
    status: 'published',

    // Registration
    capacity: 150,
    allowGuests: true,

    // Relations
    createdBy: MBR_ADAEZE,
    registrations: [MBR_ADAEZE, MBR_STELLA, MBR_JOSEPHINE],

    // Timestamps
    createdAt: '2026-09-01T12:00:00Z',
    publishedAt: '2026-09-01T13:00:00Z',

    // Legacy
    type: 'upcoming',
  },

  {
    // Identity
    id: 'evt-006',
    slug: 'night-party',
    title: 'Night Party',

    // Timing
    date: '2026-12-12',
    startTime: '20:00',
    endTime: '02:00',

    // Content
    description: 'An evening of music, fun, and community.',
    content: '',
    image: 'https://images.unsplash.com/photo-1470225620780-dba8ba36b745?w=700&q=80',

    // Location
    location: 'Zoom, Global',
    isVirtual: true,
    virtualLink: 'https://zoom.us/j/1111222233?pwd=PARTY2026',
    attire: '',

    // Classification
    category: 'Social',
    tags: ['party', 'social', 'virtual', 'fun'],
    featured: false,
    status: 'published',

    // Registration
    capacity: undefined, // Unlimited
    allowGuests: false,

    // Relations
    createdBy: MBR_BELLA,
    registrations: [MBR_NGOZI, MBR_CHIDINMA, MBR_ABIGAL_OJO, MBR_PRECIOUS],

    // Timestamps
    createdAt: '2026-10-15T16:00:00Z',
    publishedAt: '2026-10-15T16:30:00Z',

    // Legacy
    type: 'upcoming',
  },

  // ═══════════════════════════════════════════════════════════════════════════
  // PAST EVENTS
  // ═══════════════════════════════════════════════════════════════════════════

  {
    // Identity
    id: 'evt-007',
    slug: 'annual-alumni-meet-2024',
    title: 'Annual Alumni Meet 2024',

    // Timing
    date: '2024-12-15',
    startTime: '15:00',
    endTime: '19:00',

    // Content
    description: 'Join us for our most anticipated event of the year!',
    content: '',
    image: 'https://images.unsplash.com/photo-1511795409834-ef04bbd61622?w=700&q=80',

    // Location
    location: 'Main Campus Auditorium',
    isVirtual: false,
    attire: 'Smart Casual',

    // Classification
    category: 'Reunion',
    tags: ['reunion', 'networking', 'annual', 'alumni'],
    featured: false,
    status: 'completed',

    // Registration
    capacity: 250,
    allowGuests: true,

    // Relations
    createdBy: MBR_STELLA,
    registrations: [MBR_ADAEZE, MBR_NGOZI, MBR_CHIDINMA, MBR_PRECIOUS, MBR_THERESA, MBR_ABIGAL_OJO],

    // Timestamps
    createdAt: '2024-09-01T10:00:00Z',
    publishedAt: '2024-09-01T11:00:00Z',
    updatedAt: '2024-12-16T10:00:00Z',

    // Legacy
    type: 'past',
  },

  {
    // Identity
    id: 'evt-008',
    slug: 'startup-pitch-competition-2024',
    title: 'Startup Pitch Competition 2024',

    // Timing
    date: '2024-04-20',
    startTime: '10:00',
    endTime: '16:00',

    // Content
    description:
      'Annual startup pitch competition showcasing innovative ideas from our alumni community.',
    content: '',
    image: 'https://images.unsplash.com/photo-1559136555-9303baea8ebd?w=700&q=80',

    // Location
    location: 'Innovation Hub, University Campus',
    isVirtual: false,
    attire: 'Business Casual',

    // Classification
    category: 'Entrepreneurship',
    tags: ['startup', 'pitch', 'entrepreneurship', 'innovation'],
    featured: false,
    status: 'completed',

    // Registration
    capacity: 100,
    allowGuests: false,

    // Relations
    createdBy: MBR_ADAEZE,
    registrations: [MBR_CHIDINMA, MBR_ABIGAL_CEO, MBR_ABIGAL_DEV],

    // Timestamps
    createdAt: '2024-01-15T09:00:00Z',
    publishedAt: '2024-01-15T10:00:00Z',
    updatedAt: '2024-04-21T14:00:00Z',

    // Legacy
    type: 'past',
  },

  {
    // Identity
    id: 'evt-009',
    slug: 'tech-career-fair-2024',
    title: 'Tech Career Fair 2024',

    // Timing
    date: '2024-03-15',
    startTime: '09:00',
    endTime: '17:00',

    // Content
    description: 'Annual technology career fair connecting alumni with top tech companies.',
    content: '',
    image: 'https://images.unsplash.com/photo-1540575467063-178a50c2df87?w=700&q=80',

    // Location
    location: 'Convention Center, Downtown',
    isVirtual: false,
    attire: 'Business Formal',

    // Classification
    category: 'Career',
    tags: ['career', 'technology', 'jobs', 'networking'],
    featured: false,
    status: 'completed',

    // Registration
    capacity: 300,
    allowGuests: false,

    // Relations
    createdBy: MBR_ADAEZE,
    registrations: [MBR_CHIDINMA, MBR_ABIGAL_DEV, MBR_ABIGAL_ART5],

    // Timestamps
    createdAt: '2023-12-01T08:00:00Z',
    publishedAt: '2023-12-01T09:00:00Z',
    updatedAt: '2024-03-16T12:00:00Z',

    // Legacy
    type: 'past',
  },

  {
    // Identity
    id: 'evt-010',
    slug: 'scholarship-award-night-2024',
    title: 'Scholarship Award Night 2024',

    // Timing
    date: '2024-02-10',
    startTime: '18:00',
    endTime: '22:00',

    // Content
    description: 'Celebrating our brightest students with the annual scholarship award ceremony.',
    content: '',
    image: 'https://images.unsplash.com/photo-1523580494863-6f3031224c94?w=700&q=80',

    // Location
    location: 'Grand Ballroom, Eko Hotel, Lagos',
    isVirtual: false,
    attire: 'Black Tie',

    // Classification
    category: 'Education',
    tags: ['scholarship', 'awards', 'education', 'excellence'],
    featured: true,
    status: 'completed',

    // Registration
    capacity: 180,
    allowGuests: true,

    // Relations
    createdBy: MBR_STELLA,
    registrations: [MBR_ADAEZE, MBR_NGOZI, MBR_PRECIOUS, MBR_THERESA, MBR_STELLA, MBR_FAVOUR],

    // Timestamps
    createdAt: '2023-11-01T10:00:00Z',
    publishedAt: '2023-11-01T11:00:00Z',
    updatedAt: '2024-02-11T09:00:00Z',

    // Legacy
    type: 'past',
  },

  {
    // Identity
    id: 'evt-011',
    slug: 'virtual-mentorship-summit-2023',
    title: 'Virtual Mentorship Summit 2023',

    // Timing
    date: '2023-11-05',
    startTime: '14:00',
    endTime: '18:00',

    // Content
    description: 'A virtual summit connecting senior alumni mentors with recent graduates.',
    content: '',
    image: 'https://images.unsplash.com/photo-1587825140708-dfaf72ae4b04?w=700&q=80',

    // Location
    location: 'Zoom, Global',
    isVirtual: true,
    virtualLink: 'https://zoom.us/j/5555666677?pwd=MENTOR2023',
    attire: '',

    // Classification
    category: 'Mentorship',
    tags: ['mentorship', 'virtual', 'career', 'guidance'],
    featured: false,
    status: 'completed',

    // Registration
    capacity: undefined, // Unlimited
    allowGuests: false,

    // Relations
    createdBy: MBR_ADAEZE,
    registrations: [MBR_NGOZI, MBR_CHIDINMA, MBR_ABIGAL_DEV, MBR_ABIGAL_ART5, MBR_ABIGAL_ART3],

    // Timestamps
    createdAt: '2023-09-01T10:00:00Z',
    publishedAt: '2023-09-01T11:00:00Z',
    updatedAt: '2023-11-06T10:00:00Z',

    // Legacy
    type: 'past',
  },

  {
    // Identity
    id: 'evt-012',
    slug: 'cultural-day-celebration-2023',
    title: 'Cultural Day Celebration 2023',

    // Timing
    date: '2023-10-01',
    startTime: '11:00',
    endTime: '20:00',

    // Content
    description:
      'A vibrant celebration of Nigerian culture, heritage, and the bonds that unite alumni.',
    content: '',
    image: 'https://images.unsplash.com/photo-1504609773096-104ff2c73ba4?w=700&q=80',

    // Location
    location: 'National Arts Theatre, Lagos',
    isVirtual: false,
    attire: 'Traditional Attire',

    // Classification
    category: 'Networking',
    tags: ['networking', 'celebration', 'alumni', 'annual'],
    featured: true,
    status: 'completed',

    // Registration
    capacity: 500,
    allowGuests: true,

    // Relations
    createdBy: MBR_BELLA,
    registrations: [
      MBR_ADAEZE,
      MBR_NGOZI,
      MBR_CHIDINMA,
      MBR_ABIGAL_OJO,
      MBR_PRECIOUS,
      MBR_THERESA,
      MBR_ABIGAL_CEO,
    ],

    // Timestamps
    createdAt: '2023-07-01T08:00:00Z',
    publishedAt: '2023-07-01T09:00:00Z',
    updatedAt: '2023-10-02T12:00:00Z',

    // Legacy
    type: 'past',
  },
];

export const events: Event[] = legacyEvents.map(toEvent);

// ─── Businesses (Marketplace) ─────────────────────────────────────────────────
// ownerId → memberId of the business owner

export const businesses = [
  {
    slug: 'ngozis-catering-service',
    businessId: 'BIZ-2024-001',
    ownerId: MBR_ABIGAL_CHEF, // Abigal Ojo (chef) — catering makes sense
    name: "Ngozi's Catering Service",
    category: 'Food & Beverages',
    description:
      'Premium catering services for weddings, corporate events, and private parties. Specialising in local and continental dishes.',
    images: [
      'https://images.unsplash.com/photo-1555939594-58d7cb561ad1?w=600&q=80',
      'https://images.unsplash.com/photo-1504674900247-0877df9cc836?w=600&q=80',
    ],
    location: 'Lekki, Lagos',
    phone: '+234 801 123 5678',
    website: 'www.ngozycatering.com',
    owner: 'Abigal Ojo',
  },
  {
    slug: 'johnnys-tech-limited',
    businessId: 'BIZ-2024-002',
    ownerId: MBR_ABIGAL_DEV, // Abigal Ojo (dev) — tech business
    name: "Abigal's Tech Limited",
    category: 'Technology',
    description:
      'Leading provider of IT solutions, phone sales, repairs, and software services for businesses and individuals across Lagos.',
    images: [
      'https://images.unsplash.com/photo-1519389950473-47ba0277781c?w=600&q=80',
      'https://images.unsplash.com/photo-1461749280684-dccba630e2f6?w=600&q=80',
    ],
    location: 'Yaba, Lagos',
    phone: '+234 801 123 5654',
    website: 'www.abigaltech.com',
    owner: 'Abigal Ojo',
  },
  {
    slug: 'abi-fashion-house',
    businessId: 'BIZ-2024-003',
    ownerId: MBR_ABIGAL_CEO, // Abigal Ojo (CEO) — fashion brand
    name: "Abi's Stitches Fashion House",
    category: 'Fashion & Beauty',
    description:
      'Contemporary African-inspired fashion label offering bespoke designs, ready-to-wear, and styling services.',
    images: [
      'https://images.unsplash.com/photo-1558769132-cb1aea458c5e?w=600&q=80',
      'https://images.unsplash.com/photo-1509631179647-0177331693ae?w=600&q=80',
    ],
    location: 'Lekki, Lagos',
    phone: '+234 801 123 6878',
    website: 'www.abistitches.com',
    owner: 'Abigal Ojo',
  },
  {
    slug: 'ngozi-medical-consulting',
    businessId: 'BIZ-2024-004',
    ownerId: MBR_NGOZI, // Ngozi Ibrahim — medical professional
    name: 'Ngozi Medical Consulting',
    category: 'Health & Wellness',
    description:
      'Professional medical consulting, health advisory, and wellness coaching for individuals and corporate clients.',
    images: [
      'https://images.unsplash.com/photo-1579684385127-1ef15d508118?w=600&q=80',
      'https://images.unsplash.com/photo-1559757175-5700dde675bc?w=600&q=80',
    ],
    location: 'Surulere, Lagos',
    phone: '+234 805 678 9012',
    website: 'www.ngozimedicare.com',
    owner: 'Ngozi Blessing Ibrahim',
  },
  {
    slug: 'chidinma-software-studio',
    businessId: 'BIZ-2024-005',
    ownerId: MBR_CHIDINMA, // Chidinma Eze — software developer
    name: 'Chidinma Software Studio',
    category: 'Technology',
    description:
      'Custom software development, mobile apps, and digital transformation consulting for Nigerian businesses.',
    images: [
      'https://images.unsplash.com/photo-1498050108023-c5249f4df085?w=600&q=80',
      'https://images.unsplash.com/photo-1581091226825-a6a2a5aee158?w=600&q=80',
    ],
    location: 'Abuja, FCT',
    phone: '+234 807 890 1234',
    website: 'www.chidinmastudio.com',
    owner: 'Chidinma Sandra Eze',
  },
  {
    slug: 'adaeze-consulting-group',
    businessId: 'BIZ-2024-006',
    ownerId: MBR_ADAEZE, // Adaeze Okonkwo — entrepreneur/consultant
    name: 'Adaeze Consulting Group',
    category: 'Consulting',
    description:
      'Strategic business consulting, financial advisory, and nonprofit management services for organisations across Nigeria.',
    images: [
      'https://images.unsplash.com/photo-1552664730-d307ca884978?w=600&q=80',
      'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?w=600&q=80',
    ],
    location: 'Lekki, Lagos',
    phone: '+234 803 123 4567',
    website: 'www.adaezeconsulting.com',
    owner: 'Adaeze Chioma Okonkwo',
  },
  {
    slug: 'precious-investment-advisory',
    businessId: 'BIZ-2024-007',
    ownerId: MBR_PRECIOUS, // Precious Ojeka — banker
    name: 'Precious Investment Advisory',
    category: 'Consulting',
    description:
      'Personal finance coaching, investment portfolio management, and banking advisory for high-net-worth individuals.',
    images: [
      'https://images.unsplash.com/photo-1611974789855-9c2a0a7236a3?w=600&q=80',
      'https://images.unsplash.com/photo-1554224155-6726b3ff858f?w=600&q=80',
    ],
    location: 'Lekki, Lagos',
    phone: '+234 801 000 0002',
    website: 'www.preciousadvisory.com',
    owner: 'Precious Ojeka',
  },
  {
    slug: 'abigal-art-gallery',
    businessId: 'BIZ-2024-008',
    ownerId: MBR_ABIGAL_ART2,
    name: 'Abigal Art Gallery & Studio',
    category: 'Events & Entertainment',
    description:
      'Contemporary Nigerian art gallery showcasing photography, painting, and mixed media works. Available for private exhibitions and corporate art installations.',
    images: [
      'https://images.unsplash.com/photo-1531243269054-5ebf6f34081e?w=600&q=80',
      'https://images.unsplash.com/photo-1578926375605-eaf7559b1458?w=600&q=80',
    ],
    location: 'Ikeja, Lagos',
    phone: '+234 801 000 0007',
    website: 'www.abigalartgallery.com',
    owner: 'Abigal Ojo',
  },
  {
    slug: 'impact-creative-agency',
    businessId: 'BIZ-2024-009',
    ownerId: MBR_ABIGAL_ART3,
    name: 'Impact Creative Agency',
    category: 'Consulting',
    description:
      'Creative communications agency specialising in NGO branding, social impact campaigns, and nonprofit storytelling across West Africa.',
    images: [
      'https://images.unsplash.com/photo-1542744173-8e7e53415bb0?w=600&q=80',
      'https://images.unsplash.com/photo-1556761175-4b46a572b786?w=600&q=80',
    ],
    location: 'Gbagada, Lagos',
    phone: '+234 801 000 0008',
    website: 'www.impactcreativeagency.com',
    owner: 'Abigal Ojo',
  },
];

export const categories = [
  'Food & Beverages',
  'Technology',
  'Consulting',
  'Fashion & Beauty',
  'Health & Wellness',
  'Education',
  'Real Estate',
  'Events & Entertainment',
];

// ─── Announcements ────────────────────────────────────────────────────────────
// createdBy → admin memberId

const newsItems: NewsItem[] = [
  {
    id: 1,
    slug: 'association-awards-45m-scholarships',
    title: 'Association Awards ₦45M in Scholarships — Largest in Our History',
    date: '2026-03-01',
    image: 'https://images.unsplash.com/photo-1523580494863-6f3031224c94?w=700&q=80',
    tag: 'SCHOLARSHIP',
    excerpt:
      'In an emotional community ceremony, the Alumni Association announced its highest-ever scholarship disbursement, directly supporting 180 participants across multiple community programs, with special focus on STEM and the Arts.',
    featured: true,
    createdBy: MBR_ADAEZE,
  },
  {
    id: 2,
    slug: 'houston-chapter-officially-launched',
    title: 'Houston Chapter Officially Launched — Our 32nd Global Chapter',
    date: '2026-03-01',
    image: 'https://images.unsplash.com/photo-1529156069898-49953e39b3ac?w=700&q=80',
    createdBy: MBR_STELLA,
  },
  {
    id: 3,
    slug: 'new-science-laboratory-wing-commissioned',
    title: 'New Innovation Space Commissioned at the Community Hub',
    date: '2026-03-01',
    image: 'https://images.unsplash.com/photo-1562774053-701939374585?w=700&q=80',
    createdBy: MBR_ADAEZE,
  },
  {
    id: 4,
    slug: 'alumni-of-the-year-2025-dr-chiamaka-obi',
    title: 'Alumni of the Year 2025 — Dr. Chiamaka Obi Honoured in Abuja',
    date: '2026-03-01',
    image: 'https://images.unsplash.com/photo-1567532939604-b6b5b0db2604?w=700&q=80',
    createdBy: MBR_JOSEPHINE,
  },
  {
    id: 5,
    slug: 'digital-yearbook-archive-now-live',
    title: "Digital Yearbook Archive Now Live — Access Your Set's Photos",
    date: '2026-03-01',
    image: 'https://images.unsplash.com/photo-1529333166437-7750a6dd5a70?w=700&q=80',
    createdBy: MBR_ADAEZE,
  },
].map((item) => ({ ...item, type: 'info' }));

// ─── Getters ──────────────────────────────────────────────────────────────────

export function getEvents(): Event[] {
  return events;
}
export function getEventBySlug(slug: string): Event | undefined {
  return events.find((e) => e.slug === slug);
}

export function getAlumni(): Alumni[] {
  return alumni;
}
export function getAlumnusBySlug(slug: string): Alumni | undefined {
  return alumni.find((a) => a.slug === slug);
}
export function getAlumnusByMemberId(id: string): Alumni | undefined {
  return alumni.find((a) => a.memberId === id);
}

export function getLeadership(): LeadershipMember[] {
  return leadership;
}
export function getLeaderById(id: number): LeadershipMember | undefined {
  return leadership.find((m) => m.id === id);
}
export function getLeaderByMemberId(memberId: string): LeadershipMember | undefined {
  return leadership.find((m) => m.memberId === memberId);
}

export function getProjects(): Project[] {
  return projects;
}
export function getProjectById(id: string): Project | undefined {
  return projects.find((p) => p.id === id);
}

export function getAnnouncements(): NewsItem[] {
  return newsItems;
}
export function getAnnouncementBySlug(slug: string): NewsItem | undefined {
  return newsItems.find((n) => n.slug === slug);
}

export function getBusinesses(): typeof businesses {
  return businesses;
}
export function getBusinessesByOwner(ownerId: string): typeof businesses {
  return businesses.filter((b) => b.ownerId === ownerId);
}
export function getBusinessBySlug(slug: string): (typeof businesses)[0] | undefined {
  return businesses.find((b) => b.slug === slug);
}

export function getEventRegistrations(memberId: string): Event[] {
  return events.filter((e) => e.registrations?.includes(memberId));
}
export function getEventAttendeeCount(eventSlug: string): number {
  return events.find((e) => e.slug === eventSlug)?.registrations?.length ?? 0;
}
