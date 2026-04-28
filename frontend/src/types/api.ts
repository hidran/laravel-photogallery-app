export interface PaginatedResponse<T> {
  data: T[];
  links: {
    next: string | null;
    prev: string | null;
  };
  meta: {
    path: string;
    per_page: number;
    next_cursor: string | null;
    prev_cursor: string | null;
  };
}

export interface SingleResponse<T> {
  data: T;
}

export interface ApiError {
  message: string;
  errors?: Record<string, string[]>;
}

export interface AuthResponse {
  data: {
    token: string;
    expires_at: string;
    user: {
      id: string;
      name: string;
      email: string;
      is_admin: boolean;
      created_at: string;
    };
  };
}

export interface BatchPhoto {
  id: string;
  title: string;
  processing_status: 'pending' | 'processing' | 'completed' | 'failed';
  processing_error: string | null;
}

export interface BatchResponse {
  data: {
    id: string;
    total: number;
    pending: number;
    failed: number;
    finished: boolean;
    photos: BatchPhoto[];
  };
}
