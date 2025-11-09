import { ChangeDetectionStrategy, Component, OnDestroy, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute, Router } from '@angular/router';
import { Store } from '@ngrx/store';
import { Subject, takeUntil } from 'rxjs';
import { MatProgressBarModule } from '@angular/material/progress-bar';
import { MatDividerModule } from '@angular/material/divider';
import { TranslateModule, TranslateService } from '@ngx-translate/core';
import { ReactiveFormsModule, FormBuilder } from '@angular/forms';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';

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
    MatDividerModule,
    TranslateModule,
    ReactiveFormsModule,
    MatFormFieldModule,
    MatInputModule,
    MatButtonModule,
    MatIconModule
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
  readonly languageOptions = ['es', 'en', 'fr', 'de'];
  readonly searchForm = this.fb.group({
    query: ['']
  });

  ngOnInit(): void {
    this.route.queryParams.pipe(takeUntil(this.destroy$)).subscribe((params) => {
      const query = params['q'];
      const tag = params['tag'];
      if (query) {
        this.store.dispatch(SearchActions.setQuery({ query }));
      }
      if (tag) {
        this.store.dispatch(SearchActions.setFilters({ filters: { dementiaTypes: [tag] } }));
      }
      this.store.dispatch(SearchActions.executeSearch({}));
    });

    this.query$.pipe(takeUntil(this.destroy$)).subscribe((query) => {
      this.currentQuery = query ?? '';
      this.searchForm.patchValue({ query }, { emitEvent: false });
      this.updateSeo(this.currentQuery);
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
  }

  onTag(tag: string): void {
    this.store.dispatch(SearchActions.setFilters({ filters: { dementiaTypes: [tag] } }));
    this.store.dispatch(SearchActions.executeSearch({}));
  }

  onSubmit(): void {
    const query = this.searchForm.value.query?.trim() ?? '';
    this.store.dispatch(SearchActions.setQuery({ query }));
    this.store.dispatch(SearchActions.executeSearch({}));
    this.router.navigate([], {
      relativeTo: this.route,
      queryParams: { q: query || null },
      queryParamsHandling: 'merge'
    });
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

