"""
Help & Guides screenshots (help guides Phase 2). Dev tool only; the app never runs it.

Retakes public/images/guides/*.png: light mode, 1366 px wide, numbered marks drawn
on the real elements, each number matching a step of the task the image belongs to
(see the 'images' entries in app/Support/Guides/Guides.php).

Needs: Python 3, `pip install playwright pillow`, `playwright install chromium`.

1. On a LOCAL copy (never production), seed the demo data:
       php artisan db:seed --class=DemoDataResetSeeder
   Seed and shoot early in the afternoon: the Appointments shot needs bookings whose
   start time has passed today (Process buttons), and later in the evening today's
   bookings are already finished.
2. Run the app:  php artisan serve      (and `npm run build`)
3. Run:          DEMO_SEED_PASSWORD=... python tools/guide-screenshots/shoot.py [names]
   With names (e.g. `schedule staff`) only those images are retaken.

Accounts are the "Serenity Wellness Spa (Demo)" showcase spa from DemoDataSeeder.
If a page's layout changes, adjust its block below (the selectors) and retake.
"""
import io, os, sys, re
from pathlib import Path

from PIL import Image
from playwright.sync_api import sync_playwright
from PIL import Image

B = os.environ.get('GUIDE_BASE_URL', 'http://127.0.0.1:8000')
PW = os.environ.get('DEMO_SEED_PASSWORD', '')
OUT = str(Path(__file__).resolve().parents[2] / 'public' / 'images' / 'guides')

MARK_JS = """
([boxes]) => {
  document.querySelectorAll('.guide-mark').forEach(e => e.remove());
  for (const [x, y, w, h, n, pad] of boxes) {
    const box = document.createElement('div');
    box.className = 'guide-mark';
    Object.assign(box.style, {position:'fixed', left:(x-pad)+'px', top:(y-pad)+'px', width:(w+2*pad)+'px', height:(h+2*pad)+'px',
      border:'3px solid #7A6348', borderRadius:'14px', boxShadow:'0 0 0 3px rgba(255,255,255,.85)', zIndex:2147483646, pointerEvents:'none', boxSizing:'border-box'});
    const b = document.createElement('div');
    b.className = 'guide-mark'; b.textContent = n;
    Object.assign(b.style, {position:'fixed', left:(x-pad-13)+'px', top:(y-pad-13)+'px', width:'28px', height:'28px', borderRadius:'9999px',
      background:'#7A6348', color:'#fff', font:'700 15px/28px Inter, system-ui, sans-serif', textAlign:'center', zIndex:2147483647,
      boxShadow:'0 0 0 3px #fff, 0 2px 6px rgba(0,0,0,.25)', pointerEvents:'none'});
    document.body.append(box, b);
  }
}
"""

def login(pg, email):
    pg.goto(B + '/login'); pg.fill('input[name=email]', email); pg.fill('input[name=password]', PW)
    pg.click('form button[type=submit]'); pg.wait_for_load_state('networkidle')

def vbox(loc):
    bb = loc.bounding_box()
    return [bb['x'], bb['y'], bb['width'], bb['height']]

def shoot(pg, name, marks, region, pad=6, margin=24, max_h=None):
    """marks: [(locator, number)]; region: locator or list of locators (their union is captured).
    Scroll first, then measure in viewport coordinates (pages scroll inside <main>)."""
    regions = region if isinstance(region, list) else [region]
    regions[0].evaluate("e => { e.scrollIntoView({block: 'start'}); const m = document.querySelector('main'); if (m && m.scrollTop > 0) m.scrollBy(0, -48); }")
    pg.wait_for_timeout(250)
    boxes = [vbox(l) + [n, pad] for l, n in marks]
    pg.evaluate(MARK_JS, [boxes])
    rb = [vbox(r) for r in regions]
    x0 = min(b[0] for b in rb); y0 = min(b[1] for b in rb)
    x1 = max(b[0] + b[2] for b in rb); y1 = max(b[1] + b[3] for b in rb)
    vw, vh = pg.viewport_size['width'], pg.viewport_size['height']
    x = max(x0 - margin, 0); y = max(y0 - margin, 0)
    w = min(x1 + margin, vw) - x; h = min(y1 + margin, vh) - y
    if max_h: h = min(h, max_h)
    png = pg.screenshot(clip={'x': x, 'y': y, 'width': w, 'height': h}, animations='disabled')
    img = Image.open(io.BytesIO(png)).convert('RGB')
    if img.width > 1200:
        img = img.resize((1200, round(img.height * 1200 / img.width)), Image.LANCZOS)
    img.quantize(colors=256, method=Image.Quantize.MEDIANCUT, dither=Image.Dither.NONE).save(f'{OUT}/{name}.png', optimize=True)
    import os; print(name, img.size, os.path.getsize(f'{OUT}/{name}.png') // 1024, 'kB')
    pg.evaluate("document.querySelectorAll('.guide-mark').forEach(e => e.remove())")

def session(b, email, height=900):
    ctx = b.new_context(viewport={'width': 1366, 'height': height}, color_scheme='light', device_scale_factor=1, timezone_id='Asia/Manila')
    pg = ctx.new_page()
    login(pg, email)
    # Keep the "New here?" banner out of the pictures.
    pg.request.post(B + '/help/banner/dismiss', headers={'Accept': 'application/json',
        'X-CSRF-TOKEN': pg.eval_on_selector('meta[name=csrf-token]', 'e => e.content')})
    return ctx, pg


OWNER = 'serenity.owner@levictas-demo.test'
MANAGER = 'serenity.manager.imus@levictas-demo.test'
THERAPIST = 'serenity.therapist.imus1@levictas-demo.test'

def card(loc):
    """The card (rounded-2xl) that contains loc."""
    return loc.locator('xpath=ancestor::div[contains(@class,"rounded-2xl")][1]')

def parent(pg, sel):
    return pg.locator(sel).locator('xpath=..')

def go(pg, url):
    pg.goto(B + url); pg.wait_for_load_state('networkidle'); pg.wait_for_timeout(500)

ONLY = sys.argv[1:]
if not PW:
    sys.exit('Set DEMO_SEED_PASSWORD (the password the demo seeder used).')

def want(name):
    return not ONLY or name in ONLY

with sync_playwright() as p:
    b = p.chromium.launch()

    # ── Manager ─────────────────────────────────────────────────────────────
    ctx, pg = session(b, MANAGER, 1300)

    if want('getting-started'):
        go(pg, '/dashboard')
        sb = pg.locator('#app-sidebar')
        shoot(pg, 'getting-started', [
            (sb.get_by_role('link', name='Dashboard', exact=True), 2),
            (sb.locator('button', has_text='People').first, 3),
            (sb.get_by_role('link', name=re.compile('^Schedule')), 4),
        ], [sb.locator('a').first, sb.locator('button', has_text='Branch Settings').first], pad=2, margin=16)

    if want('dashboard'):
        go(pg, '/dashboard')
        w = pg.locator('#alertsWidget')
        shoot(pg, 'dashboard', [(w, 1), (w.locator('a').first, 2)], w)

    if want('book'):
        go(pg, '/booking')
        pg.click('#walkinFillBtn'); pg.wait_for_timeout(600)
        form = card(pg.locator('#bookingForm'))
        shoot(pg, 'book', [
            (pg.locator('#walkinFillBtn'), 1),
            (parent(pg, '#customer_name'), 2), (parent(pg, '#customer_phone'), 2),
            (parent(pg, '#treatment'), 3),
            (parent(pg, '#therapist_id'), 4),
            (pg.locator('#submitBtn'), 5),
        ], form)

    if want('appointments'):
        go(pg, '/appointments')
        pg.locator('#needsAttentionSection button:has-text("Process")').first.click()
        pg.wait_for_timeout(500)
        pg.select_option('#process_status', 'checkout'); pg.wait_for_timeout(400)
        panel = card(pg.locator('#processModalTitle'))
        shoot(pg, 'appointments', [
            (parent(pg, '#process_status'), 2),
            (pg.locator('#process_amount_wrapper'), 3),
            (panel.get_by_role('button', name='Save Action'), 4),
        ], panel)

    if want('schedule'):
        go(pg, '/schedule')
        toolbar = pg.locator('[aria-label="Previous week"]').locator('xpath=ancestor::div[contains(@class,"rounded-2xl")][1]')
        appt = pg.locator('div[role=button][onclick="openAppointmentModal(this)"]:visible').first
        region = pg.locator('[aria-label="Previous week"]').locator('xpath=ancestor::div[contains(@class,"rounded-2xl")][1]')
        shoot(pg, 'schedule', [
            (pg.locator('[aria-label="Previous week"]'), 1),
            (pg.locator('[aria-label="Next week"]'), 1),
            (pg.locator('[aria-label="Jump to this week"]'), 1),
            (appt, 3),
        ], [region, appt], max_h=640)
    ctx.close()

    # ── Owner ───────────────────────────────────────────────────────────────
    ctx, pg = session(b, OWNER, 1300)

    if want('staff'):
        go(pg, '/staff')
        pg.select_option('#roles', 'receptionist'); pg.wait_for_timeout(300)
        add = card(pg.get_by_text('Add New Staff Member', exact=True).first)
        shoot(pg, 'staff', [
            (parent(pg, '#first_name'), 1), (parent(pg, '#last_name'), 1), (parent(pg, '#email'), 1),
            (parent(pg, '#roles'), 2),
            (pg.get_by_text('Also performs treatments').locator('xpath=ancestor::label[1]'), 3),
            (add.get_by_role('button', name='Add Staff Member'), 4),
        ], add)

    if want('services'):
        go(pg, '/services')
        pg.evaluate('openAddTreatmentModal()'); pg.wait_for_timeout(400)
        panel = card(pg.locator('#addTreatmentTitle'))
        shoot(pg, 'services', [
            (parent(pg, '#add_treatment_name'), 2), (parent(pg, '#add_treatment_duration'), 2), (parent(pg, '#add_treatment_price'), 2),
            (parent(pg, '#add_treatment_service_type'), 3),
            (panel.locator('button[type=submit]'), 4),
        ], panel)
    ctx.close()

    # ── Therapist ───────────────────────────────────────────────────────────
    ctx, pg = session(b, THERAPIST, 1300)

    if want('attendance'):
        go(pg, '/attendance')
        box = card(pg.get_by_text('My Attendance Today', exact=True))
        clock_in = box.get_by_role('button', name='Clock In')
        marks = [(clock_in, 1)] if clock_in.count() else [(box.get_by_role('button', name='Clock Out'), 3)]
        shoot(pg, 'attendance', marks, box)

    if want('performance'):
        go(pg, '/therapist/performance')
        # This page's cards are rounded-lg + shadow-sm.
        sc = lambda l: l.locator('xpath=ancestor::div[contains(@class,"shadow-sm")][1]')
        avg = sc(pg.get_by_text('Average Rating', exact=True).first)
        done = sc(pg.get_by_text('Completed Services', exact=True).first)
        dist = sc(pg.get_by_role('heading', name='Rating Distribution'))
        fb = sc(pg.get_by_role('heading', name='Recent Customer Feedback'))
        shoot(pg, 'performance', [(avg, 1), (done, 2), (dist, 3)], [avg.locator('xpath=..'), dist])

    if want('my-payslips'):
        go(pg, '/my-payslips')
        table = card(pg.get_by_role('link', name='View').first)
        shoot(pg, 'my-payslips', [
            (pg.locator('#f_year').locator('xpath=ancestor::form[1]'), 1),
            (pg.get_by_role('link', name='View').first, 2),
        ], table)

    if want('profile'):
        go(pg, '/profile')
        sec = card(pg.get_by_role('heading', name='Change Password'))
        shoot(pg, 'profile', [
            (sec.locator('input[type=password]').first.locator('xpath=ancestor::*[count(.//input[@type="password"])>=3][1]'), 2),
            (sec.get_by_role('button', name='Save new password'), 4),
        ], sec)
    ctx.close()
    b.close()
