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
  private readonly articlesErrorSubject = new BehaviorSubject<boolean>(false);
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

  private readonly articlesSubject = new BehaviorSubject<ArticleSummary[]>([]);
  readonly articles$: Observable<ArticleSummary[]> = this.articlesSubject.asObservable();
  readonly articlesError$ = this.articlesErrorSubject.asObservable();

  private readonly newsSubject = new BehaviorSubject<NewsItem[]>([]);
  readonly news$: Observable<NewsItem[]> = this.newsSubject.asObservable();
  readonly newsError$ = this.newsErrorSubject.asObservable();

  ngOnInit(): void {
    this.updateSeo();
    this.loadLatestArticles();
    this.loadNews();
    this.translate.onLangChange.pipe(takeUntil(this.destroy$)).subscribe(() => {
      this.updateSeo();
      this.loadLatestArticles();
      this.loadNews();
    });
  }

  private loadLatestArticles(): void {
    const language = this.translate.currentLang || 'en';
    this.api.getLatestArticles(language, 4).pipe(
      catchError(() => {
        this.articlesErrorSubject.next(true);
        return of([]);
      })
    ).subscribe(articles => this.articlesSubject.next(articles));
  }

  private loadNews(): void {
    const language = this.translate.currentLang || 'en';
    this.api.getNews(language, 4).pipe(
      catchError(() => {
        this.newsErrorSubject.next(true);
        return of([]);
      })
    ).subscribe(news => this.newsSubject.next(news));
  }

  submit(): void {
    const query = this.searchForm.value.query?.trim() ?? '';
    this.router.navigate(['/search'], { queryParams: { q: query } });
  }

  handleTag(tag: string): void {
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
    const imageAlt = this.translate.instant('SEO.HOME.IMAGE_ALT');

    this.seo.update({
      title,
      description,
      path: '/',
      locale,
      imageAlt
    });
  }
}

