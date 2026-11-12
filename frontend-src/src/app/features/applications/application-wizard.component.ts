import { Component, OnInit, computed, inject, input, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { Api, errText } from '../../core/api.service';
import { Auth } from '../../core/auth.service';
import { AttachmentSlotsComponent } from '../../shared/attachment-slots.component';
import { LandEditorComponent } from '../../shared/land-editor.component';

/** Step 01 of the workflow: new proposal -> general info -> lands & owners -> attachments -> submit (5-step wizard). */
@Component({
  selector: 'app-wizard', standalone: true, imports: [FormsModule, RouterLink, AttachmentSlotsComponent, LandEditorComponent],
  template: `
  <h2>{{ id() ? 'অধিগ্রহণ আবেদন' : 'নতুন প্রস্তাব করুন' }} @if (app()?.tracking_no) { <small>— {{ app().tracking_no }}</small> }</h2>

  @if (!id()) {
    <div class="panel">
      <h3>প্রত্যাশী সংস্থার তথ্য ও প্রকল্প</h3>
      <div class="grid">
        <label class="w2">প্রকল্পের নাম (বাংলা) *<input [(ngModel)]="f.project_name_bn" /></label>
        <label class="w2">Project Name (English)<input [(ngModel)]="f.project_name_en" /></label>
        <label>পিডি/প্রতিনিধির নাম (বাংলা)<input [(ngModel)]="f.pd_name_bn" /></label>
        <label>Name (English)<input [(ngModel)]="f.pd_name_en" /></label>
        <label>পদবি<input [(ngModel)]="f.pd_designation" /></label>
        <label>মোবাইল<input [(ngModel)]="f.pd_mobile" /></label>
        <label>ইমেইল<input [(ngModel)]="f.pd_email" /></label>
        <label>জাতীয় পরিচয়পত্র নং<input [(ngModel)]="f.pd_nid" /></label>
        <label class="w2">অফিসের ঠিকানা<input [(ngModel)]="f.pd_office_address" /></label>
      </div>
      @if (error()) { <p class="err">{{ error() }}</p> }
      <button (click)="create()">সংরক্ষণ করুন ও পরবর্তী ধাপে যান</button>
    </div>
  }
  @if (data(); as d) {
    @if (d.application.status === 'needs_refinement') { <div class="warn"><b>সংশোধন প্রয়োজন:</b> {{ d.application.refine_note }}<br>প্রয়োজনীয় তথ্য/ফাইল সংশোধন করে "পদক্ষেপ" মেনু থেকে "সংশোধিত আবেদন দাখিল করুন" চাপুন।</div> }
    @if (!canEdit()) { <div class="warn">এই আবেদন এখন সম্পাদনাযোগ্য নয়।</div> }
    <div class="stepper">@for (s of steps; track s[0]) { <button class="step" [class.on]="step() === s[0]" (click)="go(s[0])">{{ s[0] }}. {{ s[1] }}</button> }</div>

    @switch (step()) {
      @case (1) {
        <div class="panel"><h3>অধিগ্রহণ আবেদনের সাধারণ তথ্য</h3>
          <div class="grid">
            <label class="w2">প্রকল্পের নাম (বাংলা)<input [(ngModel)]="f.project_name_bn" [disabled]="!canEdit()" /></label>
            <label class="w2">Project Name (English)<input [(ngModel)]="f.project_name_en" [disabled]="!canEdit()" /></label>
            <label>পিডি/প্রতিনিধির নাম *<input [(ngModel)]="f.pd_name_bn" [disabled]="!canEdit()" /></label>
            <label>Name (English)<input [(ngModel)]="f.pd_name_en" [disabled]="!canEdit()" /></label>
            <label>পদবি *<input [(ngModel)]="f.pd_designation" [disabled]="!canEdit()" /></label>
            <label>মোবাইল *<input [(ngModel)]="f.pd_mobile" [disabled]="!canEdit()" /></label>
            <label>ইমেইল *<input [(ngModel)]="f.pd_email" [disabled]="!canEdit()" /></label>
            <label>জাতীয় পরিচয়পত্র নং<input [(ngModel)]="f.pd_nid" [disabled]="!canEdit()" /></label>
            <label class="w2">অফিসের ঠিকানা<input [(ngModel)]="f.pd_office_address" [disabled]="!canEdit()" /></label>
            <label>বিভাগ<select [ngModel]="divisionOf(f.district_id)" (ngModelChange)="div = $event; f.district_id = null" [disabled]="!canEdit()">@for (x of auth.meta()?.divisions; track x.id) { <option [ngValue]="x.id">{{ x.name_bn }}</option> }</select></label>
            <label>জেলা *<select [(ngModel)]="f.district_id" [disabled]="!canEdit()"><option [ngValue]="null">—</option>@for (x of districts(); track x.id) { <option [ngValue]="x.id">{{ x.name_bn }}</option> }</select></label>
            <label>আবেদনের প্রকার<input value="ভূমি অধিগ্রহণ" disabled /></label>
            <label class="inl"><input type="checkbox" [(ngModel)]="f.is_npp" [disabled]="!canEdit()" /> জাতীয় গুরুত্বপূর্ণ প্রকল্প (সময়সীমা সংক্ষিপ্ত)</label>
            <label class="w2">বিষয় (প্রকল্পের লক্ষ্য ও বিবরণ) *<textarea rows="4" [(ngModel)]="f.subject" [disabled]="!canEdit()"></textarea></label>
            <label class="inl w2"><input type="checkbox" [(ngModel)]="f.has_prior_acquisition" [disabled]="!canEdit()" /> পূর্বের কোনো অধিগ্রহণের সাথে সংশ্লিষ্টতা আছে কি?</label>
            @if (f.has_prior_acquisition) {
              <label>পূর্বের প্রকল্পের নাম<input [(ngModel)]="f.prior_project_name" [disabled]="!canEdit()" /></label>
              <label>পূর্বের এল.এ কেস নং<input [(ngModel)]="f.prior_la_case_no" [disabled]="!canEdit()" /></label>
            }
          </div>
          <h4>শর্তসাপেক্ষ প্রশ্ন (উত্তর অনুযায়ী প্রয়োজনীয় সংযুক্তি নির্ধারিত হয়)</h4>
          @for (k of flagKeys(); track k) {
            <div class="flagq"><span>{{ auth.meta()?.attachments.flags[k] }}</span>
              <label class="inl"><input type="radio" [name]="k" value="yes" [(ngModel)]="f.flags[k]" [disabled]="!canEdit()" /> হ্যাঁ</label>
              <label class="inl"><input type="radio" [name]="k" value="no" [(ngModel)]="f.flags[k]" [disabled]="!canEdit()" /> না</label></div>
          }
          @if (error()) { <p class="err">{{ error() }}</p> } @if (msg()) { <p class="ok">{{ msg() }}</p> }
          @if (canEdit()) { <button (click)="saveInfo()">সংরক্ষণ করুন</button> <button class="ghost" (click)="saveInfo(2)">সংরক্ষণ করে পরবর্তী ধাপ →</button> }
          <h4>এই ধাপের সংযুক্তি</h4>
          <app-slots [appId]="d.application.id" [items]="items(1)" [attachments]="d.attachments" [editable]="canEdit()" (changed)="load()" />
        </div>
      }
      @case (2) {
        <div class="panel"><h3>জমি ও মালিকানার তথ্য</h3>
          @if (!d.application.district_id) { <p class="warn">প্রথমে ধাপ ১-এ জেলা নির্বাচন করে সংরক্ষণ করুন।</p> }
          <table><tr><th>উপজেলা</th><th>মৌজা</th><th>জরিপ</th><th>জেএল</th><th>খতিয়ান</th><th>দাগ</th><th>রেকর্ডীয়/ব্যবহার শ্রেণি</th><th>মোট (একর)</th><th>প্রস্তাবিত (একর)</th><th>মালিক</th><th></th></tr>
            @for (l of d.lands; track l.id) {
              <tr><td>{{ l.upazila?.name_bn }}</td><td>{{ l.mouza?.name_bn }}</td><td>{{ l.survey_name }}</td><td>{{ l.jl_no }}</td><td>{{ l.khatian_no }}</td><td>{{ l.dag_no }}</td>
                <td>{{ l.record_class?.name_bn }} / {{ l.usage_class?.name_bn }}</td><td>{{ l.total_area_acre }}</td><td>{{ l.proposed_area_acre }}</td><td>{{ ownerNames(l) }}</td>
                <td>@if (canEdit()) { <a class="lnk" (click)="editLand(l)">সংশোধন</a> <a class="lnk bad" (click)="delLand(l)">মুছুন</a> }</td></tr>
            } @empty { <tr><td colspan="11" class="muted">কোনো দাগ যোগ করা হয়নি</td></tr> }
          </table>
          <p>মোট প্রস্তাবিত ভূমি: <b>{{ d.application.total_area_acre }}</b> একর @if (+d.application.total_area_acre > 16.5) { <span class="badge cond">৫০ বিঘার ঊর্ধ্বে — সিদ্ধান্ত ভূমি মন্ত্রণালয়ের</span> }</p>
          @if (canEdit() && d.application.district_id) { <button (click)="editLand(null)">➕ দাগ যোগ করুন</button> }
          @if (error()) { <p class="err">{{ error() }}</p> }
          <h4>ভূমি ও নকশা সংক্রান্ত সংযুক্তি</h4>
          <app-slots [appId]="d.application.id" [items]="items(2)" [attachments]="d.attachments" [editable]="canEdit()" (changed)="load()" />
        </div>
      }
      @case (3) { <div class="panel"><h3>অনুমোদন ও ছাড়পত্র</h3><p class="muted">মেয়াদোত্তীর্ণ অনাপত্তি/ছাড়পত্র দিয়ে আবেদন দাখিল করা যাবে না — মেয়াদ থাকলে তারিখ দিন।</p>
        <app-slots [appId]="d.application.id" [items]="items(3)" [attachments]="d.attachments" [editable]="canEdit()" (changed)="load()" /></div> }
      @case (4) { <div class="panel"><h3>আর্থিক ও অঙ্গীকারপত্র</h3>
        <app-slots [appId]="d.application.id" [items]="items(4)" [attachments]="d.attachments" [editable]="canEdit()" (changed)="load()" /></div> }
      @case (5) {
        <div class="panel"><h3>রিভিউ ও দাখিল</h3>
          <p>প্রকল্প: <b>{{ d.application.project_name_bn }}</b><br>প্রত্যাশী সংস্থা: {{ d.application.body?.name_bn }} ({{ auth.meta()?.body_types[d.application.body_type] }})<br>
            দাগ: {{ d.lands.length }}টি, মোট প্রস্তাবিত ভূমি: {{ d.application.total_area_acre }} একর, বাধ্যতামূলক সংযুক্তি জমা: {{ uploaded() }} / {{ requiredCount() }}</p>
          @if (errors().length) { <div class="warn"><b>দাখিলের আগে নিচের বিষয়গুলো সংশোধন করুন:</b><ul>@for (e of errors(); track e) { <li>{{ e }}</li> }</ul></div> } @else if (checked()) { <p class="ok">✔ সব আবশ্যক তথ্য ও সংযুক্তি আছে — আবেদন দাখিল করা যাবে।</p> }
          @if (error()) { <p class="err">{{ error() }}</p> }
          <button class="ghost" (click)="check()">যাচাই করুন</button>
          @if (d.application.status === 'draft') { <button (click)="submit()">আবেদন সম্পন্ন এবং দাখিল করুন</button> }
        </div>
      }
    }
    @if (editing() !== undefined) { <app-land-editor [appId]="d.application.id" [districtId]="d.application.district_id" [land]="editing()" (saved)="editing.set(undefined); load()" (closed)="editing.set(undefined)" /> }
  } @else if (id()) { <p>লোড হচ্ছে…</p> }`,
})
export class ApplicationWizardComponent implements OnInit {
  id = input<string>();
  auth = inject(Auth); private api = inject(Api); private router = inject(Router);
  steps: [number, string][] = [[1, 'প্রকল্পের তথ্য'], [2, 'ভূমি ও নকশা'], [3, 'অনুমোদন ও ছাড়পত্র'], [4, 'আর্থিক ও অঙ্গীকারপত্র'], [5, 'রিভিউ ও সাবমিট']];
  data = signal<any>(null); step = signal(1); error = signal(''); msg = signal(''); errors = signal<string[]>([]); checked = signal(false);
  editing = signal<any>(undefined); f: any = { flags: {} }; div: number | null = null;
  app = computed(() => this.data()?.application);
  canEdit = computed(() => ['draft', 'needs_refinement'].includes(this.app()?.status));
  ngOnInit() { if (this.id()) this.load(); }
  load() {
    this.api.get(`applications/${this.id()}`).subscribe((r: any) => {
      this.data.set(r); this.f = { ...r.application, flags: { ...(r.application.flags ?? {}) } };
      this.f.has_prior_acquisition = !!r.application.has_prior_acquisition; this.f.is_npp = !!r.application.is_npp;
    });
  }
  go(n: number) { this.step.set(n); this.error.set(''); this.msg.set(''); if (n === 5) this.check(); }
  districts() { const d = this.divisionOf(this.f.district_id) ?? this.div; return (this.auth.meta()?.districts ?? []).filter((x: any) => !d || x.division_id === d); }
  divisionOf(did: any) { return this.auth.meta()?.districts?.find((x: any) => x.id === did)?.division_id ?? this.div; }
  /** yes/no questions relevant for this applicant type */
  flagKeys() {
    const m = this.auth.meta()?.attachments; if (!m) return [];
    const list = this.app()?.body_type === 'private' ? m.private : m.govt;
    return Object.keys(m.flags).filter(k => list.some((i: any) => i.cond === k));
  }
  items(step: number) { return (this.data()?.checklist ?? []).filter((i: any) => i.step === step); }
  uploaded() { return (this.data()?.checklist ?? []).filter((i: any) => i.required && i.uploaded).length; }
  requiredCount() { return (this.data()?.checklist ?? []).filter((i: any) => i.required).length; }
  ownerNames(l: any) { return (l.owners ?? []).map((o: any) => o.name).join(', '); }
  create() {
    this.error.set('');
    this.api.post('applications', this.f).subscribe({ next: (a: any) => this.router.navigate(['/applications', a.id, 'edit']), error: e => this.error.set(errText(e)) });
  }
  saveInfo(next?: number) {
    this.error.set(''); this.msg.set('');
    const body = { ...this.f }; ['id', 'tracking_no', 'status', 'phase', 'body', 'district'].forEach(k => delete body[k]);
    this.api.put(`applications/${this.id()}`, body).subscribe({ next: () => { this.msg.set('সংরক্ষিত হয়েছে'); this.load(); if (next) this.go(next); }, error: e => this.error.set(errText(e)) });
  }
  editLand(l: any) { this.editing.set(l); }
  delLand(l: any) { if (confirm('দাগটি মুছে ফেলবেন?')) this.api.del(`lands/${l.id}`).subscribe({ next: () => this.load(), error: e => this.error.set(errText(e)) }); }
  check() { this.api.get(`applications/${this.id()}/readiness`).subscribe((r: any) => { this.errors.set(r.errors); this.checked.set(true); }); }
  submit() {
    this.error.set('');
    this.api.post(`applications/${this.id()}/submit`).subscribe({ next: (a: any) => this.router.navigate(['/applications', a.id]), error: e => { this.error.set(errText(e)); this.check(); } });
  }
}
