import React from 'react';
import { Icon } from '@iconify/react';

interface CoreValue {
  id: number;
  title: string;
  description: string;
  icon: string;
}

const values: CoreValue[] = [
  {
    id: 1,
    title: 'Connection',
    description: 'Relationships built across generations and borders.',
    icon: 'mdi:account-group',
  },
  {
    id: 2,
    title: 'Growth',
    description: 'Creating opportunities for learning, mentorship, and progress.',
    icon: 'mdi:trending-up',
  },
  {
    id: 3,
    title: 'Impact',
    description: 'Members leading change across business, medicine, arts, and policy.',
    icon: 'mdi:web',
  },
  {
    id: 4,
    title: 'Community',
    description: 'A trusted network where members support one another.',
    icon: 'mdi:account-group',
  },
  {
    id: 5,
    title: 'Service',
    description: 'Contributing time, expertise, and resources to meaningful causes.',
    icon: 'mdi:hand-heart',
  },
  {
    id: 6,
    title: 'Impact',
    description: 'Turning shared experience into practical support and opportunity.',
    icon: 'mdi:web',
  },
];

function ValueCard({ value }: { value: CoreValue }) {
  return (
    <div className="bg-white rounded-2xl border border-gray-100 shadow-sm p-8 flex flex-col items-center text-center hover:shadow-md transition-shadow">
      <div className="w-14 h-14 rounded-full bg-blue-100 flex items-center justify-center mb-5">
        <Icon icon={value.icon} className="w-6 h-6 text-primary-500" />
      </div>
      <h3 className="text-gray-900 font-bold text-base mb-3">{value.title}</h3>
      <p className="text-gray-500 text-sm leading-relaxed">{value.description}</p>
    </div>
  );
}

export default function CoreValues() {
  return (
    <section className="section">
      <div className="container-custom">
        {/* Header */}
        <div className="text-center mb-12">
          <h2 className="text-3xl md:text-4xl font-bold text-gray-900 mb-4">Our Core Values</h2>
          <p className="text-xl text-gray-500 text-sm max-w-md mx-auto leading-relaxed">
            The principles that guide our community and shape our collective future
          </p>
        </div>

        {/* Grid */}
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
          {values.map((value) => (
            <ValueCard key={value.id} value={value} />
          ))}
        </div>
      </div>
    </section>
  );
}
