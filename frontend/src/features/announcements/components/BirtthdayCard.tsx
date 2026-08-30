import { X } from 'lucide-react';
import { Birthday } from '../types/announcement.types';
import { Link } from 'react-router-dom';


interface BirthdayCardProps {
  person: Birthday;
  onDismiss?: (userId: string) => void;
}

const FALLBACK_AVATAR = '/default.png';


export function BirthdayCard({ person, onDismiss }: BirthdayCardProps) {
  return (
    <div className="relative">
      {onDismiss && (
        <button
          type="button"
          onClick={() => onDismiss(person.userId)}
          aria-label={`Dismiss ${person.fullName}'s birthday announcement`}
          className="absolute -top-1 -right-1 z-10 flex h-7 w-7 items-center justify-center rounded-full text-primary-700 transition-colors hover:text-primary-900"
        >
          <X size={18} strokeWidth={2.5} />
        </button>
      )}

      <Link
        to={`/messages?threadId=${person.userId}`}
        // className="relative flex aspect-[9/10] w-full flex-col items-center overflow-hidden bg-cover bg-top bg-no-repeat px-4 pb-4 pt-[15%] text-center"
        className="relative flex aspect-[7/8] w-full flex-col items-center overflow-hidden bg-cover bg-top bg-no-repeat px-4 pb-4 pt-[8%] text-center"
        style={{ backgroundImage: "url('/birthday-bg-complete.png')" }}
      >
        <div className="aspect-[4/5] w-[42%] flex-shrink-0 overflow-hidden rounded-t-full rounded-x-2xl bg-white/20">
          <img
            src={person.avatar || FALLBACK_AVATAR}
            alt={person.fullName}
            className="h-full w-full object-cover"
            onError={(e) => {
              e.currentTarget.src = FALLBACK_AVATAR;
            }}
          />
        </div>

        <div className="mt-auto flex flex-col items-center gap-0.5 text-white">
          <p className="text-[0.7rem] font-medium leading-tight sm:text-xs">
            It&apos;s her birthday today!
          </p>
          <p className="text-base font-bold leading-tight sm:text-lg">{person.fullName}</p>
         {person.nameInSchool && <p className="text-xs font-medium leading-tight text-white/85 sm:text-sm">
            nee {person.nameInSchool}
          </p>}
          <p className="text-xs font-medium leading-tight text-white/85 sm:text-sm">
            {person.classLabel}
          </p>
        </div>
      </Link>
    </div>
  );
}