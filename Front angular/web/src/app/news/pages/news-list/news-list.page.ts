import { ChangeDetectionStrategy, Component, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { TranslateModule } from '@ngx-translate/core';

import { ApiService } from '../../../core/services/api.service';
import { NewsGridComponent } from '../../../shared/components/news-grid/news-grid.component';

@Component({
  standalone: true,
  selector: 'app-news-list-page',
  imports: [CommonModule, TranslateModule, NewsGridComponent],
  templateUrl: './news-list.page.html',
  styleUrl: './news-list.page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush
})
export class NewsListPageComponent {
  private readonly api = inject(ApiService);

  readonly news$ = this.api.getNews();
}

