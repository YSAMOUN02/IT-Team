# TEAMIT Cambodia (Laravel)

Laravel 12 rebuild of the WordPress site teamitcambodia.com: home page, courses,
free tutorials, news, blog, about, contact (with a working contact form), search
and an admin panel to edit all of it.

News items are added in the admin under **Content → News** and appear on `/news`
and in the "Latest news" block on the home page. Dates use Phnom Penh time
(`APP_TIMEZONE` in `.env`); a publish date in the future schedules the item.

## Run it locally

```bash
composer install
cp .env.example .env          # then set ADMIN_EMAIL / ADMIN_PASSWORD
php artisan key:generate
php artisan migrate --seed    # creates the admin user and empty pages
php artisan storage:link
php artisan wp:import         # copies the content + images from the WordPress site
php artisan serve --port=8124
```

Open http://127.0.0.1:8124 — admin at http://127.0.0.1:8124/admin
(login = `ADMIN_EMAIL` / `ADMIN_PASSWORD` from `.env`).

The database is **MySQL / MariaDB** (XAMPP), database `it_team`, viewable in
phpMyAdmin at http://localhost/phpmyadmin. Before the first `migrate`, create it with
`CREATE DATABASE it_team CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`
(utf8mb4 is needed for Khmer). Start MySQL in the XAMPP Control Panel before the site.
`database/database.sqlite` is the SQLite copy the site used before 27 Oct 2026, kept as a backup.

If XAMPP's MySQL stops with "MySQL shutdown unexpectedly" and its log ends right
after "Server socket created", `C:\xampp\mysql\data\multi-master.info` has been
filled with log text. Stop MySQL, move that file and the `master-*.info` /
`mysql-relay-bin*` files out of `data`, then start again (databases are not affected).

## Importing from WordPress

`php artisan wp:import` reads the WordPress REST API and the rendered pages and
fills the database:

| WordPress                              | Laravel                          |
|----------------------------------------|----------------------------------|
| Services (`tdcpt_service`)             | Courses (`/courses/{slug}`)      |
| Locations (`tdcpt_location`)           | Tutorials (`/tutorials/{slug}`)  |
| Posts                                  | Blog (`/blog/{slug}`)            |
| Testimonials (`tdcpt_testimonial`)     | Testimonials (home page)         |
| Home / About / Contact / Tutorials pages | Pages (editable sections)      |
| Partner logos on the home page         | Partners                         |
| Images in `wp-content/uploads`         | `storage/app/public/wp/...`      |

- Responses are cached in `storage/app/wp-import`; `--refresh` downloads again.
- The old server is slow, so images download in parallel and resume if a
  connection drops. If some don't finish, run the command again.
- Running it again updates imported records (it overwrites admin edits to them).
- Old WordPress links (`/tdcpt_service/...`, `/tdcpt_location/...`,
  `/2025/07/14/...`) redirect (301) to the new addresses.

## Online courses (Cisco NetAcad-style)

- **Course builder**: Admin → Courses → *Curriculum*. A course has modules; a
  module holds lessons (video link or MP4, notes, practice workbook + solution)
  and tests: *Practice* (instant feedback), *Module quiz*, *Mock exam* (timed)
  and *Final exam* (timed, limited attempts). Lessons/tests can be free previews.
- **Completion**: every lesson done + every module quiz and final exam passed →
  a printable certificate with a public verification link (`/certificates/{code}`).
- **Students** register at `/register`, buy at `/courses/{slug}/checkout`, learn
  at `/my/courses`. Admin → Students can also give access by hand (cash payment).
- Sample curriculum: `php artisan db:seed --class=SampleCurriculumSeeder`
  (only adds to a course that has no curriculum yet).
- **Pivot Table & PivotChart** (a complete course): built from the Word manual and
  practice workbook in `example/` with
  `php artisan db:seed --class=PivotTableCourseSeeder`: 7 modules, 19 lessons
  with the manual's screenshots, practice workbook, module tests, mock and final
  exam. It does nothing if the course already exists (delete it in the admin to
  import again). `App\Support\DocxReader` does the Word → lesson HTML part.
- Lesson videos and practice files are stored privately (`storage/app/private`)
  and served only to students with access.

### Writing lessons (the editor)

Lesson notes, articles, pages and course descriptions use TinyMCE 7 (open-source
GPL build, loaded from jsDelivr). The editing area uses the site's own CSS, so
text looks the way students see it.

- **Screenshots**: paste them straight in (Win+Shift+S, then Ctrl+V), drop or
  pick them. They upload automatically, and anything wider than 1600 px is scaled down.
- **Insert block**: tip / note / warning boxes, numbered steps, exercise box,
  answer hidden until clicked, two screenshots side by side, keyboard shortcut
  (`<kbd>`), Excel formula, divider. The same boxes are under Format → Boxes.
- **Word**: imports a .docx at the cursor, with its pictures (same converter
  as the Pivot Table course).
- File → Restore last draft brings back text that was never saved.

### Buying

Prices (access periods, discounts) are shown on each course page; there is no
separate pricing page (`/pricing` redirects to `/courses`). A visitor
who is not signed in and clicks Buy (or Enrol free) sees a "one step left" page:
their course and plan on one side, *Create account* / *Sign in* / *Continue with
Google* on the other. After signing in they land on the checkout with the same plan.

### Continue with Google

1. Google Cloud Console → *APIs & Services* → *OAuth consent screen*: app name
   "TEAMIT Cambodia", support email, scopes `email` and `profile`, publish it.
2. *Credentials* → *Create credentials* → *OAuth client ID* → *Web application*.
   Authorised redirect URIs: `https://YOUR-DOMAIN/auth/google/callback` (and
   `http://127.0.0.1:8124/auth/google/callback` for testing on this PC).
   Google does not accept LAN addresses such as 192.168.x.x.
3. Put the two values in `.env`: `GOOGLE_CLIENT_ID=…` and `GOOGLE_CLIENT_SECRET=…`.

The button appears on sign-in, register and the "one step left" page as soon as
both values are set. A Google account with the same verified email as an
existing student signs into that student account. Admin accounts can never sign
in with Google. Students who joined with Google can set a password later in
*My profile*.

### Who can see paid content

- Lessons, files, tests and the course home need a paid, unexpired enrollment
  (or a free course). Free-preview lessons are open, except their solution file.
  A pending, failed or refunded order gives nothing.
- **Refunds**: Admin → Orders → *Mark as refunded* (or PayWay reporting a refund)
  takes back the access that order bought; access from other paid orders stays.
- **Shared accounts**: a student can be signed in on 2 devices at once (Admin →
  Settings → Student accounts; 0 = no limit). Signing in on one more device signs
  out the oldest. Admin → Students → *Sign out everywhere* clears them all.
- Paid pages are sent with `Cache-Control: no-store`, so Back after signing out
  does not show them. Videos show the student's email as a moving watermark.
- Lesson screenshots live in public storage under random names: hard to guess,
  but a paying student could share an image link.

## Payments (ABA PayWay)

1. Register a sandbox account: https://sandbox.payway.com.kh/register-sandbox/
2. Put the merchant ID and API key in `.env`:
   `PAYWAY_MERCHANT_ID=…`, `PAYWAY_API_KEY=…`
   (`PAYWAY_BASE_URL` stays on the sandbox until ABA gives you production keys/URL).
3. Give ABA your callback URL: `https://YOUR-DOMAIN/payway/pushback`.

The buyer pays on PayWay's own page (cards / ABA KHQR); the site never sees card
details. Orders are only marked paid after the site asks PayWay's Check
Transaction API, and a payment with the wrong amount is flagged *Needs review*.
Without keys, local development shows a **test payment page** instead. It only
runs when `APP_URL` is this computer and the browser address is this computer or
the office network (localhost, 127.0.0.1, 192.168.x.x, *.test), and never in
production, so a live site with missing keys cannot be "paid" with the test
button. Course pages always show the periods and prices; if online payment is
not available, checkout says so. Receipt emails need real mail settings
(`MAIL_*` in `.env`).

## Ads, visitors, newsletter

- Admin → *Ads*: six ad spots. Each shows either **your own banner image**
  (upload, optional phone-size image, link, description) or **Google AdSense**
  (slot ID or pasted code). Banner links go through `/go/{spot}`, which counts
  the click; the counts show on the Ads page. A spot that is off outputs nothing;
  unfilled Google ads hide themselves. `/ads.txt` is generated from the publisher ID.
- Admin → *Visitors*: the site's own statistics (no Google Analytics). Visitors,
  visits, page views, time on page, busiest hours, where people came from
  (Facebook, Telegram, Google…), devices, which courses they open and click Buy
  on, and each visitor's path through the site. Admins and bots are not counted
  and no IP addresses are stored. Data: `page_views` table, filled by the small
  tracker at the end of `public/js/site.js`.
- Footer newsletter sign-ups: Admin → *Subscribers* (CSV export).

## Tests

`php artisan test` covers PayWay signing/verification, access rules and quiz grading.

## Where things are

- Page section fields: `config/page_templates.php`
- Site settings fields (logo, contact details, footer): `config/site_settings.php`
- Main menu: `config/navigation.php`
- Interface text (Khmer / English): `lang/km/site.php`, `lang/en/site.php`
- Styles: `public/css/site.css` (site), `public/css/admin.css` (admin)
- Admin behaviour (confirm dialog, drop zones, Ctrl+S, chart tooltips): `public/js/admin.js`
- Tutorials, news and blog posts share the `articles` table (`type` column);
  `App\Models\Article::TYPES` lists them
- Importer: `app/Console/Commands/ImportWordPress.php`
