import { Link } from 'react-router-dom';
import { copy } from '../data/copy';

export function NotFoundPage() {
  return (
    <div className="flex min-h-[60vh] flex-col items-center justify-center space-y-4">
      <p className="text-lg text-gray-600">{copy.errors.notFound}</p>
      <Link
        to="/"
        className="text-blue-600 hover:text-blue-800 hover:underline"
      >
        {copy.nav.allPhotos}
      </Link>
    </div>
  );
}

export default NotFoundPage;
