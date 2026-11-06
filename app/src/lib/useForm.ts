import { useState } from 'react';

import { ApiError } from './api';

/**
 * Minimal form state for API-backed forms: values, field errors from Laravel (422)
 * and a general error message.
 */
export function useForm<T extends Record<string, string>>(initial: T) {
  const [values, setValues] = useState<T>(initial);
  const [errors, setErrors] = useState<Partial<Record<keyof T, string>>>({});
  const [formError, setFormError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  const set = (key: keyof T) => (value: string) => {
    setValues((v) => ({ ...v, [key]: value }));
    setErrors((e) => ({ ...e, [key]: undefined }));
  };

  async function submit(action: (values: T) => Promise<void>) {
    setSubmitting(true);
    setFormError(null);
    setErrors({});
    try {
      await action(values);
    } catch (error) {
      if (error instanceof ApiError && error.isValidation) {
        const fieldErrors: Partial<Record<keyof T, string>> = {};
        const unknown: string[] = [];
        for (const [field, messages] of Object.entries(error.errors)) {
          if (field in values) fieldErrors[field as keyof T] = messages[0];
          else unknown.push(messages[0]);
        }
        setErrors(fieldErrors);
        if (unknown.length > 0) setFormError(unknown.join(' '));
      } else {
        setFormError(error instanceof Error ? error.message : 'Das hat nicht geklappt.');
      }
    } finally {
      setSubmitting(false);
    }
  }

  return { values, set, errors, formError, setFormError, submitting, submit };
}
