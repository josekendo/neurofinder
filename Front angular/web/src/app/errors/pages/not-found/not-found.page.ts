import { ChangeDetectionStrategy, Component, OnDestroy, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { Router } from '@angular/router';
import { TranslateModule, TranslateService } from '@ngx-translate/core';
import { Subject, takeUntil } from 'rxjs';

import { SeoService } from '../../../core/services/seo.service';

@Component({
  standalone: true,
  selector: 'app-not-found-page',
  imports: [CommonModule, MatButtonModule, MatIconModule, TranslateModule],
  templateUrl: './not-found.page.html',
  styleUrl: './not-found.page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush
})
export class NotFoundPageComponent implements OnInit, OnDestroy {
  private readonly router = inject(Router);
  private readonly translate = inject(TranslateService);
  private readonly seo = inject(SeoService);
  private readonly destroy$ = new Subject<void>();

  ngOnInit(): void {
    this.updateSeo();
    this.translate.onLangChange.pipe(takeUntil(this.destroy$)).subscribe(() => this.updateSeo());
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  goHome(): void {
    this.router.navigateByUrl('/');
  }

  private updateSeo(): void {
    const title = this.translate.instant('ERROR404.SEO_TITLE');
    const description = this.translate.instant('ERROR404.SEO_DESCRIPTION');
    const locale = this.translate.currentLang === 'en' ? 'en_US' : 'es_ES';

    this.seo.update({
      title,
      description,
      path: '/404',
      locale,
      type: 'website'
    });
  }
}

