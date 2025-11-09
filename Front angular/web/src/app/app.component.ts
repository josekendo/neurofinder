import { Component, HostListener, inject } from '@angular/core';
import { NavigationEnd, Router, RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { CommonModule } from '@angular/common';
import { MatToolbarModule } from '@angular/material/toolbar';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatMenuModule } from '@angular/material/menu';
import { MatDividerModule } from '@angular/material/divider';
import { LangChangeEvent, TranslateModule, TranslateService } from '@ngx-translate/core';
import { filter } from 'rxjs/operators';

interface SocialLink {
  id: 'telegram' | 'whatsapp' | 'facebook' | 'x' | 'instagram' | 'linkedin';
  labelKey: string;
  ariaKey: string;
  url: string;
  icon: string;
}

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
  private readonly shareBaseUrl = 'https://www.neurofinder.org';

  readonly languages = [
    { code: 'es', label: 'Español', flag: '🇪🇸' },
    { code: 'en', label: 'English', flag: '🇬🇧' }
  ];
  currentLang = 'es';
  showShareFab = false;
  shareUrl = this.shareBaseUrl;
  socialLinks: SocialLink[] = [];
  fabOffset = 24;

  private readonly fabDefaultOffset = 24;
  private readonly fabRaisedOffset = 96;
  private readonly fabBottomThreshold = 48;

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
      this.updateShareLinks();
    });

    this.router.events
      .pipe(filter((event: unknown): event is NavigationEnd => event instanceof NavigationEnd))
      .subscribe((event) => {
        this.updateShareState(event.urlAfterRedirects);
        this.updateFabOffset();
      });

    this.updateShareState(this.router.url);
    this.updateFabOffset();
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

  private updateShareState(route: string): void {
    const normalized = route.startsWith('/') ? route : `/${route}`;
    this.showShareFab =
      normalized.startsWith('/articles/') ||
      normalized.startsWith('/news/');

    this.shareUrl = `${this.shareBaseUrl}${normalized}`;
    if (this.showShareFab) {
      this.updateShareLinks();
    }
  }

  @HostListener('window:scroll')
  onWindowScroll(): void {
    this.updateFabOffset();
  }

  @HostListener('window:resize')
  onWindowResize(): void {
    this.updateFabOffset();
  }

  private updateShareLinks(): void {
    const encodedUrl = encodeURIComponent(this.shareUrl);
    const message = this.translate.instant('SOCIAL.SHARE_MESSAGE');
    const encodedMessage = encodeURIComponent(message);

    this.socialLinks = [
      {
        id: 'telegram',
        labelKey: 'SOCIAL.TELEGRAM',
        ariaKey: 'SOCIAL.SHARE_TELEGRAM',
        url: `https://t.me/share/url?url=${encodedUrl}&text=${encodedMessage}`,
        icon: 'send'
      },
      {
        id: 'whatsapp',
        labelKey: 'SOCIAL.WHATSAPP',
        ariaKey: 'SOCIAL.SHARE_WHATSAPP',
        url: `https://api.whatsapp.com/send?text=${encodedMessage}%20${encodedUrl}`,
        icon: 'sms'
      },
      {
        id: 'facebook',
        labelKey: 'SOCIAL.FACEBOOK',
        ariaKey: 'SOCIAL.SHARE_FACEBOOK',
        url: `https://www.facebook.com/sharer/sharer.php?u=${encodedUrl}`,
        icon: 'thumb_up'
      },
      {
        id: 'x',
        labelKey: 'SOCIAL.X',
        ariaKey: 'SOCIAL.SHARE_X',
        url: `https://twitter.com/intent/tweet?url=${encodedUrl}&text=${encodedMessage}`,
        icon: 'share'
      },
      {
        id: 'instagram',
        labelKey: 'SOCIAL.INSTAGRAM',
        ariaKey: 'SOCIAL.SHARE_INSTAGRAM',
        url: `https://www.instagram.com/?url=${encodedUrl}`,
        icon: 'camera_alt'
      },
      {
        id: 'linkedin',
        labelKey: 'SOCIAL.LINKEDIN',
        ariaKey: 'SOCIAL.SHARE_LINKEDIN',
        url: `https://www.linkedin.com/sharing/share-offsite/?url=${encodedUrl}`,
        icon: 'work'
      }
    ];
  }

  private updateFabOffset(): void {
    if (typeof window === 'undefined' || typeof document === 'undefined') {
      return;
    }
    const docHeight = document.documentElement.scrollHeight;
    const scrollPosition = window.scrollY + window.innerHeight;
    const distanceToBottom = Math.max(docHeight - scrollPosition, 0);
    this.fabOffset = distanceToBottom < this.fabBottomThreshold ? this.fabRaisedOffset : this.fabDefaultOffset;
  }
}
