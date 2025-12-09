import { ChangeDetectionStrategy, Component, OnDestroy, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { TranslateModule, TranslateService } from '@ngx-translate/core';
import { BehaviorSubject, Subject, takeUntil } from 'rxjs';

import { ApiService } from '../../../core/services/api.service';
import { NewsItem } from '../../../core/models/content.models';
import { NewsGridComponent } from '../../../shared/components/news-grid/news-grid.component';
import { SeoService } from '../../../core/services/seo.service';

@Component({
  standalone: true,
  selector: 'app-news-list-page',
  imports: [CommonModule, TranslateModule, NewsGridComponent],
  templateUrl: './news-list.page.html',
  styleUrl: './news-list.page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush
})
export class NewsListPageComponent implements OnInit, OnDestroy {
  private readonly api = inject(ApiService);
  private readonly translate = inject(TranslateService);
  private readonly seo = inject(SeoService);
  private readonly destroy$ = new Subject<void>();

  private readonly newsSubject = new BehaviorSubject<NewsItem[]>([]);
  readonly news$ = this.newsSubject.asObservable();

  ngOnInit(): void {
    this.updateSeo();
    this.loadNews();
    this.translate.onLangChange.pipe(takeUntil(this.destroy$)).subscribe(() => {
      this.updateSeo();
      this.loadNews();
    });
  }

  private loadNews(): void {
    const language = this.translate.currentLang || 'en';
    this.api.getNews(language, 20).subscribe(news => this.newsSubject.next(news));
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  private updateSeo(): void {
    const title = this.translate.instant('SEO.NEWS.TITLE');
    const description = this.translate.instant('SEO.NEWS.DESCRIPTION');
    const locale = this.translate.currentLang === 'en' ? 'en_US' : 'es_ES';
    const imageAlt = this.translate.instant('SEO.NEWS.IMAGE_ALT');

    this.seo.update({
      title,
      description,
      path: '/news',
      locale,
      imageAlt
    });
  }
}

