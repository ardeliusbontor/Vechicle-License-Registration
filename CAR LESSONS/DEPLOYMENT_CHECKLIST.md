# Deployment Priority Checklist

## Priority 1: Security and access control
- [ ] Restrict admin-only pages to users with role = Admin
- [ ] Hide system-management links from other staff roles
- [ ] Enforce session expiry and automatic logout
- [ ] Prevent applicant access to admin dashboard pages
- [ ] Validate all form input and sanitize database queries
- [ ] Enable HTTPS-only deployment settings
- [ ] Remove debug output in production

## Priority 2: Core business flow stability
- [ ] Confirm user registration works correctly
- [ ] Confirm login works for Admin, Officer, Clerk, and Applicant
- [ ] Confirm forgot password and reset password work end-to-end
- [ ] Confirm application creation and status updates work
- [ ] Confirm plate generation and approval flow works
- [ ] Confirm renewal and expiry logic works correctly
- [ ] Confirm payment flow and receipt creation are stable

## Priority 3: Plate expiry and renewal automation
- [ ] Show expiring-soon and expired plate statuses clearly
- [ ] Add warning banners for expiring plates on dashboards
- [ ] Send renewal reminder notices before expiry date
- [ ] Keep renewal requests separated from new registrations
- [ ] Display renewal history per plate and vehicle record

## Priority 4: Search, reports, and admin visibility
- [ ] Add search filter across users, vehicles, applications, and renewals
- [ ] Add CSV/PDF export for reports
- [ ] Add reports by county, status, and expiry range
- [ ] Keep system administrator as the only role with access to all users
- [ ] Make user management hidden for non-admin staff

## Priority 5: QR and mobile experience
- [ ] Add QR code generation for valid plate records
- [ ] Link QR scan to detail/verification page
- [ ] Make QR verification work on mobile devices
- [ ] Ensure all pages remain responsive on phone/tablet screens
- [ ] Confirm scanning works over HTTPS with correct public URL

## Priority 6: Email and notifications
- [ ] Configure production SMTP for registration and reset emails
- [ ] Send payment confirmations and admin notifications
- [ ] Send plate expiry and renewal reminder emails
- [ ] Keep fallback debug links only on localhost

## Priority 7: Final launch readiness
- [ ] Backup the database before production deployment
- [ ] Test full registration-to-approval flow
- [ ] Test admin login, applicant login, and staff login
- [ ] Test search, filter, and export pages
- [ ] Test QR scanning from a real phone
- [ ] Review all environment variables and secrets
- [ ] Set APP_ENV to production and APP_URL to the live domain
- [ ] Confirm hosting path and database configuration are correct
- [ ] Run a final smoke test before launch

## Recommended order to implement
1. Security and access control
2. Login, reset, and role integrity
3. Plate expiry and renewal logic
4. QR + mobile verification
5. Search and reporting
6. Email notifications
7. Final deployment smoke test
