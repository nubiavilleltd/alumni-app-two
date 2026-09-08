import { Check, Copy, Mail } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Modal } from './Modal';
import { toast } from './Toast';

interface EmailInstructionModalProps {
  isOpen: boolean;
  onClose: () => void;
  email: string;
  title?: string;
  description?: string;
}

export function EmailInstructionModal({
  isOpen,
  onClose,
  email,
  title = 'Send an email',
  description = 'Send your message to the email address below.',
}: EmailInstructionModalProps) {
  const [isCopied, setIsCopied] = useState(false);

  useEffect(() => {
    if (!isOpen) setIsCopied(false);
  }, [isOpen]);

  if (!email) return null;

  const handleCopy = async () => {
    try {
      if (!navigator.clipboard) throw new Error('Clipboard unavailable');
      await navigator.clipboard.writeText(email);
      setIsCopied(true);
    } catch {
      toast.error('Unable to copy the email address. Please select it manually.');
    }
  };

  return (
    <Modal isOpen={isOpen} onClose={onClose} title={title}>
      <div className="space-y-5">
        <p className="text-sm leading-6 text-slate-600">{description}</p>

        <div className="rounded-2xl border border-primary-100 bg-primary-50/60 p-4">
          <a
            href={`mailto:${email}`}
            className="flex min-w-0 items-center gap-3 text-sm font-bold text-primary-600 underline-offset-4 hover:underline"
          >
            <Mail className="h-5 w-5 shrink-0" strokeWidth={2.2} />
            <span className="min-w-0 break-all">{email}</span>
          </a>
        </div>

        <div className="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
          <button
            type="button"
            onClick={onClose}
            className="rounded-full border border-slate-200 px-4 py-2.5 text-sm font-bold text-slate-600 transition-colors hover:bg-slate-50"
          >
            Close
          </button>
          <button
            type="button"
            onClick={() => {
              void handleCopy();
            }}
            className="inline-flex items-center justify-center gap-2 rounded-full bg-primary-500 px-4 py-2.5 text-sm font-bold text-white transition-colors hover:bg-primary-600"
          >
            {isCopied ? (
              <>
                <Check className="h-4 w-4" strokeWidth={2.4} />
                Copied
              </>
            ) : (
              <>
                <Copy className="h-4 w-4" strokeWidth={2.2} />
                Copy email
              </>
            )}
          </button>
        </div>
      </div>
    </Modal>
  );
}
