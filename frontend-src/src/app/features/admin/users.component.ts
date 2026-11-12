import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Api, errText } from '../../core/api.service';
import { Auth } from '../../core/auth.service';

@Component({
  selector: 'app-users', standalone: true, imports: [FormsModule],
  template: `
  <div class="bar"><h2>ব্যবহারকারী</h2><span class="sp"></span><button (click)="edit({ is_active: true, role: 'lao' })">➕ নতুন ব্যবহারকারী</button></div>
  <div class="row"><input [(ngModel)]="q" placeholder="নাম / ইউজার আইডি / মোবাইল" (keyup.enter)="load()" /><select [(ngModel)]="role" (change)="load()"><option value="">সব ভূমিকা</option>@for (r of roles(); track r[0]) { <option [value]="r[0]">{{ r[1] }}</option> }</select><button (click)="load()">খুঁজুন</button></div>
  <table><tr><th>ইউজার আইডি</th><th>নাম</th><th>ভূমিকা</th><th>উপজেলা</th><th>মোবাইল</th><th>সক্রিয়</th><th></th></tr>
    @for (u of rows(); track u.id) { <tr><td>{{ u.username }}</td><td>{{ u.name_bn }}</td><td>{{ auth.roleLabel(u.role) }}</td><td>{{ auth.upazila(u.upazila_id) }}</td><td>{{ u.mobile }}</td><td>{{ u.is_active ? '✔' : '' }}</td>
      <td><a class="lnk" (click)="edit(u)">সংশোধন</a> <a class="lnk" (click)="reset(u)">পাসওয়ার্ড রিসেট</a></td></tr> }</table>
  @if (f(); as u) {
    <div class="modal"><div class="dlg"><h3>{{ u.id ? 'ব্যবহারকারী সংশোধন' : 'নতুন ব্যবহারকারী' }}</h3>
      <div class="grid">
        <label>ইউজার আইডি *<input [(ngModel)]="u.username" /></label><label>নাম (বাংলা) *<input [(ngModel)]="u.name_bn" /></label>
        <label>ভূমিকা *<select [(ngModel)]="u.role">@for (r of roles(); track r[0]) { <option [value]="r[0]">{{ r[1] }}</option> }</select></label>
        <label>পদবি<input [(ngModel)]="u.designation" /></label><label>মোবাইল<input [(ngModel)]="u.mobile" /></label><label>ইমেইল<input [(ngModel)]="u.email" /></label><label>এনআইডি<input [(ngModel)]="u.nid" /></label>
        <label>জেলা<select [(ngModel)]="u.district_id"><option [ngValue]="null">—</option>@for (d of auth.meta()?.districts; track d.id) { <option [ngValue]="d.id">{{ d.name_bn }}</option> }</select></label>
        <label>উপজেলা<select [(ngModel)]="u.upazila_id"><option [ngValue]="null">—</option>@for (d of auth.meta()?.upazilas; track d.id) { <option [ngValue]="d.id">{{ d.name_bn }}</option> }</select></label>
        <label>প্রত্যাশী সংস্থা<select [(ngModel)]="u.requiring_body_id"><option [ngValue]="null">—</option>@for (b of auth.meta()?.bodies; track b.id) { <option [ngValue]="b.id">{{ b.name_bn }}</option> }</select></label>
        @if (!u.id) { <label>পাসওয়ার্ড (খালি = ডিফল্ট)<input [(ngModel)]="u.password" /></label> }
        <label class="inl"><input type="checkbox" [(ngModel)]="u.is_active" /> সক্রিয়</label></div>
      @if (error()) { <p class="err">{{ error() }}</p> }
      <div class="bar"><button (click)="save()">সংরক্ষণ</button><button class="ghost" (click)="f.set(null)">বাতিল</button></div></div></div>
  }`,
})
export class UsersComponent {
  auth = inject(Auth); private api = inject(Api);
  rows = signal<any[]>([]); f = signal<any>(null); error = signal(''); q = ''; role = '';
  roles = () => Object.entries(this.auth.meta()?.roles ?? {}) as [string, string][];
  constructor() { this.load(); }
  load() { this.api.get('admin/users', { q: this.q, role: this.role }).subscribe((r: any) => this.rows.set(r.data)); }
  edit(u: any) { this.error.set(''); this.f.set({ ...u }); }
  save() {
    const u = { ...this.f() }; const req = u.id ? this.api.put(`admin/users/${u.id}`, u) : this.api.post('admin/users', u);
    req.subscribe({ next: () => { this.f.set(null); this.load(); }, error: e => this.error.set(errText(e)) });
  }
  reset(u: any) { if (confirm(`${u.name_bn} এর পাসওয়ার্ড ডিফল্টে রিসেট করবেন?`)) this.api.post(`admin/users/${u.id}/reset-password`).subscribe(() => alert('পাসওয়ার্ড রিসেট হয়েছে')); }
}
