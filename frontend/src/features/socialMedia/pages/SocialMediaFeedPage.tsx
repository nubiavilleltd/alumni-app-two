import { Icon } from '@iconify/react';
import { SEO } from '@/shared/common/SEO';

const INSTAGRAM_PROFILE_URL = 'https://www.instagram.com/instagram/';

const socialPosts = [
  {
    id: 'community',
    image: '/images/social_media/Rectangle 102 (2).png',
    alt: 'Alumni gathered together outdoors',
    type: 'image',
  },
  {
    id: 'members',
    image: '/images/social_media/Group 593.png',
    alt: 'Two alumni smiling together',
    type: 'carousel',
  },
  {
    id: 'culture',
    image: '/images/social_media/Group 594.png',
    alt: 'Alumni celebrating in traditional attire',
    type: 'reel',
  },
] as const;

const profileStats = [
  ['126', 'Posts'],
  ['2.4K', 'Followers'],
  ['1.9K', 'Following'],
] as const;

function SectionEyebrow({ children }: { children: string }) {
  return (
    <div className="relative inline-flex w-fit max-w-max flex-none self-start px-3 py-2 text-sm font-semibold leading-[1.4] tracking-[0.01em] text-[#061015] min-[1200px]:text-base">
      <span className="absolute bottom-0 left-0 h-[1.35rem] w-[1.35rem] border-b-2 border-l-2 border-primary-500" />
      <span className="absolute right-0 top-0 h-[1.35rem] w-[1.35rem] border-r-2 border-t-2 border-primary-500" />
      <span className="relative">{children}</span>
    </div>
  );
}

function FeedPostIcon({ type }: { type: (typeof socialPosts)[number]['type'] }) {
  if (type === 'carousel') {
    return (
      <span className="flex h-10 w-10 items-center justify-center rounded-full bg-black/18 backdrop-blur-sm">
        <Icon icon="mdi:cards-outline" className="h-6 w-6 text-white drop-shadow" />
      </span>
    );
  }

  if (type === 'reel') {
    return (
      <span className="flex h-10 w-10 items-center justify-center rounded-full bg-black/18 backdrop-blur-sm">
        <Icon icon="mdi:video-outline" className="h-7 w-7 text-white drop-shadow" />
      </span>
    );
  }

  return null;
}

export default function SocialMediaFeedPage() {
  return (
    <>
      <SEO
        title="Social Media Feed"
        description="Follow the alumni community on Instagram."
      />

      <main className="min-h-screen bg-transparent text-[#061015]">
        <section className="px-[var(--app-page-inline-padding)] py-10 sm:py-14 lg:py-16">
          <div className="mx-auto grid max-w-[1312px] gap-9 lg:gap-12">
            <header className="grid gap-7 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-end">
              <div className="max-w-[760px]">
                <SectionEyebrow>Social Media</SectionEyebrow>
                <h1 className="type-section-title mt-6 max-w-[720px] text-[#000E17]">
                  Follow our community in motion
                </h1>
              </div>

              <p className="type-card-body max-w-[420px] text-[#4B5563] lg:text-right">
                Catch moments from alumni across events, milestones, and everyday community life.
              </p>
            </header>

            <section
              aria-label="Instagram profile preview"
              className="overflow-hidden rounded-[24px] bg-white shadow-[0_24px_80px_rgba(2,30,68,0.08)]"
            >
              <div className="grid gap-8 px-5 py-6 sm:px-8 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center lg:px-10 lg:py-8">
                <div className="flex min-w-0 flex-col gap-6 sm:flex-row sm:items-center">
                  <div className="relative h-20 w-20 shrink-0 rounded-full bg-[linear-gradient(135deg,#833AB4_0%,#FD1D1D_52%,#FCB045_100%)] p-[4px] sm:h-[96px] sm:w-[96px]">
                    <div className="flex h-full w-full items-center justify-center overflow-hidden rounded-full bg-white p-1.5">
                      <img
                        src="/new_logo.png"
                        alt="Alumni Portal"
                        className="h-full w-full rounded-full object-contain"
                      />
                    </div>
                  </div>

                  <div className="min-w-0">
                    <h2 className="type-card-title-featured text-[#000E17]">
                      Alumni Portal
                    </h2>
                    <p className="mt-2 text-sm font-semibold leading-tight tracking-[0.03em] text-primary-500 sm:text-base">
                      @alumniportal
                    </p>
                  </div>
                </div>

                <div className="flex flex-col gap-6 sm:flex-row sm:items-center lg:justify-end">
                  <dl className="grid grid-cols-3 gap-5 text-left sm:gap-8 sm:text-center">
                    {profileStats.map(([value, label]) => (
                      <div key={label}>
                        <dt className="text-lg font-semibold leading-none tracking-[0.01em] text-[#000E17] sm:text-xl">
                          {value}
                        </dt>
                        <dd className="mt-1 text-xs font-semibold leading-tight tracking-[0.03em] text-[#4B5563] sm:text-sm">
                          {label}
                        </dd>
                      </div>
                    ))}
                  </dl>

                  <a
                    href={INSTAGRAM_PROFILE_URL}
                    target="_blank"
                    rel="noreferrer"
                    className="inline-flex h-11 min-w-[8.75rem] items-center justify-center gap-2 rounded-full bg-[#ED0000] px-5 text-sm font-semibold tracking-[0.03em] text-white transition-colors hover:bg-[#D90000] focus-visible:outline focus-visible:outline-4 focus-visible:outline-offset-2 focus-visible:outline-red-200"
                  >
                    <Icon icon="mdi:instagram" className="h-5 w-5" />
                    Follow
                  </a>
                </div>
              </div>

              <div className="h-px bg-[#000E17]/10" />

              <div className="grid gap-5 px-5 py-6 sm:px-8 lg:grid-cols-3 lg:px-10 lg:py-10">
                {socialPosts.map((post) => (
                  <a
                    key={post.id}
                    href={INSTAGRAM_PROFILE_URL}
                    target="_blank"
                    rel="noreferrer"
                    className="group relative block aspect-[405/456] overflow-hidden rounded-[24px] bg-[#E0E0E0] focus-visible:outline focus-visible:outline-4 focus-visible:outline-offset-4 focus-visible:outline-primary-200"
                  >
                    <img
                      src={post.image}
                      alt={post.alt}
                      className="h-full w-full object-cover transition-transform duration-500 group-hover:scale-[1.03]"
                    />
                    <span className="absolute right-4 top-4">
                      <FeedPostIcon type={post.type} />
                    </span>
                    <span className="pointer-events-none absolute inset-0 bg-[linear-gradient(180deg,rgba(0,0,0,0)_55%,rgba(2,30,68,0.2)_100%)] opacity-0 transition-opacity duration-300 group-hover:opacity-100" />
                  </a>
                ))}
              </div>
            </section>
          </div>
        </section>
      </main>
    </>
  );
}
