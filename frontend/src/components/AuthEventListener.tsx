import { useEffect } from 'react';
import { useNavigate } from 'react-router-dom';

/**
 * Listens for 'auth:logout' events dispatched by the Axios 401 interceptor
 * and performs a React Router navigation instead of a hard page redirect.
 */
export function AuthEventListener() {
  const navigate = useNavigate();

  useEffect(() => {
    const handler = () => {
      navigate('/login', { replace: true });
    };

    window.addEventListener('auth:logout', handler);
    return () => window.removeEventListener('auth:logout', handler);
  }, [navigate]);

  return null;
}
