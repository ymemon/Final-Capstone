from api import call
text = (
    "Hi Faraz, just checking whether the charity prospect list landed okay on "
    "your end — 86 orgs in the 15-60 staff range, with the 18 priority "
    "targets already carrying a named CEO/director contact. No rush if you're "
    "still working through it, but let me know if you want me to start "
    "drafting outreach for those 18, or if anything about the list needs "
    "adjusting first."
)
status, resp = call("POST", "/nudge-draft", {"ticket": "AZW-1024", "text": text})
print(status, resp)
