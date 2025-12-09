import { inject, Injectable } from '@angular/core';
import { Actions, createEffect, ofType } from '@ngrx/effects';
import { Store } from '@ngrx/store';
import { catchError, map, of, switchMap, withLatestFrom } from 'rxjs';

import { ApiService } from '../../core/services/api.service';
import { SearchActions } from './search.actions';
import { selectFilters, selectQuery } from './search.reducer';

@Injectable()
export class SearchEffects {
  private readonly actions$ = inject(Actions);
  private readonly api = inject(ApiService);
  private readonly store = inject(Store);

  executeSearch$ = createEffect(() =>
    this.actions$.pipe(
      ofType(SearchActions.executeSearch),
      withLatestFrom(
        this.store.select(selectQuery),
        this.store.select(selectFilters)
      ),
      switchMap(([action, query, filters]) => {
        // Siempre usar los valores actuales del store para asegurar que los filtros estén actualizados
        // Solo usar action.request si se pasa explícitamente y tiene todos los campos
        const request: { query: string; filters: any } = action.request 
          ? { 
              query: action.request.query ?? query ?? '', 
              filters: { ...filters, ...(action.request.filters ?? {}) }
            }
          : { 
              query: query ?? '', 
              filters: filters ?? {
                dementiaTypes: [],
                documentTypes: [],
                languages: [],
                dateFrom: undefined,
                dateTo: undefined,
                minScore: undefined,
                sortBy: 'score'
              }
            };
        
        return this.api.search(request).pipe(
          map((results) => SearchActions.searchSuccess({ results })),
          catchError(() => of(SearchActions.searchFailure({ error: 'ERROR.GENERIC' })))
        );
      })
    )
  );
}

