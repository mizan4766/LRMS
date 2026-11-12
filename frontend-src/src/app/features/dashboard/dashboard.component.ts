import { DatePipe } from '@angular/common';
import { Component, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { Api } from '../../core/api.service';
import { Auth } from '../../core/auth.service';

const STATUS: Record<string, string> = { draft: 'খসড়া', submitted: 'দাখিলকৃত', processing: 'প্রক্রিয়াধীন', needs_refinement: 'সংশোধন প্রয়োজন', under_verification: 'পুনঃযাচাইয়ে', completed: 'সম্পন্ন', rejected: 'নামঞ্জুর' };

@Component({
  selector: 'app-dashboard', standalone: true, imports: [RouterLink, DatePipe],
  template: `
  <h2>ড্যাশবোর্ড</h2>
  @if (d(); as d) {
    <div class="cards">
      @if (!auth.is('citizen')) {
        <a class="card link" routerLink="/tasks"><b>{{ d.my_tasks }}</b><span>আমার অপেক্ষমাণ পদক্ষেপ</span></a>
        <div class="card" [class.bad]="d.overdue > 0"><b>{{ d.overdue }}</b><span>সময়সীমা অতিক্রান্ত</span></div>
        <a class="card link" routerLink="/applications"><b>{{ d.applications }}</b><span>দৃশ্যমান আবেদন</span></a>
      }
      @if (d.users !== null) { <div class="card"><b>{{ d.users }}</b><span>ব্যবহারকারী</span></div> }
    </div>
    @if (auth.is('rb')) { <p><a class="btn" routerLink="/applications/new">➕ নতুন অধিগ্রহণ আবেদন</a></p> }
    @if (auth.is('citizen')) { <p><a class="btn" routerLink="/citizen">নোটিশ দেখুন ও আবেদন করুন</a></p> }
    <h3>অবস্থা অনুযায়ী আবেদন</h3>
    <div class="chips">@for (s of statusList(d.by_status); track s.key) { <span class="chip">{{ s.label }}: {{ s.n }}</span> } @empty { <span class="muted">কোনো আবেদন নেই</span> }</div>
    @if (d.pending_by_step) { <h3>ধাপ অনুযায়ী অপেক্ষমাণ কাজ</h3><div class="chips">@for (s of stepList(d.pending_by_step); track s.key) { <span class="chip">{{ s.key }}: {{ s.n }}</span> }</div> }
    <h3>সাম্প্রতিক আবেদন</h3>
    <table><tr><th>ট্র্যাকিং নং</th><th>এল.এ কেস</th><th>প্রকল্প</th><th>ধাপ</th><th>অবস্থা</th></tr>
      @for (a of d.recent; track a.id) { <tr><td><a [routerLink]="['/applications', a.id]">{{ a.tracking_no || '(খসড়া #' + a.id + ')' }}</a></td><td>{{ a.la_case_no }}</td><td>{{ a.project_name_bn }}</td><td>{{ a.phase }}</td><td>{{ st(a.status) }}</td></tr> }
    </table>
  } @else { <p>লোড হচ্ছে…</p> }`,
})
export class DashboardComponent {
  auth = inject(Auth); d = signal<any>(null);
  constructor() { inject(Api).get('dashboard').subscribe(x => this.d.set(x)); }
  st(s: string) { return STATUS[s] ?? s; }
  statusList(o: any) { return Object.entries(o ?? {}).map(([key, n]) => ({ key, label: STATUS[key] ?? key, n })); }
  stepList(o: any) { return Object.entries(o ?? {}).map(([key, n]) => ({ key, n })); }
}
