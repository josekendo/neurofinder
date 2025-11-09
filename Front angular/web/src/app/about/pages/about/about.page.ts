import { ChangeDetectionStrategy, Component, OnDestroy, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { TranslateModule, TranslateService } from '@ngx-translate/core';
import { Subject, takeUntil } from 'rxjs';

import { SeoService } from '../../../core/services/seo.service';

@Component({
  standalone: true,
  selector: 'app-about-page',
  imports: [CommonModule, MatButtonModule, MatIconModule, TranslateModule],
  templateUrl: './about.page.html',
  styleUrl: './about.page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush
})
export class AboutPageComponent implements OnInit, OnDestroy {
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

  private updateSeo(): void {
    const title = this.translate.instant('SEO.ABOUT.TITLE');
    const description = this.translate.instant('SEO.ABOUT.DESCRIPTION');
    const locale = this.translate.currentLang === 'en' ? 'en_US' : 'es_ES';

    this.seo.update({
      title,
      description,
      path: '/quienes-somos',
      locale
    });
  }
}

