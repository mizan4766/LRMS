import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';

@Injectable({ providedIn: 'root' })
export class Api {
  private http = inject(HttpClient);
  base = environment.apiUrl;
  private params(p?: Record<string, any>) {
    let hp = new HttpParams();
    Object.entries(p ?? {}).forEach(([k, v]) => { if (v !== null && v !== undefined && v !== '') hp = hp.set(k, String(v)); });
    return hp;
  }
  get<T = any>(path: string, p?: Record<string, any>): Observable<T> { return this.http.get<T>(`${this.base}/${path}`, { params: this.params(p) }); }
  post<T = any>(path: string, body: any = {}): Observable<T> { return this.http.post<T>(`${this.base}/${path}`, body); }
  put<T = any>(path: string, body: any = {}): Observable<T> { return this.http.put<T>(`${this.base}/${path}`, body); }
  del(path: string) { return this.http.delete(`${this.base}/${path}`); }
  blob(path: string) { return this.http.get(`${this.base}/${path}`, { responseType: 'blob' }); }
}

/** Readable text from the API error envelope {error:{message,details}} */
export function errText(e: any): string {
  const er = e?.error?.error;
  if (!er) return e?.status === 0 ? 'সার্ভারের সাথে যোগাযোগ করা যাচ্ছে না' : (e?.message ?? 'ত্রুটি');
  const d = er.details;
  const det = Array.isArray(d) ? d.join('; ') : d && typeof d === 'object' ? Object.values(d).flat().join('; ') : '';
  return det ? `${er.message}: ${det}` : er.message;
}

export function bnDate(v: any): string {
  if (!v) return '';
  const d = new Date(String(v).replace(' ', 'T'));
  return isNaN(d.getTime()) ? String(v) : d.toLocaleDateString('bn-BD', { year: 'numeric', month: 'long', day: 'numeric' });
}
