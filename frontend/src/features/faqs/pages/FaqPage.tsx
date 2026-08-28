import { useState } from 'react';
import { ExternalLink, Link2, Minus, Plus } from 'lucide-react';
import { SEO } from '@/shared/common/SEO';
import { AppLink } from '@/shared/components/ui/AppLink';
import { ROUTES } from '@/shared/constants/routes';
import { usePublishedFaqs } from '../hooks/useFaqs';
import type { Faq } from '../types/faq.types';
import { isExternalFaqLink, parseFaqAnswerWithLinks } from '../utils/faqLinks';

function FaqPageSkeleton() {
  return (
    <main className="min-h-screen bg-[#fbfbfa]">
      <section className="container-custom py-16 sm:py-20 lg:py-24">
        <div className="max-w-4xl animate-pulse">
          <div className="mb-4 h-12 w-3/4 rounded-full bg-gray-200 sm:h-14" />
          <div className="mb-2 h-7 w-full max-w-3xl rounded-full bg-gray-200" />
          <div className="h-7 w-2/3 rounded-full bg-gray-200" />
        </div>

        <div className="mt-16 grid gap-4 lg:grid-cols-2 lg:gap-x-10 lg:gap-y-4">
          {Array.from({ length: 10 }).map((_, index) => (
            <div
              key={index}
              className="h-[6.4rem] animate-pulse rounded-[0.85rem] border border-primary-100 bg-white"
            />
          ))}
        </div>
      </section>
    </main>
  );
}

function FaqCard({ faq, isOpen, onToggle }: { faq: Faq; isOpen: boolean; onToggle: () => void }) {
  const answerId = `faq-answer-${faq.id}`;
  const { answerText, links } = parseFaqAnswerWithLinks(faq.answer);

  return (
    <article className="rounded-[0.85rem] border border-primary-100 bg-white shadow-[0_1px_2px_rgb(var(--color-primary-500)/0.03)]">
      <button
        type="button"
        className="flex min-h-[4.5rem] w-full items-center justify-between gap-5 px-6 text-left sm:px-8"
        aria-expanded={isOpen}
        aria-controls={answerId}
        onClick={onToggle}
      >
        <span
          className={[
            'min-w-0 flex-1 text-[1rem] font-bold leading-[1.25] tracking-normal text-[#071116] sm:text-[18px]',
            isOpen ? '' : 'line-clamp-1',
          ].join(' ')}
        >
          {faq.question}
        </span>
        {isOpen ? (
          <Minus className="h-7 w-7 shrink-0 text-primary-500" strokeWidth={2.4} />
        ) : (
          <Plus className="h-7 w-7 shrink-0 text-primary-500" strokeWidth={2.4} />
        )}
      </button>

      {isOpen ? (
        <div id={answerId} className="px-6 pb-7 pt-0 sm:px-8">
          <div className="max-w-3xl space-y-4">
            <p className="whitespace-pre-line text-base font-medium leading-7 text-[#556070]">
              {answerText}
            </p>

            {links.length > 0 ? (
              <div className="flex flex-wrap gap-2 pt-1">
                {links.map((link) => {
                  const isExternal = isExternalFaqLink(link.url);
                  const Icon = isExternal ? ExternalLink : Link2;

                  return (
                    <a
                      key={`${link.label}-${link.url}`}
                      href={link.url}
                      target={isExternal ? '_blank' : undefined}
                      rel={isExternal ? 'noopener noreferrer' : undefined}
                      className="inline-flex max-w-full items-center gap-2 rounded-full bg-primary-50 px-4 py-2 text-sm font-bold text-primary-600 transition-colors hover:bg-primary-100"
                    >
                      <span className="truncate">{link.label}</span>
                      <Icon className="h-4 w-4 shrink-0" strokeWidth={2.35} />
                    </a>
                  );
                })}
              </div>
            ) : null}
          </div>
        </div>
      ) : null}
    </article>
  );
}

export function FaqPage() {
  const { data: faqs = [], isLoading, isError } = usePublishedFaqs();
  const [openFaqId, setOpenFaqId] = useState<string | null>(null);

  if (isLoading) {
    return <FaqPageSkeleton />;
  }

  return (
    <>
      <SEO
        title="Frequently Asked Questions"
        description="Find answers to common questions about the alumni portal and alumni community."
      />

      <main className="min-h-screen bg-[#fbfbfa]">
        <section className="container-custom py-16 sm:py-20 lg:py-24">
          <header className="max-w-4xl">
            <h1 className="m-0 text-[32px] font-bold leading-[1.08] tracking-normal text-[#071116]">
              Frequently Asked Questions
            </h1>
            <p className="mt-2 max-w-[48rem] font-base leading-[1.25] tracking-normal text-[#556070] sm:text-[20px]">
              Find answers to common questions about the platform, features, community programmes,
              and how to stay connected. Can't find what you're looking for?{' '}
              <AppLink href={ROUTES.CONTACT} className="underline underline-offset-4">
                Contact us!
              </AppLink>
            </p>
          </header>

          {isError ? (
            <div className="mt-16 rounded-[0.85rem] border border-primary-100 bg-white px-6 py-8 text-[#556070]">
              We could not load FAQs right now. Please try again later.
            </div>
          ) : null}

          {!isError && faqs.length === 0 ? (
            <div className="mt-16 rounded-[0.85rem] border border-primary-100 bg-white px-6 py-8 text-[#556070]">
              No FAQs have been published yet.
            </div>
          ) : null}

          {!isError && faqs.length > 0 ? (
            <div className="mt-16 grid items-start gap-4 lg:grid-cols-2 lg:gap-x-10 lg:gap-y-4">
              {faqs.map((faq) => (
                <FaqCard
                  key={faq.id}
                  faq={faq}
                  isOpen={openFaqId === faq.id}
                  onToggle={() => setOpenFaqId((current) => (current === faq.id ? null : faq.id))}
                />
              ))}
            </div>
          ) : null}
        </section>
      </main>
    </>
  );
}
