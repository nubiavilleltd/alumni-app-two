import { AppLink } from '@/shared/components/ui/AppLink';

import { useLeadership } from '@/features/leadership/hooks/useLeadership';
import { LeadershipMember } from '@/features/leadership/types/leadership.types';
import { Link } from 'react-router-dom';
import { ALUMNI_ROUTES } from '@/features/alumni/routes';

function SectionEyebrow({ children }: { children: string }) {
  return (
    <div className="relative inline-flex w-fit max-w-max flex-none self-start px-3 py-2 text-sm font-semibold leading-[1.4] tracking-[0.01em] text-[#061015] min-[1200px]:text-base">
      <span className="absolute bottom-0 left-0 h-[1.35rem] w-[1.35rem] border-b-2 border-l-2 border-primary-500" />
      <span className="absolute right-0 top-0 h-[1.35rem] w-[1.35rem] border-r-2 border-t-2 border-primary-500" />
      <span className="relative">{children}</span>
    </div>
  );
}

function MemberCard({ member }: { member: LeadershipMember }) {
  return (
   <Link to={ALUMNI_ROUTES.PROFILE(member.memberId)}>
    <div className="rounded-2xl overflow-hidden shadow-sm border border-gray-100 hover:shadow-md transition-shadow flex flex-col">
      {/* <div className="h-57 w-full overflow-hidden bg-gray-100"> */}
      <div className="aspect-square w-full overflow-hidden rounded-2xl">
        <img
          src={member.image}
          alt={member.name}
          className="w-full h-full object-cover object-top hover:scale-105 transition-transform duration-300"
        />
      </div>
      <div className="py-3 px-3">
        <h4 className="text-gray-900 font-bold text-sm">{member.name}</h4>
        <p className="text-gray-400 text-xs mt-0.5">{member.role}</p>
      </div>
    </div>
   </Link>
  );
}

function MemberCardSkeleton() {
  return (
    <div className="rounded-2xl overflow-hidden shadow-sm border border-gray-100 animate-pulse flex flex-col">
      <div className="h-57" />
      <div className="py-3 px-3 flex flex-col items-center gap-2">
        <div className="h-4 bg-gray-200 rounded w-32" />
        <div className="h-3 bg-gray-200 rounded w-20" />
      </div>
    </div>
  );
}

export default function Leadership() {
  const { data: members = [], isLoading } = useLeadership();

  const board = members.filter((m) => !m.featured);


  return (
    <section className="section">
      <div className="container-custom">
        <div className="mb-12">
          <SectionEyebrow>Our Leadership</SectionEyebrow>
          <p className="mt-2 font-semibold text-gray-700">Meet the leaders guiding the community</p>
        </div>

        <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-6">
          {isLoading
            ? Array.from({ length: 10 }).map((_, i) => <MemberCardSkeleton key={i} />)
            : board.map((member) => <MemberCard key={member.id} member={member} />)}
        </div>
      </div>
    </section>
  );
}

export function MessageFromPresident() {
  const { data: members = [], isLoading } = useLeadership();
  const president = members.find((m) => m.featured);

  if (!isLoading && !president) {
    return null;
  }

  return (
    <section className="section">
      <div className="container-custom">
        {isLoading ? (
          <div className="grid grid-cols-1 gap-8 animate-pulse md:grid-cols-[minmax(240px,311px)_1fr] md:gap-10 lg:gap-[clamp(2.5rem,5vw,5rem)]">
            <div className="flex flex-col gap-3">
              <div className="h-8 w-48 rounded bg-gray-200" />
              <div className="h-5 w-56 rounded bg-gray-200" />
              <div className="aspect-[311/403] w-full max-w-[311px] rounded-[24px] bg-gray-200" />
            </div>

            <div className="flex flex-col gap-4 pt-14">
              <div className="h-4 w-full rounded bg-gray-200" />
              <div className="h-4 w-full rounded bg-gray-200" />
              <div className="h-4 w-3/4 rounded bg-gray-200" />
              <div className="h-4 w-full rounded bg-gray-200" />
              <div className="h-4 w-5/6 rounded bg-gray-200" />
            </div>
          </div>
        ) : (
          president && (
            <Link to={ALUMNI_ROUTES.PROFILE(president.memberId)}>
              <div className="mb-6">
                <h2 className="text-2xl font-semibold leading-tight text-[#061015] md:text-[32px]">
                  From the President
                </h2>
                <h3 className="mt-1 text-lg font-semibold text-[#4B5563] md:text-[24px]">-{president.name}</h3>
              </div>

              <div className="grid grid-cols-1 items-start gap-8 md:grid-cols-[minmax(240px,311px)_1fr] md:gap-10 lg:gap-[clamp(2.5rem,5vw,5rem)]">
                <div className="mx-auto w-full max-w-[311px] md:mx-0">
                  <div className="aspect-[311/403] w-full overflow-hidden rounded-[24px] bg-gray-100 shadow-md">
                    <img
                      src={president.image}
                      alt={president.name}
                      className="h-full w-full object-cover object-top"
                    />
                  </div>
                </div>

                <div className="pt-1">
                  {president.bio?.split('\n\n').map((para, i) => (
                    <p key={i} className="mb-6 text-md leading-relaxed text-gray-700">
                      {para}
                    </p>
                  ))}
                </div>
              </div>
            </Link>
          )
        )}
      </div>
    </section>
  );
}
