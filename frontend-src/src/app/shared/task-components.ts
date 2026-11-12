import { Component, ElementRef, EventEmitter, Input, OnInit, Output, ViewChild, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Api, errText } from '../core/api.service';
import { money } from './util';

/** Editors for the structured parts of the workflow forms. Each edits the object/array it receives in place. */

@Component({
  selector: 'app-committee', standalone: true, imports: [FormsModule],
  template: `
  <table class="mini"><tr><th>নাম</th><th>পদবি</th><th>কমিটিতে ভূমিকা</th><th></th></tr>
    @for (m of value; track $index) { <tr><td><input [(ngModel)]="m.name" [disabled]="ro" /></td><td><input [(ngModel)]="m.designation" [disabled]="ro" /></td>
      <td><input [(ngModel)]="m.role" [disabled]="ro" placeholder="সভাপতি/সদস্য/সদস্য-সচিব" /></td><td>@if (!ro) { <button class="ghost sm" (click)="value.splice($index, 1)">✖</button> }</td></tr> }</table>
  @if (!ro) { <button class="ghost" (click)="value.push({ name: '', designation: '', role: '' })">➕ সদস্য যোগ</button> }`,
})
export class CommitteeComponent { @Input() value: any[] = []; @Input() ro = false; }

@Component({
  selector: 'app-feasibility', standalone: true, imports: [FormsModule],
  template: `
  <table class="mini"><tr><th>ক্রম</th><th>বিষয়</th><th>মতামত</th></tr>
    @for (it of value; track it.no) { <tr><td>{{ it.no }}</td><td>{{ it.title }}</td><td><textarea rows="2" [(ngModel)]="it.opinion" [disabled]="ro"></textarea></td></tr> }</table>`,
})
export class FeasibilityComponent { @Input() value: any[] = []; @Input() ro = false; }

@Component({
  selector: 'app-field-book', standalone: true, imports: [FormsModule],
  template: `
  @for (e of value; track e.land_id) {
    <div class="fb"><h4>{{ label(e.land_id) }}</h4>
      <div class="grid">
        <label>অবকাঠামো (ঘরবাড়ি/দালানকোঠা)<textarea rows="2" [(ngModel)]="e.structures" [disabled]="ro"></textarea></label>
        <label>বাণিজ্যিক ব্যবহার/শিল্পকারখানা<textarea rows="2" [(ngModel)]="e.commercial_use" [disabled]="ro"></textarea></label>
        <label>জলাশয় (মৎস্য চাষের আওতাধীন/না)<textarea rows="2" [(ngModel)]="e.water_body" [disabled]="ro"></textarea></label>
        <label>বৃক্ষাদির বিবরণ (নামসহ সংখ্যা)<textarea rows="2" [(ngModel)]="e.trees" [disabled]="ro"></textarea></label>
        <label>দণ্ডায়মান ফসলের বিবরণী<textarea rows="2" [(ngModel)]="e.standing_crops" [disabled]="ro"></textarea></label>
        <label>অন্য কোনো তথ্য<textarea rows="2" [(ngModel)]="e.other_info" [disabled]="ro"></textarea></label>
        <label class="w2">মন্তব্য<textarea rows="2" [(ngModel)]="e.comment" [disabled]="ro"></textarea></label>
      </div>
      <b>চ-ফর্ম থেকে পাওয়া স্বার্থসংশ্লিষ্ট ব্যক্তিগণ</b>
      <table class="mini"><tr><th>নাম</th><th>পরিচয়পত্র</th><th>মোবাইল</th><th>ধরন</th><th></th></tr>
        @for (s of e.stakeholders; track $index) { <tr><td><input [(ngModel)]="s.name" [disabled]="ro" /></td><td><input [(ngModel)]="s.nid" [disabled]="ro" /></td><td><input [(ngModel)]="s.mobile" [disabled]="ro" /></td>
          <td><select [(ngModel)]="s.type" [disabled]="ro"><option value="owner">মালিক</option><option value="possessor">দখলদার</option><option value="bargadar">বর্গাদার</option><option value="other">চিহ্নিত অন্য ব্যক্তি</option></select></td>
          <td>@if (!ro) { <button class="ghost sm" (click)="e.stakeholders.splice($index, 1)">✖</button> }</td></tr> }</table>
      @if (!ro) { <button class="ghost" (click)="e.stakeholders.push({ name: '', type: 'other' })">➕ চিহ্নিত ব্যক্তি যুক্ত করুন</button> }
    </div>
  }`,
})
export class FieldBookComponent {
  @Input() value: any[] = []; @Input() lands: any[] = []; @Input() ro = false;
  label(id: number) { const l = this.lands.find(x => x.id === id); return l ? `${l.upazila?.name_bn} — খতিয়ান ${l.khatian_no} / দাগ ${l.dag_no} (${l.proposed_area_acre} একর)` : `দাগ #${id}`; }
}

@Component({
  selector: 'app-parcel-media', standalone: true, imports: [FormsModule],
  template: `
  @for (e of value; track e.land_id) {
    <div class="fb"><h4>{{ label(e.land_id) }}</h4>
      <div class="grid"><label>জমির উপরিস্থিত অবকাঠামোর বিবরণ<textarea rows="2" [(ngModel)]="e.structures_desc" [disabled]="ro"></textarea></label>
        <label>ফসল, গাছপালা, বাগান ইত্যাদির বিবরণ<textarea rows="2" [(ngModel)]="e.crops_desc" [disabled]="ro"></textarea></label>
        <label>জমির বাস্তব অবস্থা<select [(ngModel)]="e.condition" [disabled]="ro"><option value="structure">স্থাপনাসহ</option><option value="cultivated">আবাদি</option><option value="vacant">খালি</option><option value="water">জলাশয়</option></select></label></div>
      @if (!ro) { <label>ভিডিও/ছবি আপলোড (একাধিকবার দেওয়া যায়) <input type="file" #f (change)="up(e, f)" /></label> }
      @for (n of (files[e.land_id] || []); track n) { <div class="ok">✔ {{ n }}</div> }
    </div>
  }
  @if (error()) { <p class="err">{{ error() }}</p> }`,
})
export class ParcelMediaComponent {
  private api = inject(Api);
  @Input() value: any[] = []; @Input() lands: any[] = []; @Input() ro = false; @Input() appId!: number;
  files: Record<number, string[]> = {}; error = signal('');
  label(id: number) { const l = this.lands.find(x => x.id === id); return l ? `${l.upazila?.name_bn} — খতিয়ান ${l.khatian_no} / দাগ ${l.dag_no}` : `দাগ #${id}`; }
  up(e: any, input: HTMLInputElement) {
    const f = input.files?.[0]; if (!f) return;
    const fd = new FormData(); fd.append('kind', 'media'); fd.append('ref_id', String(e.land_id)); fd.append('title', 'ভিডিও/স্থিরচিত্র'); fd.append('file', f);
    this.api.post(`applications/${this.appId}/attachments`, fd).subscribe({ next: () => { (this.files[e.land_id] ??= []).push(f.name); input.value = ''; this.error.set(''); }, error: x => this.error.set(errText(x)) });
  }
}

@Component({
  selector: 'app-class-changes', standalone: true, imports: [FormsModule],
  template: `
  <table class="mini"><tr><th>দাগ</th><th>বর্তমান ব্যবহার শ্রেণি</th><th>পরিবর্তিত শ্রেণি</th></tr>
    @for (e of value; track e.land_id) { <tr><td>{{ label(e.land_id) }}</td><td>{{ current(e.land_id) }}</td>
      <td><select [(ngModel)]="e.new_class_id" [disabled]="ro"><option [ngValue]="null">পরিবর্তন নেই</option>@for (c of classes; track c.id) { <option [ngValue]="c.id">{{ c.name_bn }}</option> }</select></td></tr> }</table>`,
})
export class ClassChangesComponent {
  @Input() value: any[] = []; @Input() lands: any[] = []; @Input() classes: any[] = []; @Input() ro = false;
  label(id: number) { const l = this.lands.find(x => x.id === id); return l ? `খতিয়ান ${l.khatian_no} / দাগ ${l.dag_no}` : `#${id}`; }
  current(id: number) { return this.lands.find(x => x.id === id)?.usage_class?.name_bn ?? ''; }
}

@Component({
  selector: 'app-valuation', standalone: true, imports: [FormsModule],
  template: `
  @for (e of value; track e.land_id) {
    <div class="fb"><h4>{{ label(e.land_id) }}</h4>
      <p class="muted">ধারা ৪ নোটিশের পূর্ববর্তী ১২ মাসের সমশ্রেণির দলিল (বিধি ২৯) — কমপক্ষে ৩টি; না থাকলে প্রত্যয়নপত্র (তফসিল-২৬) আবশ্যক।</p>
      <table class="mini"><tr><th>দলিল নং</th><th>তারিখ</th><th>মূল্য (৳)</th><th>আয়তন (একর)</th><th></th></tr>
        @for (d of e.deeds; track $index) { <tr><td><input [(ngModel)]="d.deed_no" [disabled]="ro" /></td><td><input type="date" [(ngModel)]="d.deed_date" [disabled]="ro" /></td>
          <td><input type="number" [(ngModel)]="d.value" [disabled]="ro" /></td><td><input type="number" step="0.0001" [(ngModel)]="d.area_acre" [disabled]="ro" /></td><td>@if (!ro) { <button class="ghost sm" (click)="e.deeds.splice($index, 1)">✖</button> }</td></tr> }</table>
      @if (!ro) { <button class="ghost" (click)="e.deeds.push({})">➕ দলিল</button> <button class="ghost" (click)="rate(e)">গড় দর হিসাব করুন</button> }
      <div class="grid"><label>একরপ্রতি বাজারমূল্য (৳) *<input type="number" [(ngModel)]="e.rate_per_acre" [disabled]="ro" /></label>
        <label class="inl"><input type="checkbox" [(ngModel)]="e.needs_certificate" [disabled]="ro" /> দলিল ৩টির কম — প্রত্যয়নপত্র (তফসিল-২৬) সংযুক্ত</label></div>
      <b>অন্যান্য সম্পত্তির মূল্যায়ন (৳) — এর উপর ১০০% অতিরিক্ত যুক্ত হবে (ধারা ৯(৩), বিধি ৩০(১১))</b>
      <div class="grid">@for (k of keys(); track k[0]) { <label>{{ k[1] }}<input type="number" [(ngModel)]="e.others[k[0]]" [disabled]="ro" /></label> }</div>
      <label>টীকা<input [(ngModel)]="e.note" [disabled]="ro" /></label>
    </div>
  }
  @if (msg()) { <p class="ok">{{ msg() }}</p> } @if (error()) { <p class="err">{{ error() }}</p> }`,
})
export class ValuationComponent {
  private api = inject(Api);
  @Input() value: any[] = []; @Input() lands: any[] = []; @Input() otherKeys: Record<string, string> = {}; @Input() ro = false;
  msg = signal(''); error = signal('');
  keys() { return Object.entries(this.otherKeys); }
  label(id: number) { const l = this.lands.find(x => x.id === id); return l ? `${l.upazila?.name_bn} — খতিয়ান ${l.khatian_no} / দাগ ${l.dag_no}` : `#${id}`; }
  rate(e: any) {
    this.api.post('estimate/deed-rate', { deeds: (e.deeds ?? []).filter((d: any) => +d.value > 0 && +d.area_acre > 0) }).subscribe({
      next: (r: any) => { e.rate_per_acre = r.rate_per_acre; e.needs_certificate = r.needs_certificate; this.msg.set(`${r.deeds}টি দলিলের গড় দর: ${money(r.rate_per_acre)} ৳/একর${r.needs_certificate ? ' — ৩টির কম, প্রত্যয়নপত্র লাগবে' : ''}`); this.error.set(''); },
      error: x => this.error.set(errText(x)),
    });
  }
}

@Component({
  selector: 'app-estimate', standalone: true, imports: [FormsModule],
  template: `
  <p class="muted">জমির মূল্যের সাথে {{ bodyType === 'private' ? '৩০০%' : '২০০%' }} এবং অন্যান্য সম্পত্তির মূল্যের সাথে ১০০% অতিরিক্ত যুক্ত হয়ে মোট ক্ষতিপূরণ নির্ধারিত হয় (ধারা ৯, বিধি ২৯–৩০)। হিসাব সার্ভারে সম্পন্ন হয়।</p>
  <table class="mini wide"><tr><th>দাগ</th><th>মালিক</th><th>অংশ</th><th>আয়তন (একর)</th><th>একরপ্রতি দর (৳)</th>@for (k of keys(); track k[0]) { <th>{{ k[1] }} (৳)</th> }<th></th></tr>
    @for (l of value.lines; track $index) { <tr><td>{{ l.land_label }}</td><td>{{ l.owner_name }}</td><td><input class="n" type="number" step="0.0001" [(ngModel)]="l.share" [disabled]="ro" /></td>
      <td><input class="n" type="number" step="0.0001" [(ngModel)]="l.area_acre" [disabled]="ro" /></td><td><input class="n" type="number" [(ngModel)]="l.rate_per_acre" [disabled]="ro" /></td>
      @for (k of keys(); track k[0]) { <td><input class="n" type="number" [(ngModel)]="l.others[k[0]]" [disabled]="ro" /></td> }
      <td>@if (!ro) { <button class="ghost sm" (click)="value.lines.splice($index, 1)">✖</button> }</td></tr> }</table>
  <div class="row"><label>উৎসে কর (%)<input class="n" type="number" [(ngModel)]="value.tax_percent" [disabled]="ro" /></label>
    @if (!ro) { <button (click)="calc()">হিসাব করুন</button> }</div>
  @if (res(); as r) {
    <table class="mini"><tr><th>মালিক</th><th>জমির মূল্য</th><th>+প্রিমিয়াম ({{ bodyType === 'private' ? '৩০০%' : '২০০%' }})</th><th>অন্যান্য সম্পত্তি</th><th>+১০০%</th><th>মোট</th><th>কর</th><th>নীট</th></tr>
      @for (x of r.lines; track $index) { <tr><td>{{ x.owner_name }}</td><td>{{ m(x.land_value) }}</td><td>{{ m(x.premium) }}</td><td>{{ m(x.other_sum) }}</td><td>{{ m(x.extra_100) }}</td><td>{{ m(x.gross) }}</td><td>{{ m(x.tax) }}</td><td>{{ m(x.net) }}</td></tr> }
      <tr class="tot"><td colspan="5">মোট ক্ষতিপূরণ</td><td>{{ m(r.gross_total) }}</td><td>{{ m(r.tax_total) }}</td><td>{{ m(r.net_total) }}</td></tr>
      <tr class="tot"><td colspan="5">আনুষঙ্গিক ব্যয় (বিধি ৩১(২))</td><td colspan="3">{{ m(r.incidental) }}</td></tr>
      <tr class="tot"><td colspan="5"><b>প্রাক্কলিত মোট (জমার পরিমাণ)</b></td><td colspan="3"><b>{{ m(r.estimate_total) }} ৳</b></td></tr></table>
  }
  @if (error()) { <p class="err">{{ error() }}</p> }`,
})
export class EstimateComponent implements OnInit {
  private api = inject(Api);
  @Input() value: any = { lines: [], tax_percent: 0 }; @Input() bodyType = 'govt'; @Input() otherKeys: Record<string, string> = {}; @Input() ro = false;
  res = signal<any>(null); error = signal(''); m = money;
  keys() { return Object.entries(this.otherKeys); }
  ngOnInit() { if (!this.ro) this.calc(); }
  calc() {
    this.api.post('estimate/calc', { lines: this.value.lines, tax_percent: this.value.tax_percent || 0, body_type: this.bodyType }).subscribe({ next: r => { this.res.set(r); this.error.set(''); }, error: e => this.error.set(errText(e)) });
  }
}

@Component({
  selector: 'app-notice-editor', standalone: true,
  template: `<p class="muted">খসড়া নোটিশ নিচে সম্পাদনা করা যাবে।</p><div #box class="notice" [attr.contenteditable]="!ro" (input)="changed.emit(box.innerHTML)" (blur)="changed.emit(box.innerHTML)"></div>`,
})
export class NoticeEditorComponent implements OnInit {
  @Input() html = ''; @Input() ro = false; @Output() changed = new EventEmitter<string>();
  @ViewChild('box', { static: true }) box!: ElementRef<HTMLElement>;
  ngOnInit() { this.box.nativeElement.innerHTML = this.html; this.changed.emit(this.html); }
}
