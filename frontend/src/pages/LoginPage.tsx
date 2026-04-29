import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useLogin, useRegister } from '../hooks';
import { copy } from '../data/copy';
import type { ApiError } from '../types';
import { AxiosError } from 'axios';

type AuthMode = 'login' | 'register';

export function LoginPage() {
  const navigate = useNavigate();
  const loginMutation = useLogin();
  const registerMutation = useRegister();

  const [mode, setMode] = useState<AuthMode>('login');
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [generalError, setGeneralError] = useState('');

  const isLogin = mode === 'login';
  const mutation = isLogin ? loginMutation : registerMutation;

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setErrors({});
    setGeneralError('');

    const onSuccess = () => {
      void navigate('/');
    };

    const onError = (error: Error) => {
      if (error instanceof AxiosError && error.response?.status === 422) {
        const apiError = error.response.data as ApiError;
        setErrors(apiError.errors ?? {});
        setGeneralError(apiError.message);
      } else {
        setGeneralError(copy.errors.generic);
      }
    };

    if (isLogin) {
      loginMutation.mutate({ email, password }, { onSuccess, onError });
    } else {
      registerMutation.mutate(
        { name, email, password, password_confirmation: passwordConfirmation },
        { onSuccess, onError },
      );
    }
  };

  const toggleMode = () => {
    setMode(isLogin ? 'register' : 'login');
    setErrors({});
    setGeneralError('');
  };

  return (
    <div className="flex min-h-[60vh] items-center justify-center">
      <div className="w-full max-w-sm space-y-6">
        <h1 className="text-center text-2xl font-bold text-gray-900">
          {isLogin ? copy.auth.loginHeading : copy.auth.registerHeading}
        </h1>

        <form onSubmit={handleSubmit} className="space-y-4">
          {generalError && (
            <p className="text-center text-sm text-red-600">{generalError}</p>
          )}

          {!isLogin && (
            <div>
              <label htmlFor="name" className="block text-sm font-medium text-gray-700">
                {copy.auth.nameLabel}
              </label>
              <input
                id="name"
                type="text"
                value={name}
                onChange={(e) => setName(e.target.value)}
                required
                className="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
              />
              {errors['name'] && (
                <p className="mt-1 text-sm text-red-600">{errors['name'][0]}</p>
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
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              required
              className="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
            />
            {errors['email'] && (
              <p className="mt-1 text-sm text-red-600">{errors['email'][0]}</p>
            )}
          </div>

          <div>
            <label htmlFor="password" className="block text-sm font-medium text-gray-700">
              {copy.auth.passwordLabel}
            </label>
            <input
              id="password"
              type="password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              required
              className="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
            />
            {errors['password'] && (
              <p className="mt-1 text-sm text-red-600">{errors['password'][0]}</p>
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
                value={passwordConfirmation}
                onChange={(e) => setPasswordConfirmation(e.target.value)}
                required
                className="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
              />
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
