import { ChangeDetectionStrategy, Component, Input } from '@angular/core';
import { CommonModule, DatePipe } from '@angular/common';
import { MatIconModule } from '@angular/material/icon';
import { TranslateModule } from '@ngx-translate/core';

import { MetricsSnapshot } from '../../../core/models/content.models';

@Component({
  selector: 'app-metrics-banner',
  standalone: true,
  imports: [CommonModule, DatePipe, MatIconModule, TranslateModule],
  templateUrl: './metrics-banner.component.html',
  styleUrl: './metrics-banner.component.scss',
  changeDetection: ChangeDetectionStrategy.OnPush
})
export class MetricsBannerComponent {
  @Input() metrics: MetricsSnapshot | null = null;
}

