#!/bin/sh
# Add the studio credit line under the AZ Web Corp credit on staging sites.
#
# Yasir: "for all stagings right under where it says a website by azwebcorp,
# right underneath in italic it should say 'A design by Memon'".
#
# Uploaded and run as a file rather than passed to ssh_run.bat inline, because
# that wrapper mangles < > | % and single quotes - which is most of this script.
#
# Idempotent: files already carrying the line are skipped, so a re-run is safe.
set -e

DIR="${1:-/html/preview/prestige-v2}"
STAMP=$(date +%Y%m%d-%H%M%S)
BACKUP="/html/azw-backups/memon-credit-$(basename "$DIR")-$STAMP.tar.gz"

OLD='<span>Site by AZ Web Corp</span>'
NEW='<span>Site by AZ Web Corp<br><em style="font-style:italic">A design by Memon</em></span>'

cd "$DIR"

FILES=$(grep -rl "Site by AZ Web Corp" --include=*.html . || true)
if [ -z "$FILES" ]; then
  echo "no files carry the AZ Web Corp credit under $DIR - nothing to do"
  exit 0
fi

TODO=""
for f in $FILES; do
  if grep -q "A design by Memon" "$f"; then
    continue
  fi
  TODO="$TODO $f"
done

if [ -z "$TODO" ]; then
  echo "all files already carry the credit - nothing to do"
  exit 0
fi

echo "files to change: $(echo $TODO | wc -w)"
mkdir -p /html/azw-backups
tar czf "$BACKUP" $TODO
echo "backup: $BACKUP"

CHANGED=0
for f in $TODO; do
  if grep -qF "$OLD" "$f"; then
    sed -i "s#$OLD#$NEW#g" "$f"
    CHANGED=$((CHANGED + 1))
  else
    echo "  SKIPPED (credit markup differs): $f"
  fi
done

echo "changed: $CHANGED"
echo "verify: $(grep -rl 'A design by Memon' --include=*.html . | wc -l) files now carry the line"
