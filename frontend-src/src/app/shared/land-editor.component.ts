import { Component, EventEmitter, Input, OnInit, Output, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Api, errText } from '../core/api.service';
import { Auth } from '../core/auth.service';

/** Add/edit one land (dag) with its owners — wizard step 2 (Step 1.3 of the workflow script). */
@Component({
  selector: 'app-land-editor', standalone: true, imports: [FormsModule],
  template: `
  <div class="modal"><div class="dlg">
    <h3>{{ land?.id ? 'দাগ সংশোধন' : 'নতুন দাগ' }}</h3>
    <div class="grid">
      <label>উপজেলা/রাজস্ব সার্কেল *<select [(ngModel)]="f.upazila_id" (ngModelChange)="loadMouza()">@for (u of upazilas(); track u.id) { <option [ngValue]="u.id">{{ u.name_bn }}</option> }</select></label>
      <label>মৌজা<select [(ngModel)]="f.mouza_id" (ngModelChange)="setJl()"><option [ngValue]="null">—</option>@for (m of mouzas(); track m.id) { <option [ngValue]="m.id">{{ m.name_bn }}</option> }</select></label>
      <label>সর্বশেষ জরিপের নাম<select [(ngModel)]="f.survey_name">@for (s of auth.meta()?.survey_names; track s) { <option [value]="s">{{ s }}</option> }</select></label>
      <label>জে.এল. নম্বর<input [(ngModel)]="f.jl_no" /></label>
      <label>খতিয়ান নং *<input [(ngModel)]="f.khatian_no" /></label>
      <label>দাগ নং *<input [(ngModel)]="f.dag_no" /></label>
      <label>রেকর্ডীয় শ্রেণি<select [(ngModel)]="f.record_class_id"><option [ngValue]="null">—</option>@for (c of auth.meta()?.land_classes; track c.id) { <option [ngValue]="c.id">{{ c.name_bn }}</option> }</select></label>
      <label>ব্যবহারভিত্তিক শ্রেণি<select [(ngModel)]="f.usage_class_id"><option [ngValue]="null">—</option>@for (c of auth.meta()?.land_classes; track c.id) { <option [ngValue]="c.id">{{ c.name_bn }}</option> }</select></label>
      <label>দাগে মোট ভূমির পরিমাণ (একর) *<input type="number" step="0.0001" [(ngModel)]="f.total_area_acre" /></label>
      <label>অধিগ্রহণের জন্য প্রস্তাবিত পরিমাণ (একর) *<input type="number" step="0.0001" [(ngModel)]="f.proposed_area_acre" /></label>
    </div>
    <h4>নামজারি অনুযায়ী ভূমি মালিকগণ</h4>
    @for (o of f.owners; track $index) {
      <div class="owner">
        <input [(ngModel)]="o.name" placeholder="নাম *" /><input [(ngModel)]="o.nid" placeholder="জাতীয় পরিচয়পত্র নং" /><input [(ngModel)]="o.mobile" placeholder="মোবাইল" />
        <input [(ngModel)]="o.email" placeholder="ইমেইল (ঐচ্ছিক)" /><input [(ngModel)]="o.address" placeholder="ঠিকানা" /><input type="number" step="0.0001" min="0" max="1" [(ngModel)]="o.share" placeholder="অংশ (০–১)" />
        <label class="inl"><input type="checkbox" [(ngModel)]="o.mutation_done" /> নামজারি সম্পন্ন</label>
        @if (!o.mutation_done) { <input [(ngModel)]="o.extra.dead_owner" placeholder="মৃত মালিকের নাম" /><input [(ngModel)]="o.extra.mutation_case_no" placeholder="নামজারি মামলা নং" /><input [(ngModel)]="o.extra.heirs" placeholder="ওয়ারিশ/ক্রেতাগণের নাম ও ঠিকানা" /> }
        <button class="ghost sm" (click)="f.owners.splice($index, 1)">✖</button>
      </div>
    }
    <button class="ghost" (click)="addOwner()">➕ মালিক যোগ করুন</button>
    @if (error()) { <p class="err">{{ error() }}</p> }
    <div class="bar"><button (click)="save()">সংরক্ষণ</button><button class="ghost" (click)="closed.emit()">বাতিল</button></div>
  </div></div>`,
})
export class LandEditorComponent implements OnInit {
  auth = inject(Auth); private api = inject(Api);
  @Input() appId!: number; @Input() districtId: any; @Input() land: any = null;
  @Output() saved = new EventEmitter<void>(); @Output() closed = new EventEmitter<void>();
  f: any = { survey_name: 'MUTATION', owners: [] }; mouzas = signal<any[]>([]); error = signal('');
  upazilas = () => (this.auth.meta()?.upazilas ?? []).filter((u: any) => u.district_id === +this.districtId);
  ngOnInit() {
    if (this.land) {
      this.f = JSON.parse(JSON.stringify(this.land)); delete this.f.upazila; delete this.f.mouza; delete this.f.record_class; delete this.f.usage_class;
      this.f.owners = (this.land.owners ?? []).map((o: any) => ({ ...o, extra: o.extra ?? {}, mutation_done: !!o.mutation_done }));
      this.loadMouza();
    } else this.addOwner();
  }
  addOwner() { this.f.owners.push({ name: '', mutation_done: true, extra: {} }); }
  loadMouza() { if (this.f.upazila_id) this.api.get('lookups/mouzas', { upazila_id: this.f.upazila_id }).subscribe((m: any) => this.mouzas.set(m)); }
  setJl() { const m = this.mouzas().find(x => x.id === this.f.mouza_id); if (m?.jl_no && !this.f.jl_no) this.f.jl_no = m.jl_no; }
  save() {
    this.error.set('');
    const body = { ...this.f, owners: this.f.owners.map((o: any) => ({ ...o, share: o.share === '' ? null : o.share })) };
    const req = this.land?.id ? this.api.put(`lands/${this.land.id}`, body) : this.api.post(`applications/${this.appId}/lands`, body);
    req.subscribe({ next: () => this.saved.emit(), error: e => this.error.set(errText(e)) });
  }
}
