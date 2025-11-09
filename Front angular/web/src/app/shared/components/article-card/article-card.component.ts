import { ChangeDetectionStrategy, Component, EventEmitter, Input, Output } from '@angular/core';
import { CommonModule, DatePipe } from '@angular/common';
import { MatCardModule } from '@angular/material/card';
import { MatChipsModule } from '@angular/material/chips';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { RouterLink } from '@angular/router';

import { ArticleSummary } from '../../../core/models/content.models';

@Component({
  selector: 'app-article-card',
  standalone: true,
  imports: [CommonModule, DatePipe, MatCardModule, MatChipsModule, MatButtonModule, MatIconModule, RouterLink],
  templateUrl: './article-card.component.html',
  styleUrl: './article-card.component.scss',
  changeDetection: ChangeDetectionStrategy.OnPush
})
export class ArticleCardComponent {
  @Input({ required: true }) article!: ArticleSummary;
  @Input() showActions = true;
  @Output() tagSelected = new EventEmitter<string>();

  emitTag(tag: string): void {
    this.tagSelected.emit(tag);
  }
}

