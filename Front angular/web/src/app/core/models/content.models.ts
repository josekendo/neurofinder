export interface ArticleSummary {
  id: string;
  title: string;
  excerpt?: string;
  summary?: string;
  publishedAt: string;
  processedAt: string;
  score: number;
  source: string;
  language: string;
  tags: string[];
  type: 'article' | 'news' | 'paper' | 'clinical-report' | 'guideline' | 'dataset';
  url?: string; // Para noticias
}

export interface ArticleDetail extends ArticleSummary {
  summary: string;
  keyPoints: string[];
  related: ArticleSummary[];
  originalUrl: string;
}

export interface NewsItem {
  id: string;
  title: string;
  summary: string;
  publishedAt: string;
  url: string;
  imageUrl?: string;
  tags: string[];
  score: number;
}

export interface SearchFilters {
  dementiaTypes: string[];
  documentTypes: string[];
  languages: string[];
  dateFrom?: string;
  dateTo?: string;
  minScore?: number;
  sortBy: 'score' | 'date';
}

export interface SearchRequest {
  query: string;
  filters: SearchFilters;
}

export interface MetricsSnapshot {
  sources: number;
  articles: number;
  updatedAt: string;
}

export interface ReportRequest {
  itemUrl: string;
  email: string;
  description?: string;
}

export interface PaginationInfo {
  page: number;
  pageSize: number;
  total: number;
  totalPages: number;
  hasNext: boolean;
  hasPrev: boolean;
}

export interface PaginatedResponse<T> {
  data: T[];
  pagination: PaginationInfo;
}
