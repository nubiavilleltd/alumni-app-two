import { X } from 'lucide-react';
import { useEffect } from 'react';

// ─── Reusable Modal Shell ─────────────────────────────────────────────────────
interface ModalProps {
  isOpen: boolean;
  onClose: () => void;
  title: string;
  children: React.ReactNode;
  size?: 'default' | 'wide';
}

export function Modal({ isOpen, onClose, title, children, size = 'default' }: ModalProps) {
  // Lock body scroll when open
  useEffect(() => {
    if (isOpen) {
      document.body.style.overflow = 'hidden';
    } else {
      document.body.style.overflow = '';
    }
    return () => {
      document.body.style.overflow = '';
    };
  }, [isOpen]);

  if (!isOpen) return null;

  const isWide = size === 'wide';

  return (
    // Overlay
    <div
      className={`fixed inset-0 z-50 overflow-y-auto px-4 py-6 ${
        isWide ? 'bg-black/35 backdrop-blur-[1.5px]' : 'bg-black/50 backdrop-blur-sm'
      }`}
      onClick={onClose}
    >
      <div className="flex min-h-full items-start justify-center sm:items-center">
        {/* Modal panel — stop click propagation so clicking inside doesn't close */}
        <div
          className={`relative my-auto w-full bg-white shadow-2xl ${
            isWide
              ? 'max-h-[82vh] max-w-[62rem] overflow-y-auto rounded-[2rem] shadow-[0_28px_72px_rgba(15,23,42,0.16)]'
              : 'max-w-lg overflow-visible rounded-2xl'
          }`}
          onClick={(e) => e.stopPropagation()}
        >
          {/* Header */}
          <div
            className={`flex items-start justify-between gap-4 border-b border-gray-100 ${
              isWide ? 'px-5 pb-4 pt-8 sm:px-8 md:px-10 md:pt-10' : 'px-6 pb-4 pt-6'
            }`}
          >
            <h2 className="min-w-0 whitespace-normal break-words text-xl font-bold text-primary-500 [overflow-wrap:anywhere]">
              {title}
            </h2>
            <button
              type="button"
              onClick={onClose}
              className="shrink-0 text-lg font-semibold leading-none text-gray-500 transition-colors hover:text-gray-800"
              aria-label="Close modal"
            >
              <X />
            </button>
          </div>

          {/* Content */}
          <div className={isWide ? 'px-5 pb-6 pt-5 sm:px-8 md:px-10 md:pb-8' : 'px-6 py-5'}>
            {children}
          </div>
        </div>
      </div>
    </div>
  );
}
