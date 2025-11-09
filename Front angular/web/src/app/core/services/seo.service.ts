import { DOCUMENT } from '@angular/common';
import { Inject, Injectable, inject } from '@angular/core';
import { Meta, MetaDefinition, Title } from '@angular/platform-browser';

export interface SeoConfig {
  title?: string;
  description?: string;
  path?: string;
  image?: string;
  type?: 'website' | 'article';
  locale?: string;
  extra?: MetaDefinition[];
}

@Injectable({ providedIn: 'root' })
export class SeoService {
  private readonly meta = inject(Meta);
  private readonly title = inject(Title);
  private readonly baseUrl = 'https://www.neurofinder.org';

  constructor(@Inject(DOCUMENT) private readonly document: Document) {}

  update(config: SeoConfig): void {
    const type = config.type ?? 'website';
    const title = config.title ?? 'NeuroFinder';
    const description =
      config.description ??
      'NeuroFinder centraliza evidencias clínicas sobre demencias para agilizar la búsqueda de información fiable.';
    const url = this.resolveUrl(config.path);

    this.title.setTitle(title);

    this.meta.updateTag({ name: 'description', content: description });
    this.meta.updateTag({ name: 'robots', content: 'index, follow' });

    this.meta.updateTag({ property: 'og:title', content: title });
    this.meta.updateTag({ property: 'og:description', content: description });
    this.meta.updateTag({ property: 'og:type', content: type });
    this.meta.updateTag({ property: 'og:url', content: url });
    this.meta.updateTag({ property: 'og:site_name', content: 'NeuroFinder' });

    this.meta.updateTag({ name: 'twitter:card', content: config.image ? 'summary_large_image' : 'summary' });
    this.meta.updateTag({ name: 'twitter:title', content: title });
    this.meta.updateTag({ name: 'twitter:description', content: description });

    if (config.image) {
      this.meta.updateTag({ property: 'og:image', content: config.image });
      this.meta.updateTag({ name: 'twitter:image', content: config.image });
    }

    if (config.locale) {
      this.meta.updateTag({ property: 'og:locale', content: config.locale });
    }

    config.extra?.forEach((definition) => this.meta.updateTag(definition));
  }

  private resolveUrl(path?: string): string {
    if (path?.startsWith('http')) {
      return path;
    }

    const normalizedPath = path ?? this.document?.location?.pathname ?? '/';
    const cleanPath = normalizedPath.startsWith('/') ? normalizedPath : `/${normalizedPath}`;
    return `${this.baseUrl}${cleanPath}`;
  }
}

