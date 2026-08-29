import type { ComponentPropsWithoutRef } from 'react';

import LogoImage from '/new_logo.png';

type HeaderLogoProps = Omit<ComponentPropsWithoutRef<'div'>, 'children'> & {
  className?: string;
  imageClassName?: string;
  wordmarkClassName?: string;
};

function mergeClasses(...values: Array<string | undefined>) {
  return values.filter(Boolean).join(' ');
}

function HeaderLogo({ className, imageClassName, wordmarkClassName, ...props }: HeaderLogoProps) {
  return (
    <div className={mergeClasses('flex items-center gap-3', className)} {...props}>
      <img
        src={LogoImage}
        alt="Alumni Portal"
        className={mergeClasses('h-12 w-12 flex-none object-contain', imageClassName)}
      />

      <span
        className={mergeClasses(
          'font-sans text-lg font-semibold uppercase tracking-wide text-white',
          wordmarkClassName,
        )}
      >
        Alumni Portal
      </span>
    </div>
  );
}

export default HeaderLogo;
