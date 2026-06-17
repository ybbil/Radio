#!/bin/bash

# יוצרת תיקיה אם לא קיימת
mkdir -p /opt/radio/test_logs

# הגדרת קובץ לוג לבדיקה
LOG_FILE="/opt/radio/test_time.log"

# קבלת זמן לפי ירושלים
HOUR=$(TZ=Asia/Jerusalem date +%H)
MIN=$(TZ=Asia/Jerusalem date +%M)
DOW=$(TZ=Asia/Jerusalem date +%u)  # 1=Monday ... 7=Sunday

# כתיבה ללוג
echo "----------------------------------------" >> "$LOG_FILE"
echo "Cron execution: $(date -u) UTC" >> "$LOG_FILE"
echo "Jerusalem time: $(TZ=Asia/Jerusalem date)" >> "$LOG_FILE"
echo "Hour: $HOUR, Minute: $MIN, DayOfWeek: $DOW" >> "$LOG_FILE"

# בדיקה האם זה יום ושעה רצויים
# נניח שבת 22:45
TARGET_HOUR=22
TARGET_MIN=45
TARGET_DOW=6  # שבת

if [[ "$DOW" -eq "$TARGET_DOW" && "$HOUR" -eq "$TARGET_HOUR" && "$MIN" -ge "$TARGET_MIN" ]]; then
    echo "✅ Correct time to run the script"
else
    echo "❌ Not the target time yet"
fi