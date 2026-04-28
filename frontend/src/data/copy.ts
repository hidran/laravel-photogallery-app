export const copy = {
  app: {
    name: 'PhotoGallery Pro',
  },

  nav: {
    allPhotos: 'All Photos',
    favorites: 'Favorites',
    albums: 'Albums',
    tags: 'Tags',
  },

  auth: {
    login: 'Log in',
    register: 'Create account',
    logout: 'Log out',
    emailLabel: 'Email',
    passwordLabel: 'Password',
    passwordConfirmLabel: 'Confirm password',
    nameLabel: 'Name',
    loginHeading: 'Sign in to your account',
    registerHeading: 'Create a new account',
    noAccount: "Don't have an account?",
    hasAccount: 'Already have an account?',
  },

  gallery: {
    emptyState: 'No photos yet. Upload your first photo to get started.',
    emptyFavorites: 'No favorite photos yet. Heart a photo to see it here.',
    emptyAlbum: 'This album is empty. Add photos to get started.',
    emptySearch: 'No photos match your search.',
    loading: 'Loading photos...',
  },

  upload: {
    title: 'Upload Photos',
    dragDrop: 'Drag and drop photos here, or click to browse',
    uploading: 'Uploading...',
    processing: 'Processing...',
    complete: 'Upload complete',
    errorMaxFiles: 'You can upload up to 20 files at a time.',
    errorMaxSize: 'Each file must be 10 MB or smaller.',
    errorFileType: 'Only JPEG, PNG, and WebP files are supported.',
  },

  lightbox: {
    previous: 'Previous photo',
    next: 'Next photo',
    close: 'Close',
    edit: 'Edit details',
    deleteConfirm: 'Are you sure you want to delete this photo? This action cannot be undone.',
    delete: 'Delete photo',
    viewFullSize: 'View full size',
  },

  errors: {
    generic: 'Something went wrong. Please try again.',
    unauthorized: 'You must be logged in to do that.',
    forbidden: 'You do not have permission to perform this action.',
    notFound: 'The requested resource was not found.',
    tooLarge: 'The file is too large. Maximum size is 10 MB.',
    validationFailed: 'Please check your input and try again.',
    serverError: 'A server error occurred. Please try again later.',
  },

  favorites: {
    title: 'Favorites',
    add: 'Add to favorites',
    remove: 'Remove from favorites',
  },

  albums: {
    title: 'Albums',
    create: 'Create album',
    edit: 'Edit album',
    delete: 'Delete album',
    deleteConfirm: 'Are you sure you want to delete this album? Photos will not be deleted.',
    nameLabel: 'Album name',
    descriptionLabel: 'Description',
    emptyState: 'No albums yet. Create one to organize your photos.',
  },

  tags: {
    title: 'Tags',
    add: 'Add tag',
    remove: 'Remove tag',
    placeholder: 'Type a tag name...',
  },

  search: {
    placeholder: 'Search photos...',
  },
} as const;
