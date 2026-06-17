#!/bin/bash

set -euo pipefail

TZ=Asia/Jerusalem

#//hour=$(date +%H)
#//dow=$(date +%u) # 2 = יום שלישי

#//if [[ "$dow" != "2" || "$hour" != "22" ]]; then
#//  exit 0
#//fi

### CONFIG ###
STREAM_URL="https://live.kcm.fm/live-new/hls.m3u8"
#DURATION=3600   # 90 דקות = 5400 שניות
DURATION=3600   # 90 דקות = 5400 שניות
BASE_DIR="/opt/radio"
REC_DIR="$BASE_DIR/recordings"
TMP_DIR="$BASE_DIR/tmp"
LOG_DIR="$BASE_DIR/logs"

# יוצר את התיקייה אם חסרה (רק פעם אחת)
mkdir -p "$(dirname "$REC_DIR")" 2>/dev/null || true
mkdir -p "$(dirname "$TMP_DIR")" 2>/dev/null || true
mkdir -p "$(dirname "$LOG_DIR")" 2>/dev/null || true

DATE=$(date +"%Y-%m-%d_%H-%M")
RAW_FILE="$TMP_DIR/kcm_$DATE.ts"
MP3_FILE="$REC_DIR/kcm_$DATE.mp3"
LOG_FILE="$LOG_DIR/kcm_$DATE.log"

echo "$(date '+%Y-%m-%d %H:%M:%S') - record_kcm.sh התחיל" >> ./record_kcm.log

### START ###
echo "[$(date)] Recording started" >> "$LOG_FILE"

ffmpeg \
  -loglevel error \
  -stats \
  -i "$STREAM_URL" \
  -t "$DURATION" \
  -c copy \
  "$RAW_FILE" 
#>> "$LOG_FILE" 2>&1

if [ ! -s "$RAW_FILE" ]; then
  echo "[$(date)] ERROR: recording failed" >> "$LOG_FILE"
  exit 1
fi

echo "[$(date)] Recording finished, converting..." >> "$LOG_FILE"

### POST-PROCESS: convert to mp3 ###
ffmpeg \
  -loglevel error \
  -i "$RAW_FILE" \
  -vn \
  -acodec libmp3lame \
  -ab 128k \
  "$MP3_FILE" >> "$LOG_FILE" 2>&1

#העלאה למערכת הטלפונית
TOKEN="033069597:1478"
YEMOT_PATH="/1234/6"
CONVERT_AUDIO=1

if /opt/radio/php upload_cli.php \
  "$TOKEN" \
  "$YEMOT_PATH" \
  "$(basename "$MP3_FILE")" \
  "$MP3_FILE" \
  "$CONVERT_AUDIO" \
  0
  then
  RESULT="VVV עלה"
else
  RESULT="XXX לא עלה"
fi



if [ -s "$MP3_FILE" ]; then
  rm -f "$RAW_FILE"
  echo "[$(date)] SUCCESS: saved $MP3_FILE" >> "$LOG_FILE"
else
  echo "[$(date)] ERROR: mp3 conversion failed" >> "$LOG_FILE"
fi
echo "UTC: $(date -u)" >> "$LOG_FILE"
TZ=Asia/Jerusalem echo "Jerusalem: $(date)" >> "$LOG_FILE"



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
