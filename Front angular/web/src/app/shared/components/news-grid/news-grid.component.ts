import { ChangeDetectionStrategy, Component, inject, Input, OnDestroy } from '@angular/core';
import { CommonModule, DatePipe } from '@angular/common';
import { MatCardModule } from '@angular/material/card';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatChipsModule } from '@angular/material/chips';
import { MatDialog } from '@angular/material/dialog';
import { MatSnackBar } from '@angular/material/snack-bar';
import { TranslateModule, TranslateService } from '@ngx-translate/core';
import { Subject, takeUntil } from 'rxjs';

import { NewsItem } from '../../../core/models/content.models';
import { ApiService } from '../../../core/services/api.service';
import { ReportModalComponent } from '../report-modal/report-modal.component';

@Component({
  selector: 'app-news-grid',
  standalone: true,
  imports: [CommonModule, DatePipe, MatCardModule, MatButtonModule, MatIconModule, MatChipsModule, TranslateModule],
  templateUrl: './news-grid.component.html',
  styleUrl: './news-grid.component.scss',
  changeDetection: ChangeDetectionStrategy.OnPush
})
export class NewsGridComponent implements OnDestroy {
  @Input() items: NewsItem[] | null = [];

  private readonly dialog = inject(MatDialog);
  private readonly snackBar = inject(MatSnackBar);
  private readonly api = inject(ApiService);
  private readonly translate = inject(TranslateService);
  private readonly destroy$ = new Subject<void>();

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  openReportModal(newsUrl: string): void {
    const dialogRef = this.dialog.open(ReportModalComponent, {
      width: '500px',
      data: { articleUrl: newsUrl }
    });

    dialogRef.componentInstance.reportSubmitted.pipe(
      takeUntil(this.destroy$)
    ).subscribe(({ email, description }) => {
      this.api.reportItem({
        itemUrl: newsUrl,
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

