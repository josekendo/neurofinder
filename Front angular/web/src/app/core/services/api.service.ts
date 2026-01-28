import { inject, Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, map } from 'rxjs';
import { ArticleDetail, ArticleSummary, MetricsSnapshot, NewsItem, ReportRequest, SearchRequest } from '../models/content.models';
import { environment } from '../../../environments/environment';

@Injectable({
  providedIn: 'root'
})
export class ApiService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = environment.apiUrl;

  search(request: SearchRequest): Observable<ArticleSummary[]> {
    return this.http.post<ArticleSummary[]>(`${this.baseUrl}/search`, request);
  }

  getArticle(url: string): Observable<ArticleDetail> {
    return this.http.post<ArticleDetail>(`${this.baseUrl}/articles`, { url });
  }

  getNews(language?: string, limit?: number): Observable<NewsItem[]> {
    const params = new URLSearchParams();
    if (language) {
      params.append('language', language);
    }
    if (limit !== undefined && limit > 0) {
      params.append('limit', limit.toString());
    }
    const queryString = params.toString();
    const url = `${this.baseUrl}/news/latest${queryString ? '?' + queryString : ''}`;
    return this.http.get<NewsItem[]>(url).pipe(
      map((news) =>
        (news ?? []).map((item) => ({
          ...item,
          // Si el backend no envía score, usar 0.1 por defecto
          score: item?.score ?? 0.1
        }))
      )
    );
  }

  getMetrics(): Observable<MetricsSnapshot> {
    return this.http.get<MetricsSnapshot>(`${this.baseUrl}/metrics`);
  }

  getLatestArticles(language?: string, limit?: number): Observable<ArticleSummary[]> {
    const params = new URLSearchParams();
    if (language) {
      params.append('language', language);
    }
    if (limit !== undefined && limit > 0) {
      params.append('limit', limit.toString());
    }
    const queryString = params.toString();
    const url = `${this.baseUrl}/articles/latest${queryString ? '?' + queryString : ''}`;
    return this.http.get<ArticleSummary[]>(url);
  }

  reportItem(request: ReportRequest): Observable<{ success: boolean; message?: string }> {
    return this.http.post<{ success: boolean; message?: string }>(`${this.baseUrl}/report`, request);
  }
}

