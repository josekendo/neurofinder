import { Routes } from '@angular/router';

export const routes: Routes = [
  {
    path: '',
    loadComponent: () =>
      import('./search/pages/home/home.page').then((m) => m.HomePageComponent)
  },
  {
    path: 'search',
    loadComponent: () =>
      import('./search/pages/results/results.page').then((m) => m.ResultsPageComponent)
  },
  {
    path: 'articles/:id',
    loadComponent: () =>
      import('./articles/pages/article-detail/article-detail.page').then(
        (m) => m.ArticleDetailPageComponent
      )
  },
  {
    path: 'news',
    loadComponent: () =>
      import('./news/pages/news-list/news-list.page').then((m) => m.NewsListPageComponent)
  },
  {
    path: 'quienes-somos',
    loadComponent: () =>
      import('./about/pages/about/about.page').then((m) => m.AboutPageComponent)
  },
  {
    path: '404',
    loadComponent: () =>
      import('./errors/pages/not-found/not-found.page').then((m) => m.NotFoundPageComponent)
  },
  {
    path: '**',
    redirectTo: '404'
  }
];
