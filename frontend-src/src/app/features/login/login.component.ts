import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { errText } from '../../core/api.service';
import { Auth } from '../../core/auth.service';

@Component({
  selector: 'app-login', standalone: true, imports: [FormsModule],
  template: `
  <div class="login">
    <h2>ভূমি অধিগ্রহণ ও হুকুমদখল ব্যবস্থাপনা</h2>
    <p class="muted">স্থাবর সম্পত্তি অধিগ্রহণ ও হুকুমদখল আইন, ২০১৭ ও বিধিমালা, ২০২৬ অনুযায়ী</p>
    <input [(ngModel)]="login" placeholder="ইউজার আইডি / ইমেইল / মোবাইল" autocomplete="username" />
    <input [(ngModel)]="password" type="password" placeholder="পাসওয়ার্ড" (keyup.enter)="go()" autocomplete="current-password" />
    <button (click)="go()" [disabled]="busy()">লগইন</button>
    @if (error()) { <p class="err">{{ error() }}</p> }
  </div>`,
})
export class LoginComponent {
  private auth = inject(Auth); private router = inject(Router);
  login = ''; password = ''; busy = signal(false); error = signal('');
  go() {
    this.busy.set(true); this.error.set('');
    this.auth.login(this.login, this.password).subscribe({
      next: async () => { await this.auth.load(); this.busy.set(false); this.router.navigate([this.auth.is('citizen') ? '/citizen' : '/dashboard']); },
      error: e => { this.busy.set(false); this.error.set(errText(e)); },
    });
  }
}
