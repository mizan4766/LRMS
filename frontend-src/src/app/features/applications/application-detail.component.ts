import { DatePipe } from '@angular/common';
import { Component, OnInit, computed, inject, input, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { Api, bnDate, errText } from '../../core/api.service';
import { Auth } from '../../core/auth.service';
import { KIND, ROLE_SHORT, STATUS, printHtml, saveBlob } from '../../shared/util';

/** Milestones of the workflow script (Step 02 ... 31); a milestone is current when one of its steps is open. */
const MILESTONES: [string, string[]][] = [
  ['আবেদন গ্রহণ ও প্রক্রিয়াকরণ (০২–০৩)', ['S02', 'S03']], ['যাচাই-বাছাই: চ-ফর্ম ও সংযুক্তি (০৩.১–৫)', ['S03_1', 'S04', 'S04_1', 'S04_2', 'S04_3', 'S04_4', 'S04_5', 'S05A', 'S05B', 'S05C', 'S05', 'S05R', 'S05_4', 'S04R']],
  ['প্রাথমিক সিদ্ধান্ত (০৬)', ['S06']], ['সম্ভাব্যতা যাচাই কমিটি ও প্রতিবেদন (০৭)', ['S07_1', 'S07_2']], ['অনুমোদন সভা ও প্রাথমিক সিদ্ধান্ত (০৮)', ['S08_1', 'S08_2', 'S08_3', 'S08_4']],
  ['ভিডিও/স্থিরচিত্র ও এল.এ কেস নং (০৯–১০)', ['S09', 'S10']], ['৪(১) ধারার নোটিশ (১১–১২)', ['S11', 'S12_1']], ['যৌথ তদন্ত কমিটি (১২.২)', ['S12_2']],
  ['ফিল্ডবহি ও শ্রেণি পরিবর্তন (১৩–১৪)', ['S13', 'S13_1', 'S13_2', 'S13_3', 'S14']], ['৭(১) ধারার নোটিশ (১৫–১৬)', ['S15', 'S16']], ['মালিকানা দাবি (১৭–১৯)', ['S18', 'S19']],
  ['মূল্য নির্ধারণ (২০–২০.১)', ['S20', 'S20_1']], ['প্রাক্কলন ও রোয়েদাদ (২০.২–২১)', ['S20_2', 'S21']], ['ক্ষতিপূরণের অর্থ জমা (২১.২–২২)', ['S21_2', 'S22', 'S22V']], ['৮(৩) ধারার নোটিশ (২৩–২৪)', ['S23', 'S24']],
  ['ক্ষতিপূরণ আবেদন ও বিতরণ (২৫–২৭)', ['S26', 'S27']], ['দখল হস্তান্তর (২৮–২৯)', ['S28', 'S29']], ['ঘোষণা, গেজেট ও দলিল (৩০–৩১)', ['S30', 'S31A', 'S31B', 'S31C']],
  ['আপত্তি ও শুনানি', ['O02A', 'O02B', 'O02C', 'O02D']],
];

@Component({
  selector: 'app-application-detail', standalone: true, imports: [DatePipe, FormsModule, RouterLink],
  template: `
  @if (d(); as d) {
    <div class="bar"><h2>{{ a().tracking_no || 'খসড়া আবেদন #' + a().id }}</h2><span class="sp"></span>
      @if (a().status === 'draft' && auth.is('rb')) { <a class="btn" [routerLink]="['/applications', a().id, 'edit']">সম্পাদনা</a> }</div>
    <div class="card-info">
      <div><small>প্রকল্প</small><b>{{ a().project_name_bn }}</b><br><small>{{ a().project_name_en }}</small></div>
      <div><small>এল.এ কেস নং</small><b>{{ a().la_case_no || '—' }}</b></div>
      <div><small>প্রত্যাশী সংস্থা</small><b>{{ a().body?.name_bn }}</b></div>
      <div><small>জেলা</small><b>{{ a().district?.name_bn }}</b></div>
      <div><small>মোট ভূমি</small><b>{{ a().total_area_acre }} একর</b><br><small>সিদ্ধান্ত: {{ d.authority_label }}</small></div>
      <div><small>অবস্থা / ধাপ</small><span class="badge st-{{ a().status }}">{{ st(a().status) }}</span><br><small>{{ a().phase }}</small></div>
      @if (a().objection_due_at) { <div><small>আপত্তির শেষ সময় (ধারা ৫(১))</small><b>{{ bn(a().objection_due_at) }}</b></div> }
    </div>
    @if (d.open_tasks.length) { <p>অপেক্ষমাণ ধাপ: @for (t of d.open_tasks; track t.id) { <span class="chip">{{ stepLabel(t.step_code) }} ({{ role(t.role) }})</span> }</p> }
    <div class="tabs">@for (t of tabs; track t[0]) { <button [class.on]="tab() === t[0]" (click)="tab.set(t[0])">{{ t[1] }}</button> }</div>

    @switch (tab()) {
      @case ('road') {
        <div class="road">@for (m of milestones(); track m.label) { <div class="ms {{ m.state }}"><span class="dot"></span>{{ m.label }}</div> }</div>
      }
      @case ('info') {
        <table class="kv"><tr><th>প্রতিনিধি</th><td>{{ a().pd_name_bn }} ({{ a().pd_designation }}) — {{ a().pd_mobile }}, {{ a().pd_email }}</td></tr>
          <tr><th>বিষয়</th><td>{{ a().subject }}</td></tr>
          <tr><th>পূর্বের অধিগ্রহণ</th><td>{{ a().has_prior_acquisition ? (a().prior_project_name + ' — এল.এ কেস ' + a().prior_la_case_no) : 'নেই' }}</td></tr>
          <tr><th>দাখিলের তারিখ</th><td>{{ bn(a().submitted_at) }}</td></tr>
          <tr><th>ক্ষতিপূরণ (মোট / জমার প্রাক্কলন)</th><td>{{ a().compensation_total }} / {{ a().deposit_required }} ৳</td></tr></table>
      }
      @case ('lands') {
        <table><tr><th>উপজেলা</th><th>মৌজা</th><th>জেএল</th><th>খতিয়ান</th><th>দাগ</th><th>শ্রেণি (রেকর্ড/ব্যবহার)</th><th>প্রস্তাবিত (একর)</th><th>মালিক</th></tr>
          @for (l of d.lands; track l.id) { <tr><td>{{ l.upazila?.name_bn }}</td><td>{{ l.mouza?.name_bn }}</td><td>{{ l.jl_no }}</td><td>{{ l.khatian_no }}</td><td>{{ l.dag_no }}</td>
            <td>{{ l.record_class?.name_bn }} / {{ l.usage_class?.name_bn }}</td><td>{{ l.proposed_area_acre }} / {{ l.total_area_acre }}</td><td>@for (o of l.owners; track o.id) { <div>{{ o.name }} <small>({{ o.nid }})</small></div> }</td></tr> }</table>
      }
      @case ('att') {
        <table><tr><th>ক্রম</th><th>কাগজপত্র</th><th>ফাইল</th><th>যাচাই</th></tr>
          @for (c of d.checklist; track c.no) { @if (c.visible) {
            <tr><td>{{ c.no }}</td><td>{{ c.title }} @if (c.required && !c.uploaded) { <span class="badge bad">নেই</span> }</td>
              <td>@if (att(c.no); as f) { <a class="lnk" (click)="open(f)">{{ f.original_name }}</a> }</td>
              <td>@if (att(c.no); as f) { <span class="badge {{ f.status === 'ok' ? 'req' : f.status === 'fix' ? 'bad' : 'cond' }}">{{ f.status === 'ok' ? 'সঠিক' : f.status === 'fix' ? 'সংশোধন প্রয়োজন' : 'অপেক্ষমাণ' }}</span> {{ f.note }}
                @if (canVerify()) { <a class="lnk" (click)="mark(f, 'ok')">সঠিক</a> <a class="lnk bad" (click)="markFix(f)">সংশোধন প্রয়োজন</a> } }</td></tr> } }
        </table>
        @if (verr()) { <p class="err">{{ verr() }}</p> }
      }
      @case ('rec') {
        <table><tr><th>নথি</th><th>নং</th><th>অবস্থা</th><th>তারিখ</th><th></th></tr>
          @for (r of d.records; track r.id) { <tr><td>{{ kind(r.kind) }}</td><td>{{ r.ref_no }}</td><td>{{ r.status }}</td><td>{{ bn(r.published_at || r.created_at) }}</td>
            <td><a class="lnk" (click)="view(r)">দেখুন/প্রিন্ট</a></td></tr> } @empty { <tr><td colspan="5" class="muted">কোনো নথি নেই</td></tr> }</table>
        @if (rec()) { <div class="panel"><div class="bar"><h3>{{ kind(rec().record.kind) }}</h3><span class="sp"></span><button class="ghost" (click)="print()">প্রিন্ট</button><button class="ghost" (click)="rec.set(null)">বন্ধ</button></div>
          @if (rec().record.data?.html) { <div [innerHTML]="rec().record.data.html"></div> } @else { <pre class="json">{{ pretty(rec().record.data) }}</pre> }</div> }
      }
      @case ('log') {
        <table><tr><th>সময়</th><th>ধাপ</th><th>কার্যক্রম</th><th>ভূমিকা</th><th>মন্তব্য</th></tr>
          @for (l of d.logs; track l.id) { <tr><td>{{ l.created_at | date:'dd/MM/yyyy HH:mm' }}</td><td>{{ stepLabel(l.step_code) }}</td><td>{{ l.action }}</td><td>{{ role(l.actor_role) }}</td><td>{{ l.comment }}</td></tr> }</table>
      }
    }
  } @else { <p>লোড হচ্ছে…</p> }`,
})
export class ApplicationDetailComponent implements OnInit {
  id = input<string>();
  auth = inject(Auth); private api = inject(Api);
  d = signal<any>(null); a = computed(() => this.d()?.application); tab = signal('road'); rec = signal<any>(null); verr = signal('');
  tabs: [string, string][] = [['road', 'রোডম্যাপ'], ['info', 'সারসংক্ষেপ'], ['lands', 'দাগ ও মালিক'], ['att', 'সংযুক্তি'], ['rec', 'নোটিশ ও নথি'], ['log', 'ইতিহাস']];
  ngOnInit() { this.load(); }
  load() { this.api.get(`applications/${this.id()}`).subscribe((r: any) => this.d.set(r)); }
  st(s: string) { return STATUS[s] ?? s; }
  kind(k: string) { return KIND[k] ?? k; }
  role(r: string) { return ROLE_SHORT[r] ?? r; }
  bn(v: any) { return bnDate(v); }
  stepLabel(code: string) { return code ? `${this.auth.meta()?.steps?.find((s: any) => s.code === code)?.step_no ?? ''} ${this.auth.meta()?.steps?.find((s: any) => s.code === code)?.label ?? code}` : ''; }
  att(no: number) { return (this.d()?.attachments ?? []).find((f: any) => f.kind === 'proposal' && f.slot_no === no); }
  canVerify() { return this.auth.is('lao', 'kanungo', 'surveyor', 'adc', 'dc'); }
  open(f: any) { this.api.blob(`attachments/${f.id}/download`).subscribe(b => saveBlob(b, f.original_name)); }
  mark(f: any, status: string, note = '') { this.api.post(`attachments/${f.id}/verify`, { status, note }).subscribe({ next: () => this.load(), error: e => this.verr.set(errText(e)) }); }
  markFix(f: any) { const n = prompt('কী সংশোধন প্রয়োজন?') ?? ''; if (n) this.mark(f, 'fix', n); }
  milestones() {
    const open = new Set((this.d()?.open_tasks ?? []).map((t: any) => t.step_code)); const done = new Set((this.d()?.tasks_done ?? []).map((t: any) => t.step_code));
    const curIdx = MILESTONES.findIndex(([, steps]) => steps.some(s => open.has(s)));
    return MILESTONES.map(([label, steps], i) => ({ label, state: steps.some(s => open.has(s)) ? 'cur' : steps.some(s => done.has(s)) && (curIdx < 0 || i < curIdx) ? 'done' : steps.some(s => done.has(s)) ? 'part' : '' }));
  }
  view(r: any) { this.api.get(`records/${r.id}`).subscribe((x: any) => this.rec.set(x)); }
  print() { printHtml(this.kind(this.rec().record.kind), this.rec().record.data?.html ?? `<pre>${this.pretty(this.rec().record.data)}</pre>`); }
  pretty(o: any) { return JSON.stringify(o, null, 2); }
}
