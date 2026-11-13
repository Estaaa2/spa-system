<?php

namespace App\Support\Guides;

/**
 * Help & Guides content — the one source for the Help page (/help) and the
 * header's Help button (help guides Phase 1, D6). Same idea as
 * App\Support\Legal\Terms: plain PHP arrays, rendered by Blade.
 *
 * One entry per page, keyed by its sidebar link key (Sidebar.php), so the
 * Help page can show a guide only when the user's sidebar shows that page
 * (D1). Two entries are not sidebar pages:
 *   getting-started — shown to every business user;
 *   profile         — shown to every business user, matched by `routes`.
 *
 * Entry shape:
 *   title    string    the page name as the sidebar shows it
 *   summary  string    one line: what this page is for
 *   tasks    list      ['title' => ..., 'steps' => [...], 'when' => ?]
 *   tips     list      short notes (optional)
 *   images   list      ['src' => 'images/guides/<key>.png', 'alt' => ..., 'task' => i]: shown above task i;
 *                      its numbered marks are that task's steps (Phase 2)
 *   tour     ?string   driver.js tour id (Phase 3)
 *   routes   list      route patterns, only for guides with no sidebar link
 *
 * A task or a step can carry `when`: a condition (or list of conditions, all
 * required) checked by GuideCatalog, so nobody reads about a button they
 * don't have. Conditions: 'perm:<permission>', 'feature:<plan feature>',
 * 'switcher', 'spa_zone', 'self_service', 'reassign', 'can_verify'.
 *
 * Every step must match a label on the page (D9: plain English, short
 * sentences). Change a button label → change the guide.
 */
class Guides
{
    /** Guides that are not sidebar pages; every business user gets them. */
    public const ALWAYS = ['getting-started', 'profile'];

    /** Shown when a user can open a page but none of its tasks apply to their role. */
    public const VIEW_ONLY_TIP = 'Your role can view this page but not change it. If you need to make changes here, ask the owner.';

    /** @return array<string, array> */
    public static function all(): array
    {
        return [
            'getting-started' => [
                'title' => 'Getting started',
                'summary' => 'How Levictas is laid out, and where to find things.',
                'images' => [['src' => 'images/guides/getting-started.png', 'alt' => 'The menu: Dashboard at the top, the Schedule page with a number waiting, and the People group.', 'task' => 0]],
                'tasks' => [
                    [
                        'title' => 'Find your way around',
                        'steps' => [
                            'The menu on the left lists only the pages your role can open. On a phone, tap the menu button at the top left to open it.',
                            'Your most-used pages sit at the top of the menu, under Dashboard.',
                            'The other pages are in groups, such as Bookings or People. Click a group name to open or close it.',
                            'A number next to a page means something is waiting for you there, such as a request to review.',
                        ],
                    ],
                    [
                        'title' => 'Switch branches',
                        'when' => 'switcher',
                        'steps' => [
                            'The branch name at the top of the menu shows the branch you are working in.',
                            'Click it and choose another branch. The pages under the branch name now show that branch.',
                            ['text' => 'Pages under "Whole spa" cover every branch. They don\'t change when you switch.', 'when' => 'spa_zone'],
                        ],
                    ],
                    [
                        'title' => 'Get help on any page',
                        'steps' => [
                            'Click Help at the top right of a page. That page\'s guide opens in a new tab, so you don\'t lose what you typed.',
                            'Or click Help & Guides at the bottom of the menu to see every guide you can use.',
                            'Type in the search box on the Help page to find a task, for example "clock in".',
                        ],
                    ],
                ],
                'tips' => [
                    'If a guide mentions a button you can\'t see, your role doesn\'t have that permission. Ask the owner or your manager.',
                    'Your name at the bottom of the menu opens your Profile. The button next to it logs you out.',
                ],
            ],

            'profile' => [
                'title' => 'Profile',
                'summary' => 'Your photo, phone number, identity verification and password.',
                'images' => [['src' => 'images/guides/profile.png', 'alt' => 'The Change Password section with the three password boxes and the Save new password button.', 'task' => 0]],
                'routes' => ['profile.*'],
                'tasks' => [
                    [
                        'title' => 'Change your password',
                        'steps' => [
                            'Open your Profile: click your name at the bottom of the menu.',
                            'Under Change Password, type your Current password, then the New password twice.',
                            'Use at least 8 characters, with uppercase and lowercase letters, a number and a symbol.',
                            'Click Save new password.',
                        ],
                    ],
                    [
                        'title' => 'Update your phone number',
                        'steps' => [
                            'Under Account Information, type your phone number: 11 digits, starting with 09.',
                            'Click Save phone number.',
                            'Your name and email are managed by your spa. Ask the owner if they need changing.',
                        ],
                    ],
                    [
                        'title' => 'Add a profile photo',
                        'steps' => [
                            'Under Profile Photo, click Upload a photo (or Choose a new photo).',
                            'Pick a clear, recent photo of your face: JPG, PNG or WebP.',
                            'Check the preview, then save it.',
                        ],
                    ],
                    [
                        'title' => 'Verify your identity',
                        'when' => 'can_verify',
                        'steps' => [
                            'You need this before you can be given online home-service bookings.',
                            'Add a profile photo first.',
                            'Under Identity Verification, choose an ID: a valid Philippine government ID with your photo and name.',
                            'Add a photo of the whole ID, and a selfie holding the ID next to your face.',
                            'Click Submit for review. The status shows "Waiting for review" until an admin checks it.',
                        ],
                    ],
                ],
            ],

            'dashboard' => [
                'title' => 'Dashboard',
                'summary' => 'Your home page. It shows today at a glance. The cards you see depend on your role.',
                'images' => [['src' => 'images/guides/dashboard.png', 'alt' => 'The Alerts card on the Dashboard, with Late Check-ins at the top.', 'task' => 1]],
                'tasks' => [
                    [
                        'title' => 'Clock in and out',
                        'when' => 'self_service',
                        'steps' => [
                            'When you arrive, click Clock In on the card at the top of the Dashboard.',
                            'The Attendance & Leave page opens with a confirmation. Check the time, your shift and your bookings, then click Clock In.',
                            'Before you leave, click Clock Out on the same card and confirm. If you leave more than 15 minutes early, type why you\'re leaving early first.',
                        ],
                    ],
                    [
                        'title' => 'See what needs attention',
                        'when' => 'perm:view dashboard alerts',
                        'steps' => [
                            'Look at the Alerts card. It lists problems right now, such as late check-ins, appointments that need checkout, and leave or reassignment requests.',
                            'Click an alert to go straight to the page where you fix it.',
                        ],
                    ],
                    [
                        'title' => 'Check today\'s appointments',
                        'steps' => [
                            'The schedule card lists today\'s appointments in time order, with their status.',
                            'Reserved (blue) is booked. Pending (amber) has reached its start time but nobody has started it. Ongoing (green) is in session. Completed (grey) is checked out.',
                            ['text' => 'Therapist Status shows who is free or busy, and who is absent, on leave or clocked out.', 'when' => 'perm:view dashboard therapist status'],
                        ],
                    ],
                    [
                        'title' => 'Start a new booking',
                        'when' => 'perm:view dashboard booking button',
                        'steps' => [
                            'Click New Booking at the top right. It opens Book an Appointment.',
                        ],
                    ],
                ],
                'tips' => [
                    'The Dashboard updates by itself every 30 seconds. The label at the top says Live when it is up to date.',
                ],
            ],

            'book' => [
                'title' => 'Book an Appointment',
                'summary' => 'Book a walk-in or phone customer. Online bookings from the website and app come in by themselves.',
                'images' => [['src' => 'images/guides/book.png', 'alt' => 'The booking form: Walk-in Now, customer name and phone, treatment, therapist and Reserve Booking.', 'task' => 0]],
                'tasks' => [
                    [
                        'title' => 'Book a walk-in customer',
                        'steps' => [
                            'Click Walk-in Now. It fills in today\'s date and the current time.',
                            'Type the customer\'s Full Name and Phone Number (11 digits, starting with 09). Email is optional.',
                            'Choose the Treatment / Package.',
                            'Choose the Assigned Therapist. Only therapists who are free at that time are listed.',
                            'Check the Appointment Summary, then click Reserve Booking.',
                        ],
                    ],
                    [
                        'title' => 'Book for a later date',
                        'steps' => [
                            'Type the customer\'s Full Name and Phone Number.',
                            'Choose the Service Type: In Branch, or In Home for a home visit. For In Home, type the customer\'s Address.',
                            'Choose the Treatment / Package.',
                            'Choose the Appointment Date and Start Time. If the branch is closed that day, the page tells you.',
                            'Choose the Assigned Therapist. If it says "No therapist available for this slot", try another time.',
                            'Click Reserve Booking.',
                        ],
                    ],
                ],
                'tips' => [
                    'Some treatments are In Branch only. They are removed from the list when you choose In Home.',
                    'In Home bookings need a therapist whose identity is verified.',
                    'The new booking shows up on Appointments and Schedule right away.',
                ],
            ],

            'appointments' => [
                'title' => 'Appointments',
                'summary' => 'Run today\'s appointments: start sessions, check customers out, and handle changes.',
                'images' => [['src' => 'images/guides/appointments.png', 'alt' => 'The Process Appointment window with Check out chosen, the amount to collect, and Save Action.', 'task' => 1]],
                'tasks' => [
                    [
                        'title' => 'Start a session when the customer arrives',
                        'when' => 'perm:edit appointments',
                        'steps' => [
                            'Find the appointment under Today\'s Appointments. Once its start time passes, it also shows under Needs Attention Right Now.',
                            'Click Process.',
                            'Under Action, choose "Start session (customer has arrived)".',
                            'Click Save Action. The status changes to Ongoing.',
                        ],
                    ],
                    [
                        'title' => 'Check out a finished appointment',
                        'when' => 'perm:edit appointments',
                        'steps' => [
                            'Click Process on the appointment.',
                            'Under Action, choose "Check out (service done, collect the balance)".',
                            'The window shows the Total, Already Paid and Remaining. Collect the remaining balance and enter it in Amount Collected Now.',
                            'Click Save Action. The appointment is now Completed and moves to History.',
                        ],
                    ],
                    [
                        'title' => 'Mark a no-show or a cancellation',
                        'when' => 'perm:edit appointments',
                        'steps' => [
                            'Click Process on the appointment.',
                            'Choose "Mark as No-show" if the customer didn\'t come, or "Cancel" if they called to cancel.',
                            'Click Save Action. No payment is collected, and anything already paid stays recorded.',
                        ],
                    ],
                    [
                        'title' => 'Close appointments from earlier days',
                        'when' => 'perm:edit appointments',
                        'steps' => [
                            'Look at the Needs Checkout card. It lists open appointments that are past their end time, oldest first.',
                            'If a row says Assign therapist, click it first and choose who did the session.',
                            'If a home visit says Arrival not confirmed, confirm the arrival first.',
                            'Then click Process and check it out, or mark it as a no-show.',
                        ],
                    ],
                    [
                        'title' => 'Change or remove an appointment',
                        'when' => 'perm:edit appointments',
                        'steps' => [
                            'Click Edit to change the details, the therapist or the time. Click Save Changes.',
                            ['text' => 'Remove deletes the appointment for good. To stop an appointment but keep the record, use Process and choose Cancel.', 'when' => 'perm:delete appointments'],
                        ],
                    ],
                    [
                        'title' => 'Handle a reassignment request',
                        'when' => 'perm:edit appointments',
                        'steps' => [
                            'When a therapist can\'t make an appointment, it shows under Reassignment Requested.',
                            'Click Review.',
                            'Pick a Replacement Therapist, then click Confirm Reassignment. Or click Reject and give a reason.',
                        ],
                    ],
                    [
                        'title' => 'Tell the front desk you can\'t make an appointment',
                        'when' => 'reassign',
                        'steps' => [
                            'Find your appointment and click Can\'t Make It?.',
                            'Choose the Leave Type and say why (at least 10 characters).',
                            'Click Submit Request. The front desk is told right away and picks someone else.',
                        ],
                    ],
                ],
                'tips' => [
                    'Only a checkout completes an appointment. Nothing becomes Completed by itself when the time passes.',
                    'Checkout needs a therapist on the appointment, and a confirmed arrival for online home visits.',
                ],
            ],

            'schedule' => [
                'title' => 'Schedule',
                'summary' => 'A weekly calendar of this branch\'s appointments, with therapist shifts and customers\' reschedule requests.',
                'images' => [['src' => 'images/guides/schedule.png', 'alt' => 'The week navigation (Today, previous and next week) and an appointment card in the calendar.', 'task' => 0]],
                'tasks' => [
                    [
                        'title' => 'Move around the calendar',
                        'steps' => [
                            'Use the arrows to go to the previous or next week. Click Today to come back to this week.',
                            'On a phone, tap a day to see that day\'s appointments.',
                            'Click an appointment to see its date, time, service and customer.',
                        ],
                    ],
                    [
                        'title' => 'Check who is working',
                        'steps' => [
                            'Click Shifts. Therapist Shifts lists each therapist\'s hours for the week.',
                            '"One-off" marks a change for a single date, such as a day off or a swapped rest day.',
                        ],
                    ],
                    [
                        'title' => 'Approve or reject a reschedule request',
                        'when' => 'perm:edit appointments',
                        'steps' => [
                            'Customers\' requests to move an appointment show under Reschedule Requests at the top. Click Review.',
                            'Check the Requested Date, Requested Time and the Customer\'s Reason.',
                            'Click Approve. Or click Reject, type the reason for rejection, and click Confirm Rejection.',
                        ],
                    ],
                ],
                'tips' => [
                    'A request nobody reviews before the original start time expires. The appointment keeps its time and the customer is emailed.',
                ],
            ],

            'performance' => [
                'title' => 'My Performance',
                'summary' => 'Your ratings, customer feedback and completed services.',
                'images' => [['src' => 'images/guides/performance.png', 'alt' => 'Average Rating, Completed Services and the Rating Distribution chart.', 'task' => 0]],
                'tasks' => [
                    [
                        'title' => 'Check your ratings and feedback',
                        'steps' => [
                            'Average Rating and Customer Satisfaction come from the ratings customers give after a visit.',
                            'Completed Services counts every appointment you finished.',
                            'Rating Distribution and Monthly Performance show how your ratings change over time.',
                            'Recent Customer Feedback lists the latest comments.',
                        ],
                    ],
                ],
                'tips' => [
                    'Customers can rate a visit only after it is checked out. Ask the front desk to check out your finished sessions.',
                ],
            ],

            'staff' => [
                'title' => 'Staff',
                'summary' => 'Add staff accounts, set their roles, and set their work schedules.',
                'images' => [['src' => 'images/guides/staff.png', 'alt' => 'The Add New Staff Member form: names and email, Staff Assignment, Also performs treatments, Add Staff Member.', 'task' => 0]],
                'tasks' => [
                    [
                        'title' => 'Add a staff member',
                        'when' => 'perm:create staff',
                        'steps' => [
                            'Under Add New Staff Member, type the First Name, Last Name and Email. The middle name is optional.',
                            'Choose a role under Staff Assignment.',
                            'If this person also gives treatments, turn on Also performs treatments.',
                            'Click Add Staff Member. They get an email with their login details and must change the password the first time they log in.',
                        ],
                    ],
                    [
                        'title' => 'Set a work schedule',
                        'when' => 'perm:edit staff schedules',
                        'steps' => [
                            'In the Staff Directory, click Schedule on the person\'s row.',
                            'Under Weekly pattern, set each day to Branch hours, Custom hours or Rest day, and choose the date it applies from.',
                            'Click Save Weekly Pattern.',
                            'For a single date, add a One-off change: pick the Date and the Change (Day off (unpaid), Rest-day swap, or Shift), then click Add Change.',
                        ],
                    ],
                    [
                        'title' => 'Edit or remove a staff member',
                        'steps' => [
                            ['text' => 'Click Edit on the person\'s row, change the details, and click Save Changes.', 'when' => 'perm:edit staff'],
                            ['text' => 'Click Remove, then Yes, Remove. This removes them from the branch for good.', 'when' => 'perm:delete staff'],
                        ],
                    ],
                ],
                'tips' => [
                    'Booking, attendance and payroll all follow the work schedule. A therapist can\'t be booked outside their shift.',
                    'When a schedule change affects booked appointments, they are flagged for reassignment on Appointments.',
                    'Your plan has a staff limit. When it\'s reached, the owner can upgrade the plan.',
                ],
            ],

            'attendance' => [
                'title' => 'Attendance & Leave',
                'summary' => 'Clock in and out, see your attendance, and ask for time off.',
                'images' => [['src' => 'images/guides/attendance.png', 'alt' => 'My Attendance Today with today\'s shift and the Clock In button.', 'task' => 0]],
                'tasks' => [
                    [
                        'title' => 'Clock in and out',
                        'when' => 'self_service',
                        'steps' => [
                            'Under My Attendance Today, click Clock In.',
                            'Check the time, your shift and your bookings today, then click Clock In to confirm.',
                            'Before you leave, click Clock Out. If you leave more than 15 minutes early, type why you\'re leaving early. Then confirm.',
                            'Only a manager or HR can change your times afterwards.',
                        ],
                    ],
                    [
                        'title' => 'Ask for time off',
                        'when' => 'perm:create leave requests',
                        'steps' => [
                            'Click Request Leave.',
                            'Choose the Leave type, the Start date and End date, and type the Reason (at least 10 characters).',
                            'Click Submit Request. It shows under My Leave Requests until someone approves or rejects it.',
                            'Your appointments in those dates are flagged for reassignment once the leave is approved. You don\'t need to do anything.',
                        ],
                    ],
                    [
                        'title' => 'Record or correct someone\'s attendance',
                        'when' => 'perm:edit attendance',
                        'steps' => [
                            'In the Branch Roster, pick the date and click Show.',
                            'Click Record (or Edit) on the person\'s row.',
                            'Choose the Status, and enter the Time in and Time out. You can\'t enter a time later than now.',
                            'Click Save.',
                        ],
                    ],
                    [
                        'title' => 'Approve or reject a leave request',
                        'when' => 'perm:edit leave requests',
                        'steps' => [
                            'Requests waiting for you are under Pending Leave Approvals. Click Review.',
                            'Check the appointments affected by the leave. Pick a replacement for each, or leave them to resolve later on Appointments.',
                            'Click Approve. Or click Reject, give a reason (at least 5 characters), and click Confirm Reject.',
                        ],
                    ],
                ],
                'tips' => [
                    'You can clock in from 15 minutes before your shift. After shift start plus 15 minutes, you are marked Late.',
                    'Nobody is marked absent on a rest day or a day the branch is closed.',
                ],
            ],

            'services' => [
                'title' => 'Services',
                'summary' => 'Your treatments and packages: names, prices, durations, and promos.',
                'images' => [['src' => 'images/guides/services.png', 'alt' => 'The Add Treatment window: name, duration, price, service type and the Add Treatment button.', 'task' => 0]],
                'tasks' => [
                    [
                        'title' => 'Add a treatment',
                        'when' => 'perm:create treatments',
                        'steps' => [
                            'Click Add Treatment.',
                            'Type the Name, choose the Duration, and type the Price (₱).',
                            'Choose the Service Type: In Branch Only, or In Branch & Home if it can be done on a home visit.',
                            'Click Add Treatment.',
                        ],
                    ],
                    [
                        'title' => 'Make a package',
                        'when' => 'perm:create packages',
                        'steps' => [
                            'Click Add Package.',
                            'Type the Package Name and select one or more Included Treatments. The duration and price are worked out from the treatments.',
                            'Click Add Package.',
                        ],
                    ],
                    [
                        'title' => 'Add many treatments or packages at once',
                        'when' => 'perm:create treatments',
                        'steps' => [
                            'Click CSV Format Guide to see the columns, and Download Sample CSV for an example file.',
                            'Fill in the file in Excel or Google Sheets and save it as CSV.',
                            'Click Import CSV in the Treatments or Packages card and choose the file.',
                        ],
                    ],
                    [
                        'title' => 'Set the products a treatment uses',
                        'when' => ['perm:edit treatments', 'feature:inventory_basic'],
                        'steps' => [
                            'Click Recipe on the treatment.',
                            'Click Add Product, choose the product and the quantity used per session.',
                            'Click Save Recipe. These products come out of stock when an appointment is checked out.',
                        ],
                    ],
                    [
                        'title' => 'Run a promo',
                        'when' => 'perm:create promos',
                        'steps' => [
                            'Click Manage Promos at the top right, then New Promo.',
                            'Type the Promo Name. Choose % Off or ₱ Off, and type the Discount Value.',
                            'Choose the Start Date and End Date, and the treatments or packages it applies to.',
                            'Keep Promo is active turned on, and click Save Promo.',
                        ],
                    ],
                ],
                'tips' => [
                    'Edit and Remove are on each treatment\'s and package\'s row.',
                ],
            ],

            // ── People ─────────────────────────────────────────────────────────

            'deployment' => [
                'title' => 'Staff Deployment',
                'summary' => 'Send a staff member to work at another branch for a while, or move them for good.',
                'tasks' => [
                    [
                        'title' => 'Send someone to another branch',
                        'when' => 'perm:create deployments',
                        'steps' => [
                            'Under Staff Members, click Manage on the person\'s row.',
                            'Under New Deployment Request, choose the Target branch and the Start date.',
                            'For a short stay, pick an End date. Leave it blank to keep them there until recalled. Tick Permanent transfer to move them for good.',
                            'Click Submit Request. The staff member is asked to accept it.',
                        ],
                    ],
                    [
                        'title' => 'Follow up a declined request',
                        'steps' => [
                            'Staff Response shows what the staff member answered. A red warning sign next to their name means they declined.',
                            'Click Manage to read their reason and decide what to do next.',
                        ],
                    ],
                ],
                'tips' => [
                    'Only branches of your spa are listed. Create another branch first if the list is empty.',
                ],
            ],

            // ── Hiring ─────────────────────────────────────────────────────────

            'job-openings' => [
                'title' => 'Job Openings',
                'summary' => 'Post the jobs you are hiring for. Open postings show "We\'re Hiring" on your branch\'s public page.',
                'tasks' => [
                    [
                        'title' => 'Post a job opening',
                        'when' => 'perm:create hiring',
                        'steps' => [
                            'Click New Job Opening.',
                            'Type the Job title, and choose the Role when hired, the Job type and the Number of openings.',
                            'Set the Weekly schedule. Applicants are asked if they can work it.',
                            'Pay is optional. Turn on Show pay on the landing page to show it publicly.',
                            'Choose Open (shows publicly), or Draft (hidden) to finish later. Click Save Job Opening.',
                        ],
                    ],
                    [
                        'title' => 'Close or reopen a posting',
                        'when' => 'perm:edit hiring',
                        'steps' => [
                            'Click Close when you stop hiring for it. Click Reopen to start again.',
                            'A posting closes by itself when the Number of openings is filled, or after its deadline.',
                        ],
                    ],
                ],
                'tips' => [
                    'Don\'t ask for an age or a birth date. Philippine law (RA 10911) doesn\'t allow it.',
                    'When you hire someone, the posting\'s schedule becomes their work schedule. You can change it on the Staff page.',
                ],
            ],

            'hiring' => [
                'title' => 'Application Form',
                'summary' => 'Record an applicant who applied in person (a walk-in). Online applications come in by themselves.',
                'tasks' => [
                    [
                        'title' => 'Record a walk-in applicant',
                        'when' => 'perm:create hiring',
                        'steps' => [
                            'Choose the Job opening, or "No posting (general walk-in)".',
                            'If you chose an opening, answer "Can the applicant work this schedule?".',
                            'Fill in the Applicant details: Full name, Email, Phone and Address. Upload their Résumé / CV if they brought one.',
                            'Click Submit Application. It appears on the Applicants page.',
                        ],
                    ],
                ],
                'tips' => [
                    'The form doesn\'t ask for age, date of birth, gender or civil status, on purpose (RA 10911, RA 10173).',
                ],
            ],

            'applicants' => [
                'title' => 'Applicants',
                'summary' => 'Everyone who applied, online or in person, and where they are in hiring.',
                'tasks' => [
                    [
                        'title' => 'Review an applicant',
                        'steps' => [
                            'Click View on the applicant\'s row to see their details, the job, and their résumé.',
                            '"Online" means they applied on the website. "Walk-in" means someone recorded them on the Application Form.',
                        ],
                    ],
                    [
                        'title' => 'Schedule an interview',
                        'when' => ['perm:edit applications', 'perm:create interviews'],
                        'steps' => [
                            'Click Schedule on a pending applicant.',
                            'Choose the Interview date and Interview time. Add Remarks if needed.',
                            'Click Schedule Interview. The interview then appears on the Interviews page.',
                        ],
                    ],
                ],
            ],

            'interviews' => [
                'title' => 'Interviews',
                'summary' => 'Record how interviews went, and create staff accounts for the people you hire.',
                'tasks' => [
                    [
                        'title' => 'Approve or reject after the interview',
                        'when' => 'perm:edit interviews',
                        'steps' => [
                            'Find the applicant under Interview Records.',
                            'Click Approve if they passed.',
                            'Or click Reject. Type the Internal reason (for HR only) and, if you want, a Message to applicant. They get a polite "not selected" email.',
                        ],
                    ],
                    [
                        'title' => 'Create a staff account for a hire',
                        'when' => ['perm:edit interviews', 'perm:create staff'],
                        'steps' => [
                            'On an approved applicant, click Create Account.',
                            'Check the First name and Last name. They must be the legal name, used for identity verification and payroll.',
                            'Choose the Role, then confirm. They get an email with their login details.',
                            'If the person already has an account, use Link Existing Account instead.',
                        ],
                    ],
                ],
            ],

            // ── Payroll ────────────────────────────────────────────────────────

            'pay-runs' => [
                'title' => 'Pay Runs',
                'summary' => 'Make each payroll cutoff for the whole spa: generate it, check it, approve it, and release payslips.',
                'tasks' => [
                    [
                        'title' => 'Make a pay run',
                        'when' => 'perm:prepare payroll runs',
                        'steps' => [
                            'Click New Run. Choose the Run type and Month. The dates come from Payroll Setup.',
                            'Click Generate Draft. Nothing is paid yet.',
                            'Open the run and read the Warnings. They list staff who were not paid and why.',
                            'Fix anything needed, such as a missing pay profile, then click Regenerate.',
                        ],
                    ],
                    [
                        'title' => 'Add a one-off amount to a payslip',
                        'when' => 'perm:prepare payroll runs',
                        'steps' => [
                            'In a draft run, find the person in the Payroll Register and click Add Adjustment.',
                            'Choose the Type, type the Label on the payslip and the Amount (₱), then save. The payslip is worked out again right away.',
                        ],
                    ],
                    [
                        'title' => 'Approve, finalize and release',
                        'steps' => [
                            ['text' => 'Click Approve when the run is correct. This freezes the payslips.', 'when' => 'perm:approve payroll runs'],
                            ['text' => 'Click Finalize after checking it again. This can\'t be undone.', 'when' => 'perm:release payroll runs'],
                            ['text' => 'After pay has gone out, click Release Payslips. Staff then see theirs under My Payslips.', 'when' => 'perm:release payroll runs'],
                        ],
                    ],
                ],
                'tips' => [
                    'Cutoff 2 of a month can only be made after cutoff 1 is finalized.',
                    'SSS, PhilHealth and Pag-IBIG are deducted on the 2nd cutoff of each month.',
                ],
            ],

            'staff-pay' => [
                'title' => 'Staff Pay',
                'summary' => 'Each staff member\'s pay profile: rate, commission, rest days, government IDs, and regular allowances or deductions.',
                'tasks' => [
                    [
                        'title' => 'Set someone\'s pay',
                        'when' => 'perm:manage payroll setup',
                        'steps' => [
                            'Click Open on the staff member\'s row.',
                            'Under Pay Profile, pick the Effective from date and the Pay basis: Daily-paid or Monthly-paid.',
                            'Type the rate, turn on Earns commission if it applies, and choose the Weekly rest days.',
                            'Click Save Profile. To change pay later, add a new profile from the date it takes effect.',
                        ],
                    ],
                    [
                        'title' => 'Add a regular allowance or deduction',
                        'when' => 'perm:manage payroll setup',
                        'steps' => [
                            'Under Recurring Allowances & Deductions, click Add item.',
                            'Choose Allowance (earning) or Deduction, then the Label, Amount (₱), Frequency and Start date.',
                            'A deduction needs the employee\'s written authorization reference.',
                            'Click Add Item. It is included in every run until you click Stop.',
                        ],
                    ],
                ],
                'tips' => [
                    '"Not included in payroll" or "No pay profile" means this person is not paid until it\'s fixed.',
                ],
            ],

            'payroll-setup' => [
                'title' => 'Payroll Setup',
                'summary' => 'The pay schedule, each branch\'s minimum wage, and the commission rules.',
                'tasks' => [
                    [
                        'title' => 'Set the pay schedule',
                        'when' => 'perm:manage payroll setup',
                        'steps' => [
                            'Under Semi-monthly Pay Schedule, choose the day the first cutoff ends and how many days after a cutoff pay day falls.',
                            'Click Save Schedule. Upcoming Periods shows the next dates.',
                        ],
                    ],
                    [
                        'title' => 'Enter a branch\'s minimum daily wage',
                        'when' => 'perm:manage payroll setup',
                        'steps' => [
                            'Under Branch Minimum Daily Wage, click the branch\'s rate (or Missing rate).',
                            'Type the minimum daily wage from the regional wage order for that branch, and the date it takes effect.',
                            'Click Save Rate. Staff of a branch with no rate are not paid until it is set.',
                        ],
                    ],
                    [
                        'title' => 'Add a commission rule',
                        'when' => 'perm:manage payroll setup',
                        'steps' => [
                            'Under Commission Rules, click Add Rule and choose what it covers and the amount.',
                            'Payroll uses the first rule that matches each booking, in the order shown under Which Rule Wins.',
                        ],
                    ],
                ],
            ],

            // ── Catalog & Stock ────────────────────────────────────────────────

            'products' => [
                'title' => 'Product Inventory',
                'summary' => 'The products this branch keeps, how much is in stock, and when to reorder.',
                'tasks' => [
                    [
                        'title' => 'Add a product',
                        'when' => 'perm:create inventory items',
                        'steps' => [
                            'Click Add Product.',
                            'Type the Product Name and choose the Inventory Type: Backbar / Service Use, Retail, or both.',
                            'Set the Purchase Unit, the Usage Unit and the Conversion Factor. For example, 1 bottle = 1000 ml means 1000.',
                            'Type the Opening Stock and the Minimum, Reorder and Maximum levels, then click Save Product.',
                        ],
                    ],
                    [
                        'title' => 'Receive new stock',
                        'when' => 'perm:edit inventory items',
                        'steps' => [
                            'Click Receive on the product.',
                            'Type the Batch Number, the Quantity Received (in the usage unit), the Unit Cost and the Expiration Date.',
                            'Save it. A new batch appears on Product Batches.',
                        ],
                    ],
                    [
                        'title' => 'Correct the stock count',
                        'when' => 'perm:edit inventory items',
                        'steps' => [
                            'Click Edit on the product.',
                            'Under Stock Adjustment, choose Increase Stock or Decrease Stock, the Quantity and the Reason.',
                            'Click Apply Stock Adjustment. It is recorded in Product Logs.',
                        ],
                    ],
                ],
                'tips' => [
                    'Low Stock means the product is at or below its reorder level.',
                    '"Stock Shortfalls from Checkouts" lists appointments checked out without enough stock. Restock or adjust those products.',
                ],
            ],

            'batches' => [
                'title' => 'Product Batches',
                'summary' => 'Every batch of stock received, sorted by expiry date so the oldest is used first (FEFO).',
                'tasks' => [
                    [
                        'title' => 'Find stock that is about to expire',
                        'steps' => [
                            'Check the Expiring Soon and Expired cards at the top.',
                            'Use the Status filter, then click Apply.',
                        ],
                    ],
                    [
                        'title' => 'Record damaged, wasted or expired stock',
                        'when' => 'perm:edit inventory items',
                        'steps' => [
                            'Click Record Loss on the batch.',
                            'Choose the Loss Type: Wastage, Damage or Expired Stock. Type the Quantity and the Reason.',
                            'Save it. Stock goes down and the loss is recorded in Product Logs.',
                        ],
                    ],
                ],
                'tips' => [
                    'New batches come from Receive on Product Inventory or from Goods Receipts.',
                ],
            ],

            'product-logs' => [
                'title' => 'Product Logs',
                'summary' => 'A permanent record of every change to this branch\'s stock: who, when, how much, and why.',
                'tasks' => [
                    [
                        'title' => 'Look up stock changes',
                        'steps' => [
                            'Choose a Product, a Movement type and a date range, then click Apply.',
                            'Balance shows the stock left after each change.',
                            'Click Export PDF to save or print the list.',
                        ],
                    ],
                ],
            ],

            'transfers' => [
                'title' => 'Stock Transfers',
                'summary' => 'Move usable stock from this branch to another branch.',
                'tasks' => [
                    [
                        'title' => 'Send stock to another branch',
                        'when' => 'perm:create stock transfers',
                        'steps' => [
                            'Click New Transfer.',
                            'Choose the Destination Branch, the product and the Quantity.',
                            'Click Create Transfer. It waits under Pending until it is processed.',
                        ],
                    ],
                    [
                        'title' => 'Process or cancel a transfer',
                        'steps' => [
                            ['text' => 'Click Process, then Process Transfer. The stock moves, oldest-expiring batches first.', 'when' => 'perm:process stock transfers'],
                            ['text' => 'Click Cancel, then Cancel Transfer, to stop a pending transfer. No stock has moved yet.', 'when' => 'perm:cancel stock transfers'],
                        ],
                    ],
                ],
                'tips' => [
                    'A completed transfer can\'t be cancelled, because the stock has already moved.',
                ],
            ],

            'replenishment' => [
                'title' => 'Replenishment',
                'summary' => 'Which products need reordering, counting stock already on order.',
                'tasks' => [
                    [
                        'title' => 'See what to reorder',
                        'steps' => [
                            'Check Needs Reorder and Critical at the top.',
                            'In the table, Projected = On Hand + Incoming (from issued purchase orders).',
                            'Recommendation suggests how much to order. Make a Purchase Request for it.',
                        ],
                    ],
                ],
                'tips' => [
                    'This page only advises. Stock changes only when goods are received.',
                    'A product needs Minimum, Reorder and Maximum levels for a recommendation.',
                ],
            ],

            // ── Purchasing ─────────────────────────────────────────────────────

            'suppliers' => [
                'title' => 'Suppliers',
                'summary' => 'The suppliers your spa buys from, and which products each one sells.',
                'tasks' => [
                    [
                        'title' => 'Add a supplier',
                        'when' => 'perm:create suppliers',
                        'steps' => [
                            'Click Add Supplier.',
                            'Type the Supplier Name, Contact Person, Email, Phone and Address.',
                            'Save it.',
                        ],
                    ],
                    [
                        'title' => 'Link a product to a supplier',
                        'when' => 'perm:manage supplier products',
                        'steps' => [
                            'Click Add Product on the supplier\'s row.',
                            'Choose the Product and type the Supplier Unit Cost (₱), Lead Time (days) and Minimum Order Qty.',
                            'Tick "Make this the preferred supplier for the product" if it is your usual source, then save.',
                        ],
                    ],
                ],
            ],

            'purchase-requests' => [
                'title' => 'Purchase Requests',
                'summary' => 'Ask for products this branch needs. A request doesn\'t order or add stock by itself.',
                'tasks' => [
                    [
                        'title' => 'Ask for products',
                        'when' => 'perm:create purchase requests',
                        'steps' => [
                            'Click New Request.',
                            'Type a Reason so reviewers understand the request.',
                            'Click Add Product for each item and type the Quantity.',
                            'Click Submit Request. It shows as Pending.',
                        ],
                    ],
                    [
                        'title' => 'Approve or reject a request',
                        'when' => 'perm:review purchase requests',
                        'steps' => [
                            'Click View to check the products and quantities.',
                            'Click Approve, then Approve Request. It can now become a purchase order.',
                            'Or click Reject, type the Rejection Reason, then Reject Request.',
                        ],
                    ],
                ],
            ],

            'purchase-orders' => [
                'title' => 'Purchase Orders',
                'summary' => 'Turn an approved request into an order for a supplier.',
                'tasks' => [
                    [
                        'title' => 'Make a purchase order',
                        'when' => 'perm:create purchase orders',
                        'steps' => [
                            'Click New Purchase Order.',
                            'Choose the Approved Purchase Request and the supplier. Add the Expected Delivery Date.',
                            'Click Create Purchase Order. It starts as Draft.',
                        ],
                    ],
                    [
                        'title' => 'Send it to the supplier',
                        'when' => 'perm:manage purchase orders',
                        'steps' => [
                            'Click Issue when the order has been sent to the supplier, then Issue Order.',
                            'To stop an order, click Cancel Order and give a reason. It stays in the history.',
                        ],
                    ],
                ],
                'tips' => [
                    'An order doesn\'t add stock. Stock goes up only when the goods are received on Goods Receipts.',
                ],
            ],

            'goods-receipts' => [
                'title' => 'Goods Receipts',
                'summary' => 'Record what a supplier actually delivered. This is what adds the stock.',
                'tasks' => [
                    [
                        'title' => 'Receive a delivery',
                        'when' => 'perm:receive goods',
                        'steps' => [
                            'Click Receive Goods and choose the purchase order.',
                            'For each product, type the Delivered, Accepted and Rejected quantities. Delivered must equal Accepted + Rejected.',
                            'Type the Batch Number, and a Rejection Reason for anything rejected.',
                            'Click Post Goods Receipt. Accepted stock is added as a new batch.',
                        ],
                    ],
                ],
                'tips' => [
                    'An order delivered in parts stays under Partial Deliveries until the rest arrives.',
                    'Rejected quantities never become usable stock.',
                ],
            ],

            // ── Finance ────────────────────────────────────────────────────────

            'revenue' => [
                'title' => 'Revenue',
                'summary' => 'Money collected on checked-out appointments, with kept deposits and amounts still owed shown apart.',
                'tasks' => [
                    [
                        'title' => 'Check revenue for a period',
                        'steps' => [
                            'Choose a Period, or Custom range with From and To, then click Apply.',
                            'Service Revenue is money collected on checked-out (completed) appointments.',
                            'Kept Deposits are from no-shows and cancellations. Owed on Open Appointments is still to be collected.',
                            'Completed Bookings lists each appointment behind the totals.',
                        ],
                    ],
                ],
                'tips' => [
                    'An appointment counts here only after it is checked out on Appointments.',
                ],
            ],

            'billing' => [
                'title' => 'Billing & Expenses',
                'summary' => 'What each customer owes or paid, and the spa\'s expense requests.',
                'tasks' => [
                    [
                        'title' => 'Check customer balances',
                        'steps' => [
                            'Choose the Period and click Apply.',
                            'Billing Records lists each active booking with its Total, Amount Paid and Status.',
                        ],
                    ],
                    [
                        'title' => 'File an expense',
                        'when' => 'perm:create billing',
                        'steps' => [
                            'Click File Expense.',
                            'Type the Expense request / title, a Description and the Amount (₱).',
                            'Click Submit Request. It shows under Expense Requests until it\'s reviewed.',
                        ],
                    ],
                    [
                        'title' => 'Review an expense',
                        'when' => 'perm:edit billing',
                        'steps' => [
                            'Click Review on the expense.',
                            'Choose a Decision: Accept — release funds, Mark as under review, or Reject. Add Notes for the person who filed it.',
                            'Click Save Decision.',
                        ],
                    ],
                ],
            ],

            'vendor-bills' => [
                'title' => 'Vendor Bills',
                'summary' => 'Record supplier invoices and check them against the order and the delivery (3-way match).',
                'tasks' => [
                    [
                        'title' => 'Record a supplier invoice',
                        'when' => 'perm:create vendor bills',
                        'steps' => [
                            'Click Create Vendor Bill and choose the purchase order.',
                            'Type the Invoice Number, Invoice Date and Due Date.',
                            'For each line, type the Billed Quantity and Unit Cost from the invoice.',
                            'Click Save Vendor Bill. It shows as Pending Match.',
                        ],
                    ],
                    [
                        'title' => 'Check the bill (3-way match)',
                        'when' => 'perm:match vendor bills',
                        'steps' => [
                            'Click Run Match. The bill is compared with the purchase order and the goods receipt.',
                            'If it shows a discrepancy, click View Match to see which line differs.',
                            ['text' => 'If the bill was typed wrong, click Correct Bill, fix it, and run the match again.', 'when' => 'perm:edit vendor bills'],
                        ],
                    ],
                ],
            ],

            // ── Insights ───────────────────────────────────────────────────────

            'reports' => [
                'title' => 'Reports',
                'summary' => 'Bookings, revenue, services and therapist activity for a period.',
                'tasks' => [
                    [
                        'title' => 'Read a report',
                        'steps' => [
                            'Choose a Period, or Custom range with From and To, then click Apply.',
                            'Booking Summary counts every booking by status.',
                            'Service Usage shows which treatments and packages sold. Therapist Activity shows bookings and revenue per therapist.',
                        ],
                    ],
                ],
                'tips' => [
                    'Revenue counts only checked-out appointments, the same as the Revenue page.',
                ],
            ],

            'decision-support' => [
                'title' => 'Decision Support',
                'summary' => 'Suggestions from your booking patterns: busy days and hours, growing services, and therapist workload.',
                'tasks' => [
                    [
                        'title' => 'Use the suggestions',
                        'steps' => [
                            'Choose a Period and click Apply.',
                            'Read Actionable Insights first. The most urgent are at the top.',
                            'Booking Activity by Hour shows peak hours, which need the most staff. Quiet hours suit promos and training.',
                            'Therapist Booking Load shows if some therapists get far more bookings than others.',
                        ],
                    ],
                ],
                'tips' => [
                    'More booking history gives better suggestions.',
                ],
            ],

            // ── Branch Settings ────────────────────────────────────────────────

            'edit-branch' => [
                'title' => 'Edit Branch',
                'summary' => 'This branch\'s name, opening hours and public profile.',
                'tasks' => [
                    [
                        'title' => 'Change opening hours',
                        'when' => 'perm:edit branch hours',
                        'steps' => [
                            'Open the Hours tab.',
                            'For each day, set Opens and Closes, or tick Closed all day.',
                            'Click Save Operating Hours. Online booking follows these hours right away.',
                        ],
                    ],
                    [
                        'title' => 'Update the public profile',
                        'when' => 'perm:edit branch profile',
                        'steps' => [
                            'Open the Profile tab.',
                            'Update the Branch Details customers see, the Cover Image and the gallery photos.',
                            'Click Save Public Profile. Customers see the change on the landing page.',
                        ],
                    ],
                    [
                        'title' => 'Rename the branch',
                        'when' => 'perm:edit branch general',
                        'steps' => [
                            'Open the General tab, change the Branch Name, and click Save General Info.',
                        ],
                    ],
                ],
                'tips' => [
                    'At least one day must be open, or online bookings stop.',
                ],
            ],

            'branch-details' => [
                'title' => 'Branch Details',
                'summary' => 'Your branch\'s information, hours and public listing. View only.',
                'tasks' => [
                    [
                        'title' => 'Check your branch\'s details',
                        'steps' => [
                            'Find your branch in the Branch Directory.',
                            'It shows the location, the staff count, and whether the branch is on the public listing.',
                            'To change anything, ask the owner.',
                        ],
                    ],
                ],
            ],

            // ── Business (owner) ───────────────────────────────────────────────

            'roles' => [
                'title' => 'Roles & Permissions',
                'summary' => 'Choose what each role can see and do in this branch.',
                'tasks' => [
                    [
                        'title' => 'Change what a role can do',
                        'steps' => [
                            'Switch to the branch you want first. Changes apply only to that branch.',
                            'Under Branch Roles, click Edit on the role.',
                            'Tick or untick permissions. Or pick Start from a preset and click Use this preset.',
                            'Click Save Changes. Click Revert to go back to the default.',
                        ],
                    ],
                ],
                'tips' => [
                    'If a set of permissions is risky, the page warns you first. You can still save.',
                    'A person\'s role is set on the Staff page, not here.',
                ],
            ],

            'all-branches' => [
                'title' => 'All Branches',
                'summary' => 'Every branch of your spa: add, open or remove branches.',
                'tasks' => [
                    [
                        'title' => 'Add a branch',
                        'steps' => [
                            'Click Add New Branch.',
                            'Type the Branch Name and choose the Location / City.',
                            'Set the Operating Hours, then click Create Branch.',
                            'Upload the branch\'s documents on Spa Profile so it can be verified.',
                        ],
                    ],
                    [
                        'title' => 'Change or remove a branch',
                        'steps' => [
                            'Click General, Hours or Profile on the branch\'s card to edit it.',
                            'Click Remove Branch, then Yes, Remove. Its records are kept. Move its staff out first.',
                        ],
                    ],
                ],
                'tips' => [
                    'Your plan has a branch limit. Available Slots shows how many you have left.',
                ],
            ],

            'spa-profile' => [
                'title' => 'Spa Profile',
                'summary' => 'Your spa\'s verification documents and their expiry dates.',
                'tasks' => [
                    [
                        'title' => 'Upload or renew a document',
                        'steps' => [
                            'Find the document under its branch. Required ones are marked Required.',
                            'Click Choose File and pick the file: PDF, JPG or PNG, up to 10 MB.',
                            'Enter the Expiration Date printed on it, or tick "This document has no expiration date".',
                            'Click Submit Documents. An admin reviews them.',
                        ],
                    ],
                ],
                'tips' => [
                    'Sent back means the admin needs a new file. Read Admin Remarks for why.',
                    'Renew documents before they expire, or the branch can be locked.',
                ],
            ],

            'subscription' => [
                'title' => 'Subscription & Billing',
                'summary' => 'Your Levictas plan, your trial, payments and receipts.',
                'tasks' => [
                    [
                        'title' => 'Pay for or change your plan',
                        'steps' => [
                            'Choose Monthly billing or Yearly billing (2 months free).',
                            'Pick a plan. The total and the dates are shown before you pay.',
                            'Click Continue to Payment and pay with PayMongo.',
                        ],
                    ],
                    [
                        'title' => 'Get a receipt',
                        'steps' => [
                            'Under Billing History, click Receipt next to the payment.',
                        ],
                    ],
                ],
                'tips' => [
                    'Paying early adds a new period after your current one. You never lose days.',
                    'If your spa has more branches or staff than a plan allows, choose which stay active. Nothing is deleted.',
                ],
            ],


            'my-payslips' => [
                'title' => 'My Payslips',
                'summary' => 'Your payslips from released pay runs.',
                'images' => [['src' => 'images/guides/my-payslips.png', 'alt' => 'The payslip list with the Year filter and a View button.', 'task' => 0]],
                'tasks' => [
                    [
                        'title' => 'View and print a payslip',
                        'steps' => [
                            'Choose the Year and click Show.',
                            'Click View on the payslip you want.',
                            'Click Print to print it or save it as a PDF.',
                        ],
                    ],
                ],
                'tips' => [
                    'A payslip appears here only after HR and Finance release its pay run.',
                    'Questions about an amount go to HR.',
                ],
            ],
        ];
    }

    public static function get(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, self::all());
    }
}
