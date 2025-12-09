import { inject, Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
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

  getArticle(id: string): Observable<ArticleDetail> {
    return this.http.get<ArticleDetail>(`${this.baseUrl}/articles/${id}`);
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
    return this.http.get<NewsItem[]>(url);
  }

  getMetrics(): Observable<MetricsSnapshot> {
    return this.http.get<MetricsSnapshot>(`${this.baseUrl}/metrics`);
  }

  reportItem(request: ReportRequest): Observable<{ success: boolean; message?: string }> {
    return this.http.post<{ success: boolean; message?: string }>(`${this.baseUrl}/report`, request);
  }
}

