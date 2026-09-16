import { SearchInput } from '@/shared/components/ui/input/SearchInput';
import { FilterDropdown } from '@/shared/components/ui/FilterDropdown';
import { ClearFiltersButton } from '@/shared/components/ui/ClearFiltersButton';
import clsx from 'clsx';

interface Props {
  search: string;
  category: string;
  categories: string[];
  priceRange: string;
  availability: string;
  variantFilter: string;
  onSearch: (value: string) => void;
  onCategoryChange: (value: string) => void;
  onPriceRangeChange: (value: string) => void;
  onAvailabilityChange: (value: string) => void;
  onVariantChange: (value: string) => void;
  onClearFilters: () => void;
  showClearFilters: boolean;

  className?: string;
}

export function StoreFilters({
  search,
  category,
  categories,
  priceRange,
  availability,
  variantFilter,
  onSearch,
  onCategoryChange,
  onPriceRangeChange,
  onAvailabilityChange,
  onVariantChange,
  onClearFilters,
  showClearFilters,
  className,
}: Props) {
  return (
    <div
      className={clsx('flex flex-col gap-3 lg:flex-row lg:flex-wrap lg:items-center', className)}
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
        options={categories.map((c) => ({
          label: c,
          value: c,
        }))}
        className="w-full lg:w-44 lg:flex-shrink-0"
      />

      <FilterDropdown
        value={priceRange}
        onChange={onPriceRangeChange}
        placeholder="Price range"
        options={[
          { label: 'Under 5,000', value: 'under-5000' },
          { label: '5,000–15,000', value: '5000-15000' },
          { label: '15,000–30,000', value: '15000-30000' },
          { label: '30,000 and above', value: '30000-plus' },
        ]}
        sortOptionsAlphabetically={false}
        className="w-full lg:w-44 lg:flex-shrink-0"
      />

      <FilterDropdown
        value={availability}
        onChange={onAvailabilityChange}
        placeholder="Availability"
        options={[
          { label: 'In stock', value: 'in-stock' },
          { label: 'Out of stock', value: 'out-of-stock' },
        ]}
        sortOptionsAlphabetically={false}
        className="w-full lg:w-44 lg:flex-shrink-0"
      />

      <FilterDropdown
        value={variantFilter}
        onChange={onVariantChange}
        placeholder="Variety"
        options={[
          { label: 'Available in sizes', value: 'sizes' },
          { label: 'Available in colors', value: 'colors' },
        ]}
        sortOptionsAlphabetically={false}
        className="w-full lg:w-44 lg:flex-shrink-0"
      />

      {showClearFilters && (
        <ClearFiltersButton onClick={onClearFilters} className="w-full lg:w-auto" />
      )}
    </div>
  );
}
