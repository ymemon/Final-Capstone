# Rebound Body Studio — MassageBook feature map

The brief was "all features present on MassageBook". She keeps all of them. Nothing is
taken away. What changes is which ones a *client* touches on her own domain, and which
stay where they already work.

The short version: **her site becomes the front door, MassageBook stays the engine.**
MassageBook publishes embeddable widgets for the client-facing parts, so booking, gift
certificates, packages and offers can all happen on reboundbodystudio.com rather than on
a directory that lists her competitors down the side of the same page.

---

## 1. Moves onto her site (embedded, client-facing)

MassageBook provides copy-paste embed code for each of these under
**Booking Widgets → Add a Booking Page To Your Own Website**.

| Feature | How it appears on her site |
|---|---|
| Online scheduling | Scheduler embedded in the booking section — client never leaves the domain |
| Service menu | Rendered natively as the pricing cards, with live booking links per service |
| Gift certificates | Its own card and widget. Biggest single seasonal driver for a massage studio |
| Series / memberships | Card ready; prices still to be decided |
| Promotions / deals | Pulled from MassageBook so the site can never advertise an expired offer |
| Reviews | MassageBook's verified reviews widget — only real attendees can leave one |
| Client accounts | "Sign in" in the header, straight into her MassageBook client portal |
| Client blog | Optional; MassageBook has an embed for it, but her own posts would rank better |

## 2. Stays in MassageBook (staff-side — no reason to rebuild)

These never needed to be on a website. Rebuilding them would cost months and make things
worse, not better.

- Client database / CRM
- Appointment reminders and no-show prevention (email + SMS)
- Email marketing campaigns
- Payments — card processing via Stripe/Square, and Tap to Pay
- Waitlist, outcall booking, availability management
- Reports and analytics
- Her mobile app
- Google Calendar sync, Reserve with Google, Facebook/Instagram booking

## 3. One I would refuse to rebuild

**SOAP notes and client intake forms.**

These are clinical treatment records and health history — protected health information.
Storing them in a custom WordPress build means taking on medical-records liability on
shared hosting, with no BAA, no audit trail and no encryption guarantees worth the name.
MassageBook already handles this inside a platform built for it.

If anybody proposes moving intake forms onto the website "so it's all in one place", that
is the one item on this list to say no to. The correct answer is a link from her site into
the MassageBook intake flow.

## 4. What she gains that MassageBook cannot give her

This is the actual argument for building at all — not features, but ownership.

- **The domain earns the authority.** Right now reboundbodystudio.com is a parked
  Namecheap redirect with no HTTPS at all (port 443 refuses connections). Every bit of
  search equity she builds accrues to massagebook.com.
- **She stops being one listing among thousands.** A MassageBook profile page shows
  competing therapists in the same city. Her own site does not.
- **Local SEO becomes possible.** Google Business Profile, local content, service pages
  for the terms people in east Mesa actually search. A directory profile cannot be
  optimised the way a site can.
- **The positioning becomes hers.** MassageBook renders every therapist in the same
  template. Sports and orthopedic recovery reads very differently from a day spa, and the
  template cannot express that.

---

## Blocking items before anything is published

1. **The street address is disputed.** MassageBook says 1138 N. Higley Rd Suite 109; a
   search result says 1918 N. Higley Rd. One is wrong, and it is currently baked into the
   prototype's schema and footer. Confirm before launch or before touching a Google
   Business Profile.
2. **Her name, licence type and licence number.** Not invented in the prototype — the
   about section is an explicit placeholder. A site selling clinical bodywork with no
   named licensed practitioner is a credibility problem, not a cosmetic gap.
3. **Photography of the actual studio and of her.** Stock spa imagery would actively
   undercut the positioning. This is the highest-value asset to shoot.
4. **Reviews.** She has none published. Turn on review requests in MassageBook so they
   start arriving; the reviews section stays out of the launched site until they exist.
5. **Series / membership pricing**, if she wants to offer them.
6. **Mobile test of the embedded scheduler.** MassageBook has a support article about
   embedded pages rendering badly on mobile, so this needs verifying on a real phone
   rather than assumed.

## Also worth doing at launch

- HTTPS. There is currently no certificate at all on the domain.
- Google Business Profile — claimed, categorised, with the confirmed address and hours.
  For a local studio this usually outperforms the website in the first months.
- Keep the MassageBook profile live. It is a genuine referral source; the site is not a
  reason to delete it.
