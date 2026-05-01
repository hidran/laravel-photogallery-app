import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { useLogin, useRegister } from '../hooks';
import { copy } from '../data/copy';
import type { ApiError } from '../types';
import { AxiosError } from 'axios';

type AuthMode = 'login' | 'register';

const loginSchema = z.object({
  email: z.string().email('Invalid email address'),
  password: z.string().min(1, 'Password is required'),
});

const registerSchema = z
  .object({
    name: z.string().min(1, 'Name is required'),
    email: z.string().email('Invalid email address'),
    password: z.string().min(8, 'Password must be at least 8 characters'),
    passwordConfirmation: z.string().min(1, 'Please confirm your password'),
  })
  .refine((data) => data.password === data.passwordConfirmation, {
    message: "Passwords don't match",
    path: ['passwordConfirmation'],
  });

type LoginFormData = z.infer<typeof loginSchema>;
type RegisterFormData = z.infer<typeof registerSchema>;

export function LoginPage() {
  const navigate = useNavigate();
  const loginMutation = useLogin();
  const registerMutation = useRegister();

  const [mode, setMode] = useState<AuthMode>('login');
  const [generalError, setGeneralError] = useState('');

  const isLogin = mode === 'login';
  const mutation = isLogin ? loginMutation : registerMutation;

  const loginForm = useForm<LoginFormData>({
    resolver: zodResolver(loginSchema),
    defaultValues: { email: '', password: '' },
  });

  const registerForm = useForm<RegisterFormData>({
    resolver: zodResolver(registerSchema),
    defaultValues: { name: '', email: '', password: '', passwordConfirmation: '' },
  });

  // Cast to unify union types — both forms share email/password fields,
  // and register-only fields are guarded by `!isLogin` checks below.
  const activeForm = (isLogin ? loginForm : registerForm) as ReturnType<typeof useForm<RegisterFormData>>;

  const onSubmit = (data: LoginFormData | RegisterFormData) => {
    setGeneralError('');

    const onSuccess = () => {
      void navigate('/');
    };

    const onError = (error: Error) => {
      if (error instanceof AxiosError && error.response?.status === 422) {
        const apiError = error.response.data as ApiError;
        setGeneralError(apiError.message);
      } else {
        setGeneralError(copy.errors.generic);
      }
    };

    if (isLogin) {
      loginMutation.mutate(data as LoginFormData, { onSuccess, onError });
    } else {
      const { passwordConfirmation, ...rest } = data as RegisterFormData;
      registerMutation.mutate(
        { ...rest, password_confirmation: passwordConfirmation },
        { onSuccess, onError },
      );
    }
  };

  const toggleMode = () => {
    setMode(isLogin ? 'register' : 'login');
    setGeneralError('');
    loginForm.reset();
    registerForm.reset();
  };

  return (
    <div className="flex min-h-[60vh] items-center justify-center">
      <div className="w-full max-w-sm space-y-6">
        <h1 className="text-center text-2xl font-bold text-gray-900">
          {isLogin ? copy.auth.loginHeading : copy.auth.registerHeading}
        </h1>

        <form onSubmit={activeForm.handleSubmit(onSubmit)} className="space-y-4">
          {generalError && <p className="text-center text-sm text-red-600">{generalError}</p>}

          {!isLogin && (
            <div>
              <label htmlFor="name" className="block text-sm font-medium text-gray-700">
                {copy.auth.nameLabel}
              </label>
              <input
                id="name"
                type="text"
                {...registerForm.register('name')}
                className="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
              />
              {registerForm.formState.errors.name && (
                <p className="mt-1 text-sm text-red-600">{registerForm.formState.errors.name.message}</p>
              )}
            </div>
          )}

          <div>
            <label htmlFor="email" className="block text-sm font-medium text-gray-700">
              {copy.auth.emailLabel}
            </label>
            <input
              id="email"
              type="email"
              {...activeForm.register('email')}
              className="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
            />
            {activeForm.formState.errors.email && (
              <p className="mt-1 text-sm text-red-600">{activeForm.formState.errors.email.message}</p>
            )}
          </div>

          <div>
            <label htmlFor="password" className="block text-sm font-medium text-gray-700">
              {copy.auth.passwordLabel}
            </label>
            <input
              id="password"
              type="password"
              {...activeForm.register('password')}
              className="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
            />
            {activeForm.formState.errors.password && (
              <p className="mt-1 text-sm text-red-600">{activeForm.formState.errors.password.message}</p>
            )}
          </div>

          {!isLogin && (
            <div>
              <label
                htmlFor="password_confirmation"
                className="block text-sm font-medium text-gray-700"
              >
                {copy.auth.passwordConfirmLabel}
              </label>
              <input
                id="password_confirmation"
                type="password"
                {...registerForm.register('passwordConfirmation')}
                className="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
              />
              {registerForm.formState.errors.passwordConfirmation && (
                <p className="mt-1 text-sm text-red-600">
                  {registerForm.formState.errors.passwordConfirmation.message}
                </p>
              )}
            </div>
          )}

          <button
            type="submit"
            disabled={mutation.isPending}
            className="w-full rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50"
          >
            {isLogin ? copy.auth.login : copy.auth.register}
          </button>
        </form>

        <p className="text-center text-sm text-gray-600">
          {isLogin ? copy.auth.noAccount : copy.auth.hasAccount}{' '}
          <button
            type="button"
            onClick={toggleMode}
            className="font-medium text-blue-600 hover:text-blue-500"
          >
            {isLogin ? copy.auth.register : copy.auth.login}
          </button>
        </p>
      </div>
    </div>
  );
}

export default LoginPage;
