# Course Assignment System

A web application that assigns courses to teachers, tracks the sessions actually taught and pays teachers for the hours they taught.

**Technologies:** HTML, CSS, Bootstrap 5 and Bootstrap Icons (files copied into the project), plain JavaScript, PHP and MySQL through **MySQLi**, running on XAMPP or WAMP.
No PHP or JS framework, no npm, no Composer, no PDO.

## Installation (10 minutes)

1. **Start** Apache and MySQL in XAMPP (or WAMP).
2. **Copy** the `course-assignment` folder into:
   - XAMPP: `C:\xampp\htdocs\`
   - WAMP: `C:\wamp64\www\`
3. **Create the database**: open <http://localhost/phpmyadmin>, go to the **Import** tab, choose `database/database.sql` and click **Import**.
   The script creates the `course_assignment` database, its 10 tables and the test data.
4. **Open** <http://localhost/course-assignment/>.

If MySQL uses a password or another port, edit `config/db.php`.

## Test accounts

Password for every account: **Passe1234**

| Role | Email | What you can try |
| --- | --- | --- |
| Administrator | admin@cas.local | Reference data, assignments, approving heads' sessions, reports |
| Head of department (CSC) | tchoua@cas.local | Assignments and session approval for Computer Science, plus their own teaching |
| Teacher | essomba@cas.local | Declare sessions, follow workload and payments |
| Accountant | accountant@cas.local | Record a payment, printable statements, CSV export |

Other heads: mbarga@cas.local (MTH), fouda@cas.local (MGT). Other teachers: nkoulou, kamga, owona, djoko, abena, ndjock, ewane, tamba, moussa (`@cas.local`).

On the login page, open *Try the demo accounts*: one click fills in the email and the password.

## Creating an account

A new teacher clicks **Create my teacher account** on the login page (`register.php`) and fills in three short parts: who they are, where they teach, a password.

1. The account is created **deactivated** (`is_active = 0`) with the `teacher` role, and the request is written in `activity_log` (action `Sign-up request`).
2. While the request waits, logging in shows *“Your account request has been received…”*.
3. The administrator sees the request at the top of the **Home** page (and on *Settings → Users*) and clicks **Accept** or **Refuse**.
   - Accept: the account is activated and the log entry becomes `Sign-up approved`.
   - Refuse: the request is deleted (it has no course, session or payment yet).

Only teachers can sign up this way. Heads of department, accountants and administrators get their account from the administrator. No change to the database is needed: the feature uses the existing `users.is_active` column and the `activity_log` table.

## Demo scenario

0. **Sign-up**: on the login page, *Create my teacher account*, then log in as admin and click *Accept* on the Home page.
1. **Admin**: *Assignments → New assignment*: assign CSC203 TP, 10 h, to Kamga Boris. The form shows the hours still available and the teacher's workload live.
2. **Teacher** (essomba): *I taught a class*: declare a class for today.
3. **Head** (tchoua): *Classes to approve*: approve the class (or reject it with a reason).
4. **Accountant**: *Pay a teacher*: choose Essomba, tick the classes, save. The amount is computed from the hourly rate grid.
5. **Teacher**: *My money*: the payment and the balance still to receive are shown.

## Designed for people who are not at ease with computers

- Every home page starts with **“What would you like to do?”**: three or four big buttons with a short sentence each.
- A **Help** button (top right, on every page) explains the role in plain words, with a small glossary (CM, TD, TP, workload…).
- **How does it work?** boxes give the steps of each job (open the first time a teacher has nothing declared yet).
- Plain words: *I taught a class*, *My money*, *Waiting for approval*; every status has an icon and a colour; CM, TD and TP always show their meaning (*CM · Lecture*).
- Large text (17 px), large buttons and fields, a *Show* button on password fields.
- Confirmations open in a clear window (*No, go back* / *Yes, continue*) instead of the browser's small box.
- A short burst of confetti after a successful action (switched off when the device asks for reduced motion).

## Structure

```
course-assignment/
├── index.php, logout.php        login / logout
├── register.php                 teacher sign-up (account waits for the administrator)
├── config/db.php                single MySQLi connection + settings
├── includes/
│   ├── functions.php            MySQLi prepared statements, security, business calculations (workload, amount due…)
│   ├── auth.php                 session and role checks
│   ├── header.php, footer.php   Bootstrap layout, role-based menu
│   └── views/                   shared blocks (assignment list, teacher table, account requests)
├── admin/                       dashboard, users, departments, courses, years, rates, assignments, reports
├── head/                        head of department: dashboard, assignments, session approval
├── teacher/                     my courses and workload, my sessions, my payments, profile
├── accountant/                  dashboard, payments (+ CSV), new payment, printable statements
├── api/                         JSON called with fetch(): workload, course search, amount due
├── assets/
│   ├── css/style.css, print.css
│   ├── js/main.js, validation.js, assignments.js, sessions.js, payments.js
│   └── vendor/                  Bootstrap 5.3.3 (CSS + JS) and Bootstrap Icons 1.11.3, copied locally, no CDN
└── database/database.sql        10 tables + test data
```

## Database

| Table | Content |
| --- | --- |
| `users` | accounts (roles `admin`, `head`, `teacher`, `accountant`), `academic_rank`, `max_load` |
| `departments` | departments and their head (`head_id`) |
| `academic_years` | academic years, only one with `is_active = 1` |
| `courses` | courses with `cm_hours`, `td_hours`, `tp_hours` (CM = lecture, TD = tutorial, TP = lab) |
| `assignments` | who teaches what: course, teacher, year, type (CM, TD, TP), hours |
| `class_sessions` | sessions taught: status `declared`, `approved` or `rejected`, linked payment |
| `hourly_rates` | hourly rate by academic rank and type |
| `payments` | payments made to teachers (`cash`, `bank_transfer`, `mobile_money`, `cheque`) |
| `preferences` | teachers' course wishes (bonus) |
| `activity_log` | audit trail of actions |

## Database access with MySQLi

Every query goes through the functions in `includes/functions.php`, which use prepared statements:

```php
$courses = db_all('SELECT * FROM courses WHERE department_id = ? AND level = ?', 'is', [$departmentId, 'L1']);
$user    = db_one('SELECT * FROM users WHERE email = ?', 's', [$email]);
db_exec('UPDATE class_sessions SET status = ? WHERE id = ?', 'si', ['approved', $id]);
```

The type letters follow `bind_param`: `i` integer, `d` decimal, `s` string. Never concatenate a variable into an SQL query.

## Business rules

| Rule | Where |
| --- | --- |
| BR1 unique email and course code | `admin/user_form.php`, `admin/course_form.php` + UNIQUE constraints |
| BR2 deactivated account blocked (also: a sign-up waits for approval) | `index.php`, `current_user()`, `register.php` |
| BR3 to BR7, BR9 assignments | `validate_assignment()` in `includes/functions.php` |
| BR6 workload override by the admin, with a reason | `admin/assignment_form.php` |
| BR8 a course or teacher in use is never deleted | `admin/courses.php`, `admin/users.php` (deactivation) |
| BR10 activity log | `log_action()`, shown on the admin dashboard |
| BR11 sessions only on your own assignments, never in the future | `teacher/sessions.php` |
| BR12 declared and approved hours ≤ assigned hours | `teacher/sessions.php`, `head/sessions_validation.php` |
| BR13 only approved sessions count | `teachers_overview()`, `unpaid_sessions()` |
| BR14 a paid session cannot be edited | `teacher/sessions.php` |
| BR15 payment ≤ balance due | `accountant/payment_form.php` (the amount comes from the ticked sessions) |

Security: `password_hash()` / `password_verify()`, `htmlspecialchars()` on every output (`e()`), CSRF token on every form, `session_regenerate_id()` at login, role checked on the server on every page.

## Bonus features still to do

- F2: lock the account after 5 failed logins (password change is done, in *My profile*).
- F11: assignment status (proposed, accepted, disputed): the `assignments.status` column already exists.
- F12: teachers' course preferences: the `preferences` table already exists, with some data.
- F24: in-app notifications.
- F25: import courses or teachers from a CSV file.

## For the demo

Keep `APP_DEBUG` set to `false` in `config/db.php`: no raw PHP error is shown. Set it to `true` while developing.
To go back to the initial data, import `database/database.sql` again (it drops and recreates the database).
