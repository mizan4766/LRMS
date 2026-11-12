import { Component, inject, signal } from '@angular/core';
import { RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { Api } from '../core/api.service';
import { Auth } from '../core/auth.service';

@Component({
  selector: 'app-shell', standalone: true, imports: [RouterOutlet, RouterLink, RouterLinkActive],
  template: `
  <div class="layout">
    <aside>
      <h1>LARMS</h1>
      <small class="sub">ভূমি অধিগ্রহণ ও হুকুমদখল ব্যবস্থাপনা</small>
      <a routerLink="/dashboard" routerLinkActive="on">ড্যাশবোর্ড</a>
      @if (!auth.is('citizen')) {
        <a routerLink="/tasks" routerLinkActive="on">আমার পদক্ষেপ @if (pending() > 0) { <span class="pill">{{ pending() }}</span> }</a>
        <a routerLink="/applications" routerLinkActive="on" [routerLinkActiveOptions]="{exact: true}">অধিগ্রহণ আবেদনসমূহ</a>
      }
      @if (auth.is('rb', 'dc', 'adc', 'lao', 'accounts', 'ministry', 'admin')) { <a routerLink="/payments" routerLinkActive="on">ক্ষতিপূরণ জমা ও পরিশোধ</a> }
      @if (auth.is('rb')) { <a routerLink="/applications/new" routerLinkActive="on">➕ নতুন আবেদন</a> }
      @if (auth.is('citizen')) { <a routerLink="/citizen" routerLinkActive="on">নোটিশ ও আবেদন</a> }
      @if (auth.is('admin')) { <a routerLink="/admin/users" routerLinkActive="on">ব্যবহারকারী</a> }
    </aside>
    <main>
      <header>
        <span><b>{{ auth.user()?.name_bn }}</b> <small>({{ auth.user()?.role_label }})</small></span>
        <span class="sp"></span>
        <button class="ghost" (click)="pw.set(!pw())">পাসওয়ার্ড পরিবর্তন</button>
        <button class="ghost" (click)="auth.logout()">লগআউট</button>
      </header>
      @if (pw()) {
        <div class="panel">
          <h3>পাসওয়ার্ড পরিবর্তন</h3>
          <div class="row"><input type="password" placeholder="বর্তমান পাসওয়ার্ড" [value]="cur" (input)="cur = val($event)" />
            <input type="password" placeholder="নতুন পাসওয়ার্ড (৮+ অক্ষর)" [value]="n1" (input)="n1 = val($event)" />
            <input type="password" placeholder="নতুন পাসওয়ার্ড আবার" [value]="n2" (input)="n2 = val($event)" />
            <button (click)="change()">সংরক্ষণ</button></div>
          @if (msg()) { <p [class]="ok() ? 'ok' : 'err'">{{ msg() }}</p> }
        </div>
      }
      <router-outlet />
    </main>
  </div>`,
})
export class ShellComponent {
  auth = inject(Auth); private api = inject(Api);
  pending = signal(0); pw = signal(false); msg = signal(''); ok = signal(false); cur = ''; n1 = ''; n2 = '';
  constructor() { if (!this.auth.is('citizen')) this.api.get('dashboard').subscribe({ next: (d: any) => this.pending.set(d.my_tasks), error: () => {} }); }
  val(e: Event) { return (e.target as HTMLInputElement).value; }
  change() {
    this.api.post('auth/password', { current: this.cur, password: this.n1, password_confirmation: this.n2 }).subscribe({
      next: () => { this.ok.set(true); this.msg.set('পাসওয়ার্ড পরিবর্তন হয়েছে'); this.cur = this.n1 = this.n2 = ''; },
      error: e => { this.ok.set(false); this.msg.set(e?.error?.error?.message ?? 'ত্রুটি'); },
    });
  }
}
