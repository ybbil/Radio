#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")"

if [ $# -lt 1 ]; then
    echo "שימוש: $0 /path/to/file"
    exit 1
fi

FILE="$1"

if [ ! -f "$FILE" ]; then
    echo "שגיאה: הקובץ לא נמצא: $FILE"
    exit 2
fi

FILE="$(realpath "$FILE")"

echo "[INFO] מתחיל טיפול בקובץ: $FILE"

# לוג בסיסי
LOG="run_$(date +%Y%m%d_%H%M%S).log"

{
    echo "[DEBUG] PATH=$PATH"
    echo "[DEBUG] Running as $(whoami)"


#העלאה למערכת הטלפונית
TOKEN="033069597:1478"
YEMOT_PATH="/1234/6"
CONVERT_AUDIO=1

if php upload_cli.php \
  "$TOKEN" \
  "$YEMOT_PATH" \
  "$(basename "$FILE")" \
  "$FILE" \
  "$CONVERT_AUDIO" \
  0
  then
  RESULT="VVV עלה"
else
  RESULT="XXX לא עלה"
fi


TO="zmoshez@gmail.com"

TS=$(TZ='Asia/Jerusalem' date '+%Y-%m-%d %H:%M:%S')

/usr/sbin/sendmail -t <<EOF
To: $TO
Subject: שיעור הר' נויגרשל הוקלט ונשמר $RESULT
From: myserver@yeshivatrabenu.site
שלום,
זו הודעה אוטומטית מהשרת.
זמן שליחה (ירושלים):
$TS

EOF


echo "הסתיים בהצלחה"

} >> "$LOG" 2>&1

echo "[INFO] הסתיים. לוג: $LOG"

