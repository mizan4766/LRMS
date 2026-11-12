import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Api, bnDate, errText } from '../../core/api.service';
import { KIND, printHtml } from '../../shared/util';

/** Interested persons: published notices, ownership claim (Step 17), compensation application (Step 25), objection. */
@Component({
  selector: 'app-citizen-portal', standalone: true, imports: [FormsModule],
  template: `
  <h2>নোটিশ ও আবেদন</h2>
  <p class="muted">আপনার জমি সংশ্লিষ্ট প্রকাশিত নোটিশ দেখুন এবং প্রয়োজনে মালিকানার দাবি, আপত্তি বা ক্ষতিপূরণের আবেদন করুন।</p>
  <div class="cards">
    @for (n of notices(); track n.id) {
      <div class="card nt"><b>{{ kind(n.kind) }}</b><span>{{ n.ref_no }}</span><span>{{ n.application.project_name_bn }}</span><small>এল.এ কেস {{ n.application.la_case_no }} · {{ n.application.district }} · প্রকাশ: {{ bn(n.published_at) }}</small>
        <div class="bar"><button class="ghost sm" (click)="view(n)">নোটিশ দেখুন</button>
          @if (n.kind === 'notice_7_1' && n.application.claims_open) { <button class="sm" (click)="open('claim', n)">মালিকানা দাবি</button> }
          @if (n.kind !== 'notice_8_3') { <button class="ghost sm" (click)="open('objection', n)">আপত্তি দাখিল</button> }
          @if (n.kind === 'notice_8_3' && n.application.compensation_open) { <button class="sm" (click)="open('comp', n)">ক্ষতিপূরণের আবেদন</button> }</div></div>
    } @empty { <p class="muted">এখনো কোনো নোটিশ প্রকাশিত হয়নি</p> }
  </div>
  <h3>আমার আবেদনসমূহ</h3>
  <table><tr><th>ধরন</th><th>বিষয়/বিবরণ</th><th>অবস্থা</th><th>তারিখ</th></tr>
    @for (r of mine(); track r.id) { <tr><td>{{ kind(r.kind) }}</td><td>{{ r.data?.subject || r.data?.description }}</td><td>{{ r.status }}</td><td>{{ bn(r.created_at) }}</td></tr> } @empty { <tr><td colspan="4" class="muted">কোনো আবেদন নেই</td></tr> }</table>

  @if (view$()) { <div class="modal"><div class="dlg"><div class="bar"><h3>{{ kind(view$().record.kind) }}</h3><span class="sp"></span><button class="ghost" (click)="printIt()">প্রিন্ট</button><button class="ghost" (click)="view$.set(null)">বন্ধ</button></div>
    <div [innerHTML]="view$().record.data.html"></div></div></div> }

  @if (form(); as m) {
    <div class="modal"><div class="dlg">
      <h3>{{ m.type === 'claim' ? 'স্বার্থের প্রকৃতির তালিকা (মালিকানা দাবি)' : m.type === 'comp' ? 'ক্ষতিপূরণের আবেদন' : 'আপত্তি দাখিল' }} — {{ m.notice.application.la_case_no }}</h3>
      @if (m.type === 'claim') {
        <div class="fld"><label>সংশ্লিষ্ট দাগ *</label>@for (l of m.notice.application.lands; track l.id) { <label class="inl"><input type="checkbox" [checked]="f.land_ids.includes(l.id)" (change)="tg(l.id)" /> {{ l.label }}</label> }</div>
        <div class="fld"><label>স্বার্থের ধরন *</label><select [(ngModel)]="f.interest_type"><option value="owner">মালিক</option><option value="possessor">দখলদার</option><option value="bargadar">বর্গাদার</option><option value="mortgagee">বন্ধকগ্রহীতা</option><option value="heir">ওয়ারিশ</option><option value="other">অন্যান্য</option></select></div>
        <div class="fld"><label>দাবিকৃত অংশ</label><input [(ngModel)]="f.claimed_share" /></div>
        <div class="fld"><label>আবেদনের বর্ণনা *</label><textarea rows="6" [(ngModel)]="f.description"></textarea></div>
      }
      @if (m.type === 'comp') {
        <div class="fld"><label>বিষয় *</label><input [(ngModel)]="f.subject" /></div>
        <div class="fld"><label>বিস্তারিত *</label><textarea rows="5" [(ngModel)]="f.details"></textarea></div>
        <div class="fld"><label>অর্থ গ্রহণের মাধ্যম *</label><select [(ngModel)]="f.account_type"><option value="mobile_banking">মোবাইল ব্যাংকিং</option><option value="bank">ব্যাংক হিসাব</option></select></div>
        <div class="fld"><label>হিসাব/মোবাইল নম্বর *</label><input [(ngModel)]="f.account_no" /></div>
      }
      @if (m.type === 'objection') {
        <div class="fld"><label>বিষয় *</label><input [(ngModel)]="f.subject" /></div>
        <div class="fld"><label>বিস্তারিত *</label><textarea rows="8" [(ngModel)]="f.details"></textarea></div>
      }
      <div class="fld"><label>সংযুক্তি (দলিল/খতিয়ান ইত্যাদি)</label><input type="file" multiple #fl /></div>
      @if (error()) { <p class="err">{{ error() }}</p> }
      <div class="bar"><button [disabled]="busy()" (click)="send(fl)">দাখিল করুন</button><button class="ghost" (click)="form.set(null)">বাতিল</button></div>
    </div></div>
  }`,
})
export class CitizenPortalComponent {
  private api = inject(Api);
  notices = signal<any[]>([]); mine = signal<any[]>([]); view$ = signal<any>(null); form = signal<any>(null); error = signal(''); busy = signal(false); f: any = {};
  constructor() { this.load(); }
  load() { this.api.get('citizen/notices').subscribe((r: any) => this.notices.set(r)); this.api.get('citizen/mine').subscribe((r: any) => this.mine.set(r)); }
  kind(k: string) { return KIND[k] ?? k; }
  bn(v: any) { return bnDate(v); }
  view(n: any) { this.api.get(`records/${n.id}`).subscribe((x: any) => this.view$.set(x)); }
  printIt() { printHtml('নোটিশ', this.view$().record.data.html); }
  tg(id: number) { const a = this.f.land_ids; const i = a.indexOf(id); i >= 0 ? a.splice(i, 1) : a.push(id); }
  open(type: string, notice: any) {
    this.error.set('');
    this.f = type === 'claim' ? { land_ids: [], interest_type: 'owner', claimed_share: '', description: 'বিনীত নিবেদন, আমি নিম্নস্বাক্ষরকারী সংশ্লিষ্ট মৌজাগুলোর একজন প্রকৃত স্বার্থাধিকারী। সরকারের ভূমি রেকর্ড হালনাগাদ প্রক্রিয়ার অংশ হিসেবে নিচে বর্ণিত জমিগুলোর "স্বার্থের প্রকৃতি" সংশোধনের জন্য এই আবেদনটি দাখিল করছি। উক্ত ভূমিগুলোর বাস্তব শ্রেণি বিবেচনা করে এবং আমার বৈধ মালিকানার সপক্ষে দাখিলকৃত প্রমাণাদি যাচাইপূর্বক সরকারি রেকর্ড হালনাগাদ করার জন্য বিনীত অনুরোধ জানাচ্ছি।' }
      : type === 'comp' ? { subject: 'ক্ষতিপূরণের আবেদন', details: 'অধিগ্রহণভুক্ত ভূমির ক্ষতিপূরণ প্রাপ্তির লক্ষ্যে সংশ্লিষ্ট কেসের তথ্য, ভূমির বিবরণ এবং প্রয়োজনীয় দলিলাদি অনুযায়ী আবেদন উপস্থাপন করা হলো। বিষয়টি যাচাইপূর্বক প্রয়োজনীয় কার্যক্রম গ্রহণের অনুরোধ করা যাচ্ছে।', account_type: 'mobile_banking', account_no: '' }
      : { subject: 'ধারার নোটিশের বিপরীতে আপত্তি দাখিল প্রসঙ্গে', details: 'বিনীত নিবেদন এই যে, নোটিশের আওতায় আমার মালিকানাধীন/স্বার্থসংশ্লিষ্ট জমি অধিগ্রহণের বিষয়ে আমার আপত্তি রয়েছে। উক্ত জমির মালিকানা, জমির পরিমাণ, শ্রেণি, মূল্য নির্ধারণ এবং অধিগ্রহণের প্রভাব যথাযথভাবে যাচাইপূর্বক প্রযোজ্য আইন ও বিধি অনুসারে বিষয়টি বিবেচনা করার জন্য অনুরোধ করছি। অতএব, আমার আপত্তি ও সংশ্লিষ্ট কাগজপত্র বিবেচনাপূর্বক প্রয়োজনীয় আইনানুগ ব্যবস্থা গ্রহণের জন্য বিনীত অনুরোধ জানাচ্ছি।' };
    this.form.set({ type, notice });
  }
  send(fl: HTMLInputElement) {
    const m = this.form(); const fd = new FormData();
    Object.entries(this.f).forEach(([k, v]) => { if (Array.isArray(v)) v.forEach(x => fd.append(`${k}[]`, String(x))); else fd.append(k, String(v ?? '')); });
    Array.from(fl.files ?? []).forEach(file => fd.append('files[]', file));
    const path = m.type === 'claim' ? 'claims' : m.type === 'comp' ? 'compensation-applications' : 'objections';
    this.busy.set(true); this.error.set('');
    this.api.post(`applications/${m.notice.application.id}/${path}`, fd).subscribe({ next: () => { this.busy.set(false); this.form.set(null); this.load(); }, error: e => { this.busy.set(false); this.error.set(errText(e)); } });
  }
}
