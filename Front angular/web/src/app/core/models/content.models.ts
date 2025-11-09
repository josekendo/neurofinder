export interface ArticleSummary {
  id: string;
  title: string;
  excerpt: string;
  publishedAt: string;
  processedAt: string;
  score: number;
  source: string;
  language: string;
  tags: string[];
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

