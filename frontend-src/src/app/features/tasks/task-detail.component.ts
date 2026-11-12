import { Component, OnInit, computed, inject, input, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { Api, errText } from '../../core/api.service';
import { Auth } from '../../core/auth.service';
import { ClassChangesComponent, CommitteeComponent, EstimateComponent, FeasibilityComponent, FieldBookComponent, NoticeEditorComponent, ParcelMediaComponent, ValuationComponent } from '../../shared/task-components';
import { ROLE_SHORT, money } from '../../shared/util';

/** One workflow step: the form comes from config/steps.php (fields + actions); components handle the structured parts. */
@Component({
  selector: 'app-task-detail', standalone: true,
  imports: [FormsModule, RouterLink, CommitteeComponent, FeasibilityComponent, FieldBookComponent, ParcelMediaComponent, ClassChangesComponent, ValuationComponent, EstimateComponent, NoticeEditorComponent],
  template: `
  @if (t(); as r) {
    <div class="bar"><div><small class="muted">ধাপ {{ r.step.step_no }} · {{ r.step.phase }} · {{ role(r.step.role) }}</small><h2>{{ r.step.label }}</h2></div><span class="sp"></span>
      <a class="btn ghost" [routerLink]="['/applications', r.application.id]">আবেদন দেখুন</a></div>
    <div class="card-info">
      <div><small>ট্র্যাকিং নং</small><b>{{ r.application.tracking_no }}</b></div><div><small>এল.এ কেস</small><b>{{ r.application.la_case_no || '—' }}</b></div>
      <div class="w3"><small>প্রকল্প</small><b>{{ r.application.project_name_bn }}</b></div><div><small>আয়তন</small><b>{{ r.application.total_area_acre }} একর</b></div>
      @if (r.task.due_at) { <div><small>শেষ সময়</small><b [class.bad]="r.task.overdue">{{ r.task.due_at.slice(0, 10) }}</b></div> }
    </div>
    @if (r.context.logs.length) { <details open><summary>পূর্ববর্তী ধাপের মন্তব্য</summary>@for (l of r.context.logs; track $index) { @if (l.comment) { <div class="cmt"><b>{{ role(l.actor_role) }}</b> ({{ l.step_code }}): {{ l.comment }}</div> } }</details> }
    @if (!r.can_act) { <div class="warn">@if (r.task.status === 'done') { এই পদক্ষেপ সম্পন্ন হয়েছে ({{ r.task.data?.comment }}) } @else { এই পদক্ষেপ অন্য ব্যবহারকারীর কাজ — আপনি শুধু দেখতে পারবেন। }</div> }

    @if (r.step.component === 'refine') { <div class="warn"><b>সংশোধনের নির্দেশ:</b> {{ r.context.refine_note }}<br><a class="btn" [routerLink]="['/applications', r.application.id, 'edit']">আবেদন সংশোধন করুন</a></div> }
    @if (r.step.component === 'deposit') { <div class="card-info"><div><small>প্রাক্কলিত ক্ষতিপূরণসহ জমার পরিমাণ</small><b>{{ m(r.context.deposit_required) }} ৳</b></div></div> }
    @if (r.step.component === 'deposit_verify') {
      <div class="card-info"><div><small>প্রাক্কলিত জমার পরিমাণ</small><b>{{ m(r.context.deposit_required) }} ৳</b></div>
        @if (r.context.deposit; as dp) { <div><small>জমাকৃত অর্থ</small><b [class.bad]="+dp.amount < +r.context.deposit_required">{{ m(dp.amount) }} ৳</b></div><div><small>মাধ্যম</small><b>{{ dp.method }}</b></div>
          <div><small>চালান/রেফারেন্স নং</small><b>{{ dp.reference }}</b></div><div><small>জমার তারিখ</small><b>{{ (dp.paid_at || '').toString().slice(0, 10) }}</b></div> }</div>
      <div class="warn">ট্রেজারি চালান/ব্যাংক বিবরণীর সাথে রেফারেন্স ও অঙ্ক মিলিয়ে যাচাই করুন।</div>
    }
    @if (r.context.record; as rec) {
      <div class="panel"><h4>সংশ্লিষ্ট আবেদন/দাবি</h4><table class="kv">@for (kv of recRows(rec.data); track kv[0]) { <tr><th>{{ kv[0] }}</th><td>{{ kv[1] }}</td></tr> }</table></div>
    }

    <div class="panel">
      @for (f of r.fields; track f.name) {
        <div class="fld"><label>{{ f.label }} @if (f.required) { <b class="req">*</b> }</label>
          @switch (f.type) {
            @case ('textarea') { <textarea rows="4" [(ngModel)]="data[f.name]" [disabled]="ro()"></textarea> }
            @case ('select') { <select [(ngModel)]="data[f.name]" [disabled]="ro()"><option value="">—</option>@for (o of entries(f.options); track o[0]) { <option [value]="o[0]">{{ o[1] }}</option> }</select> }
            @case ('user') { <select [(ngModel)]="data[f.name]" [disabled]="ro()"><option [ngValue]="null">— নির্বাচন করুন —</option>@for (u of f.choices; track u.id) { <option [ngValue]="u.id">{{ u.name_bn }}</option> }</select> }
            @case ('users') { <div class="chk">@for (u of f.choices; track u.id) { <label class="inl"><input type="checkbox" [checked]="has(data[f.name], u.id)" (change)="toggle(data[f.name], u.id)" [disabled]="ro()" /> {{ u.name_bn }}</label> } @empty { <span class="muted">কোনো ব্যবহারকারী পাওয়া যায়নি</span> }</div> }
            @case ('upazilas') { <div class="chk">@for (u of upazilas(); track u.id) { <label class="inl"><input type="checkbox" [checked]="has(data[f.name], u.id)" (change)="toggle(data[f.name], u.id)" [disabled]="ro()" /> {{ u.name }}</label> }</div> }
            @case ('component') {
              @switch (f.component) {
                @case ('committee') { <app-committee [value]="data[f.name]" [ro]="ro()" /> }
                @case ('feasibility') { <app-feasibility [value]="data[f.name]" [ro]="ro()" /> }
                @case ('field_book') { <app-field-book [value]="data[f.name]" [lands]="r.context.lands" [ro]="ro()" /> }
                @case ('parcel_media') { <app-parcel-media [value]="data[f.name]" [lands]="r.context.lands" [appId]="r.application.id" [ro]="ro()" /> }
                @case ('class_changes') { <app-class-changes [value]="data[f.name]" [lands]="r.context.lands" [classes]="r.context.classes" [ro]="ro()" /> }
                @case ('valuation') { <app-valuation [value]="data[f.name]" [lands]="r.context.lands" [otherKeys]="r.context.other_keys" [ro]="ro()" /> }
                @case ('estimate') { <app-estimate [value]="data[f.name]" [bodyType]="r.application.body_type" [otherKeys]="otherKeys()" [ro]="ro()" /> }
                @case ('notice') { <app-notice-editor [html]="data[f.name]" [ro]="ro()" (changed)="data[f.name] = $event" /> }
              }
            }
            @default { <input [type]="inputType(f.type)" [(ngModel)]="data[f.name]" [disabled]="ro()" /> }
          }
          @if (errs()[f.name]) { <small class="err">{{ errs()[f.name] }}</small> }
        </div>
      }
      @if (sel(); as s) {
        <div class="panel sub"><h4>{{ s.label }}</h4>
          @for (f of s.fields; track f.name) { <div class="fld"><label>{{ f.label }} @if (f.required) { <b class="req">*</b> }</label>
            @if (f.type === 'user') { <select [(ngModel)]="data[f.name]"><option [ngValue]="null">— নির্বাচন করুন —</option>@for (u of choices(f); track u.id) { <option [ngValue]="u.id">{{ u.name_bn }}</option> }</select> }
            @else if (f.type === 'textarea') { <textarea rows="3" [(ngModel)]="data[f.name]"></textarea> } @else { <input [(ngModel)]="data[f.name]" /> } </div> }
          <button (click)="go(s, true)">নিশ্চিত করুন</button> <button class="ghost" (click)="sel.set(null)">বাতিল</button></div>
      }
      @if (error()) { <p class="err">{{ error() }}</p> } @if (done()) { <p class="ok">{{ done() }}</p> }
      @if (!ro()) { <div class="bar">@for (a of r.actions; track a.key) { <button [class.danger]="a.style === 'danger'" [disabled]="busy()" (click)="go(a, false)">{{ a.label }}</button> }</div> }
    </div>
  } @else { <p>লোড হচ্ছে…</p> }`,
})
export class TaskDetailComponent implements OnInit {
  id = input<string>();
  private api = inject(Api); private router = inject(Router); auth = inject(Auth);
  t = signal<any>(null); data: any = {}; sel = signal<any>(null); error = signal(''); done = signal(''); busy = signal(false); errs = signal<Record<string, string>>({});
  ro = computed(() => !this.t()?.can_act); m = money;
  otherKeys = () => this.t()?.context?.other_keys ?? this.auth.meta()?.other_keys ?? {};
  ngOnInit() {
    this.api.get(`tasks/${this.id()}`).subscribe((r: any) => {
      this.data = {};
      for (const f of r.fields) this.data[f.name] = this.seed(f, r);
      if (r.task.data && !r.can_act) Object.assign(this.data, r.task.data);
      this.t.set(r);
    });
  }
  private seed(f: any, r: any): any {
    const ctx = r.context, lands: any[] = ctx.lands;
    switch (f.type) {
      case 'users': case 'upazilas': return [];
      case 'user': return null;
      case 'number': return null;
      case 'select': return f.default ?? '';
      case 'component':
        switch (f.component) {
          case 'committee': return [{ name: '', designation: '', role: 'সভাপতি' }, { name: '', designation: '', role: 'সদস্য' }, { name: '', designation: '', role: 'সদস্য-সচিব' }];
          case 'feasibility': return JSON.parse(JSON.stringify(ctx.feasibility_items ?? []));
          case 'field_book': return lands.map(l => ({ land_id: l.id, structures: '', commercial_use: '', water_body: '', trees: '', standing_crops: '', other_info: '', comment: '',
            stakeholders: (l.owners ?? []).map((o: any) => ({ name: o.name, nid: o.nid, mobile: o.mobile, type: 'owner' })) }));
          case 'parcel_media': return lands.map(l => ({ land_id: l.id, structures_desc: '', crops_desc: '', condition: 'structure' }));
          case 'class_changes': return lands.map(l => ({ land_id: l.id, new_class_id: null }));
          case 'valuation': return lands.map(l => ({ land_id: l.id, deeds: [{}, {}, {}], rate_per_acre: 0, needs_certificate: true, others: { structures: 0, trees: 0, ponds: 0, business: 0, machinery: 0, others: 0 }, note: '' }));
          case 'estimate': return JSON.parse(JSON.stringify(ctx.estimate_prefill ?? { lines: [], tax_percent: 0 }));
          case 'notice': return ctx.notice_preview ?? '';
        }
        return null;
      default: return f.default ?? '';
    }
  }
  role(c: string) { return ROLE_SHORT[c] ?? c; }
  entries(o: any) { return Object.entries(o ?? {}); }
  inputType(t: string) { return t === 'number' ? 'number' : t === 'date' ? 'date' : t === 'time' ? 'time' : 'text'; }
  has(arr: any[], id: number) { return (arr ?? []).includes(id); }
  toggle(arr: any[], id: number) { const i = arr.indexOf(id); i >= 0 ? arr.splice(i, 1) : arr.push(id); }
  upazilas() { const seen = new Map<number, string>(); for (const l of this.t().context.lands) seen.set(l.upazila_id, l.upazila?.name_bn); return [...seen].map(([id, name]) => ({ id, name })); }
  choices(f: any) { return f.choices ?? []; }
  recRows(d: any): [string, string][] {
    const L: Record<string, string> = { name: 'নাম', nid: 'এনআইডি', mobile: 'মোবাইল', interest_type: 'স্বার্থের ধরন', description: 'বিবরণ', claimed_share: 'দাবিকৃত অংশ', subject: 'বিষয়', details: 'বিস্তারিত', account_type: 'অ্যাকাউন্ট', account_no: 'অ্যাকাউন্ট নং', entitled_amount: 'রোয়েদাদ অনুযায়ী প্রাপ্য (৳)', matched_lines: 'মিলে যাওয়া রোয়েদাদ লাইন' };
    return Object.entries(d ?? {}).filter(([k]) => L[k]).map(([k, v]) => [L[k], Array.isArray(v) ? v.join(', ') : String(v)]);
  }
  go(a: any, confirmed: boolean) {
    this.error.set(''); this.errs.set({});
    if (a.fields?.length && !confirmed) { this.sel.set(a); return; }
    if (a.style === 'danger' && !confirm(`"${a.label}" — নিশ্চিত?`)) return;
    this.busy.set(true);
    this.api.post(`tasks/${this.id()}/act`, { action: a.key, data: this.data }).subscribe({
      next: (r: any) => { this.busy.set(false); this.done.set('সম্পন্ন হয়েছে' + (r.created?.length ? ' → পরবর্তী: ' + r.created.map((c: any) => c.label).join(', ') : '')); setTimeout(() => this.router.navigate(['/tasks']), 900); },
      error: e => { this.busy.set(false); this.error.set(errText(e)); const det = e?.error?.error?.details; if (det && !Array.isArray(det)) this.errs.set(Object.fromEntries(Object.entries(det).map(([k, v]) => [k, String(v)]))); },
    });
  }
}
