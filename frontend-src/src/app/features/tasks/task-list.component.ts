import { DatePipe } from '@angular/common';
import { Component, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { Api, bnDate } from '../../core/api.service';

@Component({
  selector: 'app-task-list', standalone: true, imports: [RouterLink, DatePipe],
  template: `
  <h2>আমার পদক্ষেপ</h2>
  <div class="tabs"><button [class.on]="tab() === 'todo'" (click)="tab.set('todo')">করণীয় ({{ todo().length }})</button><button [class.on]="tab() === 'done'" (click)="tab.set('done'); loadDone()">সম্পন্ন</button></div>
  @if (tab() === 'todo') {
    <table><tr><th>ধাপ</th><th>কাজ</th><th>আবেদন</th><th>এল.এ কেস</th><th>শেষ সময়</th><th></th></tr>
      @for (t of todo(); track t.id) {
        <tr [class.late]="t.overdue"><td>{{ t.step_no }}</td><td>{{ t.label }}<br><small class="muted">{{ t.phase }}</small></td><td>{{ t.application.tracking_no }}<br><small>{{ t.application.project_name_bn }}</small></td><td>{{ t.application.la_case_no }}</td>
          <td>{{ t.due_at ? bn(t.due_at) : '—' }} @if (t.overdue) { <span class="badge bad">অতিক্রান্ত</span> }</td><td><a class="btn" [routerLink]="['/tasks', t.id]">কাজ করুন</a></td></tr>
      } @empty { <tr><td colspan="6" class="muted">কোনো অপেক্ষমাণ পদক্ষেপ নেই</td></tr> }
    </table>
  } @else {
    <table><tr><th>ধাপ</th><th>কাজ</th><th>আবেদন</th><th>কার্যক্রম</th><th>সময়</th><th></th></tr>
      @for (t of done(); track t.id) { <tr><td>{{ t.step_no }}</td><td>{{ t.label }}</td><td>{{ t.application.tracking_no }}</td><td>{{ t.action }}</td><td>{{ t.completed_at | date:'dd/MM/yyyy HH:mm' }}</td><td><a class="lnk" [routerLink]="['/tasks', t.id]">দেখুন</a></td></tr> }
    </table>
  }`,
})
export class TaskListComponent {
  private api = inject(Api);
  todo = signal<any[]>([]); done = signal<any[]>([]); tab = signal('todo');
  constructor() { this.api.get('tasks').subscribe((r: any) => this.todo.set(r.tasks)); }
  loadDone() { this.api.get('tasks/history').subscribe((r: any) => this.done.set(r)); }
  bn(v: any) { return bnDate(v); }
}
