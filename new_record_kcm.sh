#!/bin/bash

set -euo pipefail

export TZ=Asia/Jerusalem

### CONFIG ###
STREAM_URL="https://live.kcm.fm/live-new/hls.m3u8"

# 3600 שניות = שעה
# 5400 שניות = שעה וחצי
DURATION=3600

BASE_DIR="/opt/radio"
REC_DIR="$BASE_DIR/NEW_recordings"
TMP_DIR="$BASE_DIR/NEW_tmp"
LOG_DIR="$BASE_DIR/NEW_logs"

# יצירת תיקיות אם חסרות
mkdir -p "$REC_DIR" "$TMP_DIR" "$LOG_DIR"

DATE=$(date +"%Y-%m-%d_%H-%M")
RAW_FILE="$TMP_DIR/NEW_kcm_$DATE.ts"
MP3_FILE="$REC_DIR/NEW_kcm_$DATE.mp3"
LOG_FILE="$LOG_DIR/NEW_kcm_$DATE.log"

PARTS_DIR="$TMP_DIR/kcm_parts_$DATE"
mkdir -p "$PARTS_DIR"

echo "$(date '+%Y-%m-%d %H:%M:%S') - record_kcm.sh התחיל" >> "$BASE_DIR/record_kcm.log"
echo "[$(date)] Recording started" >> "$LOG_FILE"

#########################################
# הקלטה עם ניסיון השלמה במקרה שנעצר מוקדם
#########################################

REMAINING=$DURATION
PART=1
TOTAL_RECORDED=0

while [ "$REMAINING" -gt 0 ]; do

  PART_FILE="$PARTS_DIR/part_${PART}.ts"

  echo "[$(date)] Starting part $PART, remaining=$REMAINING seconds" >> "$LOG_FILE"

  START_TIME=$(date +%s)

  # חשוב:
  # בתוך if כדי ש-set -e לא יפיל את כל הסקריפט במקרה ש-ffmpeg נכשל
  if ffmpeg \
    -loglevel warning \
    -stats \
    -i "$STREAM_URL" \
    -t "$REMAINING" \
    -c copy \
    "$PART_FILE" >> "$LOG_FILE" 2>&1
  then
    FFMPEG_EXIT=0
  else
    FFMPEG_EXIT=$?
  fi

  END_TIME=$(date +%s)
  RECORDED=$((END_TIME - START_TIME))

  echo "[$(date)] ffmpeg exit code: $FFMPEG_EXIT" >> "$LOG_FILE"
  echo "[$(date)] Part $PART recorded about $RECORDED seconds" >> "$LOG_FILE"

  # אם לא נוצר קובץ תקין, אין טעם להמשיך בלי סוף
  if [ ! -s "$PART_FILE" ]; then
    echo "[$(date)] ERROR: part $PART failed, file is empty" >> "$LOG_FILE"
    break
  fi

  # אם ffmpeg נפל ממש מהר, נעצור כדי לא ליצור לולאה אינסופית
  if [ "$RECORDED" -lt 5 ]; then
    echo "[$(date)] ERROR: ffmpeg stopped too quickly, stopping retries" >> "$LOG_FILE"
    break
  fi

  TOTAL_RECORDED=$((TOTAL_RECORDED + RECORDED))
  REMAINING=$((DURATION - TOTAL_RECORDED))

  if [ "$REMAINING" -lt 0 ]; then
    REMAINING=0
  fi

  echo "[$(date)] Total recorded=$TOTAL_RECORDED, remaining=$REMAINING" >> "$LOG_FILE"

  PART=$((PART + 1))

  # מנוחה קצרה לפני חיבור מחדש לסטרים
  sleep 2

done

#########################################
# בדיקה אם בכלל יש חלקים
#########################################

if ! ls "$PARTS_DIR"/*.ts >/dev/null 2>&1; then
  echo "[$(date)] ERROR: no recording parts created" >> "$LOG_FILE"
  exit 1
fi

echo "[$(date)] Recording finished, merging parts..." >> "$LOG_FILE"

#########################################
# איחוד כל החלקים לקובץ RAW אחד
#########################################

LIST_FILE="$PARTS_DIR/files.txt"
rm -f "$LIST_FILE"

for FILE in "$PARTS_DIR"/*.ts; do
  echo "file '$FILE'" >> "$LIST_FILE"
done

ffmpeg \
  -loglevel warning \
  -f concat \
  -safe 0 \
  -i "$LIST_FILE" \
  -c copy \
  "$RAW_FILE" >> "$LOG_FILE" 2>&1

if [ ! -s "$RAW_FILE" ]; then
  echo "[$(date)] ERROR: merged raw file failed" >> "$LOG_FILE"
  exit 1
fi

echo "[$(date)] Merge finished, converting to mp3..." >> "$LOG_FILE"

#########################################
# המרה ל-MP3
#########################################

ffmpeg \
  -loglevel warning \
  -i "$RAW_FILE" \
  -vn \
  -acodec libmp3lame \
  -ab 128k \
  "$MP3_FILE" >> "$LOG_FILE" 2>&1

#########################################
# העלאה למערכת הטלפונית
#########################################

TOKEN="033069597:1478"
YEMOT_PATH="/1234/6"
CONVERT_AUDIO=1

if /usr/bin/php "$BASE_DIR/upload_cli.php" \
  "$TOKEN" \
  "$YEMOT_PATH" \
  "$(basename "$MP3_FILE")" \
  "$MP3_FILE" \
  "$CONVERT_AUDIO" \
  "1" \
  0 >> "$LOG_FILE" 2>&1
then
  RESULT="VVV עלה"
else
  RESULT="XXX לא עלה"
fi

#########################################
# ניקוי ושמירה
#########################################

if [ -s "$MP3_FILE" ]; then
  rm -f "$RAW_FILE"
  rm -rf "$PARTS_DIR"
  echo "[$(date)] SUCCESS: saved $MP3_FILE" >> "$LOG_FILE"
else
  echo "[$(date)] ERROR: mp3 conversion failed" >> "$LOG_FILE"
fi

echo "UTC: $(date -u)" >> "$LOG_FILE"
echo "Jerusalem: $(date)" >> "$LOG_FILE"

#########################################
# שליחת מייל
#########################################

TO="zmoshez@gmail.com"
TS=$(date '+%Y-%m-%d %H:%M:%S')

/usr/sbin/sendmail -t <<EOF
To: $TO
Subject: שיעור הר' נויגרשל הוקלט ונשמר $RESULT
From: myserver@yeshivatrabenu.site

שלום,
זו הודעה אוטומטית מהשרת.

זמן שליחה (ירושלים):
$TS

EOF
