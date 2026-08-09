#!/bin/bash
# ===================================================================
#  AZWebCorp site issue fixer - WP-CLI edition
#
#  Targets the issues found in the Ahrefs Site Audit (project 8030444,
#  crawl 29 Jul 2026): 46 missing alt text, 42 links to redirects,
#  19 pages with multiple meta description tags, 12 orphan pages.
#
#  Runs server-side over SSH. Does NOT use the REST API, which is
#  unusable on this host (Authorization header is stripped).
#
#  USAGE - always dry-run first:
#      bash fix_issues_wpcli.sh report     # read-only, changes nothing
#      bash fix_issues_wpcli.sh fix-alt    # writes alt text
#
#  'report' is the default. Nothing is modified unless you explicitly
#  pass fix-alt.
# ===================================================================
set -u

W=/home/client_9d34da8b_644762/html
MODE="${1:-report}"

if [ ! -f "$W/wp-config.php" ]; then
  echo "ERROR: no wp-config.php at $W"
  exit 1
fi

wpc() { wp --path="$W" --skip-plugins --skip-themes "$@"; }

echo "=================================================="
echo " AZWebCorp site issues - mode: $MODE"
echo "=================================================="
echo

# ---------------------------------------------------------------
# 1. Images missing alt text
# ---------------------------------------------------------------
echo "[1] ATTACHMENTS MISSING ALT TEXT"
echo "--------------------------------------------------"

IDS=$(wpc post list --post_type=attachment --post_mime_type=image \
        --format=ids --posts_per_page=-1)

MISSING=""
COUNT=0
for ID in $IDS; do
  ALT=$(wpc post meta get "$ID" _wp_attachment_image_alt 2>/dev/null)
  if [ -z "$ALT" ]; then
    MISSING="$MISSING $ID"
    COUNT=$((COUNT+1))
  fi
done

echo "Images with no alt text: $COUNT"
echo

if [ "$COUNT" -gt 0 ]; then
  for ID in $MISSING; do
    TITLE=$(wpc post get "$ID" --field=post_title)
    # Derive readable alt text from the attachment title:
    # strip extension, turn separators into spaces, collapse whitespace.
    SUGG=$(echo "$TITLE" \
           | sed -e 's/\.[a-zA-Z0-9]\{2,4\}$//' \
                 -e 's/[-_]\+/ /g' \
                 -e 's/  \+/ /g' \
                 -e 's/^ *//' -e 's/ *$//')

    if [ "$MODE" = "fix-alt" ]; then
      if [ -n "$SUGG" ]; then
        wpc post meta update "$ID" _wp_attachment_image_alt "$SUGG" >/dev/null
        echo "  SET  #$ID -> \"$SUGG\""
      else
        echo "  SKIP #$ID (no usable title to derive alt text from)"
      fi
    else
      echo "  #$ID  would set -> \"$SUGG\""
    fi
  done
fi

echo

# ---------------------------------------------------------------
# 2. Orphan pages (no incoming internal links)
# ---------------------------------------------------------------
echo "[2] ORPHAN PAGE CHECK (published pages, no internal inbound links)"
echo "--------------------------------------------------"

PAGES=$(wpc post list --post_type=page --post_status=publish \
          --fields=ID,post_name --format=csv | tail -n +2)

for ROW in $PAGES; do
  PID=$(echo "$ROW" | cut -d, -f1)
  SLUG=$(echo "$ROW" | cut -d, -f2)
  [ -z "$SLUG" ] && continue

  # Count published posts/pages whose content links to this slug,
  # excluding the page itself.
  HITS=$(wpc db query "SELECT COUNT(*) FROM $(wpc db prefix --quiet 2>/dev/null || echo wp_)posts
          WHERE post_status='publish'
            AND post_type IN ('page','post')
            AND ID <> $PID
            AND post_content LIKE '%/$SLUG/%';" --skip-column-names 2>/dev/null)

  if [ "${HITS:-0}" = "0" ]; then
    echo "  ORPHAN  #$PID  /$SLUG/"
  fi
done

echo

# ---------------------------------------------------------------
# 3. Duplicate meta description sources
# ---------------------------------------------------------------
echo "[3] PAGES WITH NO RANK MATH META DESCRIPTION"
echo "--------------------------------------------------"
echo "(Ahrefs found 19 pages emitting MULTIPLE description tags -"
echo " usually the theme and the SEO plugin both printing one. Pages"
echo " with no Rank Math description fall back to theme output, which"
echo " is where the duplication tends to come from.)"
echo

for ROW in $PAGES; do
  PID=$(echo "$ROW" | cut -d, -f1)
  SLUG=$(echo "$ROW" | cut -d, -f2)
  [ -z "$SLUG" ] && continue
  D=$(wpc post meta get "$PID" rank_math_description 2>/dev/null)
  [ -z "$D" ] && echo "  NO DESC  #$PID  /$SLUG/"
done

echo
echo "=================================================="
if [ "$MODE" = "report" ]; then
  echo " REPORT ONLY - nothing was changed."
  echo " To write alt text:  bash $0 fix-alt"
else
  echo " Alt text written. Re-run 'report' to confirm."
fi
echo "=================================================="
echo
echo "NOT handled automatically (need human judgement):"
echo "  - Broken images: need a real replacement file."
echo "  - Links to redirects: rewriting hrefs in page content"
echo "    risks mangling Elementor-serialised data. Elementor"
echo "    stores markup in _elementor_data JSON, so blind"
echo "    search/replace can corrupt layouts. Use"
echo "    'wp search-replace --dry-run' per specific URL instead."
echo "  - Auto-derived alt text is a fallback, not real copy."
echo "    Review anything important for accessibility."
