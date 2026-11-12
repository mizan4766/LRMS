import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { Auth } from './auth.service';

export const authGuard: CanActivateFn = async () => {
  const a = inject(Auth); const r = inject(Router);
  if (!a.isLoggedIn()) return r.parseUrl('/login');
  if (a.user() && a.meta()) return true;
  return (await a.load()) ? true : r.parseUrl('/login');
};
