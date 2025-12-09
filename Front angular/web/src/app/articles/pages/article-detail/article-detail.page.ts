import { ChangeDetectionStrategy, Component, OnDestroy, inject } from '@angular/core';
import { CommonModule, DatePipe } from '@angular/common';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { MatChipsModule } from '@angular/material/chips';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatProgressBarModule } from '@angular/material/progress-bar';
import { MatDialog } from '@angular/material/dialog';
import { MatSnackBar } from '@angular/material/snack-bar';
import { TranslateModule, TranslateService } from '@ngx-translate/core';
import { BehaviorSubject, Observable, Subject, catchError, of, switchMap, takeUntil, tap } from 'rxjs';

import { ApiService } from '../../../core/services/api.service';
import { ArticleDetail } from '../../../core/models/content.models';
import { ArticleCardComponent } from '../../../shared/components/article-card/article-card.component';
import { SeoService } from '../../../core/services/seo.service';
import { ReportModalComponent } from '../../../shared/components/report-modal/report-modal.component';

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
  private readonly dialog = inject(MatDialog);
  private readonly snackBar = inject(MatSnackBar);
  private readonly destroy$ = new Subject<void>();
  private readonly errorSubject = new BehaviorSubject<boolean>(false);
  private currentArticle: ArticleDetail | null = null;

  readonly error$ = this.errorSubject.asObservable();

  readonly article$: Observable<ArticleDetail | null> = this.route.queryParams.pipe(
    switchMap((params) => {
      const url = params['url'];
      if (!url) {
        return of(null);
      }
      // Decodificar la URL que viene codificada
      const decodedUrl = decodeURIComponent(url);
      return this.api.getArticle(decodedUrl);
    }),
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
        path: `/articles?url=${encodeURIComponent(article.id)}`,
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

  getTagLabel(tag: string): string {
    const translationKey = `DEMENTIA_TYPES.${tag}`;
    const translated = this.translate.instant(translationKey);
    // Si la traducción es igual a la clave, significa que no existe, entonces devolver el tag original
    return translated !== translationKey ? translated : tag;
  }

  openReportModal(): void {
    if (!this.currentArticle) {
      return;
    }

    const dialogRef = this.dialog.open(ReportModalComponent, {
      width: '500px',
      data: { articleUrl: this.currentArticle.originalUrl }
    });

    dialogRef.componentInstance.reportSubmitted.pipe(
      takeUntil(this.destroy$)
    ).subscribe(({ email, description }) => {
      this.api.reportItem({
        itemUrl: this.currentArticle!.originalUrl,
        email,
        description
      }).pipe(
        takeUntil(this.destroy$)
      ).subscribe({
        next: () => {
          this.snackBar.open(
            this.translate.instant('REPORT.SUCCESS'),
            this.translate.instant('REPORT.CLOSE'),
            { duration: 3000 }
          );
          dialogRef.close();
        },
        error: () => {
          this.snackBar.open(
            this.translate.instant('REPORT.ERROR'),
            this.translate.instant('REPORT.CLOSE'),
            { duration: 5000 }
          );
        }
      });
    });
  }
}

