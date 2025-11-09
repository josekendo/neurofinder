import { ChangeDetectionStrategy, Component, Input } from '@angular/core';
import { CommonModule, DatePipe } from '@angular/common';
import { MatCardModule } from '@angular/material/card';
import { MatButtonModule } from '@angular/material/button';
import { MatChipsModule } from '@angular/material/chips';
import { TranslateModule } from '@ngx-translate/core';

import { NewsItem } from '../../../core/models/content.models';

@Component({
  selector: 'app-news-grid',
  standalone: true,
  imports: [CommonModule, DatePipe, MatCardModule, MatButtonModule, MatChipsModule, TranslateModule],
  templateUrl: './news-grid.component.html',
  styleUrl: './news-grid.component.scss',
  changeDetection: ChangeDetectionStrategy.OnPush
})
export class NewsGridComponent {
  @Input() items: NewsItem[] | null = [];
}

