# Global header menu centring — 2026-08-24

Updated Elementor header template `989508`, targeting navigation widget
`63c90f5`. Its explicit left alignment was changed to centre alignment so the
desktop and tablet navigation sits centrally within the available header menu
column. The logo, phone/Helpdesk controls and mobile menu behaviour were
preserved.

Because the header plugin continued emitting its legacy left-alignment wrapper
class, a widget-scoped Elementor CSS rule now centres the actual navigation list
with `justify-content:center`. The generated public header stylesheet was
verified to contain that effective rule.

The header's original columns were asymmetrical (approximately 14% / 55% /
30%), which meant a menu centred inside the middle column still appeared left
of the page centreline. The desktop header now uses balanced outer columns with
the navigation in an automatic-width middle column, placing the menu on the
true horizontal centreline while retaining the logo at left and contact actions
at right.

A timestamped rollback snapshot was stored outside the web root and in post
meta before mutation. Elementor, object, page and CDN caches were cleared.
