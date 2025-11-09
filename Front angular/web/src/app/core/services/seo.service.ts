import { DOCUMENT } from '@angular/common';
import { Inject, Injectable, inject } from '@angular/core';
import { Meta, MetaDefinition, Title } from '@angular/platform-browser';

export interface SeoConfig {
  title?: string;
  description?: string;
  path?: string;
  image?: string;
  imageAlt?: string;
  type?: 'website' | 'article';
  locale?: string;
  extra?: MetaDefinition[];
}

@Injectable({ providedIn: 'root' })
export class SeoService {
  private readonly meta = inject(Meta);
  private readonly title = inject(Title);
  private readonly baseUrl = 'https://www.neurofinder.org';
  private readonly defaultImagePath = '/assets/meta/og-default.png';
  private readonly defaultImageAlt =
    'NeuroFinder, buscador avanzado sobre demencias que centraliza evidencia clínica';

  constructor(@Inject(DOCUMENT) private readonly document: Document) {}

  update(config: SeoConfig): void {
    const type = config.type ?? 'website';
    const title = config.title ?? 'NeuroFinder';
    const description =
      config.description ??
      'NeuroFinder es un buscador avanzado especializado en demencias que centraliza fuentes clínicas, científicas y divulgativas para ofrecer información rigurosa y accesible.';
    const url = this.resolveUrl(config.path);
    const image = this.resolveUrl(config.image ?? this.defaultImagePath);
    const imageAlt = config.imageAlt ?? this.defaultImageAlt;

    this.title.setTitle(title);

    this.meta.updateTag({ name: 'description', content: description });
    this.meta.updateTag({ name: 'robots', content: 'index, follow' });

    this.meta.updateTag({ property: 'og:title', content: title });
    this.meta.updateTag({ property: 'og:description', content: description });
    this.meta.updateTag({ property: 'og:type', content: type });
    this.meta.updateTag({ property: 'og:url', content: url });
    this.meta.updateTag({ property: 'og:site_name', content: 'NeuroFinder' });

    this.meta.updateTag({ name: 'twitter:title', content: title });
    this.meta.updateTag({ name: 'twitter:description', content: description });

    this.meta.updateTag({ property: 'og:image', content: image });
    this.meta.updateTag({ property: 'og:image:secure_url', content: image });
    this.meta.updateTag({ property: 'og:image:width', content: '1200' });
    this.meta.updateTag({ property: 'og:image:height', content: '630' });
    this.meta.updateTag({ property: 'og:image:alt', content: imageAlt });
    this.meta.updateTag({ name: 'twitter:image', content: image });
    this.meta.updateTag({ name: 'twitter:image:alt', content: imageAlt });
    this.meta.updateTag({ name: 'twitter:card', content: 'summary_large_image' });

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

