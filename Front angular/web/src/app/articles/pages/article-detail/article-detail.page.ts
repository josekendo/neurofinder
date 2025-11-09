import { ChangeDetectionStrategy, Component, inject } from '@angular/core';
import { CommonModule, DatePipe } from '@angular/common';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { MatChipsModule } from '@angular/material/chips';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatProgressBarModule } from '@angular/material/progress-bar';
import { TranslateModule } from '@ngx-translate/core';
import { BehaviorSubject, Observable, catchError, of, switchMap } from 'rxjs';

import { ApiService } from '../../../core/services/api.service';
import { ArticleDetail } from '../../../core/models/content.models';
import { ArticleCardComponent } from '../../../shared/components/article-card/article-card.component';

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
export class ArticleDetailPageComponent {
  private readonly route = inject(ActivatedRoute);
  private readonly api = inject(ApiService);
  private readonly errorSubject = new BehaviorSubject<boolean>(false);

  readonly error$ = this.errorSubject.asObservable();

  readonly article$: Observable<ArticleDetail | null> = this.route.paramMap.pipe(
    switchMap((params) => this.api.getArticle(params.get('id') ?? '')),
    catchError(() => {
      this.errorSubject.next(true);
      return of(null);
    })
  );
}

