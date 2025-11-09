import { ChangeDetectionStrategy, Component, OnDestroy, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, ReactiveFormsModule } from '@angular/forms';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatProgressBarModule } from '@angular/material/progress-bar';
import { TranslateModule, TranslateService } from '@ngx-translate/core';
import { Router } from '@angular/router';
import { Store } from '@ngrx/store';
import { BehaviorSubject, Observable, Subject, catchError, of, takeUntil } from 'rxjs';

import { ApiService } from '../../../core/services/api.service';
import {
  ArticleSummary,
  MetricsSnapshot,
  NewsItem
} from '../../../core/models/content.models';
import { ArticleCardComponent } from '../../../shared/components/article-card/article-card.component';
import { NewsGridComponent } from '../../../shared/components/news-grid/news-grid.component';
import { MetricsBannerComponent } from '../../../shared/components/metrics-banner/metrics-banner.component';
import { SearchActions } from '../../state/search.actions';
import { selectError, selectLoading, selectResults } from '../../state/search.reducer';
import { SeoService } from '../../../core/services/seo.service';

@Component({
  standalone: true,
  selector: 'app-home-page',
  imports: [
    CommonModule,
    ReactiveFormsModule,
    MatFormFieldModule,
    MatInputModule,
    MatButtonModule,
    MatIconModule,
    MatProgressBarModule,
    TranslateModule,
    ArticleCardComponent,
    NewsGridComponent,
    MetricsBannerComponent
  ],
  templateUrl: './home.page.html',
  styleUrl: './home.page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush
})
export class HomePageComponent implements OnInit, OnDestroy {
  private readonly fb = inject(FormBuilder);
  private readonly api = inject(ApiService);
  private readonly store = inject(Store);
  private readonly router = inject(Router);
  private readonly translate = inject(TranslateService);
  private readonly seo = inject(SeoService);
  private readonly destroy$ = new Subject<void>();

  private readonly metricsErrorSubject = new BehaviorSubject<boolean>(false);
  private readonly newsErrorSubject = new BehaviorSubject<boolean>(false);

  readonly searchForm = this.fb.group({
    query: ['']
  });

  readonly metrics$: Observable<MetricsSnapshot | null> = this.api.getMetrics().pipe(
    catchError(() => {
      this.metricsErrorSubject.next(true);
      return of(null);
    })
  );
  readonly metricsError$ = this.metricsErrorSubject.asObservable();

  readonly news$: Observable<NewsItem[]> = this.api.getNews().pipe(
    catchError(() => {
      this.newsErrorSubject.next(true);
      return of([]);
    })
  );
  readonly newsError$ = this.newsErrorSubject.asObservable();
  readonly results$: Observable<ArticleSummary[]> = this.store.select(selectResults);
  readonly loading$: Observable<boolean> = this.store.select(selectLoading);
  readonly error$ = this.store.select(selectError);

  ngOnInit(): void {
    this.updateSeo();
    this.translate.onLangChange.pipe(takeUntil(this.destroy$)).subscribe(() => this.updateSeo());
    this.store.dispatch(SearchActions.executeSearch({}));
  }

  submit(): void {
    const query = this.searchForm.value.query?.trim() ?? '';
    this.store.dispatch(SearchActions.setQuery({ query }));
    this.store.dispatch(SearchActions.executeSearch({}));
    this.router.navigate(['/search'], { queryParams: { q: query } });
  }

  handleTag(tag: string): void {
    this.store.dispatch(SearchActions.setFilters({ filters: { dementiaTypes: [tag] } }));
    this.store.dispatch(SearchActions.executeSearch({}));
    this.router.navigate(['/search'], { queryParams: { tag } });
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  private updateSeo(): void {
    const title = this.translate.instant('SEO.HOME.TITLE');
    const description = this.translate.instant('SEO.HOME.DESCRIPTION');
    const locale = this.translate.currentLang === 'en' ? 'en_US' : 'es_ES';

    this.seo.update({
      title,
      description,
      path: '/',
      locale
    });
  }
}

