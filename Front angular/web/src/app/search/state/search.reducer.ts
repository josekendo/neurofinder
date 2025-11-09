import { createFeatureSelector, createReducer, createSelector, on } from '@ngrx/store';
import { ArticleSummary, SearchFilters, SearchRequest } from '../../core/models/content.models';
import { SearchActions } from './search.actions';

export interface SearchState {
  query: string;
  filters: SearchFilters;
  loading: boolean;
  results: ArticleSummary[];
  lastRequest?: SearchRequest;
  error?: string;
}

const initialFilters: SearchFilters = {
  dementiaTypes: [],
  documentTypes: [],
  languages: [],
  dateFrom: undefined,
  dateTo: undefined,
  minScore: undefined,
  sortBy: 'score'
};

export const initialState: SearchState = {
  query: '',
  filters: initialFilters,
  loading: false,
  results: []
};

export const SEARCH_FEATURE_KEY = 'search';

export const searchReducer = createReducer(
  initialState,
  on(SearchActions.setQuery, (state, { query }) => ({
    ...state,
    query
  })),
  on(SearchActions.setFilters, (state, { filters }) => ({
    ...state,
    filters: {
      ...state.filters,
      ...filters
    }
  })),
  on(SearchActions.executeSearch, (state, { request }) => ({
    ...state,
    loading: true,
    error: undefined,
    lastRequest: request ?? { query: state.query, filters: state.filters }
  })),
  on(SearchActions.searchSuccess, (state, { results }) => ({
    ...state,
    loading: false,
    results
  })),
  on(SearchActions.searchFailure, (state, { error }) => ({
    ...state,
    loading: false,
    error
  })),
  on(SearchActions.reset, () => initialState)
);

export const selectSearchState = createFeatureSelector<SearchState>(SEARCH_FEATURE_KEY);

export const selectQuery = createSelector(selectSearchState, (state) => state.query);
export const selectFilters = createSelector(selectSearchState, (state) => state.filters);
export const selectResults = createSelector(selectSearchState, (state) => state.results);
export const selectLoading = createSelector(selectSearchState, (state) => state.loading);
export const selectError = createSelector(selectSearchState, (state) => state.error);
export const selectLastRequest = createSelector(selectSearchState, (state) => state.lastRequest);

