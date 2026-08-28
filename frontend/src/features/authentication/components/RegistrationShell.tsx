import type { ReactNode } from 'react';
import { AuthCard } from './AuthCard';
import type { RegistrationStep } from '../lib/registrationFlow';

const progressSteps: RegistrationStep[] = ['details', 'verification', 'success'];

const stepMeta: Record<RegistrationStep, { step: string; label: string }> = {
  details: { step: 'Step 1 of 3', label: 'Account Details' },
  verification: { step: 'Step 2 of 3', label: 'Verify your Email' },
  success: { step: 'Step 3 of 3', label: 'Approval Pending' },
};

interface RegistrationShellProps {
  step: RegistrationStep;
  children: ReactNode;
  shellClassName?: string;
  cardClassName?: string;
  headerClassName?: string;
  bodyClassName?: string;
  stepperClassName?: string;
  stepCountClassName?: string;
  stepLabelClassName?: string;
  stepDotsClassName?: string;
}

export function RegistrationShell({
  step,
  children,
  shellClassName,
  cardClassName,
  headerClassName,
  bodyClassName,
  stepperClassName,
  stepCountClassName,
  stepLabelClassName,
  stepDotsClassName,
}: RegistrationShellProps) {
  const { step: stepNumber, label: stepLabel } = stepMeta[step];
  const activeIndex = progressSteps.indexOf(step);

  return (
    <AuthCard
      title="Sign Up"
      subtitle="Join the network"
      variant="registration"
      shellClassName={shellClassName}
      cardClassName={cardClassName}
      headerClassName={headerClassName}
      bodyClassName={bodyClassName}
    >
      <div
        className={`auth-stepper ${stepperClassName ?? ''}`}
        aria-label={`Registration progress: ${stepNumber}`}
      >
        <div>
          <p className={`auth-stepper__count ${stepCountClassName ?? ''}`}>{stepNumber}</p>
          <p className={`auth-stepper__label ${stepLabelClassName ?? ''}`}>{stepLabel}</p>
        </div>
        <div className={`auth-stepper__dots ${stepDotsClassName ?? ''}`}>
          {progressSteps.map((registrationStep, index) => (
            <div
              key={registrationStep}
              className={`auth-stepper__dot ${
                registrationStep === step
                  ? 'auth-stepper__dot--active'
                  : index < activeIndex
                    ? 'auth-stepper__dot--complete'
                    : 'auth-stepper__dot--idle'
              }`}
            />
          ))}
        </div>
      </div>

      <div className={`auth-registration-content auth-registration-content--${step}`}>
        {children}
      </div>
    </AuthCard>
  );
}
