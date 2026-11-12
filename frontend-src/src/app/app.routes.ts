import { Routes } from '@angular/router';
import { authGuard } from './core/guards';

export const routes: Routes = [
  { path: 'login', loadComponent: () => import('./features/login/login.component').then(m => m.LoginComponent) },
  {
    path: '', canActivate: [authGuard], loadComponent: () => import('./layout/shell.component').then(m => m.ShellComponent),
    children: [
      { path: '', pathMatch: 'full', redirectTo: 'dashboard' },
      { path: 'dashboard', loadComponent: () => import('./features/dashboard/dashboard.component').then(m => m.DashboardComponent) },
      { path: 'tasks', loadComponent: () => import('./features/tasks/task-list.component').then(m => m.TaskListComponent) },
      { path: 'tasks/:id', loadComponent: () => import('./features/tasks/task-detail.component').then(m => m.TaskDetailComponent) },
      { path: 'applications', loadComponent: () => import('./features/applications/application-list.component').then(m => m.ApplicationListComponent) },
      { path: 'applications/new', loadComponent: () => import('./features/applications/application-wizard.component').then(m => m.ApplicationWizardComponent) },
      { path: 'applications/:id/edit', loadComponent: () => import('./features/applications/application-wizard.component').then(m => m.ApplicationWizardComponent) },
      { path: 'applications/:id', loadComponent: () => import('./features/applications/application-detail.component').then(m => m.ApplicationDetailComponent) },
      { path: 'payments', loadComponent: () => import('./features/payments/payments.component').then(m => m.PaymentsComponent) },
      { path: 'citizen', loadComponent: () => import('./features/citizen/citizen-portal.component').then(m => m.CitizenPortalComponent) },
      { path: 'admin/users', loadComponent: () => import('./features/admin/users.component').then(m => m.UsersComponent) },
    ],
  },
  { path: '**', redirectTo: '' },
];
