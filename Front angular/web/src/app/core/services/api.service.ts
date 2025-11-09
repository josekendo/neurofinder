import { inject, Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { ArticleDetail, ArticleSummary, MetricsSnapshot, NewsItem, SearchRequest } from '../models/content.models';
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

  getNews(): Observable<NewsItem[]> {
    return this.http.get<NewsItem[]>(`${this.baseUrl}/news/latest`);
  }

  getMetrics(): Observable<MetricsSnapshot> {
    return this.http.get<MetricsSnapshot>(`${this.baseUrl}/metrics`);
  }
}

