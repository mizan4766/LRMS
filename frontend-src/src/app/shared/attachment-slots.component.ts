import { Component, EventEmitter, Input, Output, inject, signal } from '@angular/core';
import { Api, errText } from '../core/api.service';
import { saveBlob } from './util';

/** The required attachments of one wizard section (list depends on the applicant type and the yes/no answers). */
@Component({
  selector: 'app-slots', standalone: true,
  template: `
  <table class="slots">
    <tr><th>নং</th><th>কাগজপত্র</th><th>ধরন</th><th>তফসিল/বিধি</th><th>ফাইল</th></tr>
    @for (it of items; track it.no) {
      <tr [class.dim]="!it.visible">
        <td>{{ it.no }}</td>
        <td>{{ it.title }}
          @if (it.template) { <br><a class="lnk" (click)="template(it)">⬇ তফসিল-{{ it.template }} এর ফরম</a> }
          @if (!it.visible) { <br><small class="muted">প্রযোজ্য নয় (উপরের প্রশ্নের উত্তর "না")</small> }</td>
        <td>@if (it.mode === 'required') { <span class="badge req">বাধ্যতামূলক</span> } @else if (it.mode === 'review') { <span class="badge rev">বাধ্যতামূলক (রিভিউ)</span> } @else { <span class="badge cond">শর্তসাপেক্ষ</span> }</td>
        <td><small>{{ it.tor }}</small></td>
        <td>
          @if (it.visible) {
            @if (att(it.no); as a) {
              <span class="ok">✔ {{ a.original_name }}</span>
              <a class="lnk" (click)="open(a)">দেখুন</a>
              @if (a.status === 'fix') { <span class="badge bad">সংশোধন প্রয়োজন: {{ a.note }}</span> }
              @if (a.expires_on) { <small>মেয়াদ: {{ a.expires_on }}</small> }
              @if (editable) { <a class="lnk bad" (click)="remove(a)">মুছুন</a> }
            }
            @if (editable) {
              <div class="up"><input type="file" #f (change)="pick(it, f)" />
                @if (it.step === 3) { <input type="date" #d title="মেয়াদোত্তীর্ণের তারিখ (ছাড়পত্র/অনাপত্তির জন্য)" [value]="exp[it.no] || ''" (input)="exp[it.no] = d.value" /> }</div>
            }
          } @else { <span class="muted">—</span> }
        </td>
      </tr>
    }
  </table>
  @if (error()) { <p class="err">{{ error() }}</p> }`,
})
export class AttachmentSlotsComponent {
  private api = inject(Api);
  @Input() appId!: number; @Input() items: any[] = []; @Input() attachments: any[] = []; @Input() editable = false;
  @Output() changed = new EventEmitter<void>();
  error = signal(''); exp: Record<number, string> = {};
  att(no: number) { return this.attachments.find(a => a.kind === 'proposal' && a.slot_no === no); }
  pick(it: any, input: HTMLInputElement) {
    const f = input.files?.[0]; if (!f) return;
    const fd = new FormData(); fd.append('kind', 'proposal'); fd.append('slot_no', String(it.no)); fd.append('title', it.title); fd.append('file', f);
    if (this.exp[it.no]) fd.append('expires_on', this.exp[it.no]);
    this.error.set('');
    this.api.post(`applications/${this.appId}/attachments`, fd).subscribe({ next: () => { input.value = ''; this.changed.emit(); }, error: e => this.error.set(errText(e)) });
  }
  remove(a: any) { this.api.del(`attachments/${a.id}`).subscribe({ next: () => this.changed.emit(), error: e => this.error.set(errText(e)) }); }
  open(a: any) { this.api.blob(`attachments/${a.id}/download`).subscribe(b => saveBlob(b, a.original_name)); }
  template(it: any) {
    const html = `<html><head><meta charset="utf-8"><title>তফসিল-${it.template}</title></head><body style="font-family:Arial;margin:40px"><h2>তফসিল-${it.template}</h2><h3>${it.title}</h3>
      <p>(বিধিমালা, ২০২৬ এর নির্ধারিত ফরম এখানে প্রতিস্থাপন করুন — তথ্য পূরণ করে স্বাক্ষরসহ স্ক্যান করে আপলোড করুন)</p>
      <p>প্রকল্পের নাম: ..............................................</p><p>প্রত্যাশী সংস্থা: ..............................................</p><p>তারিখ: ............................</p><p style="margin-top:80px">স্বাক্ষর ও সিল</p></body></html>`;
    saveBlob(new Blob([html], { type: 'text/html;charset=utf-8' }), `tafsil-${it.template}.html`);
  }
}
