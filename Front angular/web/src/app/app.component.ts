import { Component, inject } from '@angular/core';
import { RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { CommonModule } from '@angular/common';
import { MatToolbarModule } from '@angular/material/toolbar';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatMenuModule } from '@angular/material/menu';
import { MatDividerModule } from '@angular/material/divider';
import { LangChangeEvent, TranslateModule, TranslateService } from '@ngx-translate/core';

@Component({
  selector: 'app-root',
  standalone: true,
  imports: [
    CommonModule,
    RouterOutlet,
    RouterLink,
    RouterLinkActive,
    MatToolbarModule,
    MatButtonModule,
    MatIconModule,
    MatMenuModule,
    MatDividerModule,
    TranslateModule
  ],
  templateUrl: './app.component.html',
  styleUrl: './app.component.scss'
})
export class AppComponent {
  private readonly translate = inject(TranslateService);

  readonly languages = [
    { code: 'es', label: 'Español', flag: '🇪🇸' },
    { code: 'en', label: 'English', flag: '🇬🇧' }
  ];
  currentLang = 'es';

  constructor() {
    const browserLang = this.translate.getBrowserLang();
    const fallback = this.languages.map((l) => l.code);
    this.translate.addLangs(fallback);
    this.translate.setDefaultLang('es');
    const initial = fallback.includes(browserLang ?? '') ? (browserLang as string) : 'es';
    this.translate.use(initial);
    this.currentLang = initial;

    this.translate.onLangChange.subscribe((event: LangChangeEvent) => {
      this.currentLang = event.lang;
    });
  }

  switchLanguage(lang: string): void {
    this.translate.use(lang);
  }

  get currentLanguageFlag(): string {
    return this.languages.find((l) => l.code === this.currentLang)?.flag ?? '🌐';
  }

  get currentLanguageLabel(): string {
    return this.languages.find((l) => l.code === this.currentLang)?.label ?? 'Global';
  }
}
