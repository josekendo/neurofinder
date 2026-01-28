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
    // Reemplazar completamente los filtros con los nuevos valores
    // El componente de filtros siempre envía todos los campos
    filters: {
      dementiaTypes: filters.dementiaTypes ?? [],
      documentTypes: filters.documentTypes ?? [],
      languages: filters.languages ?? [],
      dateFrom: filters.dateFrom,
      dateTo: filters.dateTo,
      minScore: filters.minScore,
      sortBy: filters.sortBy ?? 'score'
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
export const selectResults = createSelector(selectSearchState, (state) => {
  const results = [...state.results];
  const sortBy = state.filters.sortBy;

  if (sortBy === 'date') {
    // Ordenar por fecha de publicación: más reciente primero
    return results.sort((a: ArticleSummary, b: ArticleSummary) => {
      const dateA = new Date(a.publishedAt).getTime() || 0;
      const dateB = new Date(b.publishedAt).getTime() || 0;
      return dateB - dateA;
    });
  }

  // Ordenar por fiabilidad (score): más alta primero
  return results.sort((a: ArticleSummary, b: ArticleSummary) => b.score - a.score);
});
export const selectLoading = createSelector(selectSearchState, (state) => state.loading);
export const selectError = createSelector(selectSearchState, (state) => state.error);
export const selectLastRequest = createSelector(selectSearchState, (state) => state.lastRequest);

