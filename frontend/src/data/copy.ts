export const copy = {
  app: {
    name: 'PhotoGallery Pro',
  },

  common: {
    cancel: 'Cancel',
    save: 'Save',
    tryAgain: 'Try again',
    delete: 'Delete',
    confirm: 'Confirm',
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
    selectMode: 'Select',
    cancelSelect: 'Cancel',
    selectedCount: (n: number) => `${n} selected`,
    deleteSelected: 'Delete selected',
    deleteSelectedConfirm: (n: number) =>
      `Are you sure you want to delete ${n} photo${n > 1 ? 's' : ''}? This cannot be undone.`,
    photoDeleted: 'Photo deleted',
    photosDeleted: (n: number) => `Deleted ${n} photo${n > 1 ? 's' : ''}`,
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
    toggleExif: 'Toggle EXIF info',
    newTagPlaceholder: 'New tag name...',
  },

  exif: {
    title: 'EXIF Data',
    noData: 'No EXIF data',
    camera: 'Camera',
    iso: 'ISO',
    aperture: 'Aperture',
    shutter: 'Shutter Speed',
    focalLength: 'Focal Length',
    takenAt: 'Taken At',
  },

  sort: {
    newest: 'Newest',
    oldest: 'Oldest',
    titleAsc: 'Title A\u2013Z',
    titleDesc: 'Title Z\u2013A',
    favoritesFirst: 'Favorites first',
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
    removeSelected: 'Unfavorite selected',
    removeAll: 'Unfavorite all',
    selectMode: 'Select',
    cancelSelect: 'Cancel',
    selectedCount: (n: number) => `${n} selected`,
  },

  albums: {
    title: 'Albums',
    create: 'Create album',
    edit: 'Edit album',
    delete: 'Delete album',
    deleteConfirm: 'Are you sure you want to delete this album? Photos will not be deleted.',
    nameLabel: 'Album name',
    descriptionLabel: 'Description',
    descriptionPlaceholder: 'Add a description...',
    emptyState: 'No albums yet.',
  },

  tags: {
    title: 'Tags',
    add: 'Filter by tag',
    remove: 'Remove tag',
    placeholder: 'Filter by tag...',
    createHint: 'Press Enter to filter',
  },

  stats: {
    photos: 'Photos',
    albums: 'Albums',
    tags: 'Tags',
  },

  search: {
    placeholder: 'Search photos...',
  },
} as const;
