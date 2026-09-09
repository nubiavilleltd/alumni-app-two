import { useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { Modal } from '@/shared/components/ui/Modal';
import { FormInput } from '@/shared/components/ui/input/FormInput';
import { SelectInput } from '@/shared/components/ui/SelectInput';
import { TextareaInput } from '@/shared/components/ui/TextAreaInput';
import Button from '@/shared/components/ui/Button';
import type { Address } from '../types/address.types';
import { useDeliveryZones } from '../hooks/useDeliveryZones';
import { useAddresses } from '../hooks/useAddresses';
import { useLocations } from '@/shared/hooks/useLocations';
import {
  eventFormFieldLabelClassName,
  eventFormSelectClassName,
  eventFormSelectControlClassName,
} from '@/features/events/constants/eventFormStyles';
import { useCurrentUser } from '@/features/authentication/hooks/useCurrentUser';
import { useAuth } from '@/features/authentication/hooks/useAuth';

const addressSchema = z.object({
  firstName: z.string().trim().min(1, 'First name is required'),
  lastName: z.string().trim().min(1, 'Last name is required'),
  phone: z
    .string()
    .trim()
    .regex(/^0[789][01]\d{8}$/, 'Enter a valid 11-digit Nigerian phone number'),
  additionalPhone: z
    .string()
    .trim()
    .regex(/^(0[789][01]\d{8})?$/, 'Enter a valid 11-digit phone number or leave blank')
    .optional(),
  address: z
    .string()
    .trim()
    .min(5, 'Please enter a valid delivery address')
    .max(200, 'Character limits exceeded'),
  landmark: z.string().trim().optional(),
  state: z.string().min(1, 'Please select a state'),
  area: z.string().min(1, 'Please select an area'),
});

type AddressFormValues = z.infer<typeof addressSchema>;

interface Props {
  isOpen: boolean;
  onClose: () => void;
  editAddress?: Address | null;
}

export function AddressModal({ isOpen, onClose, editAddress }: Props) {
  const { addAddress, editAddress: editAddressMutation } = useAddresses();

  const { data: deliveryZones = [] } = useDeliveryZones();
  const { data: locations = [] } = useLocations();

  const { user } = useAuth();

  const stateOptions = locations.map((location) => ({
    label: location.state,
    value: location.state,
  }));

  const {
    register,
    handleSubmit,
    watch,
    setValue,
    trigger,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<AddressFormValues>({
    resolver: zodResolver(addressSchema),
    mode: 'onChange',
    defaultValues: {
      firstName: '',
      lastName: '',
      phone: '',
      additionalPhone: '',
      address: '',
      landmark: '',
      state: '',
      area: '',
    },
  });

  useEffect(() => {
    if (isOpen && editAddress) {
      reset({
        firstName: editAddress.firstName,
        lastName: editAddress.lastName,
        phone: editAddress.phone,
        additionalPhone: editAddress.additionalPhone ?? '',
        address: editAddress.address,
        landmark: editAddress.landmark ?? '',
        state: editAddress.state,
        area: editAddress.area,
      });

      setTimeout(() => {
        trigger('state');
        trigger('area');
      }, 0);
    } else if (isOpen) {
      reset({
        firstName: user?.otherNames ?? '',
        lastName: user?.surname ?? '',
        phone: user?.alternativePhone ?? '',
        additionalPhone: user?.whatsappPhone,
        address: '',
        landmark: '',
        state: '',
        area: '',
      });
    }
  }, [isOpen, editAddress, reset]);

  const selectedState = watch('state');
  const selectedArea = watch('area');

  useEffect(() => {
    if (selectedState && !editAddress) {
      setValue('area', '', { shouldValidate: false });
    }
  }, [selectedState, setValue, editAddress]);

  const selectedLocation = locations.find((location) => location.state === selectedState);

  const selectedZone = deliveryZones.find((zone) => zone.state === selectedState);

  const areaOptions = selectedZone?.areas.length
    ? selectedZone.areas.map((area) => ({
        label: area.area,
        value: area.area,
      }))
    : (selectedLocation?.cities.map((city) => ({
        label: city,
        value: city,
      })) ?? []);
  // Shipping fee hint for the selected area
  const selectedAreaFee =
    selectedZone?.areas.find((area) => area.area === selectedArea)?.fee ?? null;

  useEffect(() => {
    if (isOpen && editAddress && selectedState) {
      // Verify the area belongs to the selected state.
      const areaExists =
        selectedZone?.areas.some((area) => area.area === editAddress.area) ||
        selectedLocation?.cities.includes(editAddress.area);

      if (areaExists) {
        setValue('area', editAddress.area, {
          shouldValidate: true,
          shouldDirty: false,
        });
      }
    }
  }, [selectedState, editAddress, selectedZone, selectedLocation, setValue, isOpen]);

  const onSubmit = async (data: AddressFormValues) => {
    try {
      if (editAddress) {
        await editAddressMutation.mutateAsync({
          id: Number(editAddress.id),
          ...data,
          additionalPhone: data.additionalPhone || undefined,
          landmark: data.landmark || undefined,
        });
      } else {
        await addAddress.mutateAsync({
          ...data,
          additionalPhone: data.additionalPhone || undefined,
          landmark: data.landmark || undefined,
        });
      }

      onClose();
    } catch (error) {
      console.error(error);
    }
  };

  const isEditing = !!editAddress;

  return (
    <Modal isOpen={isOpen} onClose={onClose} title={isEditing ? 'Change Address' : 'Add Address'}>
      <form onSubmit={handleSubmit(onSubmit)} noValidate className="flex flex-col gap-4">
        {/* Name */}
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <FormInput
            label="First Name"
            id="firstName"
            required
            placeholder="Enter your first name"
            error={errors.firstName?.message}
            {...register('firstName')}
            style={{ backgroundColor: '#F8F7F4' }}
          />
          <FormInput
            label="Last Name"
            id="lastName"
            required
            placeholder="Enter your last name"
            error={errors.lastName?.message}
            {...register('lastName')}
            style={{ backgroundColor: '#F8F7F4' }}
          />
        </div>

        {/* Phone */}
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <FormInput
            label="Phone Number"
            id="phone"
            required
            placeholder="e.g. 08012345678"
            error={errors.phone?.message}
            {...register('phone')}
            style={{ backgroundColor: '#F8F7F4' }}
          />
          <FormInput
            label="Additional Phone Number"
            id="additionalPhone"
            placeholder="e.g. 09087654321"
            error={errors.additionalPhone?.message}
            {...register('additionalPhone')}
            style={{ backgroundColor: '#F8F7F4' }}
          />
        </div>

        {/* Address */}
        <TextareaInput
          label="Delivery Address"
          id="address"
          required
          rows={3}
          placeholder="Enter your address"
          maxLength={200}
          error={errors.address?.message}
          {...register('address')}
          style={{ backgroundColor: '#F8F7F4' }}
        />

        {/* Landmark */}
        <FormInput
          label="Landmark"
          id="landmark"
          placeholder="Enter landmark"
          error={errors.landmark?.message}
          {...register('landmark')}
          style={{ backgroundColor: '#F8F7F4' }}
        />

        {/* State + Area */}
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <SelectInput
            label="State"
            id="state"
            required
            options={stateOptions}
            placeholder="Select your state"
            error={errors.state?.message}
            value={selectedState}
            labelClassName={eventFormFieldLabelClassName}
            className={eventFormSelectClassName}
            controlClassName={eventFormSelectControlClassName}
            onChange={(e) => {
              const newState = e.target.value;
              setValue('state', newState, { shouldValidate: true, shouldDirty: true });
              setValue('area', '', { shouldValidate: false });
            }}
            onBlur={() => trigger('state')}
          />

          <div className="flex flex-col gap-1">
            <SelectInput
              label="Area"
              id="area"
              required
              options={areaOptions}
              placeholder={selectedState ? 'Select your area' : 'Select state first'}
              disabled={!selectedState}
              error={errors.area?.message}
              value={selectedArea}
              labelClassName={eventFormFieldLabelClassName}
              className={eventFormSelectClassName}
              controlClassName={eventFormSelectControlClassName}
              onChange={(e) =>
                setValue('area', e.target.value, { shouldValidate: true, shouldDirty: true })
              }
              onBlur={() => trigger('area')}
            />
            {/* Shipping fee hint — shown once area is selected */}
            {selectedAreaFee !== null && selectedArea && (
              <p className="text-xs text-gray-500 mt-0.5">
                Shipping fee:{' '}
                <span className="font-semibold text-primary-600">
                  ₦{selectedAreaFee.toLocaleString()}
                </span>
              </p>
            )}
          </div>
        </div>

        <div className="mt-12 flex items-center justify-center">
          <Button
            type="submit"
            className="self-center max-w-[200px] rounded-full"
            disabled={isSubmitting || addAddress.isPending || editAddressMutation.isPending}
            disabled:opacity-50
            disabled:cursor-not-allowed
          >
            {isEditing
              ? editAddressMutation.isPending
                ? 'Saving...'
                : 'Save Changes'
              : addAddress.isPending
                ? 'Adding...'
                : 'Add Address'}
          </Button>
        </div>
      </form>
    </Modal>
  );
}
