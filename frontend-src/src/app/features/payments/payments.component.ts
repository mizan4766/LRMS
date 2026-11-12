import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { Api, bnDate } from '../../core/api.service';
import { money, saveBlob } from '../../shared/util';

const STATUS: Record<string, string> = { received: 'গৃহীত (যাচাই বাকি)', verified: 'যাচাইকৃত', rejected: 'প্রত্যাখ্যাত', paid: 'পরিশোধিত' };
const KIND: Record<string, string> = { deposit: 'জমা (প্রত্যাশী সংস্থা)', distribution: 'বিতরণ (স্বার্থসংশ্লিষ্ট ব্যক্তি)' };

/** Payment register: deposits and distributions with totals and CSV export (manual mode; no bank/iBAS++ connection). */
@Component({
  selector: 'app-payments', standalone: true, imports: [FormsModule, RouterLink],
  template: `
  <div class="bar"><h2>ক্ষতিপূরণ জমা ও পরিশোধ রেজিস্টার</h2><span class="sp"></span><button class="ghost" (click)="csv()">⬇ CSV (Excel)</button></div>
  <div class="row">
    <select [(ngModel)]="kind" (change)="load()"><option value="">সব ধরন</option><option value="deposit">জমা</option><option value="distribution">বিতরণ</option></select>
    <select [(ngModel)]="status" (change)="load()"><option value="">সব অবস্থা</option>@for (s of statuses; track s[0]) { <option [value]="s[0]">{{ s[1] }}</option> }</select>
    <label>থেকে<input type="date" [(ngModel)]="from" (change)="load()" /></label><label>পর্যন্ত<input type="date" [(ngModel)]="to" (change)="load()" /></label>
  </div>
  @if (d(); as d) {
    <div class="cards"><div class="card"><b>{{ m(d.totals.deposit_received) }}</b><span>জমা গৃহীত (৳)</span></div><div class="card"><b>{{ m(d.totals.deposit_verified) }}</b><span>জমা যাচাইকৃত (৳)</span></div>
      <div class="card"><b>{{ m(d.totals.distributed) }}</b><span>বিতরণ সম্পন্ন (৳)</span></div></div>
    <p class="muted">পেমেন্ট মোড: <b>{{ d.gateway }}</b> (হাতে এন্ট্রি; ব্যাংক/iBAS++ এর সাথে সংযোগ নেই)</p>
    <table><tr><th>ট্র্যাকিং/এল.এ কেস</th><th>ধরন</th><th>প্রাপক/জমাদাতা</th><th>অঙ্ক (৳)</th><th>মাধ্যম</th><th>রেফারেন্স</th><th>অবস্থা</th><th>তারিখ</th></tr>
      @for (p of d.payments; track p.id) { <tr><td><a [routerLink]="['/applications', p.application_id]">{{ p.tracking_no }}</a><br><small>{{ p.la_case_no }}</small></td><td>{{ KIND[p.kind] || p.kind }}</td><td>{{ p.payee_name }}</td>
        <td>{{ m(p.amount) }}</td><td>{{ p.method }}</td><td>{{ p.reference }}</td><td><span class="badge {{ p.status === 'rejected' ? 'bad' : p.status === 'received' ? 'cond' : 'req' }}">{{ STATUS[p.status] || p.status }}</span></td><td>{{ bn(p.paid_at) }}</td></tr>
      } @empty { <tr><td colspan="8" class="muted">কোনো পেমেন্ট এন্ট্রি নেই</td></tr> }</table>
  }`,
})
export class PaymentsComponent {
  private api = inject(Api);
  d = signal<any>(null); kind = ''; status = ''; from = ''; to = ''; m = money; STATUS = STATUS; KIND = KIND; statuses = Object.entries(STATUS);
  constructor() { this.load(); }
  private params() { return { kind: this.kind, status: this.status, from: this.from, to: this.to }; }
  load() { this.api.get('payments', this.params()).subscribe((r: any) => this.d.set(r)); }
  bn(v: any) { return bnDate(v); }
  csv() { this.download(); }
  private download() {
    const qs = new URLSearchParams(Object.entries(this.params()).filter(([, v]) => v) as [string, string][]).toString();
    fetch(`${this.api.base}/payments/export${qs ? '?' + qs : ''}`, { headers: { Authorization: `Bearer ${localStorage.getItem('larms_token')}` } })
      .then(r => r.blob()).then(b => saveBlob(b, `larms-payments-${new Date().toISOString().slice(0, 10)}.csv`));
  }
}
