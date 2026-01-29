import { inject, Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable, map } from 'rxjs';
import { ArticleDetail, ArticleSummary, MetricsSnapshot, NewsItem, ReportRequest, SearchRequest, PaginatedResponse } from '../models/content.models';
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

  getNewsPaginated(language?: string, page: number = 1, pageSize: number = 20): Observable<PaginatedResponse<NewsItem>> {
    let params = new HttpParams();
    if (language) {
      params = params.set('language', language);
    }
    params = params.set('page', page.toString());
    params = params.set('pageSize', pageSize.toString());
    
    return this.http.get<PaginatedResponse<NewsItem>>(`${this.baseUrl}/news/paginated`, { params }).pipe(
      map((response) => ({
        ...response,
        data: response.data.map((item: NewsItem) => ({
          ...item,
          score: item?.score ?? 0.1
        }))
      }))
    );
  }

  getArticlesPaginated(language?: string, page: number = 1, pageSize: number = 20): Observable<PaginatedResponse<ArticleSummary>> {
    let params = new HttpParams();
    if (language) {
      params = params.set('language', language);
    }
    params = params.set('page', page.toString());
    params = params.set('pageSize', pageSize.toString());
    
    return this.http.get<PaginatedResponse<ArticleSummary>>(`${this.baseUrl}/articles/paginated`, { params });
  }
}

