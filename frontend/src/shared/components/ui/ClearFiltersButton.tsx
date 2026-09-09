interface ClearFiltersButtonProps {
  onClick: () => void;
  label?: string;
  className?: string;
}

export function ClearFiltersButton({
  onClick,
  label = 'Clear filters',
  className = '',
}: ClearFiltersButtonProps) {
  return (
    <button
      type="button"
      onClick={onClick}
      className={`inline-flex h-10 shrink-0 items-center justify-center rounded-full border border-primary-200 bg-transparent px-4 text-sm font-semibold text-primary-600 transition-colors hover:border-primary-300 hover:bg-primary-50 hover:text-primary-700 ${className}`}
    >
      {label}
    </button>
  );
}
