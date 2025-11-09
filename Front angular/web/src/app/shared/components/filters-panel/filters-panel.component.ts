import {
  ChangeDetectionStrategy,
  Component,
  EventEmitter,
  Input,
  OnChanges,
  Output,
  SimpleChanges
} from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule } from '@angular/forms';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatSelectModule } from '@angular/material/select';
import { MatInputModule } from '@angular/material/input';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatButtonToggleModule } from '@angular/material/button-toggle';
import { TranslateModule } from '@ngx-translate/core';

import { SearchFilters } from '../../../core/models/content.models';

@Component({
  selector: 'app-filters-panel',
  standalone: true,
  imports: [
    CommonModule,
    ReactiveFormsModule,
    MatFormFieldModule,
    MatSelectModule,
    MatInputModule,
    MatButtonModule,
    MatIconModule,
    MatButtonToggleModule,
    TranslateModule
  ],
  templateUrl: './filters-panel.component.html',
  styleUrl: './filters-panel.component.scss',
  changeDetection: ChangeDetectionStrategy.OnPush
})
export class FiltersPanelComponent implements OnChanges {
  @Input({ required: true }) filters!: SearchFilters;
  @Input() dementiaOptions: string[] = [];
  @Input() documentOptions: string[] = [];
  @Input() languageOptions: string[] = [];

  @Output() filtersChange = new EventEmitter<SearchFilters>();
  @Output() clear = new EventEmitter<void>();

  readonly form: FormGroup;

  constructor(private readonly fb: FormBuilder) {
    this.form = this.fb.group({
      dementiaTypes: [[]],
      documentTypes: [[]],
      languages: [[]],
      minScore: [null],
      sortBy: ['score']
    });
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['filters']?.currentValue) {
      this.form.patchValue(this.filters, { emitEvent: false });
    }
  }

  apply(): void {
    this.filtersChange.emit({
      ...this.filters,
      ...this.form.value
    } as SearchFilters);
  }

  clearFilters(): void {
    this.form.reset({
      dementiaTypes: [],
      documentTypes: [],
      languages: [],
      minScore: null,
      sortBy: 'score'
    });
    this.clear.emit();
  }
}

