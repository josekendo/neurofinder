import { ChangeDetectionStrategy, Component, EventEmitter, inject, OnInit, Output } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { MAT_DIALOG_DATA, MatDialogModule, MatDialogRef } from '@angular/material/dialog';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatButtonModule } from '@angular/material/button';
import { TranslateModule } from '@ngx-translate/core';

@Component({
  selector: 'app-report-modal',
  standalone: true,
  imports: [
    CommonModule,
    ReactiveFormsModule,
    MatDialogModule,
    MatFormFieldModule,
    MatInputModule,
    MatButtonModule,
    TranslateModule
  ],
  templateUrl: './report-modal.component.html',
  styleUrl: './report-modal.component.scss',
  changeDetection: ChangeDetectionStrategy.OnPush
})
export class ReportModalComponent implements OnInit {
  @Output() reportSubmitted = new EventEmitter<{ email: string; description?: string }>();

  private readonly fb = inject(FormBuilder);
  private readonly dialogRef = inject(MatDialogRef<ReportModalComponent>);
  private readonly data = inject<{ articleUrl: string }>(MAT_DIALOG_DATA);

  readonly reportForm: FormGroup = this.fb.group({
    articleUrl: [{ value: '', disabled: true }, Validators.required],
    email: ['', [Validators.required, Validators.email]],
    description: ['']
  });

  ngOnInit(): void {
    this.reportForm.patchValue({
      articleUrl: this.data.articleUrl
    });
  }

  onSubmit(): void {
    if (this.reportForm.valid) {
      const formValue = this.reportForm.getRawValue();
      const description = formValue.description?.trim();
      
      this.reportSubmitted.emit({
        email: formValue.email,
        description: description && description.length > 0 ? description : undefined
      });
    }
  }

  onCancel(): void {
    this.dialogRef.close();
  }

  get emailControl() {
    return this.reportForm.get('email');
  }

  get descriptionControl() {
    return this.reportForm.get('description');
  }
}

