import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { Api } from '../../core/api.service';
import { Auth } from '../../core/auth.service';
import { STATUS } from '../../shared/util';

@Component({
  selector: 'app-application-list', standalone: true, imports: [FormsModule, RouterLink],
  template: `
  <div class="bar"><h2>অধিগ্রহণ আবেদনসমূহ</h2><span class="sp"></span>
    @if (auth.is('rb')) { <a class="btn" routerLink="/applications/new">➕ নতুন আবেদন</a> }</div>
  <div class="row"><input [(ngModel)]="q" placeholder="ট্র্যাকিং নং / এল.এ কেস / প্রকল্পের নাম" (keyup.enter)="load(1)" />
    <select [(ngModel)]="status" (change)="load(1)"><option value="">সব অবস্থা</option>@for (s of statuses; track s[0]) { <option [value]="s[0]">{{ s[1] }}</option> }</select>
    <button (click)="load(1)">খুঁজুন</button></div>
  <table><tr><th>ট্র্যাকিং নং</th><th>এল.এ কেস</th><th>প্রকল্প</th><th>প্রত্যাশী সংস্থা</th><th>আয়তন (একর)</th><th>বর্তমান ধাপ</th><th>অবস্থা</th></tr>
    @for (a of rows(); track a.id) {
      <tr><td><a [routerLink]="['/applications', a.id]">{{ a.tracking_no || '(খসড়া #' + a.id + ')' }}</a></td><td>{{ a.la_case_no }}</td><td>{{ a.project_name_bn }}</td><td>{{ a.body?.name_bn }}</td>
        <td>{{ a.total_area_acre }}</td><td>{{ a.phase }}</td><td><span class="badge st-{{ a.status }}">{{ st(a.status) }}</span></td></tr>
    } @empty { <tr><td colspan="7" class="muted">কোনো আবেদন পাওয়া যায়নি</td></tr> }
  </table>
  <div class="bar"><button class="ghost" [disabled]="page() <= 1" (click)="load(page() - 1)">← আগের</button><span>পৃষ্ঠা {{ page() }} / {{ last() }}</span><button class="ghost" [disabled]="page() >= last()" (click)="load(page() + 1)">পরের →</button></div>`,
})
export class ApplicationListComponent {
  auth = inject(Auth); private api = inject(Api);
  rows = signal<any[]>([]); page = signal(1); last = signal(1); q = ''; status = ''; statuses = Object.entries(STATUS);
  constructor() { this.load(1); }
  st(s: string) { return STATUS[s] ?? s; }
  load(p: number) { this.api.get('applications', { page: p, q: this.q, status: this.status }).subscribe((r: any) => { this.rows.set(r.data); this.page.set(r.current_page); this.last.set(r.last_page); }); }
}
