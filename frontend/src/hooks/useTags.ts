import { getTags } from '../api/tags';
import { createQueryHook } from './createQueryHook';

export const useTags = createQueryHook('tags', getTags);
