import { ChangeDetectionStrategy, Component, OnDestroy, inject } from '@angular/core';
import { CommonModule, DatePipe } from '@angular/common';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { MatChipsModule } from '@angular/material/chips';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatProgressBarModule } from '@angular/material/progress-bar';
import { TranslateModule, TranslateService } from '@ngx-translate/core';
import { BehaviorSubject, Observable, Subject, catchError, of, switchMap, takeUntil, tap } from 'rxjs';

import { ApiService } from '../../../core/services/api.service';
import { ArticleDetail } from '../../../core/models/content.models';
import { ArticleCardComponent } from '../../../shared/components/article-card/article-card.component';
import { SeoService } from '../../../core/services/seo.service';

@Component({
  standalone: true,
  selector: 'app-article-detail-page',
  imports: [
    CommonModule,
    DatePipe,
    RouterLink,
    MatChipsModule,
    MatButtonModule,
    MatIconModule,
    MatProgressBarModule,
    TranslateModule,
    ArticleCardComponent
  ],
  templateUrl: './article-detail.page.html',
  styleUrl: './article-detail.page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush
})
export class ArticleDetailPageComponent implements OnDestroy {
  private readonly route = inject(ActivatedRoute);
  private readonly api = inject(ApiService);
  private readonly translate = inject(TranslateService);
  private readonly seo = inject(SeoService);
  private readonly destroy$ = new Subject<void>();
  private readonly errorSubject = new BehaviorSubject<boolean>(false);
  private currentArticle: ArticleDetail | null = null;

  readonly error$ = this.errorSubject.asObservable();

  readonly article$: Observable<ArticleDetail | null> = this.route.paramMap.pipe(
    switchMap((params) => this.api.getArticle(params.get('id') ?? '')),
    tap((article) => {
      this.currentArticle = article;
      this.updateSeo(article);
    }),
    catchError(() => {
      this.errorSubject.next(true);
      this.currentArticle = null;
      this.updateSeo(null);
      return of(null);
    })
  );

  constructor() {
    this.translate.onLangChange.pipe(takeUntil(this.destroy$)).subscribe(() => this.updateSeo(this.currentArticle));
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  private updateSeo(article: ArticleDetail | null): void {
    const locale = this.translate.currentLang === 'en' ? 'en_US' : 'es_ES';
    if (article) {
      const rawSummary = article.summary || article.excerpt || '';
      const summary = rawSummary.length > 220 ? `${rawSummary.slice(0, 217)}...` : rawSummary;
      const title = this.translate.instant('SEO.ARTICLE.TITLE', { title: article.title });
      const description = this.translate.instant('SEO.ARTICLE.DESCRIPTION', { summary });
      const imageAlt = this.translate.instant('SEO.ARTICLE.IMAGE_ALT', { title: article.title });

      this.seo.update({
        title,
        description,
        path: `/articles/${article.id}`,
        type: 'article',
        locale,
        imageAlt
      });
    } else {
      const title = this.translate.instant('SEO.ARTICLE.FALLBACK_TITLE');
      const description = this.translate.instant('SEO.ARTICLE.FALLBACK_DESCRIPTION');
      const imageAlt = this.translate.instant('SEO.ARTICLE.FALLBACK_IMAGE_ALT');
      this.seo.update({
        title,
        description,
        path: '/articles',
        type: 'article',
        locale,
        imageAlt
      });
    }
  }
}

