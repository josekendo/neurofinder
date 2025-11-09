import { createActionGroup, emptyProps, props } from '@ngrx/store';
import { ArticleSummary, SearchFilters, SearchRequest } from '../../core/models/content.models';

export const SearchActions = createActionGroup({
  source: 'Search',
  events: {
    'Set Query': props<{ query: string }>(),
    'Set Filters': props<{ filters: Partial<SearchFilters> }>(),
    'Execute Search': props<{ request?: SearchRequest }>(),
    'Search Success': props<{ results: ArticleSummary[] }>(),
    'Search Failure': props<{ error: string }>(),
    'Reset': emptyProps()
  }
});

