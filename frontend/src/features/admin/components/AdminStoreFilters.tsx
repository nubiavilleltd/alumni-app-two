import { SearchInput } from '@/shared/components/ui/input/SearchInput';
import { FilterDropdown } from '@/shared/components/ui/FilterDropdown';
import clsx from 'clsx';

interface Props {
  search: string;
  category: string;
  categories: string[];
  onSearch: (value: string) => void;
  onCategoryChange: (value: string) => void;
  className?: string;
}

export function AdminStoreFilters({
  search,
  category,
  categories,
  onSearch,
  onCategoryChange,
  className,
}: Props) {
  return (
    <div
      className={clsx(
        'flex flex-col gap-3 lg:flex-row lg:flex-wrap lg:items-center',
        className,
      )}
    >
      <div className="w-full lg:w-64 lg:flex-shrink-0">
        <SearchInput
          value={search}
          onValueChange={onSearch}
          placeholder="Search products"
          inputClassName="!h-10 !py-0"
        />
      </div>

      <FilterDropdown
        value={category}
        onChange={onCategoryChange}
        placeholder="Category"
        options={categories.map((c) => ({ label: c, value: c }))}
        className="w-full lg:w-44 lg:flex-shrink-0"
      />
    </div>
  );
}