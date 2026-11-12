import { Injectable, computed, inject, signal } from '@angular/core';
import { Router } from '@angular/router';
import { tap } from 'rxjs';
import { Api } from './api.service';

@Injectable({ providedIn: 'root' })
export class Auth {
  private api = inject(Api);
  private router = inject(Router);
  token = signal<string | null>(localStorage.getItem('larms_token'));
  user = signal<any | null>(null);
  meta = signal<any | null>(null);
  isLoggedIn = computed(() => !!this.token());

  login(login: string, password: string) {
    return this.api.post('auth/login', { login, password }).pipe(tap((r: any) => {
      localStorage.setItem('larms_token', r.token); this.token.set(r.token); this.user.set(r.user);
    }));
  }
  /** Load the profile and the lookup data (called by the route guard). */
  load() {
    return new Promise<boolean>(resolve => {
      this.api.get('auth/me').subscribe({
        next: u => { this.user.set(u); this.api.get('lookups/bootstrap').subscribe({ next: m => { this.meta.set(m); resolve(true); }, error: () => resolve(false) }); },
        error: () => { this.clear(); resolve(false); },
      });
    });
  }
  clear() { localStorage.removeItem('larms_token'); this.token.set(null); this.user.set(null); this.meta.set(null); }
  logout() { const done = () => { this.clear(); this.router.navigate(['/login']); }; this.api.post('auth/logout').subscribe({ next: done, error: done }); }
  role = computed(() => this.user()?.role as string | undefined);
  is(...r: string[]) { const x = this.role(); return !!x && r.includes(x); }
  roleLabel(code: string) { return this.meta()?.roles?.[code] ?? code; }
  upazila(id: any) { return this.meta()?.upazilas?.find((u: any) => u.id === +id)?.name_bn ?? ''; }
  landClass(id: any) { return this.meta()?.land_classes?.find((c: any) => c.id === +id)?.name_bn ?? ''; }
}
