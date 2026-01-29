import { ChangeDetectionStrategy, Component, OnDestroy, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { TranslateModule, TranslateService } from '@ngx-translate/core';
import { MatTabsModule } from '@angular/material/tabs';
import { MatPaginatorModule, PageEvent } from '@angular/material/paginator';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { BehaviorSubject, Subject, takeUntil } from 'rxjs';

import { ApiService } from '../../../core/services/api.service';
import { ArticleSummary, NewsItem, PaginatedResponse, PaginationInfo } from '../../../core/models/content.models';
import { NewsGridComponent } from '../../../shared/components/news-grid/news-grid.component';
import { ArticleCardComponent } from '../../../shared/components/article-card/article-card.component';
import { SeoService } from '../../../core/services/seo.service';

type ContentType = 'news' | 'articles';

@Component({
  standalone: true,
  selector: 'app-archive-page',
  imports: [
    CommonModule,
    TranslateModule,
    MatTabsModule,
    MatPaginatorModule,
    MatProgressSpinnerModule,
    NewsGridComponent,
    ArticleCardComponent
  ],
  templateUrl: './archive.page.html',
  styleUrl: './archive.page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush
})
export class ArchivePageComponent implements OnInit, OnDestroy {
  private readonly api = inject(ApiService);
  private readonly translate = inject(TranslateService);
  private readonly seo = inject(SeoService);
  private readonly destroy$ = new Subject<void>();

  contentType: ContentType = 'news';
  currentPage = 1;
  pageSize = 20;
  language = 'en';

  private readonly newsSubject = new BehaviorSubject<NewsItem[]>([]);
  readonly news$ = this.newsSubject.asObservable();

  private readonly articlesSubject = new BehaviorSubject<ArticleSummary[]>([]);
  readonly articles$ = this.articlesSubject.asObservable();

  private readonly paginationSubject = new BehaviorSubject<PaginationInfo | null>(null);
  readonly pagination$ = this.paginationSubject.asObservable();

  private readonly loadingSubject = new BehaviorSubject<boolean>(false);
  readonly loading$ = this.loadingSubject.asObservable();

  ngOnInit(): void {
    this.updateSeo();
    this.loadContent();
    this.translate.onLangChange.pipe(takeUntil(this.destroy$)).subscribe(() => {
      this.language = this.translate.currentLang || 'en';
      this.updateSeo();
      this.loadContent();
    });
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  onTabChange(index: number): void {
    this.contentType = index === 0 ? 'news' : 'articles';
    this.currentPage = 1;
    this.loadContent();
  }

  onPageChange(event: PageEvent): void {
    this.currentPage = event.pageIndex + 1;
    this.pageSize = event.pageSize;
    this.loadContent();
  }

  private loadContent(): void {
    this.loadingSubject.next(true);
    this.language = this.translate.currentLang || 'en';

    if (this.contentType === 'news') {
      this.api.getNewsPaginated(this.language, this.currentPage, this.pageSize)
        .pipe(takeUntil(this.destroy$))
        .subscribe({
          next: (response: PaginatedResponse<NewsItem>) => {
            this.newsSubject.next(response.data);
            this.paginationSubject.next(response.pagination);
            this.loadingSubject.next(false);
          },
          error: () => {
            this.loadingSubject.next(false);
          }
        });
    } else {
      this.api.getArticlesPaginated(this.language, this.currentPage, this.pageSize)
        .pipe(takeUntil(this.destroy$))
        .subscribe({
          next: (response: PaginatedResponse<ArticleSummary>) => {
            this.articlesSubject.next(response.data);
            this.paginationSubject.next(response.pagination);
            this.loadingSubject.next(false);
          },
          error: () => {
            this.loadingSubject.next(false);
          }
        });
    }
  }

  private updateSeo(): void {
    const title = this.translate.instant('SEO.ARCHIVE.TITLE');
    const description = this.translate.instant('SEO.ARCHIVE.DESCRIPTION');
    const locale = this.translate.currentLang === 'en' ? 'en_US' : 'es_ES';
    const imageAlt = this.translate.instant('SEO.ARCHIVE.IMAGE_ALT');

    this.seo.update({
      title,
      description,
      path: '/archive',
      locale,
      imageAlt
    });
  }
}
