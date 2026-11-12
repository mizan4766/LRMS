import { HttpErrorResponse, HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { Router } from '@angular/router';
import { catchError, throwError } from 'rxjs';
import { Auth } from './auth.service';

export const authInterceptor: HttpInterceptorFn = (req, next) => {
  const auth = inject(Auth); const router = inject(Router);
  const t = auth.token();
  const r = req.clone({ setHeaders: { Accept: 'application/json', ...(t ? { Authorization: `Bearer ${t}` } : {}) } });
  return next(r).pipe(catchError((e: HttpErrorResponse) => {
    if (e.status === 401 && !req.url.includes('auth/login')) { auth.clear(); router.navigate(['/login']); }
    return throwError(() => e);
  }));
};
