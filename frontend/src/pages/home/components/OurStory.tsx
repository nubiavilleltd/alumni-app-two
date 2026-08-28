import { AppLink } from '@/shared/components/ui/AppLink';
import { ROUTES } from '@/shared/constants/routes';

export default function OurStory() {
  return (
    <section
      className="px-[var(--app-page-inline-padding)] py-12 md:py-[50px]"
      aria-labelledby="home-about-title"
    >
      <div className="mx-auto flex max-w-[82rem] flex-col items-center text-center">
        <p className="mb-2 text-base font-semibold leading-normal tracking-[0.03em] text-[#0077cc]">
          About Us
        </p>

        <h2
          id="home-about-title"
          className="m-0 text-[clamp(1.75rem,2.25vw,2rem)] font-semibold leading-normal tracking-[0.03em] text-[#000e17]"
        >
          A Legacy Built Through Connection
        </h2>

        <div className="mt-2 max-w-[82rem] text-base font-normal leading-normal tracking-[0.03em] text-[#000e17] md:text-[20px]">
          <p className="m-0">
            Our alumni network brings graduates together through shared history, professional
            growth, and a commitment to creating value for one another and the wider community.
          </p>

          <p className="m-0 mt-8">
            The association exists to honour that legacy: connecting alumni across generations and
            continents, supporting meaningful initiatives, and keeping the community active,
            generous, and forward-looking.{' '}
            <AppLink
              href={ROUTES.ABOUT}
              className="whitespace-nowrap font-semibold text-[#021e44] no-underline transition-colors hover:text-[#0077cc]"
            >
              Read more
            </AppLink>
          </p>
        </div>
      </div>
    </section>
  );
}
