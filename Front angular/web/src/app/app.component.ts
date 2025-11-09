import { Component, inject } from '@angular/core';
import { NavigationEnd, Router, RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { CommonModule } from '@angular/common';
import { MatToolbarModule } from '@angular/material/toolbar';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatMenuModule } from '@angular/material/menu';
import { MatDividerModule } from '@angular/material/divider';
import { LangChangeEvent, TranslateModule, TranslateService } from '@ngx-translate/core';
import { Title } from '@angular/platform-browser';
import { filter } from 'rxjs';

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
  private readonly router = inject(Router);
  private readonly title = inject(Title);

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
      this.updateTitle(this.router.url);
    });

    this.listenToRouteChanges();
    this.updateTitle(this.router.url);
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

  private updateTitle(route: string): void {
    const base = 'NeuroFinder';
    let suffix: string | null = null;

    if (route.startsWith('/search')) {
      suffix = this.translate.instant('TITLE.SEARCH');
    } else if (route.startsWith('/articles')) {
      suffix = this.translate.instant('TITLE.ARTICLE');
    } else if (route.startsWith('/news')) {
      suffix = this.translate.instant('TITLE.NEWS');
    }

    this.title.setTitle(suffix ? `${base} | ${suffix}` : base);
  }

  private listenToRouteChanges(): void {
    this.router.events.pipe(filter((event): event is NavigationEnd => event instanceof NavigationEnd)).subscribe((event) =>
      this.updateTitle(event.urlAfterRedirects)
    );
  }
}
