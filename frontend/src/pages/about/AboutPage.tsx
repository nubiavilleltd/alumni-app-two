import { AUTH_ROUTES } from "@/features/authentication/routes";
import { useIdentityStore } from "@/features/authentication/stores/useIdentityStore";
import { SEO } from "@/shared/common/SEO";
import { ButtonLink } from "@/shared/components/ui/Button";
import Leadership, { MessageFromPresident } from "./Leadership";

const missionFeatures = [
  {
    title: "Connect with Alumni",
    description:
      "Find and network with fellow graduates across different sets and industries.",
    icon: "/about_people.png",
  },
  {
    title: "Stay Updated",
    description: "Get the latest announcements, news, and important updates.",
    icon: "/about_speaker.png",
  },
  {
    title: "Attend Events",
    description:
      "Discover and participate in reunions meetups, and annual gatherings.",
    icon: "/about-balloon.png",
  },
  {
    title: "Explore Opportunities",
    description: "Browse the marketplace and organise collaborations.",
    icon: "/about-magnifying_glass.png",
  },
  {
    title: "Engage with the Community",
    description:
      "Join discussions, share ideas, and contribute to initiatives.",
    icon: "/about_message_bubble.png",
  },
];

function SectionEyebrow({ children }: { children: string }) {
  return (
    <div className="relative inline-flex w-fit max-w-max flex-none self-start px-3 py-2 text-sm font-semibold leading-[1.4] tracking-[0.01em] text-[#061015] min-[1200px]:text-base">
      <span className="absolute bottom-0 left-0 h-[1.35rem] w-[1.35rem] border-b-2 border-l-2 border-primary-500" />
      <span className="absolute right-0 top-0 h-[1.35rem] w-[1.35rem] border-r-2 border-t-2 border-primary-500" />
      <span className="relative font-semibold">{children}</span>
    </div>
  );
}

export function AboutPage() {
  const currentUser = useIdentityStore((state) => state.user);

  return (
    <>
      <SEO
        title="About"
        description="Learn about our alumni network, mission, and the team behind the Open Alumns Portal."
      />

      <main className="text-[#061015]">
        <section className="relative isolate overflow-hidden">
          <div className="pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(circle_at_88%_78%,rgba(0,119,204,0.34)_0%,rgba(0,119,204,0.24)_30%,rgba(0,119,204,0.10)_48%,transparent_68%),linear-gradient(145deg,rgba(255,255,255,0.42)_0%,rgba(250,253,255,0.42)_38%,rgba(213,236,250,0.44)_60%,rgba(148,207,244,0.58)_82%,rgba(94,184,239,0.50)_100%)]" />
          <div className="relative z-10 grid gap-10 px-[var(--app-page-inline-padding)] pb-[3.75rem] pt-11 sm:pt-16 min-[1200px]:min-h-[min(760px,calc(100svh-7rem))] min-[1200px]:grid-cols-[minmax(0,1fr)_clamp(360px,21vw,430px)] min-[1200px]:gap-[clamp(1.75rem,2.6vw,3rem)] min-[1200px]:py-[clamp(3rem,5.2vh,4.25rem)]">
            <div className="flex min-w-0 flex-col">
              <SectionEyebrow>Who We Are</SectionEyebrow>

              <h1 className="mt-6 max-w-[1340px] fonxt-sans text-[clamp(2rem,4.8vw,3.2rem)] font-semibold leading-[1.05] tracking-[0.01em] text-[#020c12] min-[1200px]:text-[clamp(2.8rem,3.68vw,3.6rem)]">
                <span className="block">We are a platform designed</span>
                <span className="block">to keep our members</span>
                <span className="block">connected across</span>
                <span className="block">generations and continents.</span>
              </h1>

              <p className="mt-11 flex flex-wrap items-center gap-x-4 gap-y-3 text-[24px] font-medium leading-[1.4] tracking-[0.01em] text-primary-500 min-[1200px]:mt-[clamp(3rem,6vh,4.5rem)] min-[1200px]:flex-nowrap min-[1200px]:gap-x-[0.5rem]">
                <span>Connection</span>
                <span className="text-[#020c12]">•</span>
                <span>Growth</span>
                <span className="text-[#020c12]">•</span>
                <span>Community</span>
                <span className="text-[#020c12]">•</span>
                <span>Impact</span>
              </p>
            </div>

            <div className="flex min-w-0 flex-col items-start min-[1200px]:items-stretch">
              <div className="w-full min-w-[min(100%,360px)] overflow-hidden rounded-[1.8rem] min-[1200px]:h-[clamp(350px,21vw,430px)] min-[1200px]:min-w-0 min-[1200px]:rounded-[2.25rem]">
                <img
                  src="/about_people.png"
                  alt="Alumni community members"
                  className="block h-[17rem] w-full object-cover object-center sm:h-[21rem] min-[1200px]:h-full"
                />
              </div>

              {!currentUser ? (
                <ButtonLink
                  href={AUTH_ROUTES.REGISTER}
                  size="lg"
                  className="type-button mt-5 inline-flex min-h-[3.4rem] w-full items-center justify-center rounded-full bg-primary-500 text-center text-white shadow-[0_18px_40px_rgb(var(--color-primary-500)/0.22)] transition-colors hover:bg-primary-600 focus:outline focus:outline-4 focus:outline-offset-2 focus:outline-primary-200 min-[1200px]:mt-[18px] min-[1200px]:h-[53px] min-[1200px]:min-h-[53px] min-[1200px]:w-[189px] min-[1200px]:max-w-none"
                >
                  Join Us
                </ButtonLink>
              ) : null}
            </div>
          </div>
        </section>

        <MessageFromPresident />

        <section className="px-[var(--app-page-inline-padding)] pb-20 pt-4 lg:pb-24">
          <div className="mx-auto max-w-[1920px]">
            <SectionEyebrow>Our Mission</SectionEyebrow>

            <div className="mt-8 max-w-[1770px]">
              <h2 className="type-section-title text-[#061015]">
                To foster a strong and supportive alumni network by enabling
                meaningful connections, encouraging collaboration, and providing
                access to valuable opportunities.
              </h2>

              <p className="type-card-body mt-7 max-w-[1850px] text-[#101820]">
                This platform is for all alumni: recent graduates,
                long-standing members, and everyone in between who want to stay
                connected, grow professionally, and give back to the community.
              </p>
            </div>

            <div className="mt-20 grid grid-cols-1 items-start justify-start justify-items-start gap-12 sm:grid-cols-2 lg:mt-[clamp(7rem,10vw,12rem)] lg:grid-cols-3 lg:gap-[clamp(2.5rem,4vw,5rem)] xl:mt-[clamp(8rem,11vw,14rem)] xl:grid-cols-5 xl:gap-[clamp(3rem,4vw,6rem)]">
              {missionFeatures.map((feature) => (
                <article
                  key={feature.title}
                  className="flex h-full w-full max-w-72 flex-col items-start text-left"
                >
                  <div className="flex h-[3.25rem] w-[3.25rem] items-center justify-center rounded-xl bg-[#E0E0E0] min-[1200px]:h-[4rem] min-[1200px]:w-[4rem]">
                    <img
                      src={feature.icon}
                      alt=""
                      className="block h-[1.65rem] w-[1.65rem] object-contain min-[1200px]:h-[1.95rem] min-[1200px]:w-[1.95rem]"
                    />
                  </div>

                  <h3 className="type-card-title mt-4 text-[#101820] min-[1200px]:mt-[1.1rem]">
                    {feature.title}
                  </h3>

                  <p className="type-card-body mt-4 text-accent-600 min-[1200px]:mt-[1.1rem]">
                    {feature.description}
                  </p>
                </article>
              ))}
            </div>
          </div>
        </section>

        <Leadership />
      </main>
    </>
  );
}
