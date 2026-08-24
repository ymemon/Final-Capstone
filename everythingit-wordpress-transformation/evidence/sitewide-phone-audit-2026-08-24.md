# Site-wide phone audit — 2026-08-24

Audited all 54 published WordPress pages plus the globally rendered homepage
header and footer. All requests completed successfully.

The page-level telephone references already included Dublin area code `1` and
used the correct callable URI `tel:+35315240755`. One global header display
defect was confirmed: `+3531 524 0755`. Header template `247`, widget `af8e0b4`,
was corrected to display `+353 1 524 0755`; its callable URI was preserved.

A timestamped rollback snapshot was stored before mutation, and public output
was checked for the corrected display, removal of the malformed display and the
working telephone link.
