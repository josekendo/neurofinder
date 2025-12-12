import { ChangeDetectionStrategy, Component, OnDestroy, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute, Router } from '@angular/router';
import { Store } from '@ngrx/store';
import { Subject, takeUntil, distinctUntilChanged } from 'rxjs';
import { MatProgressBarModule } from '@angular/material/progress-bar';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatDividerModule } from '@angular/material/divider';
import { TranslateModule, TranslateService } from '@ngx-translate/core';
import { ReactiveFormsModule, FormBuilder } from '@angular/forms';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatCardModule } from '@angular/material/card';
import { MatChipsModule } from '@angular/material/chips';
import { DatePipe } from '@angular/common';

import { FiltersPanelComponent } from '../../../shared/components/filters-panel/filters-panel.component';
import { ArticleCardComponent } from '../../../shared/components/article-card/article-card.component';
import { SearchActions } from '../../state/search.actions';
import {
  selectError,
  selectFilters,
  selectLoading,
  selectQuery,
  selectResults
} from '../../state/search.reducer';
import { SearchFilters } from '../../../core/models/content.models';
import { SeoService } from '../../../core/services/seo.service';

@Component({
  standalone: true,
  selector: 'app-results-page',
  imports: [
    CommonModule,
    FiltersPanelComponent,
    ArticleCardComponent,
    MatProgressBarModule,
    MatProgressSpinnerModule,
    MatDividerModule,
    TranslateModule,
    ReactiveFormsModule,
    MatFormFieldModule,
    MatInputModule,
    MatButtonModule,
    MatIconModule,
    MatCardModule,
    MatChipsModule,
    DatePipe
  ],
  templateUrl: './results.page.html',
  styleUrl: './results.page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush
})
export class ResultsPageComponent implements OnInit, OnDestroy {
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly store = inject(Store);
  private readonly fb = inject(FormBuilder);
  private readonly translate = inject(TranslateService);
  private readonly seo = inject(SeoService);
  private readonly destroy$ = new Subject<void>();
  private currentQuery = '';
  private currentFilters: SearchFilters = {
    dementiaTypes: [],
    documentTypes: [],
    languages: [],
    dateFrom: undefined,
    dateTo: undefined,
    minScore: undefined,
    sortBy: 'score'
  };
  private isInitialLoad = true;

  readonly results$ = this.store.select(selectResults);
  readonly filters$ = this.store.select(selectFilters);
  readonly query$ = this.store.select(selectQuery);
  readonly loading$ = this.store.select(selectLoading);
  readonly error$ = this.store.select(selectError);

  readonly dementiaOptions = [
    'tnm.alzheimer',
    'tnm.alzheimer.early',
    'tnm.alzheimer.late',
    'tnm.alzheimer.mixed',
    'tnm.vascular',
    'tnm.lewy',
    'tnm.frontotemporal',
    'tnm.traumatic',
    'tnm.substances',
    'tnm.prions',
    'tnm.parkinson',
    'tnm.huntington',
    'tnm.hiv',
    'tnm.sclerosis',
    'tnm.metabolic',
    'tnm.epilepsy',
    'tnm.hydrocephalus',
    'tnm.nutritional',
    'tnm.tumoral',
    'tnm.repetitive_trauma',
    'tnm.hepatic_renal',
    'tnm.mixed',
    'tnm.unspecified'
  ];
  readonly documentOptions = ['article', 'paper', 'clinical-report', 'news'];
  readonly languageOptions = ['es', 'en'];
  readonly searchForm = this.fb.group({
    query: ['']
  });

  ngOnInit(): void {
    // Suscribirse a los filtros primero para mantenerlos sincronizados
    this.filters$.pipe(takeUntil(this.destroy$)).subscribe((filters) => {
      this.currentFilters = filters;
    });

    this.query$.pipe(takeUntil(this.destroy$)).subscribe((query) => {
      this.currentQuery = query ?? '';
      this.searchForm.patchValue({ query }, { emitEvent: false });
      this.updateSeo(this.currentQuery);
    });

    // Manejar los queryParams de la URL
    // Solo ejecutar búsqueda en la carga inicial, después se maneja internamente
    this.route.queryParams.pipe(
      takeUntil(this.destroy$),
      distinctUntilChanged((prev, curr) => JSON.stringify(prev) === JSON.stringify(curr))
    ).subscribe((params) => {
      const query = params['q'];
      const tag = params['tag'];
      
      // Actualizar query si existe en la URL
      if (query !== undefined) {
        this.store.dispatch(SearchActions.setQuery({ query: query || '' }));
      }
      
      // Actualizar filtros si existe tag en la URL
      if (tag) {
        const updatedFilters: SearchFilters = {
          ...this.currentFilters,
          dementiaTypes: [tag]
        };
        this.store.dispatch(SearchActions.setFilters({ filters: updatedFilters }));
      }
      
      // Ejecutar búsqueda solo en la carga inicial
      // Las búsquedas subsecuentes se manejan en los métodos onSubmit, onFiltersChange, etc.
      if (this.isInitialLoad) {
        this.store.dispatch(SearchActions.executeSearch({}));
        this.isInitialLoad = false;
      }
    });

    this.translate.onLangChange.pipe(takeUntil(this.destroy$)).subscribe(() => this.updateSeo(this.currentQuery));
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  onFiltersChange(filters: SearchFilters): void {
    this.store.dispatch(SearchActions.setFilters({ filters }));
    this.store.dispatch(SearchActions.executeSearch({}));
  }

  onClear(): void {
    this.store.dispatch(SearchActions.reset());
    this.store.dispatch(SearchActions.executeSearch({}));
    // Limpiar la URL de todos los parámetros de búsqueda
    this.router.navigate([], {
      relativeTo: this.route,
      queryParams: {},
      replaceUrl: true
    });
  }

  onTag(tag: string): void {
    // Mantener los filtros actuales y solo agregar/modificar el tipo de demencia
    const updatedFilters: SearchFilters = {
      ...this.currentFilters,
      dementiaTypes: [tag]
    };
    this.store.dispatch(SearchActions.setFilters({ filters: updatedFilters }));
    this.store.dispatch(SearchActions.executeSearch({}));
  }

  onSubmit(): void {
    const query = this.searchForm.value.query?.trim() ?? '';
    this.store.dispatch(SearchActions.setQuery({ query }));
    this.store.dispatch(SearchActions.executeSearch({}));
    // Actualizar solo el parámetro 'q', eliminando 'tag' si existía
    this.router.navigate([], {
      relativeTo: this.route,
      queryParams: { q: query || null, tag: null },
      queryParamsHandling: 'merge',
      replaceUrl: true // Evita crear nueva entrada en historial y evita disparar subscribe de nuevo
    });
  }

  getTagLabel(tag: string): string {
    // Remover prefijo tnm. si existe
    return tag.replace(/^tnm\./, '').replace(/\./g, ' ').replace(/_/g, ' ');
  }

  getTypeLabel(type: string): string {
    const translationKey = `DOCUMENT_TYPES.${type}`;
    const translated = this.translate.instant(translationKey);
    return translated !== translationKey ? translated : type;
  }

  private updateSeo(query: string): void {
    const topic = query?.trim() ? query.trim() : this.translate.instant('SEO.SEARCH.DEFAULT_TOPIC');
    const title = this.translate.instant('SEO.SEARCH.TITLE', { topic });
    const description = this.translate.instant('SEO.SEARCH.DESCRIPTION', { topic });
    const locale = this.translate.currentLang === 'en' ? 'en_US' : 'es_ES';
    const imageAlt = this.translate.instant('SEO.SEARCH.IMAGE_ALT', { topic });

    this.seo.update({
      title,
      description,
      path: '/search',
      locale,
      imageAlt
    });
  }
}

